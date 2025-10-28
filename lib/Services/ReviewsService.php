<?php

namespace Beeralex\Reviews\Services;

use Bitrix\Main\DI\ServiceLocator;
use Beeralex\Core\Helpers\PaginationHelper;
use Beeralex\Reviews\ComponentParams;
use Beeralex\Reviews\Contracts\CreatorContract;
use Beeralex\Reviews\EvalHelper;
use Beeralex\Reviews\Models\ReviewsTable;
use Beeralex\Reviews\Options;

class ReviewsService
{
    protected readonly Options $options;
    protected readonly ComponentParams $componentParams;

    public function __construct(ComponentParams $componentParams)
    {
        $this->options = Options::getInstance();
        $this->componentParams = $componentParams;
    }

    public function getReviews(): array
    {
        $productId = $this->componentParams->productId;

        $this->updatePaginationCount($productId);

        if ($productId > 0) {
            return $this->getReviewsByProduct($productId);
        }

        return $this->buildReviewsData(0, false);
    }

    public function getReviewsByProduct(int $productId): array
    {
        return $this->buildReviewsData($productId, true);
    }

    protected function buildReviewsData(int $productId, bool $withEvalAndFiles): array
    {
        global $USER;

        $elements = $this->getElements($productId);
        $elements['isset_items'] = !empty($elements['items']);
        $elements['actions'] = $this->getActions();
        $elements['exits_review'] = false;
        $elements['user_authorize'] = $USER?->IsAuthorized() ?? false;
        $elements['pagination'] = $this->getPagination();
        $elements['sorting'] = $this->getSorting();

        if ($withEvalAndFiles) {
            $elements['eval_info'] = EvalHelper::getEvalInfo($productId);
            $elements['files'] = $this->componentParams->showFilesByProduct
                ? ReviewsTable::getFiles($productId, $this->componentParams->limitFiles)
                : [];
        }

        return $elements;
    }

    protected function updatePaginationCount(int $productId): void
    {
        $count = ReviewsTable::getCountReviews($productId);
        $limit = $this->componentParams->paginationLimit;
        $this->componentParams->setPaginationPageCount(ceil($count / $limit));
    }

    public function getElements(int $productId)
    {
        return ReviewsTable::getElements($productId, $this->componentParams->getSorting(), $this->componentParams->getPagination(), $this->componentParams->showInfoByProduct, $this->componentParams->platform);
    }

    protected function getPagination()
    {
        $current = $this->componentParams->paginationCurrent;
        $count =  $this->componentParams->paginationPageCount;
        return [
            'currentPage' => $current,
            'limit' => $this->componentParams->paginationLimit,
            'pageCount' => $count,
            'pages' => PaginationHelper::getPages($current, $count),
        ];
    }

    protected function getSorting()
    {
        return [
            'field' => $this->componentParams->sortingField,
            'type' => $this->componentParams->sortingType,
        ];
    }

    protected function getActions()
    {
        $urlManager = \Bitrix\Main\Engine\UrlManager::getInstance();
        return [
            'pagination' => $urlManager->create('beeralex:reviews.ReviewsController.pagination'),
            'add' => $urlManager->create('beeralex:reviews.ReviewsController.add'),
            'sorting' => $urlManager->create('beeralex:reviews.ReviewsController.sorting'),
            'get' => $urlManager->create('beeralex:reviews.ReviewsController.get'),
        ];
    }

    public function add(array $form, array $files)
    {
        $creator = service(CreatorContract::class);
        return $creator->create($form, $files, $this->componentParams);
    }

    public function sorting(array $sorting, array $pagination): array
    {
        $pagination['currentPage'] = 1;
        return $this->loadElements($pagination, $sorting);
    }

    public function pagination(array $pagination, array $sorting): array
    {
        return $this->loadElements($pagination, $sorting);
    }

    public function loadElements(array $pagination, array $sorting): array
    {
        $this->componentParams
            ->setPaginationCurrent($pagination['currentPage'] ?? 1)
            ->setPaginationPageCount($pagination['pageCount'] ?? 1)
            ->setSorting($sorting['field'], $sorting['type']);

        $result = $this->getElements($this->componentParams->productId);
        $result['pagination'] = $this->getPagination();

        return $result;
    }
}
