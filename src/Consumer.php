<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\Redis\RedisFactory;
use PhpLuckyQueue\Queue\Queue\BaseQueue;
use PhpLuckyQueue\Queue\Signal\Signal;

class Consumer
{
    private static $running = true;
    private static $workPids = [];
    private static $ackObject = [];
    private static $jobNumber = 0;//任务编码
    public static $rabbitmqExit = true;
    public static $count = 1000;
    /**
     * @var Drive\DriveInterface
     */
    private static $client;
    private static $consumeQueue;
    public static $workNumber;
    public static $consumeNumber;
    public static $workQueue;
    public static $queueQos;
    /**
     * @var Snowflake
     */
    public static $snowflake;

    public static function start($cfg, $consumeQueue, $workQueue)
    {
        self::$consumeQueue = $consumeQueue;
        self::$workQueue = $workQueue;
        $queueName = $cfg['queue_name'];
        self::$queueQos = $cfg['qos']??50;
        self::$workNumber = $cfg['work_number'];
        self::$consumeNumber = $cfg['consume_number'];
        self::$client = ConnPool::getQueueClient($cfg['queue_name']);
        self::$snowflake = new Snowflake(self::$consumeNumber);
        Signal::SetSigHandler([self::class, 'sigHandler']);
        while (self::$running) {
            msg_receive(self::$consumeQueue, self::$consumeNumber, $msgtype, 1024, $message, true, 1);
            if ($message) {
                //var_dump($message);
                $obj = self::$ackObject[$message] ?? 0;
                if (!empty($obj)) {
                    $b=$obj['queue']->ack($obj['envelope']->getDeliveryTag());
                    unset(self::$ackObject[$message]);
                    if (!$b){
                        Logger::warning($queueName, "ack fail");
                    }
                }
            }
            if (count(self::$ackObject) <= self::$queueQos) {
                self::$client->get($queueName, function ($envelope, $queue) use ($queueName) {
                    if (!$envelope) {
                        sleep(1);
                        return false;
                    }
                    $data = $envelope->getBody();
                    //$pid = self::getPushPid($queueName);
                    $jobNumber = self::$snowflake->getId();//self::getJobNumber();
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
                usleep(1000);
            }
        }
    }


    public static function sigHandler($signo)
    {
        self::$running = false;
    }

    private static function getJobNumber(): int
    {
        if (self::$jobNumber > 10000) {
            self::$jobNumber = 1;
            return self::$jobNumber;
        }
        return ++self::$jobNumber;
    }
}
