<?php
namespace App\Core;

class Logger {
    protected static $logFile = 'storage/logs/app.log';

    public static function log($message) {
        $manilaTimeZone = new \DateTimeZone('Asia/Manila');
        $dateTimeManila = new \DateTime('now', $manilaTimeZone);
        $manilaTime = $dateTimeManila->format('Y-m-d H:i:s');
        $entry = "[$manilaTime] $message" . PHP_EOL;

        // Ensure log directory exists
        $dir = dirname(self::$logFile);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents(self::$logFile, $entry, FILE_APPEND);
    }
}
