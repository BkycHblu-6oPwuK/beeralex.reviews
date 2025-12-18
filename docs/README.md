# Документация модуля beeralex.reviews

## Оглавление

1. [Обзор](#обзор)
2. [Архитектура](#архитектура)
3. [Установка](#установка)
4. [Базовые концепции](#базовые-концепции)
5. [ReviewsService](#reviewsservice)
6. [ReviewCreatorService](#reviewcreatorservice)
7. [UploadService](#uploadservice)
8. [ReviewDTO](#reviewdto)
9. [Репозитории](#репозитории)
10. [Импорт отзывов](#импорт-отзывов)
11. [REST API](#rest-api)
12. [Расширение функционала](#расширение-функционала)
13. [Примеры использования](#примеры-использования)

---

## Обзор

**beeralex.reviews** — модуль для управления отзывами в Bitrix с поддержкой:

- ✅ **Создание отзывов** с автоматической валидацией
- 📁 **Загрузка файлов** (фото, документы) к отзывам
- 🔐 **Модерация** — новые отзывы создаются с флагом ACTIVE='N'
- 👤 **Поддержка авторизованных и неавторизованных** пользователей
- ⭐ **Рейтинг** от 1 до 5 звезд
- 📥 **Импорт отзывов** из внешних платформ (2GIS)
- 🔄 **Сортировка** отзывов (новые/старые)
- 🌐 **REST API** для фронтенда

### Ключевые особенности

- Валидация данных на уровне DTO с атрибутами PHP 8.1+
- Contract-based архитектура с DI-контейнером
- Хранение в инфоблоке Bitrix
- Готовая интеграция с модулем `beeralex.api`
- Расширяемая система импорта отзывов

---

## Архитектура

### Диаграмма компонентов

```
┌──────────────────────────────────────────────┐
│         ReviewsService                       │
│  (Фасад для добавления отзывов)              │
└────────────┬─────────────────────────────────┘
             │
      ┌──────▼──────────────────────┐
      │  ReviewCreatorService       │
      │  (implements CreatorContract)│
      └─┬──────────────────────────┬┘
        │                          │
  ┌─────▼─────────┐      ┌────────▼─────────┐
  │ UploadService │      │ReviewsRepository │
  │  (загрузка    │      │  (работа с БД)   │
  │   файлов)     │      │                  │
  └───────────────┘      └──────────────────┘
```

### Основные компоненты

1. **ReviewsService** — фасад для добавления отзывов
2. **ReviewCreatorService** — логика создания отзыва, валидация, подготовка данных
3. **UploadService** — загрузка файлов к отзыву
4. **ReviewsRepository** — работа с инфоблоком отзывов
5. **ReviewDTO** — объект передачи данных с валидацией
6. **Import/** — импорт отзывов из внешних источников
7. **SortingRepository** — статические сортировки

---

## Установка

### Требования

- PHP 8.1+
- Bitrix Framework 22.0+
- Модуль `beeralex.core` (базовые абстракции)
- Модуль `beeralex.api` (для REST API)
- Инфоблок с кодом `product_reviews`

### Процесс установки

1. Разместите модуль в `/local/modules/beeralex.reviews/`
2. Установите через административную панель
3. Модуль автоматически зарегистрирует сервисы в DI-контейнере
4. Создайте инфоблок "Отзывы" с кодом `product_reviews`

### Структура инфоблока

**Код инфоблока:** `product_reviews`

**Свойства:**

```
USER_NAME       (string)  - Имя пользователя
EVAL            (number)  - Оценка (1-5)
REVIEW          (text)    - Текст отзыва (HTML)
CONTACT_DETAILS (string)  - Контактные данные (email/телефон)
ELEMENT_ID      (number)  - ID товара/элемента
USER            (number)  - ID пользователя Bitrix (если авторизован)
FILES           (file)    - Прикрепленные файлы (множественное)
```

**Настройки:**

- `ACTIVE` — по умолчанию 'N' (модерация)
- `IBLOCK_SECTION_ID` — используется для хранения ID товара (нестандартно, но так реализовано)

---

## Базовые концепции

### ReviewDTO

DTO (Data Transfer Object) с автоматической валидацией через атрибуты PHP 8.1+.

```php
namespace Beeralex\Reviews\Dto;

use Beeralex\Core\Http\Request\AbstractRequestDto;
use Bitrix\Main\Validation\Rule\*;

class ReviewDTO extends AbstractRequestDto
{
    #[NotEmpty(errorMessage: 'Имя пользователя обязательно')]
    #[Length(min: 2, max: 100, errorMessage: 'Имя должно быть от 2 до 100 символов')]
    public string $userName = 'Аноним';

    #[NotEmpty(errorMessage: 'Оценка обязательна')]
    #[Min(value: 1, errorMessage: 'Минимальная оценка - 1')]
    #[Max(value: 5, errorMessage: 'Максимальная оценка - 5')]
    public int $eval;

    #[NotEmpty(errorMessage: 'Текст отзыва обязателен')]
    #[Length(min: 10, max: 5000, errorMessage: 'Отзыв должен быть от 10 до 5000 символов')]
    public string $review;

    public ?string $contactDetails = null;

    #[NotEmpty(errorMessage: 'ID элемента обязателен')]
    public int $elementId;

    public ?int $userId = null;
}
```

**Валидация автоматически выполняется:**
- При биндинге в контроллере API
- При вызове `$dto->isValid()`
- Ошибки доступны через `$dto->getErrors()`

### Contracts

**CreatorContract** — интерфейс для создания отзывов:

```php
interface CreatorContract
{
    public function create(ReviewDTO $dto, array $files): Result;
}
```

**FileUploaderContract** — интерфейс для загрузки файлов:

```php
interface FileUploaderContract
{
    public function upload(array $files): array;
}
```

---

## ReviewsService

Основной фасад для работы с отзывами. Упрощает интерфейс создания отзывов.

### Конструктор

```php
public function __construct(
    protected readonly CreatorContract $creator
)
```

### Методы

#### `add(ReviewDTO $dto, array $files): Result`

Добавляет отзыв с файлами.

**Параметры:**
- `$dto` — валидированный DTO с данными отзыва
- `$files` — массив файлов из `$_FILES`

**Возвращает:**
- `Result` — результат операции с `elementId` в данных

**Пример:**

```php
use Beeralex\Reviews\Services\ReviewsService;
use Beeralex\Reviews\Dto\ReviewDTO;

$service = service(ReviewsService::class);

$dto = new ReviewDTO();
$dto->userName = 'Иван Петров';
$dto->eval = 5;
$dto->review = 'Отличный товар, рекомендую!';
$dto->elementId = 123; // ID товара
$dto->userId = 100;     // ID пользователя (если авторизован)

$result = $service->add($dto, $_FILES);

if ($result->isSuccess()) {
    $elementId = $result->getData()['elementId'];
    echo "Отзыв создан с ID: {$elementId}";
} else {
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
}
```

---

## ReviewCreatorService

Реализация `CreatorContract`. Содержит логику создания отзыва.

### Конструктор

```php
public function __construct(
    protected readonly FileUploaderContract $fileUploader,
    protected readonly ReviewsRepository $repository
)
```

### Методы

#### `create(ReviewDTO $dto, array $files): Result`

Создает отзыв в инфоблоке.

**Процесс:**
1. Валидация DTO
2. Подготовка данных элемента (`prepareElementData()`)
3. Загрузка файлов через `fileUploader`
4. Добавление элемента в инфоблок
5. Возврат результата с ID элемента

**Пример:**

```php
use Beeralex\Reviews\Services\ReviewCreatorService;
use Beeralex\Reviews\Dto\ReviewDTO;

$creator = service(CreatorContract::class);

$dto = new ReviewDTO();
$dto->userName = 'Мария';
$dto->eval = 4;
$dto->review = 'Хороший товар, быстрая доставка';
$dto->elementId = 456;

$result = $creator->create($dto, []);

if (!$result->isSuccess()) {
    print_r($result->getErrorMessages());
}
```

#### `prepareElementData(ReviewDTO $dto): array` (protected)

Подготавливает данные для сохранения в инфоблок.

**Возвращает:**

```php
[
    'NAME' => 'Отзыв от Имя',
    'ACTIVE' => 'N',  // Модерация
    'IBLOCK_SECTION_ID' => $dto->elementId,  // ID товара
    'PROPERTY_VALUES' => [
        'USER_NAME' => '...',
        'EVAL' => 5,
        'REVIEW' => ['TEXT' => '...', 'TYPE' => 'HTML'],
        'CONTACT_DETAILS' => '...',
        'ELEMENT_ID' => 123,
        'USER' => 100,  // Только если авторизован
    ]
]
```

⚠️ **Особенность:** `IBLOCK_SECTION_ID` используется для хранения ID товара (нестандартное решение).

#### `handleFiles(array $files): array` (protected)

Обрабатывает файлы, возвращает массив ID сохраненных файлов.

```php
$fileIds = $this->handleFiles($_FILES);
// [123, 456, 789]
```

---

## UploadService

Реализация `FileUploaderContract`. Загружает файлы в Bitrix.

### Конструктор

```php
public function __construct(
    protected readonly FileService $fileService  // из beeralex.core
)
```

### Методы

#### `upload(array $files): array`

Загружает файлы и возвращает массив их ID.

**Параметры:**
- `$files` — массив из `$_FILES`

**Возвращает:**
- `array` — массив ID загруженных файлов `[123, 456, ...]`

**Процесс:**
1. Форматирование файлов через `FileService::getFormattedToSafe()`
2. Сохранение каждого файла через `CFile::SaveFile()`
3. Возврат массива ID

**Пример:**

```php
use Beeralex\Reviews\Services\UploadService;

$uploader = service(FileUploaderContract::class);
$fileIds = $uploader->upload($_FILES);

// Использовать ID для свойства FILES
$elementData['PROPERTY_VALUES']['FILES'] = $fileIds;
```

---

## ReviewDTO

### Свойства

| Свойство | Тип | Обязательно | Валидация | Описание |
|----------|-----|-------------|-----------|----------|
| `userName` | string | Да | 2-100 символов | Имя пользователя (по умолчанию 'Аноним') |
| `eval` | int | Да | 1-5 | Оценка (рейтинг) |
| `review` | string | Да | 10-5000 символов | Текст отзыва |
| `contactDetails` | ?string | Нет | - | Email или телефон |
| `elementId` | int | Да | NotEmpty | ID товара/элемента |
| `userId` | ?int | Нет | - | ID пользователя Bitrix |

### Методы валидации

#### `isValid(): bool`

Проверяет валидность данных.

```php
$dto = new ReviewDTO();
$dto->userName = 'А'; // Слишком короткое

if (!$dto->isValid()) {
    foreach ($dto->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
}
// Выведет: "Имя должно быть от 2 до 100 символов"
```

#### `getErrors(): array`

Возвращает массив ошибок валидации.

```php
$errors = $dto->getErrors();
// [Error object, Error object, ...]

foreach ($errors as $error) {
    echo $error->getMessage() . "\n";
    print_r($error->getCustomData());
}
```

### Пример использования

```php
use Beeralex\Reviews\Dto\ReviewDTO;

// Создание DTO из запроса (автоматический биндинг в контроллере)
public function storeAction(ReviewDTO $review)
{
    // $review уже содержит данные из $_POST и валидирован
    $files = $this->getRequest()->getFileList()->toArray();
    return service(ReviewsService::class)->add($review, $files);
}

// Ручное создание DTO
$dto = new ReviewDTO();
$dto->userName = 'Петр Иванов';
$dto->eval = 5;
$dto->review = 'Отличный товар, очень доволен покупкой!';
$dto->contactDetails = 'petr@example.com';
$dto->elementId = 123;
$dto->userId = null; // Неавторизованный пользователь

if ($dto->isValid()) {
    service(ReviewsService::class)->add($dto, []);
}
```

---

## Репозитории

### ReviewsRepository

Репозиторий для работы с инфоблоком отзывов.

**Наследуется от:** `Beeralex\Core\Repository\IblockRepository`

```php
class ReviewsRepository extends IblockRepository
{
    // Наследует все методы базового репозитория
}
```

#### Конструктор

```php
public function __construct()
{
    parent::__construct('product_reviews'); // Код инфоблока
}
```

#### Унаследованные методы

**`add(array $data): int`**

Добавляет элемент в инфоблок.

```php
$repo = service(ReviewsRepository::class);

$elementId = $repo->add([
    'NAME' => 'Отзыв от Иван',
    'ACTIVE' => 'N',
    'PROPERTY_VALUES' => [
        'USER_NAME' => 'Иван',
        'EVAL' => 5,
        'REVIEW' => ['TEXT' => 'Отлично!', 'TYPE' => 'HTML'],
        'ELEMENT_ID' => 123,
    ]
]);
```

**`all(array $filter, array $select, array $order): array`**

Получает все отзывы с фильтрацией.

```php
// Получить все отзывы товара
$reviews = $repo->all(
    ['IBLOCK_SECTION_ID' => 123, 'ACTIVE' => 'Y'],
    ['ID', 'NAME', 'PROPERTY_USER_NAME', 'PROPERTY_EVAL'],
    ['ID' => 'DESC']
);
```

**`one(array $filter, array $select): ?array`**

Получить один отзыв.

```php
$review = $repo->one(['ID' => 456]);
```

**`update(int $id, array $data): void`**

Обновить отзыв.

```php
$repo->update(456, [
    'ACTIVE' => 'Y', // Одобрить отзыв
]);
```

**`delete(int $id): void`**

Удалить отзыв.

```php
$repo->delete(456);
```

### SortingRepository

Статический репозиторий для сортировок отзывов.

**Реализует:** `SortingRepositoryContract`

```php
class SortingRepository extends Repository implements SortingRepositoryContract
{
    public function all(...): array
    {
        return [
            [
                'ID' => 1,
                'NAME' => 'Сначала новые',
                'CODE' => 'new',
                'SORT_BY' => ['VALUE' => 'ID'],
                'DIRECTION' => ['VALUE' => 'DESC'],
            ],
            [
                'ID' => 2,
                'NAME' => 'Сначала старые',
                'CODE' => 'old',
                'SORT_BY' => ['VALUE' => 'ID'],
                'DIRECTION' => ['VALUE' => 'ASC'],
            ],
        ];
    }

    public function getDefaultSorting(...): ?array
    {
        return $this->one(['ID' => 1]);
    }
}
```

#### Использование

```php
use Beeralex\Reviews\Repository\SortingRepository;

$sortingRepo = service(DIServiceKey::SORTING_REPOSITORY->value);

// Получить все варианты сортировки
$sortings = $sortingRepo->all();

// Получить дефолтную сортировку
$default = $sortingRepo->getDefaultSorting();
// ['ID' => 1, 'NAME' => 'Сначала новые', 'CODE' => 'new', ...]
```

---

## Импорт отзывов

### BaseImport (abstract)

Абстрактный класс для импорта отзывов из внешних источников.

⚠️ **Помечен как @deprecated**. Автор рекомендует вместо импорта создать сервис для запроса к API в реальном времени.

#### Конструктор

```php
public function __construct(ReviewsService $service)
{
    $this->service = $service;
    $this->reviewsRepository = new ReviewsRepository();
}
```

#### Абстрактные методы

**`process(): void`**

Основной метод импорта (должен быть реализован в наследниках).

#### Методы

**`import(array $items): void`**

Импортирует массив отзывов.

```php
$items = [
    [
        'form' => [
            'eval' => 5,
            'review' => 'Отличный сервис',
            'user_name' => 'Иван',
            'element_id' => 123,
        ],
        'files' => $_FILES,
        'tmp_paths' => ['/tmp/img1.jpg', '/tmp/img2.jpg']
    ],
    // ...
];

$importer->import($items);
```

**Процесс:**
1. Проверка дубликатов (по `external_id` и `platform`)
2. Добавление отзыва через `ReviewsService`
3. Удаление временных файлов
4. Логирование ошибок

**`downloadFile(string $url): ?array` (protected)**

Скачивает файл по URL и возвращает массив для `$_FILES`.

```php
$file = $this->downloadFile('https://example.com/photo.jpg');
// [
//   'name' => 'tmp_abc123.jpg',
//   'type' => 'image/jpeg',
//   'tmp_name' => '/tmp/tmp_abc123.jpg',
//   'error' => 0,
//   'size' => 12345
// ]
```

**`removeFiles(array $paths): void` (protected)**

Удаляет временные файлы.

```php
$this->removeFiles(['/tmp/img1.jpg', '/tmp/img2.jpg']);
```

**`setToFiles(array &$result, array $file): void` (protected)**

Добавляет файл в структуру `$_FILES`.

**`logError(\Throwable $e, $item): void` (protected)**

Логирует ошибку импорта в `/logs/import_error.log`.

### ImportFrom2Gis

Реализация импорта отзывов из 2GIS API.

⚠️ **Помечен как @deprecated**.

#### Конструктор

```php
public function __construct(
    ReviewsService $service, 
    array $branches,  // ID филиалов в 2GIS
    string $apiKey    // API ключ 2GIS
)
```

#### Методы

**`process(): void`**

Запускает импорт отзывов из 2GIS.

**Процесс:**
1. Для каждого филиала делает запросы к API 2GIS
2. Фильтрует отзывы (рейтинг >= 4)
3. Скачивает фотографии
4. Импортирует через `import()`

**Пример:**

```php
use Beeralex\Reviews\Import\ImportFrom2Gis;
use Beeralex\Reviews\Services\ReviewsService;

$importer = new ImportFrom2Gis(
    service: service(ReviewsService::class),
    branches: ['70000001234567890', '70000009876543210'], // ID из 2GIS
    apiKey: 'your_2gis_api_key'
);

$importer->process();
// Импортирует все отзывы с рейтингом >= 4
```

**`fetch(string $url): ?array` (protected)**

Выполняет HTTP-запрос к API 2GIS.

**`downloadPhotos(array $review): array` (protected)**

Скачивает фотографии из отзыва 2GIS.

---

## REST API

Модуль интегрирован с `beeralex.api` через `ReviewController`.

### Эндпоинты

#### `GET /api/v1/review/index/`

Получить список отзывов с пагинацией.

**Параметры запроса:**

| Параметр | Тип | Описание |
|----------|-----|----------|
| `count` | int | Количество отзывов (по умолчанию 20) |
| `product_id` | int | ID товара |
| `search` | string | Поисковый запрос |
| `filter` | bool | Применить фильтрацию |

**Пример:**

```javascript
fetch('/api/v1/review/index/?product_id=123&count=10')
  .then(res => res.json())
  .then(data => {
    console.log('Отзывы:', data);
  });
```

#### `POST /api/v1/review/store/`

Создать новый отзыв.

**Тело запроса (multipart/form-data):**

```
userName: "Иван Петров"
eval: 5
review: "Отличный товар!"
contactDetails: "ivan@example.com"
elementId: 123
files[]: (file)
files[]: (file)
```

**Пример (JavaScript):**

```javascript
const formData = new FormData();
formData.append('userName', 'Иван Петров');
formData.append('eval', 5);
formData.append('review', 'Отличный товар, рекомендую!');
formData.append('elementId', 123);
formData.append('files[]', fileInput.files[0]);
formData.append('files[]', fileInput.files[1]);

fetch('/api/v1/review/store/', {
  method: 'POST',
  body: formData
})
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      console.log('Отзыв создан с ID:', data.data.elementId);
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

**Ответ (ошибка валидации):**

```json
{
  "status": "error",
  "errors": [
    {
      "message": "Имя должно быть от 2 до 100 символов",
      "code": "review_create"
    },
    {
      "message": "Отзыв должен быть от 10 до 5000 символов",
      "code": "review_create"
    }
  ]
}
```

---

## Расширение функционала

### Добавление автоответа на отзыв

Создайте расширенный сервис:

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\ReviewCreatorService as BaseCreator;
use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Result;

class ReviewCreatorService extends BaseCreator
{
    /**
     * Создание отзыва с автоматическим ответом
     */
    public function create(ReviewDTO $dto, array $files): Result
    {
        $result = parent::create($dto, $files);

        if ($result->isSuccess()) {
            $elementId = $result->getData()['elementId'];
            $this->sendAutoReply($elementId, $dto);
        }

        return $result;
    }

    protected function sendAutoReply(int $elementId, ReviewDTO $dto): void
    {
        // Отправить email с благодарностью
        \CEvent::Send(
            'REVIEW_AUTO_REPLY',
            's1',
            [
                'USER_NAME' => $dto->userName,
                'EMAIL' => $dto->contactDetails,
                'REVIEW_ID' => $elementId,
            ]
        );
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

### Добавление webhook после создания отзыва

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\ReviewsService as BaseService;
use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Result;
use Bitrix\Main\Web\HttpClient;

class ReviewsService extends BaseService
{
    public function add(ReviewDTO $dto, array $files): Result
    {
        $result = parent::add($dto, $files);

        if ($result->isSuccess()) {
            $this->notifyWebhook($dto, $result->getData()['elementId']);
        }

        return $result;
    }

    protected function notifyWebhook(ReviewDTO $dto, int $elementId): void
    {
        $client = new HttpClient();
        $client->post('https://your-webhook.com/reviews', [
            'review_id' => $elementId,
            'user_name' => $dto->userName,
            'rating' => $dto->eval,
            'product_id' => $dto->elementId,
            'created_at' => date('c'),
        ]);
    }
}
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

        // Проверка на спам
        if ($this->isSpam($dto)) {
            $result->addError(new Error('Отзыв отклонен как спам'));
            return $result;
        }

        return parent::create($dto, $files);
    }

    protected function isSpam(ReviewDTO $dto): bool
    {
        // Проверка на запрещенные слова
        $spamWords = ['казино', 'кредит', 'займ', 'viagra'];
        $text = mb_strtolower($dto->review);

        foreach ($spamWords as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        // Проверка на частоту отзывов с одного IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $recentCount = $this->repository->count([
            '>=DATE_CREATE' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'PROPERTY_IP' => $ip, // Нужно добавить свойство IP
        ]);

        return $recentCount > 5; // Максимум 5 отзывов в час с одного IP
    }
}
```

### Добавление уведомлений администратору

Используйте модуль `beeralex.notification`:

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\ReviewCreatorService as BaseCreator;
use Beeralex\Reviews\Dto\ReviewDTO;
use Beeralex\Notification\NotificationManager;
use Beeralex\Notification\Dto\NotificationMessage;
use Bitrix\Main\Result;

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

    protected function notifyAdmin(ReviewDTO $dto, int $elementId): void
    {
        $manager = new NotificationManager();

        $message = new NotificationMessage(
            eventName: 'NEW_REVIEW_MODERATION',
            fields: [
                'REVIEW_ID' => $elementId,
                'USER_NAME' => $dto->userName,
                'RATING' => $dto->eval,
                'REVIEW_TEXT' => $dto->review,
                'PRODUCT_ID' => $dto->elementId,
                'MODERATION_LINK' => "https://your-site.com/bitrix/admin/iblock_element_edit.php?ID={$elementId}",
            ],
            userId: 1 // ID администратора
        );

        $manager->notify($message);
    }
}
```

### Создание собственного валидатора

```php
namespace App\Reviews\Validators;

use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Error;

class ReviewValidator
{
    /**
     * Расширенная валидация отзыва
     */
    public static function validateExtended(ReviewDTO $dto): array
    {
        $errors = [];

        // Проверка на наличие контактов для негативных отзывов
        if ($dto->eval < 3 && empty($dto->contactDetails)) {
            $errors[] = new Error('Для отзывов с оценкой ниже 3 требуются контактные данные');
        }

        // Проверка на минимальную длину для высоких оценок
        if ($dto->eval >= 4 && mb_strlen($dto->review) < 50) {
            $errors[] = new Error('Для высокой оценки требуется более подробный отзыв (минимум 50 символов)');
        }

        // Проверка на дубликаты
        $repo = service(ReviewsRepository::class);
        $existing = $repo->one([
            'PROPERTY_ELEMENT_ID' => $dto->elementId,
            'PROPERTY_USER' => $dto->userId,
            '>=DATE_CREATE' => date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);

        if ($existing) {
            $errors[] = new Error('Вы уже оставляли отзыв на этот товар в течение последних 7 дней');
        }

        return $errors;
    }
}

// Использование
class ReviewCreatorService extends BaseCreator
{
    public function create(ReviewDTO $dto, array $files): Result
    {
        $result = new Result();

        // Базовая валидация
        if (!$dto->isValid()) {
            foreach ($dto->getErrors() as $error) {
                $result->addError($error);
            }
            return $result;
        }

        // Расширенная валидация
        $extendedErrors = ReviewValidator::validateExtended($dto);
        if (!empty($extendedErrors)) {
            foreach ($extendedErrors as $error) {
                $result->addError($error);
            }
            return $result;
        }

        return parent::create($dto, $files);
    }
}
```

### Добавление поддержки ответов на отзывы

Расширьте DTO:

```php
namespace App\Reviews\Dto;

use Beeralex\Reviews\Dto\ReviewDTO as BaseDTO;

class ReviewDTO extends BaseDTO
{
    /**
     * Ответ на отзыв (только для администраторов)
     */
    public ?string $officialAnswer = null;

    /**
     * Дата ответа
     */
    public ?\DateTime $answerDate = null;
}
```

Обновите `prepareElementData`:

```php
protected function prepareElementData(ReviewDTO $dto): array
{
    $data = parent::prepareElementData($dto);

    if ($dto->officialAnswer) {
        $data['PROPERTY_VALUES']['OFFICIAL_ANSWER'] = [
            'TEXT' => $dto->officialAnswer,
            'TYPE' => 'HTML'
        ];
        $data['PROPERTY_VALUES']['ANSWER_DATE'] = $dto->answerDate?->format('d.m.Y H:i:s');
    }

    return $data;
}
```

### Добавление загрузки фото с водяным знаком

```php
namespace App\Reviews\Services;

use Beeralex\Reviews\Services\UploadService as BaseUpload;

class UploadService extends BaseUpload
{
    public function upload(array $files): array
    {
        // Добавляем водяной знак перед загрузкой
        $processedFiles = $this->addWatermark($files);
        return parent::upload($processedFiles);
    }

    protected function addWatermark(array $files): array
    {
        foreach ($files['tmp_name'] as $key => $tmpPath) {
            if (!is_uploaded_file($tmpPath)) {
                continue;
            }

            $image = imagecreatefromjpeg($tmpPath);
            $watermark = imagecreatefrompng('/local/watermark.png');

            // Наложение водяного знака
            imagecopymerge(
                $image, 
                $watermark, 
                imagesx($image) - imagesx($watermark) - 10, 
                imagesy($image) - imagesy($watermark) - 10,
                0, 
                0, 
                imagesx($watermark), 
                imagesy($watermark), 
                50 // Прозрачность 50%
            );

            // Сохранение
            imagejpeg($image, $tmpPath, 85);
            imagedestroy($image);
            imagedestroy($watermark);
        }

        return $files;
    }
}
```

---

## Примеры использования

### Простое создание отзыва

```php
use Beeralex\Reviews\Services\ReviewsService;
use Beeralex\Reviews\Dto\ReviewDTO;

$service = service(ReviewsService::class);

$dto = new ReviewDTO();
$dto->userName = 'Анна Смирнова';
$dto->eval = 5;
$dto->review = 'Превосходный товар! Доставка быстрая, качество отличное. Рекомендую!';
$dto->contactDetails = 'anna@example.com';
$dto->elementId = 123;
$dto->userId = null; // Неавторизованный

$result = $service->add($dto, []);

if ($result->isSuccess()) {
    echo "Отзыв отправлен на модерацию!";
} else {
    echo "Ошибки:\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "- {$error}\n";
    }
}
```

### Создание отзыва с файлами

```php
// В компоненте или контроллере
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dto = new ReviewDTO();
    $dto->userName = $_POST['userName'];
    $dto->eval = (int)$_POST['eval'];
    $dto->review = $_POST['review'];
    $dto->contactDetails = $_POST['contactDetails'] ?? null;
    $dto->elementId = (int)$_POST['elementId'];
    $dto->userId = $USER->GetID() ?: null;

    $result = service(ReviewsService::class)->add($dto, $_FILES);

    if ($result->isSuccess()) {
        LocalRedirect('/thank-you/');
    }
}
```

### Получение отзывов товара

```php
use Beeralex\Reviews\Repository\ReviewsRepository;

$repo = service(ReviewsRepository::class);

// Получить все одобренные отзывы товара
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
        'PROPERTY_FILES',
    ],
    order: ['ID' => 'DESC']
);

foreach ($reviews as $review) {
    echo "<div class='review'>";
    echo "<strong>{$review['PROPERTY_USER_NAME_VALUE']}</strong> ";
    echo str_repeat('⭐', $review['PROPERTY_EVAL_VALUE']);
    echo "<p>{$review['PROPERTY_REVIEW_VALUE']['TEXT']}</p>";
    
    if (!empty($review['PROPERTY_FILES_VALUE'])) {
        echo "<div class='photos'>";
        foreach ($review['PROPERTY_FILES_VALUE'] as $fileId) {
            $file = CFile::GetFileArray($fileId);
            echo "<img src='{$file['SRC']}' alt='Фото'>";
        }
        echo "</div>";
    }
    echo "</div>";
}
```

### Компонент формы отзыва

```php
class ReviewFormComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        global $USER;

        $this->arResult['PRODUCT_ID'] = $this->arParams['PRODUCT_ID'];
        $this->arResult['USER_NAME'] = $USER->GetFullName() ?: '';
        $this->arResult['USER_ID'] = $USER->GetID();
        $this->arResult['USER_EMAIL'] = $USER->GetEmail();

        if ($this->request->isPost() && check_bitrix_sessid()) {
            $this->handleSubmit();
        }

        $this->includeComponentTemplate();
    }

    protected function handleSubmit(): void
    {
        $dto = new ReviewDTO();
        $dto->userName = $this->request->getPost('userName');
        $dto->eval = (int)$this->request->getPost('eval');
        $dto->review = $this->request->getPost('review');
        $dto->contactDetails = $this->request->getPost('contactDetails');
        $dto->elementId = (int)$this->request->getPost('elementId');
        $dto->userId = $GLOBALS['USER']->GetID() ?: null;

        $result = service(ReviewsService::class)->add($dto, $_FILES);

        if ($result->isSuccess()) {
            $this->arResult['SUCCESS'] = true;
            $this->arResult['MESSAGE'] = 'Спасибо! Ваш отзыв отправлен на модерацию.';
        } else {
            $this->arResult['ERRORS'] = $result->getErrorMessages();
        }
    }
}
```

**Шаблон компонента:**

```php
<?php if (!empty($arResult['SUCCESS'])): ?>
    <div class="alert alert-success">
        <?= $arResult['MESSAGE'] ?>
    </div>
<?php endif; ?>

<?php if (!empty($arResult['ERRORS'])): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($arResult['ERRORS'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= bitrix_sessid_post() ?>
    
    <input type="hidden" name="elementId" value="<?= $arResult['PRODUCT_ID'] ?>">
    
    <div class="form-group">
        <label>Ваше имя *</label>
        <input type="text" name="userName" class="form-control" 
               value="<?= htmlspecialchars($arResult['USER_NAME']) ?>" required>
    </div>

    <div class="form-group">
        <label>Оценка *</label>
        <div class="rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <input type="radio" name="eval" value="<?= $i ?>" id="star<?= $i ?>" required>
                <label for="star<?= $i ?>">⭐</label>
            <?php endfor; ?>
        </div>
    </div>

    <div class="form-group">
        <label>Ваш отзыв *</label>
        <textarea name="review" class="form-control" rows="5" 
                  placeholder="Расскажите о своем опыте..." required></textarea>
    </div>

    <div class="form-group">
        <label>Email (необязательно)</label>
        <input type="email" name="contactDetails" class="form-control" 
               value="<?= htmlspecialchars($arResult['USER_EMAIL']) ?>">
    </div>

    <div class="form-group">
        <label>Фото (до 5 файлов)</label>
        <input type="file" name="files[]" class="form-control" 
               accept="image/*" multiple>
    </div>

    <button type="submit" class="btn btn-primary">Отправить отзыв</button>
</form>
```

### Модерация отзывов через агент

```php
// Добавить в /local/php_interface/init.php
CAgent::AddAgent(
    "\\App\\Agents\\ReviewModerationAgent::moderate();",
    "",
    "N",
    3600, // Каждый час
    "",
    "Y",
    "",
    100
);

// /local/php_interface/classes/Agents/ReviewModerationAgent.php
namespace App\Agents;

use Beeralex\Reviews\Repository\ReviewsRepository;

class ReviewModerationAgent
{
    public static function moderate(): string
    {
        $repo = service(ReviewsRepository::class);

        // Автоматически одобрить отзывы с рейтингом 4-5 от авторизованных пользователей
        $reviews = $repo->all([
            'ACTIVE' => 'N',
            '>=PROPERTY_EVAL' => 4,
            '!PROPERTY_USER' => false, // Только авторизованные
        ]);

        foreach ($reviews as $review) {
            $repo->update($review['ID'], ['ACTIVE' => 'Y']);
        }

        return "\\App\\Agents\\ReviewModerationAgent::moderate();";
    }
}
```

### AJAX отправка отзыва

**JavaScript:**

```javascript
document.getElementById('reviewForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('/api/v1/review/store/', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('Спасибо! Ваш отзыв отправлен на модерацию.');
            e.target.reset();
        } else {
            const errors = result.errors.map(e => e.message).join('\n');
            alert('Ошибки:\n' + errors);
        }
    } catch (error) {
        console.error('Ошибка отправки:', error);
        alert('Произошла ошибка. Попробуйте позже.');
    }
});
```

### Получение статистики отзывов

```php
use Beeralex\Reviews\Repository\ReviewsRepository;

$repo = service(ReviewsRepository::class);

// Статистика по товару
$productId = 123;

$allReviews = $repo->all([
    'IBLOCK_SECTION_ID' => $productId,
    'ACTIVE' => 'Y'
]);

$totalCount = count($allReviews);
$totalRating = array_sum(array_column($allReviews, 'PROPERTY_EVAL_VALUE'));
$averageRating = $totalCount > 0 ? round($totalRating / $totalCount, 1) : 0;

// Распределение по оценкам
$ratingDistribution = [];
for ($i = 1; $i <= 5; $i++) {
    $count = count(array_filter($allReviews, fn($r) => $r['PROPERTY_EVAL_VALUE'] == $i));
    $ratingDistribution[$i] = [
        'count' => $count,
        'percent' => $totalCount > 0 ? round(($count / $totalCount) * 100) : 0
    ];
}

echo "Средний рейтинг: {$averageRating} из 5 ({$totalCount} отзывов)\n\n";
echo "Распределение:\n";
for ($i = 5; $i >= 1; $i--) {
    echo "{$i} ⭐: {$ratingDistribution[$i]['count']} ({$ratingDistribution[$i]['percent']}%)\n";
}
```

### Виджет последних отзывов

```php
class LatestReviewsComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        $repo = service(ReviewsRepository::class);

        $this->arResult['REVIEWS'] = $repo->all(
            filter: ['ACTIVE' => 'Y'],
            select: [
                'ID',
                'DATE_CREATE',
                'PROPERTY_USER_NAME',
                'PROPERTY_EVAL',
                'PROPERTY_REVIEW',
                'PROPERTY_ELEMENT_ID',
            ],
            order: ['ID' => 'DESC'],
            limit: 5
        );

        $this->includeComponentTemplate();
    }
}
```

---

## Обработка ошибок

### Типичные ошибки валидации

```php
$dto = new ReviewDTO();
$dto->userName = 'A'; // Слишком короткое
$dto->eval = 6;       // Вне диапазона
$dto->review = 'OK';  // Слишком короткий

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

### Обработка ошибок загрузки файлов

```php
try {
    $result = service(ReviewsService::class)->add($dto, $_FILES);
    
    if (!$result->isSuccess()) {
        $errors = $result->getErrors();
        foreach ($errors as $error) {
            logError($error->getMessage(), $error->getCode());
        }
    }
} catch (\Exception $e) {
    logCriticalError($e);
}
```

---

## Миграции

Модуль использует миграции для управления структурой БД.

### Version20251117100501

Миграция находится в `/migrations/Version20251117100501.php`.

**Назначение:**
- Создание/обновление инфоблока отзывов
- Создание свойств инфоблока
- Настройка прав доступа

---

## Логирование

### Логи импорта

Ошибки импорта логируются в `/logs/import_error.log`:

```php
use Beeralex\Core\Logger\FileLogger;

$logger = new FileLogger(__DIR__ . '/logs/import_error.log');
$logger->error('Ошибка импорта отзыва', [
    'external_id' => '12345',
    'platform' => '2gis',
    'error' => $exception->getMessage()
]);
```

### Включение отладки

Добавьте в `/local/php_interface/init.php`:

```php
define('REVIEWS_DEBUG', true);
```

---

## Зависимости

- **beeralex.core** — базовые абстракции (Repository, FileService, AbstractRequestDto)
- **beeralex.api** — REST API контроллеры
- Bitrix/Main — Result, Error, валидация
- Bitrix/Iblock — работа с инфоблоками

---

## Лицензия

Проприетарный модуль. © beeralex

---

## Заключение

Модуль **beeralex.reviews** предоставляет полнофункциональную систему управления отзывами с:

- ✅ Валидацией на уровне DTO
- ✅ Загрузкой файлов
- ✅ Модерацией
- ✅ REST API
- ✅ Импортом из внешних источников
- ✅ Гибкой архитектурой для расширений

Для добавления новых функций используйте наследование сервисов и регистрацию в DI-контейнере через `/local/.settings_extra.php`.
