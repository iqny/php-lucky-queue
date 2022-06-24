<?php

namespace App\Console\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use PhpLuckyQueue\Queue\Logger;
use PhpLuckyQueue\Queue\LuckyQueue as queue;

class lucky extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:lucky {commend} {--daemon=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $config;
    /**
     * @var queue
     */
    private $cli = null;

    /**
     * Create a new command instance.
     *
     * @param Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $commend = $this->argument('commend');
        $daemon = $this->option('daemon');
        $this->cli = new queue($this->config);
        switch ($commend) {
            case 'start':
                echo 'start...' . PHP_EOL;
                echo 'start success' . PHP_EOL;
                printf("使用 ps -ef|grep %s 查看运行状态\n",php_uname('n'));
                $this->start($daemon);
                break;
            case 'stop':
                echo 'stop...' . PHP_EOL;
                echo 'stop success' . PHP_EOL;
                $this->stop();
                break;
            case 'restart':
                echo 'restart...' . PHP_EOL;
                echo 'restart success' . PHP_EOL;
                $this->restart();
                break;
            default:
                break;
        }
        return 0;
    }

    private function start($daemon)
    {
        /*Logger::setLogger(function ($queueName){
            $log = new \Monolog\Logger($queueName);
            $redis = new \Redis();
            $cfg = $this->config['lucky']['redis'];
            $redis->connect($cfg['host'],$cfg['port']);
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
            $handle = new \Monolog\Handler\RedisHandler($redis,'monolog-log');
            $handle->setFormatter(new \Monolog\Formatter\JsonFormatter());
            $log->pushHandler($handle);
            return $log;
        });*/
        $this->cli->run($daemon);
    }
    private function restart()
    {
        $this->cli->restart();
    }

    private function stop()
    {
        $this->cli->kill();
    }
}
