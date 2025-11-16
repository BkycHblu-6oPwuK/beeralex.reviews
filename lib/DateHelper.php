<?php

namespace Beeralex\Reviews;

class DateHelper
{
    private function __construct() {}
    /**
     * @return string - "{$day} {$months_string_ru} {$year}"
     */
    public static function getDateFormatted(\Bitrix\Main\Type\DateTime $date): string
    {
        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];
    
        $day = (int)$date->format('d');
        $month = (int)$date->format('m');
        $year = (int)$date->format('Y');
    
        $dateFormatted = "{$day} {$months[$month]} {$year}";
    
        return $dateFormatted;
    }
}