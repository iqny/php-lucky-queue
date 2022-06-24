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
    /**
     * @var false|resource
     */
    private $msgQueue;

    /**
     * 构造方法
     */
    public function __construct(Repository $config)
    {
        $this->cfg = $config->get('lucky');
        $this->redis = RedisFactory::createClient($this->cfg['redis']);
        $this->host = php_uname('n');
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
        $sleepTime = 5;
        $queueCount = count($this->cfg['queue']) + 1;
        while ($this->running) {
            try {
                $workNumber = 1;
                $consumeNumber = $queueCount;
                $this->setMonitor();
                foreach ($this->cfg['queue'] as $cfg) {
                    $queueName = $cfg['queue_name'];
                    if (isset($cfg['run']) && $cfg['run'] && $this->running) {
                        //$this->completingCfg($cfg);
                        //$this->check($queueName, $cfg);
                        $cfg['queue_name'] = $queueName;
                        $cfg['work_number'] = $workNumber;
                        $cfg['consume_number'] = $consumeNumber;
                        $keyConsume = ftok(__FILE__, $consumeNumber);
                        $consumeQueue = msg_get_queue($keyConsume);
                        $keyWork = ftok(__FILE__, $workNumber);
                        $workQueue = msg_get_queue($keyWork);
                        $this->checkConsume($cfg, $consumeQueue, $workQueue);
                        $this->checkWork($cfg, $consumeQueue, $workQueue);
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
                Logger::warning('monitor',"exp:{$e->getMessage()},{$e->getFile()},{$e->getLine()}");
            }
        }
    }

    private function getMonitorPid()
    {
        return $this->redis->hGet($this->host, 'monitor');
    }

    public function checkConsume($cfg, $consumeQueue, $workQueue)
    {
        $queueName = $cfg['queue_name'];
        $pid = $this->queueConsumer[$queueName] ?? 0;
        if ($pid) {
            if (!intval($pid) || !posix_kill($pid, 0)) {
                $this->consumeStart($cfg, $consumeQueue, $workQueue);
            }
        } else {
            $this->consumeStart($cfg, $consumeQueue, $workQueue);
        }
    }

    public function checkWork($cfg, $consumeQueue, $workQueue)
    {
        $queueName = $cfg['queue_name'];
        if (!isset($this->queueWork[$queueName])) {
            $this->queueWork[$queueName] = [];
        }
        for ($i = 0; $i < $cfg['worker_count']; $i++) {
            $pid = $this->queueWork[$queueName][$i] ?? 0;
            if ($pid) {
                if (!intval($pid) || !posix_kill($pid, 0)) {
                    $this->workStart($cfg, $i, $consumeQueue, $workQueue);
                }
            } else {
                $this->workStart($cfg, $i, $consumeQueue, $workQueue);
            }
        }
    }

    private function consumeStart($cfg, $consumeQueue, $workQueue)
    {
        $queueName = $cfg['queue_name'];
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('ERROR:fork failed monitor');
        } elseif ($pid) {
            $this->queueConsumer[$queueName] = $pid;
        } else {
            Logger::warning('monitor', "start {$queueName} consumer");
            if (PHP_OS == 'Linux') {
                cli_set_process_title(sprintf("%s %s slave", $this->host, $queueName));
            }
            $this->redis->hSet($this->host, $queueName, posix_getpid());
            consumer::start($cfg, $consumeQueue, $workQueue);
            exit(0);
        }
    }

    private function workStart($cfg, $i, $consumeQueue, $workQueue)
    {
        $queueName = $cfg['queue_name'];
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('ERROR:fork failed monitor');
        } elseif ($pid) {
            $this->queueWork[$queueName][$i] = $pid;
        } else {
            Logger::warning('monitor', "start {$queueName} work");
            if (PHP_OS == 'Linux') {
                cli_set_process_title(sprintf("%s %s work", $this->host, $queueName));
            }
            $this->redis->sAdd(sprintf("%s:%s-work", $this->host, $queueName), posix_getpid());
            Worker::start($cfg, $consumeQueue, posix_getpid(), $workQueue);
            exit(0);
        }
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
        $this->redis->del('monitor');
        $this->redis->del($this->host);
    }

    private function stop()
    {
        foreach ($this->cfg['queue'] as $cfg) {
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
        return $this->redis->exists('monitor');
    }

    private function setMonitor()
    {
        $key = 'monitor';
        $this->redis->set($key, 1);
        $this->redis->expire($key, 10);
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
