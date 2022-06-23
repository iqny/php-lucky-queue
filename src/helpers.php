<?php
if(!function_exists('luckyPush')){
    function luckyPush($queueName,$msg){
        \PhpLuckyQueue\Queue\ConnPool::getQueueClient($queueName)->append($queueName, $msg);
    }
}
