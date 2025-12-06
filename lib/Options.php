<?php

namespace Beeralex\Reviews;

use Beeralex\Core\Config\AbstractOptions;

final class Options extends AbstractOptions
{
    protected function mapOptions(array $options): void
    {

    }

    public function getModuleId(): string
    {
        return 'beeralex.reviews';
    }
}
