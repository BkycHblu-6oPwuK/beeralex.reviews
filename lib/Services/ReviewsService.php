<?php
namespace Beeralex\Reviews\Services;

use Beeralex\Reviews\Contracts\CreatorContract;
use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Result;

class ReviewsService
{
    public function __construct(
        protected readonly CreatorContract $creator,
    )
    {}

    /**
     * Добавление отзыва
     * @param ReviewDTO $dto DTO с данными отзыва
     * @param array $files Файлы для загрузки
     */
    public function add(ReviewDTO $dto, array $files): Result
    {
        return $this->creator->create($dto, $files);
    }
}
