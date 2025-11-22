<?php

namespace Beeralex\Reviews\Services;

use Beeralex\Core\Service\FileService;
use Beeralex\Reviews\Contracts\FileUploaderContract;
use Beeralex\Reviews\Options;

class UploadService implements FileUploaderContract
{
    protected Options $options;

    public function upload(array $files): array
    {
        $toSavefiles = service(FileService::class)->getFormattedToSafe($files);
        $arSaveFiles = [];
        if (!empty($toSavefiles)) {
            foreach ($toSavefiles as $file) {
                $id_file = \CFile::SaveFile($file, 'reviews');
                if (str_starts_with($file['type'], 'video')) {
                    $thumbnail = $this->addThumbnail($id_file);
                    if($thumbnail){
                        $arSaveFiles['preview'] = [
                            'file_array' => \CFile::MakeFileArray($thumbnail),
                            'thumbnail_path' => $thumbnail
                        ];
                    }
                }
                $arSaveFiles['ids'][] = $id_file;
            }
        }
        return $arSaveFiles;
    }

    protected function addThumbnail(int $id_file)
    {
        if(!class_exists('\FFMpeg\FFMpeg')) return '';
        $path = tempnam(sys_get_temp_dir(), "img") . '.jpg';
        $videoPath = $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($id_file);
        $ffmpeg = \FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($videoPath);
        $frame = $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1));
        $frame->save($path);
        return $path;
    }
}
