<?php

namespace PhpLuckyQueue\Queue\Queue;

use PhpLuckyQueue\Queue\Logger;
use PhpLuckyQueue\Queue\Signal\Signal;

abstract class BaseQueue
{
    public static $running = true;
    private $data = [];
    protected $queueName = '';
    private $traceID = '';

    public function __construct()
    {
    }

    abstract function parse(): bool;

    public function run($cfg, $consumeQueue, $pid,$workQueue)
    {
        $pStartTime = time();
        $count = $cfg['max_exe_count'];
        $this->queueName = $cfg['queue_name'];
        $workNumber = $cfg['work_number'];
        $consumeNumber = $cfg['consume_number'];
        Signal::SetSigHandler([self::class, 'sigHandler']);
        while (self::$running) {
            $newTime = date('H', time());
            $startTime = date('H', $pStartTime);
            if ($newTime != $startTime) {
                self::$running = false;
                return ;
            }
            //echo "work start".$msgQueue.' $queueConsumerPid:',$queueConsumerPid.PHP_EOL;
            msg_receive($workQueue, $workNumber, $msgtype, 1024, $message);
            if (empty($message)){
                continue;
            }
            $msgs = explode('.', $message);
            $jobNumber = array_shift($msgs);
            $data = implode('.', $msgs);
            $this->setData($data);
            $this->traceID = $this->data['trace_id']??substr(md5(sprintf('%s%s',$data,microtime(true))),0,8);
            //记录处理数量
            //MonitorCounter::updateCounter($this->queueName);
            $ret = $this->parse();
            if ($ret) {
                msg_send($consumeQueue, $consumeNumber, $jobNumber);
                //$this->info("{$pid} finish回调成功。");
            } else {
                $this->warning("call fail");
            }
            $count--;
            if ($count <= 0) {
                self::$running = false;
            }
        }
    }

    protected function getData(): array
    {
        return $this->data;
    }

    private function setData($data)
    {
        $this->data = json_decode($data, true, JSON_UNESCAPED_UNICODE);
    }

    public static function sigHandler($signo)
    {
        self::$running = false;
    }

    private function setTraceID($type)
    {
        Logger::pushProcessor($type,$this->queueName,function($record){
            $record['trace_id'] = $this->traceID;
            return $record;
        });
    }

    public function info($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::info($this->queueName, $msg, $context);
    }

    public function alert($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::alert($this->queueName, $msg, $context);
    }

    public function notice($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::notice($this->queueName, $msg,$context);
    }

    public function debug($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::debug($this->queueName, $msg, $context);
    }

    public function warning($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::warning($this->queueName, $msg, $context);
    }

    public function critical($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::critical($this->queueName, $msg, $context);
    }

    public function emergency($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::emergency($this->queueName, $msg, $context);
    }

    public function error($msg, $context = [])
    {
        $this->setTraceID(__FUNCTION__);
        Logger::info($this->queueName, $msg, $context);
    }
}
