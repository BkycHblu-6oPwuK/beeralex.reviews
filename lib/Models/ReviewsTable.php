<?php

namespace Beeralex\Reviews\Models;

use Bitrix\Iblock\Elements\ElementProductReviewsApiTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Beeralex\Reviews\Resources\Elements;
use Beeralex\Reviews\Resources\Files;
use Beeralex\Reviews\Resources\FilesForElements;

Loader::includeModule('iblock');

class ReviewsTable extends ElementProductReviewsApiTable
{
    public static function getObjectClass()
    {
        return self::class;
    }

    public static function getCountReviews(int $productId)
    {
        static $count = 0;
        if (!$count) {
            $query = self::query()->where('ACTIVE', 'Y');

            if ($productId) {
                $query = $query->where('PRODUCT.VALUE', $productId);
            }

            $count = $query->countTotal(true)->exec()->getCount();
        }
        return $count;
    }

    private static function addFileToQuery(\Bitrix\Iblock\ORM\Query $query): \Bitrix\Iblock\ORM\Query
    {
        $query = $query->setSelect(array_merge(
            $query->getSelect(),
            ['ID_FILE' => 'FILES.VALUE', 'PREVIEW_PICTURE', 'SRC_FILE', 'SRC_THUMBAIL', 'TYPE' => 'FILE.CONTENT_TYPE']
        ))
            ->registerRuntimeField('FILE', [
                'data_type' => FileTable::class,
                'reference' => [
                    '=this.FILES.VALUE' => 'ref.ID',
                ],
                'join_type' => 'INNER'
            ])
            ->registerRuntimeField('FILE_THUMBAIL', [
                'data_type' => FileTable::class,
                'reference' => [
                    '=this.PREVIEW_PICTURE' => 'ref.ID',
                ],
                'join_type' => 'LEFT'
            ])
            ->registerRuntimeField('SRC_FILE', new ExpressionField(
                'SRC',
                'CONCAT("/upload/",beeralex_reviews_models_reviews_file.SUBDIR, "/", beeralex_reviews_models_reviews_file.FILE_NAME)'
            ))
            ->registerRuntimeField('SRC_THUMBAIL', new ExpressionField(
                'SRC',
                'CONCAT("/upload/",beeralex_reviews_models_reviews_file_thumbail.SUBDIR, "/", beeralex_reviews_models_reviews_file_thumbail.FILE_NAME)'
            ))
            ->whereNotNull('FILES.VALUE');
        return $query;
    }

    public static function getFiles(int $productId, int $limit)
    {
        $files = self::addFileToQuery(self::query()
            ->setSelect(['ID'])
            ->where('ACTIVE', 'Y')
            ->where('PRODUCT.VALUE', $productId)
            ->setOrder(["ID" => "DESC"])
            ->setLimit($limit))
            ->exec()
            ->fetchAll();
        return Files::make(compact('files'))->toArray();
    }

    public static function reviewsExistsByCurrentUserAndProductId(int $productId): bool
    {
        global $USER;
        $userId = $USER?->GetID();
        if (empty($userId)) {
            return false;
        }

        $element = self::query()
            ->setSelect(['ID'])
            ->where('ACTIVE', 'Y')
            ->where('USER.VALUE', $userId)
            ->where('PRODUCT.VALUE', $productId)
            ->exec()
            ->fetch();

        return !empty($element['ID']);
    }

    public static function getElements(int $productId, array $sorting, array $pagination, bool $getInfoByProduct) : array
    {
        $nav = new \Bitrix\Main\UI\PageNavigation("nav-reviews");
        $nav->allowAllRecords(true)
            ->setPageSize($pagination['limit'])
            ->setCurrentPage($pagination['current']);

        $elements = self::query();

        if ($productId) {
            $elements = $elements->where('PRODUCT.VALUE', $productId);
        }

        $select = [
            'ID',
            'DATE_CREATE',
            'OFFER_ID' => 'OFFER.VALUE',
            'USER_NAME_VALUE' => 'USER_NAME.VALUE',
            'EVAL_VALUE' => 'EVAL.VALUE',
            'REVIEW_VALUE' => 'REVIEW.VALUE',
            'STORE_RESPONSE_VALUE' => 'STORE_RESPONSE.VALUE',
        ];

        if ($getInfoByProduct) {
            $select = array_merge($select, ['PRODUCT_VALUE' => 'PRODUCT.VALUE']);
        }

        $elements = $elements->where('ACTIVE', 'Y')
            ->setSelect($select)
            ->setOrder($sorting)
            ->setLimit($pagination['limit'])
            ->setOffset($nav->getOffset())
            ->fetchAll();
        return Elements::make(compact('elements'))->toArray();
    }

    public static function getFilesForElements(array $idsElements) : array
    {
        $elements = !empty($idsElements) ? self::addFileToQuery(self::query()
            ->setSelect(['ID'])
            ->whereIn('ID', $idsElements))
            ->exec()
            ->fetchAll() : [];
        return FilesForElements::make(compact('elements'))->toArray();
    }

    // public static function reviewIsExistsByExternalIdAndPlatform(string $externalId, string $platform) : bool
    // {
    //     $element = self::query()
    //         ->setSelect(['ID'])
    //         ->where('EXTERNAL_ID.VALUE', $externalId)
    //         ->where('REVIEW_PLATFORM.ITEM.XML_ID', $platform)
    //         ->exec()
    //         ->fetch();

    //     return !empty($element['ID']);
    // }
}
