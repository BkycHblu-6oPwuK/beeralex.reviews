<?php
declare(strict_types=1);

namespace Beeralex\Reviews\Dto;

use Beeralex\Core\Http\Request\AbstractRequestDto;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\Min;
use Bitrix\Main\Validation\Rule\Max;

/**
 * DTO для передачи данных отзыва с автоматической валидацией
 */
class ReviewDTO extends AbstractRequestDto
{
    /**
     * Имя пользователя
     */
    #[NotEmpty(errorMessage: 'Имя пользователя обязательно')]
    #[Length(min: 2, max: 100, errorMessage: 'Имя должно быть от 2 до 100 символов')]
    public string $userName = 'Аноним';

    /**
     * Оценка (от 1 до 5)
     */
    #[NotEmpty(errorMessage: 'Оценка обязательна')]
    #[Min(min: 1, errorMessage: 'Минимальная оценка - 1')]
    #[Max(max: 5, errorMessage: 'Максимальная оценка - 5')]
    public int $eval;

    /**
     * Текст отзыва
     */
    #[NotEmpty(errorMessage: 'Текст отзыва обязателен')]
    #[Length(min: 10, max: 5000, errorMessage: 'Отзыв должен быть от 10 до 5000 символов')]
    public string $review;

    /**
     * Контактные данные (email, телефон)
     */
    public ?string $contactDetails = null;

    /**
     * ID товара/элемента к которому оставлен отзыв
     */
    #[NotEmpty(errorMessage: 'ID элемента обязателен')]
    public int $elementId;

    /**
     * ID пользователя (если авторизован)
     */
    public ?int $userId = null;
}
