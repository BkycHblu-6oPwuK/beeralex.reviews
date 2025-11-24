<?php

namespace Beeralex\Reviews;

final class ComponentParams
{
    public int $productId = 0;
    public int $paginationLimit = 5;
    public bool $showInfoByProduct = false;

    public int $paginationCurrent = 1;
    public int $paginationPageCount = 0;
    public string $sortingField = 'ID';
    public string $sortingType = 'DESC';

    public int $limitFiles = 20;
    public bool $showFilesByProduct = false;

    public function __construct(array $params = [])
    {
        $this->productId         = (int)($params['PRODUCT_ID'] ?? 0);
        $this->paginationLimit   = (int)($params['PAGINATION_LIMIT'] ?? 5);
        $this->showInfoByProduct = (bool)($params['SHOW_INFO_PRODUCT'] ?? false);
    }

    public function setProductId(int $id): self
    {
        $this->productId = $id;
        return $this;
    }
    public function setSorting(string $field, string $type): self
    {
        $this->sortingField = $field;
        $this->sortingType = $type;
        return $this;
    }
    public function setPaginationLimit(int $limit): self
    {
        $this->paginationLimit = $limit;
        return $this;
    }
    public function setPaginationCurrent(int $current): self
    {
        $this->paginationCurrent = $current;
        return $this;
    }
    public function setPaginationPageCount(float $count): self
    {
        $this->paginationPageCount = $count;
        return $this;
    }

    public function getSorting(): array
    {
        return [$this->sortingField => $this->sortingType];
    }

    public function getPagination(): array
    {
        return [
            'current'   => $this->paginationCurrent,
            'limit'     => $this->paginationLimit,
            'pageCount' => $this->paginationPageCount,
        ];
    }
}
