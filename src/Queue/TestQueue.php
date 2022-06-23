<?php

namespace App\LuckyQueue;

use PhpLuckyQueue\Queue\Queue\BaseQueue;

class TestQueue extends BaseQueue
{

    function parse():bool
    {
        // TODO: Implement parse() method.
        // echo "方法".__CLASS__.__METHOD__.PHP_EOL;
        //echo "test$i\n";
        $this->info("msg",$this->getData());
        //sleep(1);
        return true;
    }
}
