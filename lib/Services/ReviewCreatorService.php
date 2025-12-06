<?php

namespace Beeralex\Reviews\Services;

use Beeralex\Reviews\Contracts\CreatorContract;
use Beeralex\Reviews\Contracts\FileUploaderContract;
use Beeralex\Reviews\Dto\ReviewDTO;
use Beeralex\Reviews\Repository\ReviewsRepository;
use Bitrix\Main\Error;
use Bitrix\Main\Result;

class ReviewCreatorService implements CreatorContract
{
    public function __construct(
        protected readonly FileUploaderContract $fileUploader,
        protected readonly ReviewsRepository $repository
    )
    {
        \Bitrix\Main\Loader::includeModule('iblock');
    }

    /**
     * Создание отзыва
     * @param ReviewDTO $dto DTO с данными отзыва (автоматически валидируется в контроллере)
     * @param array $files Файлы для загрузки
     * @return int ID созданного элемента
     * @throws \Bitrix\Main\SystemException если данные невалидны
     */
    public function create(ReviewDTO $dto, array $files): Result
    {
        $result = new Result();
        if (!$dto->isValid()) {
            foreach ($dto->getErrors() as $error) {
                $result->addError(new Error($error->getMessage(), 'review_create'), $error->getCustomData());
            }
            return $result;
        }

        $elementData = $this->prepareElementData($dto);
        $fileIds = $this->handleFiles($files);
        
        if (!empty($fileIds)) {
            $elementData['PROPERTY_VALUES']['FILES'] = $fileIds;
        }
        $elementId = $this->repository->add($elementData);
        $result->setData(['elementId' => $elementId]);

        return $result;
    }

    /**
     * Подготовка данных элемента для сохранения
     */
    protected function prepareElementData(ReviewDTO $dto): array
    {
        $properties = [
            'USER_NAME' => $dto->userName,
            'EVAL' => $dto->eval,
            'REVIEW' => [
                'TEXT' => $dto->review,
                'TYPE' => 'HTML'
            ],
            'CONTACT_DETAILS' => $dto->contactDetails ?? '',
            'ELEMENT_ID' => $dto->elementId,
        ];

        // Добавляем USER только если пользователь авторизован
        if ($dto->userId) {
            $properties['USER'] = $dto->userId;
        }
        
        return [
            'NAME' => sprintf('Отзыв от %s', $dto->userName),
            'ACTIVE' => 'N', // Модерация
            'IBLOCK_SECTION_ID' => $dto->elementId,
            'PROPERTY_VALUES' => $properties
        ];
    }

    protected function sanitizeEval(int $eval): int
    {
        return max(1, min(5, $eval));
    }

    protected function handleFiles(array $files): array
    {
        $result = [];
        if (!empty($files)) {
            $result = $this->fileUploader->upload($files);
        }
        return $result;
    }
}
