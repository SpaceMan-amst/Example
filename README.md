Item Access Manager — Symfony API для управления доступом к пользовательским устройствам
Проект представляет собой RESTful API на Symfony для управления доступом к пользовательским устройствам (items), с возможностью безопасной авторизации, обновления push-токенов и удалённого управления (блокировка/разблокировка) с помощью silent push-уведомлений.

Функции API
Получение списка устройств пользователя с фильтрами и пагинацией

Безопасная авторизация с использованием masterPin и pin

Обновление push-токенов для устройства

Отправка silent push-команд (LOCK/UNLOCK)

Управление статусом блокировки устройства в базе

Swagger/OpenAPI-документация (NelmioApiDocBundle)

Используемые технологии
Symfony 6

Doctrine ORM

NelmioApiDocBundle (OpenAPI 3)

Symfony Security + Attribute-based access control

Сервисная архитектура: Service, Repository, Notification

DI-контейнер, типизированные контроллеры и методы

Безопасность
Вход в систему и обновление токенов защищены проверкой pin + masterPin через отдельный PinService.

Silent push-команды отправляются только на верифицированные токены.

Контроллеры защищены с помощью #[IsGranted] и #[Security(name: 'Bearer')].
