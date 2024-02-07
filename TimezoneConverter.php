<?php
class TimezoneConverter
{
    public static function convertToBerlinTime($sale_date, $version)
    {
        // Adjust timezone if version is 1.0.17+60 or newer
        if (version_compare($version, "1.0.17+60", ">=")) {
            $date = new DateTime($sale_date, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));
            return $date->format('Y-m-d H:i:s');
        }
        return $sale_date;
    }
}

