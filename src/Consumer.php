<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\DriveInterface;
use PhpLuckyQueue\Queue\Drive\Redis\RedisFactory;
use PhpLuckyQueue\Queue\Signal\Signal;

class Consumer
{
    private static $running = true;
    private static $ackObject = [];
    private static $jobNumber = 0;//任务编码
    /**
     * @var Drive\DriveInterface
     */
    private static $client;
    private static $consumeQueue;
    public static $workNumber;
    public static $consumeNumber;
    public static $workQueue;
    public static $queueQos;
    private static $queueConsumer = [];//从数据源获取数据
    public static $quit = false;
    public static function start($cfg, $consumeQueue, $workQueue)
    {
        try {
            self::$consumeQueue = $consumeQueue;
            self::$workQueue = $workQueue;
            $queueName = $cfg['queue_name'];
            self::$queueQos = $cfg['worker_count'];
            self::$workNumber = $cfg['work_number'];
            self::$consumeNumber = $cfg['consume_number'];
            self::$client = ConnPool::getQueueClient($cfg['queue_name']);
            while (self::$running) {
                Signal::SetSigHandler([self::class, 'sigHandler']);
                msg_receive(self::$consumeQueue, self::$consumeNumber, $msgtype, 1024, $message, true, 1);
                if ($message) {
                    $obj = self::$ackObject[$message] ?? 0;
                    if (!empty($obj)) {
                        $b=$obj['queue']->ack($obj['envelope']->getDeliveryTag());
                        unset(self::$ackObject[$message]);
                        if (!$b){
                            Logger::warning($queueName, "ack fail");
                        }else{
                            MonitorCounter::incCount($queueName,-1);
                        }
                    }else{
                        Logger::warning($queueName, "{$message} 丢失，无法 ack");
                    }
                }
                //如果没有需要确认的信息并且已经接收到退出指令，执行退出操作
                if (count(self::$ackObject) <= 0 && self::$quit){
                    echo "退出操作\n";
                    self::$running = false;
                }
                //接收到退出指令不要队列获取信息来处理
                if (count(self::$ackObject) < self::$queueQos && self::$quit==false) {
                    self::$client->get($queueName, function ($envelope, $queue) use ($queueName) {
                        if (!$envelope) {
                            sleep(1);
                            return false;
                        }
                        $data = $envelope->getBody();
                        if (empty($data)) {
                            sleep(1);
                            return false;
                        }
                        //$pid = self::getPushPid($queueName);
                        $jobNumber = self::getJobNumber();
                        self::$ackObject[$jobNumber] = [
                            'envelope' => $envelope,
                            'queue' => $queue,
                        ];
                        if ($data) {
                            msg_send(self::$workQueue, self::$workNumber, sprintf("%s.%s", $jobNumber, $data));
                        } else {
                            sleep(1);
                        }
                        return false;
                    });
                }elseif (empty($message)){
                    usleep(10000);
                }
            }
        }catch (\Exception $e){
            Logger::warning($queueName, 'Consumer',['message'=>$e->getMessage(),'line'=>$e->getLine(),'file'=>$e->getFile()]);
            exit(0);
        }

    }


    public static function sigHandler($signo)
    {
        //接收到指令
        echo "接收到指令\n";
        self::$quit = true;
    }

    private static function getJobNumber(): int
    {
        if (self::$jobNumber > 10000) {
            self::$jobNumber = 1;
            return self::$jobNumber;
        }
        return ++self::$jobNumber;
    }

    public static function checkConsume($cfg, $consumeQueue, $workQueue,DriveInterface $redisClient)
    {
        usleep(100000);
        $queueName = $cfg['queue_name'];
        $pid = self::$queueConsumer[$queueName] ?? 0;
        if ($pid) {
            if (!intval($pid) || !posix_kill($pid, 0)) {
                self::consumeStart($cfg, $consumeQueue, $workQueue,$redisClient);
            }
        } else {
            self::consumeStart($cfg, $consumeQueue, $workQueue,$redisClient);
        }
    }
    private static function consumeStart($cfg, $consumeQueue, $workQueue,DriveInterface $redisClient)
    {
        $host = php_uname('n');
        $queueName = $cfg['queue_name'];
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('ERROR:fork failed monitor');
        } elseif ($pid) {
            self::$queueConsumer[$queueName] = $pid;
        } else {
            Logger::warning('monitor', "start {$queueName} consumer");
            if (PHP_OS == 'Linux') {
                cli_set_process_title(sprintf("%s %s slave", $host, $queueName));
            }
            $redisClient->hSet($host, $queueName, posix_getpid());
            self::start($cfg, $consumeQueue, $workQueue);
            exit(0);
        }
    }
}
