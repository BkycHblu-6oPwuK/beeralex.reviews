<?php

namespace Beeralex\Reviews\Resources;

use Bitrix\Iblock\Iblock;
use Exception;
use Beeralex\Core\Http\Resources\Resource;
use Beeralex\Reviews\DateHelper;
use Beeralex\Reviews\Repository\ReviewsRepository;
use Beeralex\Reviews\Options;
use Illuminate\Support\Collection;

class Elements extends Resource
{
    public function toArray() : array
    {
        $result_elements = [
            'items' => []
        ];
        $elements = new Collection($this->elements);
        $reviewsRepository = new ReviewsRepository();
        $files = $reviewsRepository->getFilesForElements($elements->pluck('ID')->toArray());
        $productIds = $elements->pluck('PRODUCT_VALUE')->filter(fn($item) => $item != 0)->toArray();
        $productsInfo = [];

        if(!empty($productIds)){
            $productsInfo = $this->getInfoByProducts($productIds);
        }

        $key = 0;
        $elements->map(function ($item) use (&$result_elements, $files, &$key, $productsInfo) {
            $review = unserialize($item['REVIEW_VALUE'])['TEXT'];
            
            $store_response = null;
            if($item['STORE_RESPONSE_VALUE']){
                $store_response = unserialize($item['STORE_RESPONSE_VALUE'])['TEXT'];
            }


            $result_elements['items'][$key] = [
                'id' => $item['ID'],
                'date' => DateHelper::getDateFormatted($item['DATE_CREATE']),
                'offer_size' => null,
                'user_name' => $item['USER_NAME_VALUE'],
                'eval' => (float)$item['EVAL_VALUE'],
                'files' => $files[$item['ID']] ? $files[$item['ID']] : [],
                'review' => $review,
                'store_response' => $store_response,
            ];

            if($item['PRODUCT_VALUE']){
                $productId = (int) $item['PRODUCT_VALUE'];
                $result_elements['items'][$key]['product_info'] = $productsInfo[$productId];
            }

            $key++;
        });
        return $result_elements;
    }

    protected function getInfoByProducts(array $productIds)
    {
        $options = service(Options::class);
        $catalogApi = Iblock::wakeUp($options->catalogIblockId)->getEntityDataClass();
        if(!$catalogApi) throw new Exception("Не заполнен символьный код API инфоблока каталог товаров");
        $result = new Collection($catalogApi::query()
        ->setSelect([
            'ID',
            'NAME',
            'PREVIEW_PICTURE',
            'DETAIL_PICTURE',
            'DETAIL_PAGE_URL' => 'IBLOCK.DETAIL_PAGE_URL', 
            'IBLOCK_SECTION_ID',
            'CODE',
            'ACTIVE'
        ])
        ->whereIn('ID', $productIds)
        ->exec()
        ->fetchAll());
        $result = $result->keyBy('ID')->toArray();
        return ProductInfo::make(compact('result'))->toArray();
    }
}