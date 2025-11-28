<?php
declare(strict_types=1);

namespace Beeralex\Reviews\Repository;

use Beeralex\Core\Repository\IblockRepository;
use Beeralex\Reviews\Resources\Elements;
use Beeralex\Reviews\Resources\Files;
use Beeralex\Reviews\Resources\FilesForElements;
use Bitrix\Main\FileTable;
use Bitrix\Main\ORM\Fields\ExpressionField;

class ReviewsRepository extends IblockRepository
{
    public function __construct()
    {
        parent::__construct('product_reviews');
    }

    public function getCountReviews(int $productId): int
    {
        $query = $this->query()->where('ACTIVE', 'Y');

        if ($productId) {
            $query = $query->where('PRODUCT.VALUE', $productId);
        }

        return $query->countTotal(true)->exec()->getCount();
    }

    private function addFileToQuery(\Bitrix\Iblock\ORM\Query $query): \Bitrix\Iblock\ORM\Query
    {
        return $query->setSelect(array_merge(
            $query->getSelect(),
            ['ID_FILE' => 'FILES.VALUE', 'PREVIEW_PICTURE', 'SRC_FILE', 'TYPE' => 'FILE.CONTENT_TYPE']
        ))
            ->registerRuntimeField('FILE', [
                'data_type' => FileTable::class,
                'reference' => [
                    '=this.FILES.VALUE' => 'ref.ID',
                ],
                'join_type' => 'INNER'
            ])
            ->registerRuntimeField('SRC_FILE', new ExpressionField(
                'SRC',
                'CONCAT("/upload/",%s.SUBDIR, "/", %s.FILE_NAME)',
                ['FILE', 'FILE']
            ))
            ->whereNotNull('FILES.VALUE');
    }

    public function getFiles(int $productId, int $limit): array
    {
        $files = $this->addFileToQuery($this->query()
            ->setSelect(['ID'])
            ->where('ACTIVE', 'Y')
            ->where('PRODUCT.VALUE', $productId)
            ->setOrder(["ID" => "DESC"])
            ->setLimit($limit))
            ->exec()
            ->fetchAll();
        return Files::make(compact('files'))->toArray();
    }

    public function reviewsExistsByCurrentUserAndProductId(int $productId): bool
    {
        global $USER;
        $userId = $USER?->GetID();
        if (empty($userId)) {
            return false;
        }

        $element = $this->query()
            ->setSelect(['ID'])
            ->where('ACTIVE', 'Y')
            ->where('USER.VALUE', $userId)
            ->where('PRODUCT.VALUE', $productId)
            ->exec()
            ->fetch();

        return !empty($element['ID']);
    }

    public function getElements(int $productId, array $sorting, array $pagination, bool $getInfoByProduct): array
    {
        $nav = new \Bitrix\Main\UI\PageNavigation("nav-reviews");
        $nav->allowAllRecords(true)
            ->setPageSize($pagination['limit'])
            ->setCurrentPage($pagination['current']);

        $elementsQuery = $this->query();

        if ($productId) {
            $elementsQuery = $elementsQuery->where('PRODUCT.VALUE', $productId);
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

        $elements = $elementsQuery->where('ACTIVE', 'Y')
            ->setSelect($select)
            ->setOrder($sorting)
            ->setLimit($pagination['limit'])
            ->setOffset($nav->getOffset())
            ->fetchAll();
        return Elements::make(compact('elements'))->toArray();
    }

    public function getFilesForElements(array $idsElements): array
    {
        $elements = !empty($idsElements) ? $this->addFileToQuery($this->query()
            ->setSelect(['ID'])
            ->whereIn('ID', $idsElements))
            ->exec()
            ->fetchAll() : [];
        return FilesForElements::make(compact('elements'))->toArray();
    }

    public function reviewIsExistsByExternalIdAndPlatform(string $externalId, string $platform): bool
    {
        $element = $this->query()
            ->setSelect(['ID'])
            ->where('EXTERNAL_ID.VALUE', $externalId)
            ->where('REVIEW_PLATFORM.ITEM.XML_ID', $platform)
            ->exec()
            ->fetch();

        return !empty($element['ID']);
    }
}
