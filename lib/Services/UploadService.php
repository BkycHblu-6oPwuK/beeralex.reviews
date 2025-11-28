<?php

namespace Beeralex\Reviews\Services;

use Beeralex\Core\Service\FileService;
use Beeralex\Reviews\Contracts\FileUploaderContract;

class UploadService implements FileUploaderContract
{
    public function upload(array $files): array
    {
        $toSavefiles = service(FileService::class)->getFormattedToSafe($files);
        $arSaveFiles = [];
        if (!empty($toSavefiles)) {
            foreach ($toSavefiles as $file) {
                $id_file = \CFile::SaveFile($file, 'reviews');
                $arSaveFiles[] = $id_file;
            }
        }
        return $arSaveFiles;
    }
}
