<?php

use PhpLuckyQueue\Queue\MonitorCounter;

if(!function_exists('luckyPush')){
    function luckyPush($queueName,$msg){
        MonitorCounter::incCount($queueName,1);
        \PhpLuckyQueue\Queue\ConnPool::getQueueClient($queueName)->append($queueName, $msg);
    }
}
