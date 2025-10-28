<?php
namespace Beeralex\Reviews\Agents;

use Beeralex\Reviews\Import\ImportFrom2Gis;

class Import
{
    /**
     * @var class-string<\Beeralex\Reviews\Import\BaseImport>[] $importPlatformsMap
     */
    protected static $importPlatformsMap = [
        ImportFrom2Gis::class
    ];
    protected static function import()
    {
        foreach (static::$importPlatformsMap as $class) {
            $object = service($class);
            $object->process();
        }
    }
    public static function exec()
    {
        static::import();
        return '\\' . __METHOD__ . '();';
    }
}
