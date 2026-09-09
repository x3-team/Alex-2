<?php

namespace App\Support;

class DoctorsCopy
{
    public static function documentLabel(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $count.' документ';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $count.' документа';
        }

        return $count.' документов';
    }
}
