<?php

namespace PhpLuckyQueue\Queue\Drive\Rabbitmq;

use PhpLuckyQueue\Queue\Drive\Factory;
use PhpLuckyQueue\Queue\Drive\DriveInterface;
class RabbitmqFactory implements Factory
{

    public static function createClient($cfg): DriveInterface
    {
        return new Rabbitmq($cfg);
        // TODO: Implement createClient() method.
    }
}
