<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\Rabbitmq\RabbitmqFactory;
use PhpLuckyQueue\Queue\Drive\Redis\RedisFactory;
use PhpLuckyQueue\Queue\Drive\Rocketmq\RocketmqFactory;

class ConnPool
{
    private static $pool = [];

    /**
     * @param string $queueName
     * @return mixed|Drive\DriveInterface
     */
    public static function getQueueClient(string $queueName = '')
    {
        if (isset(self::$pool[$queueName])) {
            return self::$pool[$queueName];
        }
        $cfg = config('lucky');
        $drive = $cfg['connect']['drive'];
        $cfg['queue'] = array_column($cfg['queue'],null,'queue_name');
        $drive = isset($cfg['queue'][$queueName]['drive']) && !empty($cfg['queue'][$queueName]['drive']) ? $cfg['queue'][$queueName]['drive'] : $drive;
        switch ($drive) {
            case 'rabbitmq':
                self::$pool[$queueName] = RabbitmqFactory::createClient($cfg[$drive]);
                break;
            case 'rocketmq':
                $subCfg = [];
                if(isset($cfg['queue'][$queueName][$drive])){
                    $subCfg = $cfg['queue'][$queueName][$drive];
                }
                self::$pool[$queueName] = RocketmqFactory::createClient(array_merge($cfg[$drive],$subCfg));
                break;
            default:
                self::$pool[$queueName] = RedisFactory::createClient($cfg[$drive]);
                break;
        }
        return self::$pool[$queueName];
    }
}
