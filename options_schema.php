<?php
declare(strict_types=1);

use Beeralex\Core\Config\Module\Schema\Schema;
use Beeralex\Core\Config\Module\Schema\SchemaTab;

return Schema::make()
    ->tab(
        'edit1',
        'Общие настройки',
        'Отзывы',
        function (SchemaTab $tab) {
            $iblockOptions = ['' => 'Выберите инфоблок'];

            $tab->select(
                'reviews_iblock_id',
                'Инфоблок',
                $iblockOptions,
                'Общие',
                false,
                ''
            );

            $tab->input(
                'catalog_iblock_id',
                'ID инфоблока каталога',
                null,
                null,
                false,
                0
            );

            $tab->input(
                'offers_iblock_id',
                'ID инфоблока предложений',
                null,
                null,
                false,
                0
            );
        }
    );