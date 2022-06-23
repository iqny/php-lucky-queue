<?php

namespace PhpLuckyQueue\Queue\Drive\Redis;

use PhpLuckyQueue\Queue\Drive\Factory;
use PhpLuckyQueue\Queue\Drive\DriveInterface;
class RedisFactory implements Factory{

    public static function createClient($cfg): DriveInterface
    {
        return new Redis($cfg);
    }
}
