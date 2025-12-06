<?php

namespace Beeralex\Reviews\Contracts;

use Beeralex\Reviews\Dto\ReviewDTO;
use Bitrix\Main\Result;

interface CreatorContract
{
    /**
     * @param array $files from $_FILES
     */
    public function create(ReviewDTO $dto, array $files): Result;
}
