<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\DriveInterface;
use PhpLuckyQueue\Queue\Signal\Signal;

/**
 * 记录监控台
 * Class MonitorCounter
 */
class MonitorCounter
{
    static $conn;
    public static $sampleCount = 20;//20个长度
    private static $running = true;
    /**
     * 分别为1秒/5秒/1分钟/5分钟/1小时/5小时/1天
     * @var int[]
     */
    private static $precision = ['5' => '5s', '60' => '1m', '300' => '5m', '3600' => '1h'];//, '60' => '1m', '300' => '5m', '3600' => '1h', '18000' => '2h', '86400' => '24h', '172800' => '48h'
    private static $cleanCounter;
    private static $cleanCounterKey = 'clean_counter';

    private function __construct()
    {
    }

    private static function connect()
    {

        if (!is_null(self::$conn)) {
            return self::$conn;
        }
        $cfg = config('lucky');
        self::$conn = new \Redis();
        //连接redis-server
        self::$conn->pconnect($cfg['redis']['host'], $cfg['redis']['port']);
        //设置密码
        if (isset($cfg['redis']['password'])) {
            self::$conn->auth($cfg['redis']['password']);
        }
        if (isset($cfg['redis']['db']) && !empty($cfg['redis']['db'])) {
            self::$conn->select($cfg['redis']['db']);
        }
        if (isset($cfg['counter']['precisions']) && !empty($cfg['counter']['precisions'])) {
            self::setPrecision($cfg['counter']['precisions']);
        }
    }

    /**
     * 设置时间分片
     * @param array $precision
     */
    public static function setPrecision(array $precision)
    {
        if (empty($precision)) return;
        self::$precision = $precision;
    }

    public function getPrecision(): array
    {
        return self::$precision;
    }

    /**
     * 更新并新增每次时间分片记录
     * @param $name
     * @param int $count
     * @param null $now
     */
    public static function updateCounter($name, $count = 1, $now = null)
    {
        self::connect();
        $now = !empty($now) ? $now : microtime(true);
        foreach (self::$precision as $prec => $label) {
            $pnow = intval($now / $prec) * $prec;
            $hash = "{$prec}:{$name}";
            self::$conn->zAdd('known:', 0, $hash);
            self::$conn->hIncrBy("count:{$hash}", $pnow, $count);
        }
    }

    /**
     * 获取指定类型的时间分片
     * @param $name
     * @param $precision
     * @return mixed
     */
    public static function getCounter($name, $precision)
    {
        self::connect();
        $hash = "{$precision}:{$name}";
        $data = self::$conn->hGetAll("count:{$hash}");
        ksort($data);
        return $data;
    }

    public static function delCounter($name, $precision, $time)
    {
        self::connect();
        $hash = "{$precision}:{$name}";
        $data = self::$conn->hDel("count:{$hash}", $time);
        ksort($data);
        return $data;
    }

    public static function setSampleCount($sampleCount)
    {
        if (empty($sampleCount)) return
            self::$sampleCount = $sampleCount;;
    }

    public static function incCount($name, $val)
    {
        self::connect();
        self::$conn->hIncrBy("counter:", $name, $val);
    }

    /**
     * 清除多余的记录
     */
    private static function cleanCounter()
    {
        try {
            self::connect();
            $passes = 0;
            $sample_count = self::$sampleCount;
            while (self::$running) {
                Signal::SetSigHandler([self::class, 'sigHandler']);
                $start = time();
                $index = 0;
                while ($index < self::$conn->zCard('known:')) {
                    $hash = self::$conn->zRange('known:', $index, $index);
                    $index += 1;
                    if (empty($hash)) {
                        break;
                    }
                    $hash = $hash[0];
                    $keys = explode(':', $hash);
                    $prec = intval($keys[0]);
                    $bprec = intval($prec / 60);
                    $bprec = $bprec ? $bprec : 1;
                    if ($passes % $bprec) {
                        continue;
                    }
                    //print_r($prec);
                    $hkey = "count:{$hash}";
                    $cutoff = time() - $sample_count * $prec;
                    //$sample =
                    $sample = self::$conn->hKeys($hkey);
                    if (empty($sample)) {
                        continue;
                    }
                    sort($sample);
                    /*print_r($sample);
                    var_dump($cutoff);
                    echo PHP_EOL;*/
                    $remove = bisect_right($sample, $cutoff);
                    //var_dump($cutoff);
                    //var_dump($remove);
                    //如果少于最少范例，保留最少
                    if (count($sample) - $sample_count < $remove) {
                        //continue;
                        $remove = $remove - $sample_count;
                    }
                    if ($remove > 0) {
                        //var_dump($remove);
                        $delKeys = array_chunk($sample, $remove);
                        //print_r($delKeys);
                        foreach ($delKeys[0] as $val) {
                            //var_dump($hkey,$val);
                            self::$conn->hDel($hkey, $val);
                        }
                        //print_r('len='.$conn->hLen($hkey));
                        //echo PHP_EOL;
                        self::$conn->watch($hkey);
                        if (empty(self::$conn->hLen($hkey))) {
                            self::$conn->multi();
                            self::$conn->zRem('known:', $hash);
                            if (self::$conn->exec()) {
                                $index -= 1;
                            }
                        } else {
                            self::$conn->unwatch();
                        }
                    }
                }
                $passes += 1;
                $duration = min(intval(time() - $start) + 1, 60);
                sleep(max(60 - $duration, 1));
            }
        } catch (\Exception $e) {
            Logger::warning('monitor', 'counter', ['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
            exit(0);
        }
    }

    public static function checkCleanCounter(DriveInterface $redisClient)
    {
        usleep(100000);
        $pid = self::$cleanCounter[self::$cleanCounterKey] ?? 0;
        if ($pid) {
            if (!intval($pid) || !posix_kill($pid, 0)) {
                self::cleanCounterStart($redisClient);
            }
        } else {
            self::cleanCounterStart($redisClient);
        }
    }

    private static function cleanCounterStart(DriveInterface $redisClient)
    {
        $host = php_uname('n');
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('ERROR:fork failed monitor');
        } elseif ($pid) {
            self::$cleanCounter[self::$cleanCounterKey] = $pid;
        } else {
            Logger::warning('monitor', sprintf("start %s clean_counter", self::$cleanCounterKey));
            if (PHP_OS == 'Linux') {
                cli_set_process_title(sprintf("%s %s", $host, self::$cleanCounterKey));
            }
            $redisClient->hSet($host, self::$cleanCounterKey, posix_getpid());
            self::cleanCounter();
            exit(0);
        }
    }

    public static function sigHandler($signo)
    {
        self::$running = false;
        exit();
    }
}

if (!function_exists('bisect_right')) {
    function bisect_right($arr, $val)
    {
        sort($arr);
        $index = 0;
        foreach ($arr as $k => $v) {
            if ($val == $v || $val < $v) {
                $index = $k;
                break;
            } else {
                $index = count($arr);
            }

        }
        return $index - 1;
    }
}

