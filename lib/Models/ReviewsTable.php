<?php

namespace Beeralex\Reviews\Models;

use Bitrix\Iblock\Elements\ElementProductReviewsApiTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Beeralex\Reviews\Resources\Elements;
use Beeralex\Reviews\Resources\Files;
use Beeralex\Reviews\Resources\FilesForElements;

class ReviewsTable extends ElementProductReviewsApiTable
{
    public static function getObjectClass()
    {
        return self::class;
    }
}
