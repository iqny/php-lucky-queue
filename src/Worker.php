<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\DriveInterface;
use PhpLuckyQueue\Queue\Queue\BaseQueue;

class Worker
{
    private static $queueWork;

    public static function start($cfg, $consumeQueue, $workQueue)
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
        try {
            $queue->run($cfg, $consumeQueue, $workQueue);
        } catch (\Exception $e) {
            Logger::warning($queueName, 'worker', ['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
            exit(0);
        }
    }


    public static function checkWork($cfg, $consumeQueue, $workQueue, DriveInterface $redisClient)
    {
        $queueName = $cfg['queue_name'];
        if (!isset(self::$queueWork[$queueName])) {
            self::$queueWork[$queueName] = [];
        }
        for ($i = 0; $i < $cfg['worker_count']; $i++) {
            usleep(50000);
            $pid = self::$queueWork[$queueName][$i] ?? 0;
            if ($pid) {
                if (!intval($pid) || !posix_kill($pid, 0)) {
                    self::workStart($cfg, $i, $consumeQueue, $workQueue, $redisClient);
                }
            } else {
                self::workStart($cfg, $i, $consumeQueue, $workQueue, $redisClient);
            }
        }
    }

    private static function workStart($cfg, $i, $consumeQueue, $workQueue, DriveInterface $redisClient)
    {
        $host = php_uname('n');
        $queueName = $cfg['queue_name'];
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('ERROR:fork failed monitor');
        } elseif ($pid) {
            self::$queueWork[$queueName][$i] = $pid;
        } else {
            Logger::warning('monitor', "start {$queueName} work");
            if (PHP_OS == 'Linux') {
                cli_set_process_title(sprintf("%s %s work[%d]", $host, $queueName, ++$i));
            }
            $redisClient->sAdd(sprintf("%s:%s-work", $host, $queueName), posix_getpid());
            self::start($cfg, $consumeQueue, $workQueue);
            exit(0);
        }
    }
}
