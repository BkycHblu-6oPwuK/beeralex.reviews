<?php

declare(strict_types=1);

namespace Beeralex\Reviews\Repository;

use Beeralex\Core\Repository\IblockRepository;
use Beeralex\Core\Repository\Repository;
use Beeralex\Core\Repository\SortingRepositoryContract;

/**
 * статичный репозиторий сортировок отзывов
 */
class SortingRepository extends Repository implements SortingRepositoryContract
{
    public function __construct() {}

    public function all(
        array $filter = [],
        array $select = ['*'],
        array $order = [],
        int $cacheTtl = 0,
        bool $cacheJoins = false
    ): array {
        $sortings = [
            [
                'ID' => 1,
                'NAME' => 'Сначала новые',
                'CODE' => 'new',
                'SORT_BY' => [
                    'VALUE' => 'ID',
                ],
                'DIRECTION' => [
                    'VALUE' => 'DESC',
                ],
            ],
            [
                'ID' => 2,
                'NAME' => 'Сначала старые',
                'CODE' => 'old',
                'SORT_BY' => [
                    'VALUE' => 'ID',
                ],
                'DIRECTION' => [
                    'VALUE' => 'ASC',
                ],
            ],
        ];
        return $sortings;
    }

    public function one(
        array $filter = [],
        array $select = ['*'],
        int $cacheTtl = 0,
        bool $cacheJoins = false
    ): ?array {
        $allSortings = $this->all($filter, $select, [], $cacheTtl, $cacheJoins);
        return $allSortings[0] ?? null;
    }

    public function getDefaultSorting(
        array $select = ['*'],
        int $cacheTtl = 0,
        bool $cacheJoins = false
    ): ?array {
        return $this->one(['ID' => 1], $select, $cacheTtl, $cacheJoins);
    }
}
