<?php

namespace PhpLuckyQueue\Queue;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;

/**
 * @method static void info($name, $msg,$context=[])
 * @method static void alert($name, $msg,$context=[])
 * @method static void notice($name, $msg,$context=[])
 * @method static void debug($name, $msg,$context=[])
 * @method static void warning($name, $msg,$context=[])
 * @method static void critical($name, $msg,$context=[])
 * @method static void emergency($name, $msg,$context=[])
 * @method static void error($name, $msg,$context=[])
 */
class Logger
{
    private static $pool = [];
    private static $drive = 'default';
    private static $callHandel = null;
    /**
     * @param $type
     * @param $queue
     * @return \Monolog\Logger
     */
    private static function getInstance($type, $queue): \Monolog\Logger
    {
        switch (self::$drive) {
            case 'default':
                return self::defaultHandle($type,$queue);
            default:
                if(isset(self::$pool[$type])){
                    return self::$pool[$type];
                }
                if (!is_null(self::$callHandel)){
                    self::$pool[$type] = call_user_func(self::$callHandel,$queue);
                }
                return self::$pool[$type];
        }
    }
    private static function defaultHandle($type,$queue): \Monolog\Logger
    {
        $dataUnique = date('Y-m-d');
        $datePath = $dataUnique . "/{$type}";
        if (isset(self::$pool[$dataUnique][$type]) && self::$pool[$dataUnique][$type] != null) {
            return self::$pool[$dataUnique][$type];
        }
        $log = new \Monolog\Logger($queue);
        $handle = new StreamHandler(storage_path('logs/queue/' . $datePath . '.log'), \Monolog\Logger::DEBUG);
        $handle->setFormatter(new JsonFormatter());
        //$log->pushProcessor(new ProcessIdProcessor);
        $log->pushHandler($handle);
        $prevDate = date('Y-m-d', strtotime('-1 day'));
        if (isset(self::$pool[$prevDate]) && !empty(self::$pool[$prevDate])) {
            unset(self::$pool[$prevDate]);
        }
        self::$pool[$dataUnique][$type] = &$log;
        return $log;
    }
    public static function setLogger(callable $handle){
        self::$drive = '';
        self::$callHandel = $handle;
    }

    public static function __callStatic($name, $arguments)
    {
        $log = self::getInstance($name, $arguments[0]);
        $context = $arguments[2]??[];
        if(!is_array($context)){
            $context = [$context];
        }
        $log->$name($arguments[1],$context);
    }
    public static function pushProcessor($name,$queue,$callback){
        $log = self::getInstance($name, $queue);
        $log->pushProcessor($callback);
    }
}
