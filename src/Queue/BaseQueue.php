<?php

namespace PhpLuckyQueue\Queue\Queue;

use Illuminate\Support\Facades\DB;
use PhpLuckyQueue\Queue\Logger;
use PhpLuckyQueue\Queue\Signal\Signal;
use PhpLuckyQueue\Queue\MonitorCounter;

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

    public function run($cfg, $consumeQueue, $workQueue)
    {
        $pStartTime = time();
        $count = $cfg['max_exe_count'];
        $this->queueName = $cfg['queue_name'];
        $workNumber = $cfg['work_number'];
        $consumeNumber = $cfg['consume_number'];
        $retryCount = $cfg['retry_count'] ?? 1;
        $flags = 0;
        //安装signal_handler用阻塞模式
        if (!function_exists('attach_signal')) {
            $flags = 1;
        }
        while (self::$running) {
            Signal::SetSigHandler([self::class, 'sigHandler']);
            $newTime = date('H', time());
            $startTime = date('H', $pStartTime);
            if ($newTime != $startTime) {
                self::$running = false;
                return;
            }
            //echo "work start".$msgQueue.' $queueConsumerPid:',$queueConsumerPid.PHP_EOL;
            msg_receive($workQueue, $workNumber, $msgtype, 1024, $message, true, $flags);
            if (empty($message)) {
                sleep(1);
                continue;
            }
            $msgs = explode('.', $message);
            $jobNumber = array_shift($msgs);
            $data = implode('.', $msgs);
            $this->setData($data);
            //记录处理数量
            MonitorCounter::updateCounter($this->queueName);
            $this->traceID = $this->data['trace_id'] ?? substr(md5(sprintf('%s%s', $data, microtime(true))), 0, 8);
            //记录处理数量
            //MonitorCounter::updateCounter($this->queueName);
            for ($i = $retryCount; $i >= 0; $i--) {
                try {
                    $ret = $exception = $this->parse();
                    if ($ret) {
                        msg_send($consumeQueue, $consumeNumber, $jobNumber);
                        //$this->info("{$pid} finish回调成功。");
                        break;
                    } else {
                        $tmpC = $retryCount - $i;
                        if ($tmpC != 0) {
                            $this->warning("重试第{$tmpC}次");
                        }else{
                            $this->warning("重试开启");
                        }
                        $this->warning("call fail");

                    }
                }catch (\Exception $e){
                    $exception = json_encode(['message'=>$e->getMessage(),'line'=>$e->getLine(),'file'=>$e->getFile(),'code'=>$e->getCode()]);
                }

                if ($i == 0) {
                    msg_send($consumeQueue, $consumeNumber, $jobNumber);
                    $failed = [
                        'connection' => $cfg['drive'] ?? '',
                        'queue' => $queueName ?? '',
                        'payload' => $data ?? '',
                        'exception' => $exception ?? '',
                    ];
                    $this->failedJobs($failed);
                }
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
        Logger::pushProcessor($type, $this->queueName, function ($record) {
            $record['trace_id'] = $this->traceID;
            $record['work_pid'] = posix_getpid();
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
        Logger::notice($this->queueName, $msg, $context);
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
    public function failedJobs($data){
        $insert = [
            'connection'=>$data['connection']??'',
            'queue'=>$data['queue']??'',
            'payload'=>$data['payload']??'',
            'exception'=>$data['exception']??'',
        ];
        DB::table('failed_jobs')->insert($insert);
    }
}
