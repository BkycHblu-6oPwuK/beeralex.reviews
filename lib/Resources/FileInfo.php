<?php

namespace Beeralex\Reviews\Resources;

use Beeralex\Core\Http\Resources\Resource;

class FileInfo extends Resource
{
    public function toArray() : array
    {
        $is_video = str_starts_with($this->item['TYPE'], 'video');
        $result = [
            'id' => (int)$this->item['ID_FILE'],
            'type' => $is_video ? 'video' : 'image',
            'src' => $this->item['SRC_FILE'],
        ];
        return $result;
    }
}