<?php

namespace App\Controller;

use App\Entity\Item;
use App\Notification\PushSender;
use App\Repository\ItemRepository;
use App\Service\ItemService;
use App\Service\PinService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Nelmio\ApiDocBundle\Annotation\Security;
use Nelmio\ApiDocBundle\Annotation\Model;

#[Route('/item', name: 'app_item_')]
#[OA\Tag('Item')]
class ItemController extends AppController
{
    public function __construct(
        private ItemRepository $itemRepository,
        private PinService $pinService,
        private ItemService $itemService,
        private PushSender $pushSender,
    ) {}

    #[Security(name: 'Bearer')]
    #[IsGranted('ROLE_ADMIN', message: 'Access denied')]
    #[Route('/user/{userId}', name: 'read', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Get list of items by user ID.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'bool'),
                new OA\Property(property: 'result', properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                        ref: new Model(type: Item::class)
                    ))], type: 'object'),
                new OA\Property(property: 'pagination', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'page', type: 'int'),
                    new OA\Property(property: 'pages', type: 'int'),
                    new OA\Property(property: 'totalCount', type: 'int'),
                    new OA\Property(property: 'perPage', type: 'int'),
                ]))
            ]
        )
    )]
    public function read(string $userId, Request $request): Response
    {
        [$page, $perPage] = self::getPaginationParams($request);

        $query = $request->query->all();
        $searchValue = $query['search'] ?? null;
        $query['userId'] = $userId;

        [$filters, $sorting] = $this->getFiltersAndSorting(
            $query,
            Item::getValueQuery(),
            Item::getDateTimeValue()
        );

        [$items, $count] = $this->itemRepository->getList(
            $filters,
            $sorting,
            $page,
            $perPage,
            $this->getEntityName()::getSearchValue(),
            $searchValue
        );

        return $this->paginatedResponse($items, $count, $page, $perPage);
    }

    #[Route('/token-update', name: 'update_token', methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Update push notification token.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'bool'),
                new OA\Property(property: 'result', properties: [
                    new OA\Property(property: 'item', type: 'bool', example: true)
                ], type: 'object')
            ]
        )
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'masterPin', type: 'string'),
                new OA\Property(property: 'pin', type: 'string'),
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'itemName', type: 'string'),
            ]
        )
    )]
    public function updateToken(Request $request): Response
    {
        $data = $request->toArray();

        if (!$this->pinService->verify($data['pin'], $data['masterPin'])) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $this->itemService->updateToken($data['masterPin'], $data['token'], $data['itemName']);

        return $this->json(['item' => true]);
    }

    #[Route('/lock/{itemId}', name: 'lock', methods: ['PATCH'])]
    #[OA\Response(
        response: 200,
        description: 'Send silent push to lock the item.',
    )]
    public function lock(string $itemId): Response
    {
        $token = $this->itemService->getTokenByItemId($itemId);
        $this->pushSender->sendSilent($token, ['command' => 'LOCK']);
        $this->itemService->markAsLocked($itemId);

        return $this->json(['item' => true]);
    }

    #[Route('/unlock/{itemId}', name: 'unlock', methods: ['PATCH'])]
    #[OA\Response(
        response: 200,
        description: 'Send silent push to unlock the item.',
    )]
    public function unlock(string $itemId): Response
    {
        $token = $this->itemService->getTokenByItemId($itemId);
        $this->pushSender->sendSilent($token, ['command' => 'UNLOCK']);
        $this->itemService->markAsUnlocked($itemId);

        return $this->json(['item' => true]);
    }

    private function getEntityName(): string
    {
        return Item::class;
    }
}