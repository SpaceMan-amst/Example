<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Repository\UserRepository;

class ItemService
{
    public function __construct(
        private ItemRepository $itemRepository,
        private UserRepository $userRepository,
        private PinService $pinService
    ) {}

    public function create(User $user, ?string $token, string $name): void
    {
        if (empty($this->itemRepository->findByUserAndName($user, $name))) {
            $item = new Item();
            $item->setUser($user)
                 ->setName($name)
                 ->setIsLocked(false)
                 ->setPushToken($token);

            $this->itemRepository->save($item);
        }
    }

    public function getPushTokensByUserId(string $userId): array
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $tokens = [];
        foreach ($user->getItems() as $item) {
            $token = $item->getPushToken();
            if (!empty($token)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    public function getPushTokenByItemId(string $itemId): ?string
    {
        $item = $this->itemRepository->find($itemId);
        if (!$item || empty($item->getPushToken())) {
            throw new \Exception('Token not found');
        }

        return $item->getPushToken();
    }

    public function markAsLocked(string $itemId): void
    {
        $item = $this->itemRepository->find($itemId);
        $item->setIsLocked(true);
        $this->itemRepository->save($item);
    }

    public function markAsUnlocked(string $itemId): void
    {
        $item = $this->itemRepository->find($itemId);
        $item->setIsLocked(false);
        $this->itemRepository->save($item);
    }

    public function lockAllByUserId(string $userId): void
    {
        $user = $this->userRepository->find($userId);
        foreach ($user->getItems() as $item) {
            $item->setIsLocked(true);
            $this->itemRepository->save($item);
        }
    }

    public function unlockAllByUserId(string $userId): void
    {
        $user = $this->userRepository->find($userId);
        foreach ($user->getItems() as $item) {
            $item->setIsLocked(false);
            $this->itemRepository->save($item);
        }
    }

    public function updatePushToken(string $authCode, string $token, string $itemName): void
    {
        $decoded = $this->pinService->decode($authCode);
        $user = $decoded->getUser();

        $item = $this->itemRepository->findOneBy([
            'user' => $user->getId(),
            'name' => $itemName
        ]);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        $item->setPushToken($token);
        $this->itemRepository->save($item);
    }
}