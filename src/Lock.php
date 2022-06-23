<?php

namespace PhpLuckyQueue\Queue;

use PhpLuckyQueue\Queue\Drive\RabbitmqFactory;
use PhpLuckyQueue\Queue\Drive\RedisFactory;

/**
 * 进程锁或分布式锁
 * Lock::acquire('cn');
 * Lock::release('cn');
 * Class Lock
 */
class Lock
{
    private static $conn = null;
    private static $uuid;

    private function __construct()
    {
    }

    private static function connect()
    {
        if (!is_null(self::$conn)) {
            return self::$conn;
        }
        $cfg = config('queueqihu');
        self::$conn = new \Redis();
        //连接redis-server
        self::$conn->pconnect($cfg['redis']['host'], $cfg['redis']['port']);
        //设置密码
        if (isset($cfg['redis']['password'])) {
            self::$conn->auth($cfg['redis']['password']);
        }
        if (isset($cfg['redis']['db']) && !empty($cfg['redis']['db'])){
            self::$conn->select($cfg['redis']['db']);
        }
    }

    private static function close()
    {
        self::$conn->close();
    }

    /**
     * 获取锁
     * @param string $lockName 键名(锁键)
     * @param int $lock_timeout 获得锁时间(作业) 秒
     * @param int $acquire_timeout 尝试获得锁时间 秒
     * @return bool
     */
    public static function acquire($lockName, $lock_timeout = 10, $acquire_timeout = 30)
    {
        self::connect();
        //生成个随便数
        self::$uuid = self::getUUId();
        $time = time();
        $tryTime = $time + $acquire_timeout;
        //设置超时时间
        $bool = false;
        $lockName = 'lock:' . $lockName;
        //超时退出
        while ($tryTime > time()) {
            if (self::$conn->set($lockName, self::$uuid, ['ex' => $lock_timeout, 'nx'])) {
                $bool = true;
                break;
            };
            usleep(5000);
        }
        //self::close();
        return $bool;
    }

    /**
     * 解锁
     * @param $lockName
     * @return bool
     */
    public static function release($lockName)
    {
        self::connect();
        $lockName = 'lock:' . $lockName;
        while (true) {
            self::$conn->watch($lockName);//监控
            $v = self::$conn->get($lockName);
            if (($v !== false) && $v === self::$uuid) {//持有该锁
                self::$conn->multi();//开启事务
                self::$conn->del($lockName);//删除键
                if (!empty(self::$conn->exec())) {//如有监控到其他进程修改或修改失败，则重试
                    //self::close();
                    return true;
                }
            } else {//不持该锁，退出
                self::$conn->unwatch();//释放监控
                break;
            }
        }
        //self::close();
        return false;
    }

    /**
     * 生成唯一UUID
     * @return string
     */
    private static function getUUId()
    {
        static $guid = '';
        $uid = uniqid("", true);
        $data = self::getVCode();
        $data .= isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : '';
        $data .= isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $data .= isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : '';
        $data .= isset($_SERVER['LOCAL_PORT']) ? $_SERVER['LOCAL_PORT'] : '';
        $data .= isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $data .= isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '';
        $hash = hash('md5', $uid . $guid . md5($data));
        $guid =
            substr($hash, 0, 8) .
            '-' .
            substr($hash, 8, 4) .
            '-' .
            substr($hash, 12, 4) .
            '-' .
            substr($hash, 16, 4) .
            '-' .
            substr($hash, 20, 12);
        return $guid;
    }

    /**
     * 获取随机码
     * @return string
     */
    private static function getVCode()
    {
        $char = [
            0 => '０', 1 => '１', 2 => '２', 3 => '３', 4 => '４', 5 => '５', 6 => '６', 7 => '７', 8 => '８', 9 => '９', 10 => 'Ａ',
            11 => 'Ｂ', 12 => 'Ｃ', 13 => 'Ｄ', 14 => 'Ｅ', 15 => 'Ｆ', 16 => 'Ｇ', 17 => 'Ｈ', 18 => 'Ｉ', 19 => 'Ｊ', 20 => 'Ｋ', 21 => 'Ｌ',
            22 => 'Ｍ', 23 => 'Ｎ', 24 => 'Ｏ', 25 => 'Ｐ', 26 => 'Ｑ', 27 => 'Ｒ', 28 => 'Ｓ', 29 => 'Ｔ', 30 => 'Ｕ', 31 => 'Ｖ',
            32 => 'Ｗ', 33 => 'Ｘ', 34 => 'Ｙ', 35 => 'Ｚ', 36 => 'ａ', 37 => 'ｂ', 38 => 'ｃ', 39 => 'ｄ', 40 => 'ｅ', 41 => 'ｆ', 42 => 'ｇ',
            43 => 'ｈ', 44 => 'ｉ', 45 => 'ｊ', 46 => 'ｋ', 47 => 'ｌ', 48 => 'ｍ', 49 => 'ｎ', 50 => 'ｏ', 51 => 'ｐ',
            52 => 'ｑ', 53 => 'ｒ', 54 => 'ｓ', 55 => 'ｔ', 56 => 'ｕ', 57 => 'ｖ', 58 => 'ｗ',
            59 => 'ｘ', 60 => 'ｙ', 61 => 'ｚ', 62 => '（', 63 => '）', 64 => '〔', 65 => '〕', 66 => '【', 67 => '】', 68 => '〖',
            69 => '〗', 70 => '“', 71 => '”', 72 => '‘', 73 => '’', 74 => '｛', 75 => '｝',
            76 => '《', 77 => '》', 78 => '％', 79 => '＋', 80 => '—', 81 => '－',
            82 => '：', 83 => '。', 84 => '、', 85 => '，', 86 => '；', 87 => '？', 88 => '！', 89 => '……',
            90 => '‖', 91 => '｜', 92 => '〃', 93 => '·', 94 => '～', 95 => '　', 96 => '＄', 97 => '＠', 98 => '＃', 99 => '＾', 100 => '＆',
            101 => '＊', 102 => '＂',
        ];
        $shift = array_rand($char, 10);
        $arr = [];
        foreach ($shift as $k) {
            $arr[] = $char[$k];
        }
        return md5(implode('', $arr));
    }
}
