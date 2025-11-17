<?php

namespace Beeralex\Reviews\Services;

use Beeralex\Reviews\ComponentParams;
use Beeralex\Reviews\Contracts\CreatorContract;
use Beeralex\Reviews\Contracts\FileUploaderContract;
use Beeralex\Reviews\Options;

class ReviewCreatorService implements CreatorContract
{
    protected Options $options;

    public function __construct()
    {
        $this->options = service(Options::class);
        \Bitrix\Main\Loader::includeModule('iblock');
    }

    public function create(array $form, array $files, ComponentParams $params): int
    {
        global $USER;

        $form['eval'] = $this->sanitizeEval($form['eval'] ?? 1);
        $uploadResult = $this->handleFiles($files);

        $properties = $this->buildProperties($form, $uploadResult, $USER, $params);
        $name = $this->buildName($properties, $params);

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
        $isAuth = $USER?->IsAuthorized() ?? false;
        $userId = $isAuth ? $USER->GetID() : null;
        $properties = [
            'PRODUCT' => $params->productId,
            'EVAL' => $form['eval'],
            'REVIEW' => $form['review'],
            'FILES' => $uploadResult['ids'],
            'OFFER' => $form['offer'],
            'CONTACT_DETAILS' => $form['contact'],
            'STORE_RESPONSE' => $form['answer'] ?? '',
        ];

        if (!empty($form['user_name'])) {
            $properties['USER_NAME'] = $form['user_name'];
        } else {
            $properties['USER_NAME'] = 'Аноним';
        }

        if ($isAuth) {
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

    protected function buildName(array $properties, ComponentParams $params): string
    {
        $productId = $params->productId;
        if ($productId) {
            return 'Отзыв на товар - ' . $productId;
        }

        return 'Отзыв без товара от - ' . $properties['USER_NAME'];
    }
}
