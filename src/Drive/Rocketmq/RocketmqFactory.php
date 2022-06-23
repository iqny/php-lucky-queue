<?php

namespace PhpLuckyQueue\Queue\Drive\Rocketmq;

use PhpLuckyQueue\Queue\Drive\Factory;
use PhpLuckyQueue\Queue\Drive\DriveInterface;
class RocketmqFactory implements Factory{

    public static function createClient($cfg): DriveInterface
    {
        return new Rocketmq($cfg);
    }
}
