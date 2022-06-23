<?php
namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Queue\BaseQueue;

class Worker{
    public static function start($cfg,$consumeQueue,$pid,$workQueue)
    {
        $class = $cfg['class'];//'PhpLuckyQueue\Queue\Queue\\' . ucfirst($queueName) . 'Queue';
        $queueName = $cfg['queue_name'];
        if (!class_exists($class)) {
            Logger::error("$queueName", "$class not found!");
            die("$class not found!");
        }
        $queue = new $class();
        if (!$queue instanceof BaseQueue) {
            Logger::error("$queueName", "$class not implement PhpLuckyQueue\Queue\Queue\BaseQueue.");
            die("$class not implement PhpLuckyQueue\Queue\Queue\BaseQueue.");
        }
        $queue->run($cfg,$consumeQueue,$pid,$workQueue);
    }
}
