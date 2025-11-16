<?php
namespace Beeralex\Reviews;

use Beeralex\Core\Service\LanguageService;
use Beeralex\Reviews\Models\ReviewsTable;

class EvalHelper 
{
    public static function getAvg(int $productId)
    {
        $reviewsEvalCollect = static::getReviewsEvalCollect($productId);
        return round($reviewsEvalCollect->avg('EVAL_VALUE'), 2);
    }

    public static function getReviewsEvalCollect(int $productId) 
    {
        static $collect = null;
        if($collect === null){
            $collect = collect(ReviewsTable::query()
            ->where('PRODUCT_VALUE', $productId)
            ->where('ACTIVE','Y')
            ->setSelect(['PRODUCT_' => 'PRODUCT','EVAL_' =>'EVAL'])
            ->fetchAll());
        }
        return $collect;
    }

    public static function getEvalInfo(int $productId)
    {
        $allReviewsCount = ReviewsTable::getCountReviews($productId);
        $reviewsEvalCollect = EvalHelper::getReviewsEvalCollect($productId);
        
        $evalCounts = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];
        
        if ($allReviewsCount > 0) {
            foreach ($evalCounts as $evalValue => &$count) {
                $count = $reviewsEvalCollect->where('EVAL_VALUE', $evalValue)->count();
            }
            unset($count);
    
            $percent = array_map(function($count) use ($allReviewsCount) {
                return $count > 0 ? round(($count / $allReviewsCount) * 100, 2) : 0;
            }, $evalCounts);
        } else {
            $percent = array_fill_keys(array_keys($evalCounts), 0);
        }
    
        $result = [
            'avg' => $allReviewsCount > 0 ? EvalHelper::getAvg($productId) : 0,
            'count' => static::countReviewsFormatted($allReviewsCount),
            'countNumber' => $allReviewsCount,
        ];
    
        foreach ($evalCounts as $evalValue => $count) {
            $result[(string)$evalValue] = [
                'count' => $count,
                'percent' => $percent[$evalValue],
            ];
        }
    
        return $result;
    }
    

    public static function countReviewsFormatted(int $count): string
    {
        $text = service(LanguageService::class)->getPlural($count, ['отзыва', 'отзыва', 'отзывов']);
        return "$count $text";
    }
}