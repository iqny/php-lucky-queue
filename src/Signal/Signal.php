<?php

namespace PhpLuckyQueue\Queue\Signal;
/**
 *
 * Class Signal
 * @package PhpLuckyQueue\Queue\Signal
 */
class Signal implements SignalInterface
{
    public static function SetSigHandler($signal)
    {
        // TODO: Implement sigHandler() method.
        // 安装信号处理函数
        pcntl_signal_dispatch();
        //declare(ticks=1);
        if (function_exists('attach_signal')) {
            //rabbitmq 阻塞模式用该扩展才有效果
            attach_signal(SIGTERM, $signal);
            attach_signal(SIGHUP, $signal);
            attach_signal(SIGINT, $signal);
        } else {
            pcntl_signal(SIGTERM, $signal);
            pcntl_signal(SIGHUP, $signal);
            pcntl_signal(SIGINT, $signal);
        }

    }
}
