<?php

namespace Beeralex\Reviews\Services;

use Bitrix\Main\DI\ServiceLocator;
use Beeralex\Core\Helpers\IblockHelper;
use Beeralex\Reviews\ComponentParams;
use Beeralex\Reviews\Contracts\CreatorContract;
use Beeralex\Reviews\Contracts\FileUploaderContract;
use Beeralex\Reviews\Enum\Platforms;
use Beeralex\Reviews\Options;

class ReviewCreatorService implements CreatorContract
{
    protected Options $options;

    public function __construct()
    {
        $this->options = Options::getInstance();
        \Bitrix\Main\Loader::includeModule('iblock');
    }

    public function create(array $form, array $files, ComponentParams $params): int
    {
        global $USER;

        $form['eval'] = $this->sanitizeEval($form['eval'] ?? 1);
        $uploadResult = $this->handleFiles($files);

        $properties = $this->buildProperties($form, $uploadResult, $USER, $params);
        $name = $this->buildName($form, $properties, $params);

        $elementData = [
            'IBLOCK_ID' => $this->options->reviewsIblockId,
            'NAME' => $name,
            'IBLOCK_SECTION_ID' => false,
            'ACTIVE' => $form['active'] ? 'Y' : 'N',
            'PROPERTY_VALUES' => $properties,
        ];

        if (!empty($uploadResult['preview']['file_array'])) {
            $elementData['PREVIEW_PICTURE'] = $uploadResult['preview']['file_array'];
        }

        $id = (new \CIBlockElement)->Add($elementData);

        if (!empty($uploadResult['preview']['thumbnail_path'])) {
            @unlink($uploadResult['preview']['thumbnail_path']);
        }

        return $id;
    }

    protected function sanitizeEval(int $eval): int
    {
        return max(1, min(5, $eval));
    }

    protected function handleFiles(array $files): array
    {
        $result = ['ids' => [], 'preview' => []];
        if (!empty($files)) {
            $uploader = service(FileUploaderContract::class);
            $result = $uploader->upload($files);
        }
        return $result;
    }

    protected function buildProperties(array $form, array $uploadResult, $USER, ComponentParams $params): array
    {
        $platform = Platforms::tryFrom($form['platform'] ?? '') ?? Platforms::SITE;
        $isAuth = $USER?->IsAuthorized() ?? false;
        $isSitePlatform = ($platform === Platforms::SITE);
        $userId = $isAuth ? $USER->GetID() : null;
        $properties = [
            'PRODUCT' => $params->productId,
            'EVAL' => $form['eval'],
            'REVIEW' => $form['review'],
            'FILES' => $uploadResult['ids'],
            'OFFER' => $form['offer'],
            'CONTACT_DETAILS' => $form['contact'],
            'STORE_RESPONSE' => $form['answer'] ?? '',
            'REVIEW_PLATFORM' => $this->getPlatformId($platform),
            'EXTERNAL_ID' => $form['external_id'] ?? '',
        ];

        if (!empty($form['user_name'])) {
            $properties['USER_NAME'] = $form['user_name'];
        } elseif ($isSitePlatform && $isAuth) {
            $properties['USER_NAME'] = $this->formatUserName($USER);
        } else {
            $properties['USER_NAME'] = 'Аноним';
        }

        if ($isSitePlatform && $isAuth) {
            $properties['USER'] = $userId;
        }

        return $properties;
    }

    protected function formatUserName($USER): string
    {
        $lastName = trim($USER?->GetLastName() ?? '');
        $initial = $lastName ? mb_strtoupper(mb_substr($lastName, 0, 1)) . '.' : '';
        return trim($USER?->GetFirstName() ?? '' . ' ' . $initial);
    }

    protected function buildName(array $form, array $properties, ComponentParams $params): string
    {
        if (($form['platform'] ?? '') === Platforms::TWO_GIS->value) {
            return 'Отзыв c 2гис - ' . ($form['user_name'] ?? 'Неизвестный');
        }

        $productId = $params->productId;
        if ($productId) {
            return 'Отзыв на товар - ' . $productId;
        }

        return 'Отзыв без товара от - ' . $properties['USER_NAME'];
    }

    protected function getPlatformId(Platforms $platform): int
    {
        $propId = IblockHelper::getIblockPropIdByCode('REVIEW_PLATFORM', $this->options->reviewsIblockId);
        return IblockHelper::getEnumValues($propId, [$platform->value])[$platform->value]['id'];
    }
}
