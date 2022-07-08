<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\Redis\RedisFactory;
use PhpLuckyQueue\Queue\Signal\Signal;
use Illuminate\Config\Repository;

class LuckyQueue
{
    protected $cfg;
    public $running = true;
    public $pids = [];
    /**
     * @var \Redis
     */
    public $redis = null;
    private $host;
    private $queueConsumer = [];//从数据源获取数据
    private $queueWork = [];//队列work
    private $monitorRunningKey;

    /**
     * 构造方法
     */
    public function __construct(Repository $config)
    {
        $this->cfg = $config->get('lucky');
        $this->redis = RedisFactory::createClient($this->cfg['redis']);
        $this->host = php_uname('n');
        $this->monitorRunningKey = sprintf('%s-%s',$this->host,'monitor');
    }

    public function append($pid)
    {
        array_push($this->pids, $pid);
    }

    public function restart(): bool
    {
        return posix_kill($this->getMonitorPid(), SIGHUP);
    }

    public function kill()
    {
        if (!posix_kill($this->getMonitorPid(), SIGTERM)) {
            $this->stop();
            exit(0);
        }
    }

    public function run($daemon)
    {

        if ($daemon) {
            $this->daemon();
        }
        if (PHP_OS == 'Linux') {
            cli_set_process_title(sprintf("%s master", $this->host));
        }
        foreach ($this->cfg['queue'] as $cfg) {
            $this->redis->del(sprintf("%s:%s-work", $this->host, $cfg['queue_name']));
        }
        //为了防止多次启动
        if ($this->checkMonitor()) {
            die();
        }
        $this->redis->hSet($this->host, 'monitor', posix_getpid());
        $msgQueue = $this->createMsgQueue();//创建msg_queue
        $sleepTime = 5;
        $queueCount = count($this->cfg['queue']) + 1;
        MonitorCounter::setPrecision($this->cfg['counter']['precisions']);
        MonitorCounter::setSampleCount($this->cfg['counter']['sample_count']);
        while ($this->running) {
            try {
                $workNumber = 1;
                $consumeNumber = $queueCount;
                $this->setMonitor();
                foreach ($this->cfg['queue'] as $cfg) {
                    $queueName = $cfg['queue_name'];
                    if (isset($cfg['run']) && $cfg['run'] && $this->running) {
                        usleep(10000);
                        $cfg['queue_name'] = $queueName;
                        $cfg['work_number'] = $workNumber;
                        $cfg['consume_number'] = $consumeNumber;
                        $consumeQueue = $msgQueue[$consumeNumber];
                        $workQueue = $msgQueue[$workNumber];
                        Consumer::checkConsume($cfg, $consumeQueue, $workQueue,$this->redis);
                        Worker::checkWork($cfg, $consumeQueue, $workQueue,$this->redis);
                        MonitorCounter::checkCleanCounter($this->redis);
                    }
                    $workNumber++;
                    $consumeNumber++;
                }
                Signal::SetSigHandler([&$this, 'sigHandler']);
                $pid = pcntl_waitpid(-1, $status, WNOHANG);
                if ($pid <= 0) {
                    sleep($sleepTime);
                    $sleepTime = 1;
                } else {
                    Logger::warning('monitor', "process $pid exit");
                    foreach ($this->cfg['queue'] as $cfg) {
                        $queueName = $cfg['queue_name'];
                        if (isset($cfg['run']) && $cfg['run']) {
                            $this->redis->sRem(sprintf("%s:%s-work", $this->host, $queueName), $pid);//尝试去每个set删除
                        }
                    }
                }
            } catch (\Exception $e) {
                sleep(3);
                Logger::warning('monitor',"exp:{$e->getMessage()},{$e->getFile()},{$e->getLine()}");
            }
        }
    }

    private function getMonitorPid()
    {
        return $this->redis->hGet($this->host, 'monitor');
    }
    private function createMsgQueue()
    {
        $queueCount = count($this->cfg['queue']) + 1;
        $workNumber = 1;
        $consumeNumber = $queueCount;
        $msgQueue = [];
        foreach ($this->cfg['queue'] as $cfg) {
            if (isset($cfg['run']) && $cfg['run'] && $this->running) {
                $keyConsume = ftok(__FILE__, $consumeNumber);
                $consumeQueue = msg_get_queue($keyConsume);
                $keyWork = ftok(__FILE__, $workNumber);
                $workQueue = msg_get_queue($keyWork);
                $msgQueue[$workNumber] = $workQueue;
                $msgQueue[$consumeNumber] = $consumeQueue;
            }
            $workNumber++;
            $consumeNumber++;
        }
        return $msgQueue;
    }

    //信号事件回调
    public function sigHandler($signo)
    {
        $this->running = false;
        //stop
        switch ($signo) {
            case SIGTERM:
                $this->stop();
                $this->exitClear();
                exit(0);
            case SIGHUP:
                $this->stop();
                $this->cfg = require config_path('lucky.php');//重启重新读取配置
                break;
            default:
                $this->exitClear();
                break;
        }
    }

    private function exitClear()
    {
        $this->redis->del($this->monitorRunningKey);
        $this->redis->del($this->host);
    }

    private function stop()
    {
        $queues = $this->cfg['queue'];
        $queues[] = [
            'queue_name'=>'clean_counter'
        ];
        foreach ($queues as $cfg) {
            $queueName = $cfg['queue_name'];
            $pid = $this->redis->hGet($this->host, $queueName);
            if ($pid && posix_kill($pid, 0)) {
                $ret = posix_kill($pid, SIGTERM);
                if ($ret) {
                    Logger::warning('monitor', "send stop $pid");
                    pcntl_waitpid($pid, $status);
                    //删除redis记录
                    $this->redis->hDel($this->host, $queueName);
                }
            }
            $workKey = sprintf("%s:%s-work", $this->host, $queueName);
            $workPids = $this->redis->sMembers($workKey);
            foreach ($workPids as $wPid) {
                if ($wPid && posix_kill($wPid, 0)) {
                    $ret = posix_kill($wPid, SIGTERM);
                    if ($ret) {
                        Logger::warning('monitor', "send {$queueName}-work stop $wPid");
                        pcntl_waitpid($pid, $status);
                        //删除redis记录
                        $this->redis->sRem(sprintf("%s:%s-work", $this->host, $queueName), $wPid);//尝试去每个set删除
                    }
                }

            }

        }
    }

    private function checkMonitor()
    {
        return $this->redis->exists($this->monitorRunningKey);
    }

    private function setMonitor()
    {
        $this->redis->set($this->monitorRunningKey, 1);
        $this->redis->expire($this->monitorRunningKey, 10);
    }

    private function daemon()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die ("fork failed for daemon");
        } else if ($pid) {
            exit (0);
        } else {
            if (posix_setsid() == -1) {
                die ("could not detach from terminal");
            }
        }
    }

}
