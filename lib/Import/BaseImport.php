<?php

namespace Beeralex\Reviews\Import;

use Beeralex\Reviews\Models\ReviewsTable;
use Beeralex\Reviews\Services\ReviewsService;

/**
 * @deprecated - а лучше бы не импортировать отзывы, а написать сервис для запроса к api
 * думаю стоит создать таблицу для связи внешних сервисов их ключей с SITE_ID и ApiService для каждого сервиса
 * а инфоблок отзывов уже имеет привязку к сайту, значит можно будет получать отзывы только для нужного сайта
 */
abstract class BaseImport
{
    protected ReviewsService $service;

    public function __construct(ReviewsService $service)
    {
        $this->service = $service;
    }

    public abstract function process();

    public function import(array $items): void
    {
        foreach ($items as $item) {
            try {
                if ($item['form']['external_id'] && $item['form']['platform']) {
                    if (ReviewsTable::reviewIsExistsByExternalIdAndPlatform($item['form']['external_id'], $item['form']['platform'])) {
                        continue;
                    }
                }
                $this->service->add($item['form'], $item['files'] ?? []);
                $this->removeFiles($item['tmp_paths'] ?? []);
            } catch (\Throwable $e) {
                $this->logError($e, $item);
            }
        }
    }

    protected function downloadFile(string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        $client = new \Bitrix\Main\Web\HttpClient([
            'disableSslVerification' => true,
            'socketTimeout' => 10,
            'streamTimeout' => 10,
        ]);

        $imageData = $client->get($url);
        if (!$imageData || $client->getStatus() !== 200) {
            return null;
        }

        $contentType = $client->getHeaders()->get('Content-Type') ?? 'image/jpeg';
        $extension = match ($contentType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'jpg'
        };

        $tmpName = tempnam(sys_get_temp_dir(), 'img_');
        if ($tmpName === false) {
            return null;
        }

        file_put_contents($tmpName, $imageData);

        $realName = $tmpName . '.' . $extension;
        if (!rename($tmpName, $realName)) {
            return null;
        }

        return [
            'name' => basename($realName),
            'type' => $contentType,
            'tmp_name' => $realName,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($realName),
        ];
    }

    protected function removeFiles(array $paths): void
    {
        foreach ($paths as $file) {
            if (is_file($file)) {
                $is = unlink($file);
            }
        }
    }

    protected function setToFiles(array &$result, array $file) : void
    {
        $result['files']['name'][] = $file['name'];
        $result['files']['type'][] = $file['type'];
        $result['files']['tmp_name'][] = $file['tmp_name'];
        $result['files']['error'][] = $file['error'];
        $result['files']['size'][] = $file['size'];
        $result['tmp_paths'][] = $file['tmp_name'];
    }

    protected function logError(\Throwable $e, $item): void
    {
        (new \Beeralex\Core\Logger\FileLogger(__DIR__ . '/../../logs/import_error.log'))->error($e->getMessage(), $item);
    }
}
