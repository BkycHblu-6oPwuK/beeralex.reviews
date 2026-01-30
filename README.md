# Модуль beeralex.reviews

Система управления отзывами для Bitrix с поддержкой модерации, загрузки файлов и REST API.

## Основные возможности

- ✅ **Создание отзывов** с автоматической валидацией (PHP 8.2+ атрибуты)
- 📁 **Загрузка файлов** (фото к отзывам)
- 🔐 **Модерация** — новые отзывы требуют одобрения
- 👤 **Авторизованные и гостевые** пользователи
- ⭐ **Рейтинг 1-5** звезд
- 📥 **Импорт отзывов** из 2GIS (deprecated)
- 🔄 **Сортировка** (новые/старые)
- 🌐 **REST API** интеграция

## Требования

- PHP 8.2+
- Bitrix Framework 25.0+ (рекомендуемая для php 8.2)
- Модуль `beeralex.core` (базовые абстракции)
- Инфоблок с кодом `product_reviews`

## Установка

### Через Composer

Добавьте в `composer.json`:

```json
"extra": {
  "installer-paths": {
    "local/modules/{$name}/": ["type:bitrix-module"]
  }
}
```

Установите пакет:

```bash
composer require beeralex/beeralex.reviews
```

### Ручная установка

1. Разместите модуль в `/local/modules/beeralex.reviews/`
2. Установите через административную панель Bitrix
3. Модуль автоматически зарегистрирует сервисы

### Настройка инфоблока

Создайте инфоблок с кодом `product_reviews` и свойствами:

```
USER_NAME       (string)  - Имя пользователя
EVAL            (number)  - Оценка (1-5)
REVIEW          (text)    - Текст отзыва
CONTACT_DETAILS (string)  - Email/телефон
ELEMENT_ID      (number)  - ID товара
USER            (number)  - ID пользователя Bitrix
FILES           (file)    - Фотографии (множественное)
```

## Быстрый старт

### Создание отзыва

```php
use Beeralex\Reviews\Services\ReviewsService;
use Beeralex\Reviews\Dto\ReviewDTO;

$service = service(ReviewsService::class);

$dto = new ReviewDTO();
$dto->userName = 'Иван Петров';
$dto->eval = 5;
$dto->review = 'Отличный товар, рекомендую!';
$dto->contactDetails = 'ivan@example.com';
$dto->elementId = 123; // ID товара
$dto->userId = $USER->GetID() ?: null;

$result = $service->add($dto, $_FILES['files']);

if ($result->isSuccess()) {
    $elementId = $result->getData()['elementId'];
    echo "Отзыв создан с ID: {$elementId}";
} else {
    foreach ($result->getErrorMessages() as $error) {
        echo "Ошибка: {$error}\n";
    }
}
```

### Валидация данных

ReviewDTO использует атрибуты для автоматической валидации:

```php
$dto = new ReviewDTO();
$dto->userName = 'А'; // Слишком короткое имя
$dto->eval = 6;       // Вне диапазона 1-5
$dto->review = 'OK';  // Слишком короткий отзыв

if (!$dto->isValid()) {
    foreach ($dto->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
}

// Вывод:
// Имя должно быть от 2 до 100 символов
// Максимальная оценка - 5
// Отзыв должен быть от 10 до 5000 символов
```

### Получение отзывов товара

```php
use Beeralex\Reviews\Repository\ReviewsRepository;

$repo = service(ReviewsRepository::class);

$reviews = $repo->all(
    filter: [
        'IBLOCK_SECTION_ID' => 123, // ID товара
        'ACTIVE' => 'Y'
    ],
    select: [
        'ID',
        'DATE_CREATE',
        'PROPERTY_USER_NAME',
        'PROPERTY_EVAL',
        'PROPERTY_REVIEW',
    ],
    order: ['ID' => 'DESC']
);

foreach ($reviews as $review) {
    echo "{$review['PROPERTY_USER_NAME_VALUE']}: ";
    echo str_repeat('⭐', $review['PROPERTY_EVAL_VALUE']);
    echo " - {$review['PROPERTY_REVIEW_VALUE']['TEXT']}\n";
}
```

## REST API

Модуль интегрирован с `beeralex.api` через `ReviewController`.

### Получить список отзывов

```javascript
fetch('/api/v1/review/index/?product_id=123&count=10')
  .then(res => res.json())
  .then(data => console.log('Отзывы:', data));
```

### Создать отзыв

```javascript
const formData = new FormData();
formData.append('userName', 'Иван Петров');
formData.append('eval', 5);
formData.append('review', 'Отличный товар!');
formData.append('elementId', 123);
formData.append('files[]', fileInput.files[0]);

fetch('/api/v1/review/store/', {
  method: 'POST',
  body: formData
})
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      console.log('Отзыв создан:', data.data.elementId);
    } else {
      console.error('Ошибки:', data.errors);
    }
  });
```

**Ответ (успех):**

```json
{
  "status": "success",
  "data": {
    "elementId": 456
  }
}
```

**Ответ (ошибка):**

```json
{
  "status": "error",
  "errors": [
    {
      "message": "Имя должно быть от 2 до 100 символов",
      "code": "review_create"
    }
  ]
}
```

## Архитектура

### Основные компоненты

```
ReviewsService              → Фасад для создания отзывов
├── ReviewCreatorService    → Логика создания, валидация
│   ├── UploadService       → Загрузка файлов
│   └── ReviewsRepository   → Работа с инфоблоком
└── ReviewDTO               → Валидация данных
```

### Сервисы

**ReviewsService** — упрощенный интерфейс для добавления отзывов

**ReviewCreatorService** — реализация `CreatorContract`, содержит бизнес-логику

**UploadService** — реализация `FileUploaderContract`, загружает файлы через Bitrix CFile

**ReviewsRepository** — работа с инфоблоком отзывов (extends IblockRepository)

**SortingRepository** — статические варианты сортировки (новые/старые)

## Расширение функционала

### Добавление автоответа на отзыв

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\ReviewCreatorService as BaseCreator;
use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Result;

class ReviewCreatorService extends BaseCreator
{
    public function create(ReviewDTO $dto, array $files): Result
    {
        $result = parent::create($dto, $files);

        if ($result->isSuccess()) {
            $this->sendThankYouEmail($dto);
        }

        return $result;
    }

    protected function sendThankYouEmail(ReviewDTO $dto): void
    {
        \CEvent::Send('REVIEW_THANK_YOU', 's1', [
            'USER_NAME' => $dto->userName,
            'EMAIL' => $dto->contactDetails,
        ]);
    }
}
```

Зарегистрируйте в `/local/.settings_extra.php`:

```php
use Beeralex\Reviews\Contracts\CreatorContract;
use App\Reviews\Services\ReviewCreatorService;

return [
    'services' => [
        'value' => [
            CreatorContract::class => [
                'constructor' => static function () {
                    return new ReviewCreatorService(
                        service(FileUploaderContract::class),
                        service(ReviewsRepository::class)
                    );
                }
            ],
        ]
    ]
];
```

### Добавление проверки на спам

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\ReviewCreatorService as BaseCreator;
use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Result;
use Bitrix\Main\Error;

class ReviewCreatorService extends BaseCreator
{
    public function create(ReviewDTO $dto, array $files): Result
    {
        $result = new Result();

        if ($this->isSpam($dto)) {
            $result->addError(new Error('Отзыв отклонен как спам'));
            return $result;
        }

        return parent::create($dto, $files);
    }

    protected function isSpam(ReviewDTO $dto): bool
    {
        $spamWords = ['казино', 'кредит', 'займ'];
        $text = mb_strtolower($dto->review);

        foreach ($spamWords as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }
}
```

### Уведомления через beeralex.notification

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\ReviewCreatorService as BaseCreator;
use Beeralex\Notification\NotificationManager;
use Beeralex\Notification\Dto\NotificationMessage;

class ReviewCreatorService extends BaseCreator
{
    public function create(ReviewDTO $dto, array $files): Result
    {
        $result = parent::create($dto, $files);

        if ($result->isSuccess()) {
            $this->notifyAdmin($dto, $result->getData()['elementId']);
        }

        return $result;
    }

    protected function notifyAdmin(ReviewDTO $dto, int $reviewId): void
    {
        $manager = new NotificationManager();

        $message = new NotificationMessage(
            eventName: 'NEW_REVIEW_MODERATION',
            fields: [
                'REVIEW_ID' => $reviewId,
                'USER_NAME' => $dto->userName,
                'RATING' => $dto->eval,
                'PRODUCT_ID' => $dto->elementId,
            ],
            userId: 1 // Администратор
        );

        $manager->notify($message);
    }
}
```

### Список отзывов товара

```php
class ReviewListComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        $repo = service(ReviewsRepository::class);

        $this->arResult['REVIEWS'] = $repo->all(
            filter: [
                'IBLOCK_SECTION_ID' => $this->arParams['PRODUCT_ID'],
                'ACTIVE' => 'Y'
            ],
            select: [
                'ID', 'DATE_CREATE',
                'PROPERTY_USER_NAME',
                'PROPERTY_EVAL',
                'PROPERTY_REVIEW',
                'PROPERTY_FILES',
            ],
            order: ['ID' => 'DESC']
        );

        // Статистика
        $evals = array_column($this->arResult['REVIEWS'], 'PROPERTY_EVAL_VALUE');
        $this->arResult['AVERAGE_RATING'] = !empty($evals) 
            ? round(array_sum($evals) / count($evals), 1) 
            : 0;
        $this->arResult['TOTAL_COUNT'] = count($this->arResult['REVIEWS']);

        $this->includeComponentTemplate();
    }
}
```

## Модерация

Все новые отзывы создаются с `ACTIVE='N'` и требуют одобрения.

### Автоматическая модерация через агент

```php
// В /local/php_interface/init.php
CAgent::AddAgent(
    "\\App\\Agents\\ReviewModerationAgent::moderate();",
    "", "N", 3600 // Каждый час
);

// Класс агента
namespace App\Agents;

class ReviewModerationAgent
{
    public static function moderate(): string
    {
        $repo = service(ReviewsRepository::class);

        // Одобрить отзывы 4-5 звезд от авторизованных
        $reviews = $repo->all([
            'ACTIVE' => 'N',
            '>=PROPERTY_EVAL' => 4,
            '!PROPERTY_USER' => false,
        ]);

        foreach ($reviews as $review) {
            $repo->update($review['ID'], ['ACTIVE' => 'Y']);
        }

        return "\\App\\Agents\\ReviewModerationAgent::moderate();";
    }
}
```

## Зависимости

- `beeralex.core` — Repository, FileService, AbstractRequestDto
- Bitrix/Main — Result, Error, Validation
- Bitrix/Iblock — работа с инфоблоками

## Документация

Полная документация доступна в [docs/README.md](./docs/README.md)

## Лицензия

Проприетарный модуль. © beeralex