<?php

namespace PhpLuckyQueue\Queue\Drive\Redis;

use Predis\PredisException;
use PhpLuckyQueue\Queue\Drive\DriveInterface;
use Predis\Client;
use PhpLuckyQueue\Queue\Logger;

class Redis implements DriveInterface
{
    /**
     * @var \Redis|Client
     */
    private $client = null;
    private $cfg    = [];
    private $key    = '';
    private $keyErr;

    public function __construct($cfg)
    {
        $this->cfg = $cfg;
        $this->connect($cfg);
    }

    public function ack($getDeliveryTag = true): bool
    {
        return true;
        // TODO: Implement ack() method.
    }

    public function put($key, $val): bool
    {
        if (!is_string($val)) {
            $val = json_encode($val);
        }
        $ret = $this->client->lpush($key, $val);
        // TODO: Implement append() method.
        return (bool)$ret;
    }

    public function get($key, callable $callable)
    {
        if (!$this->client->isConnected()) {
            $this->connect($this->cfg);
        }
        $this->key = $key;
        $this->keyErr = $key . '_error';
        $callable($this, $this);


        // TODO: Implement get() method.
    }

    public function getBody()
    {
        $body = $this->client->blpop([$this->key, $this->keyErr], 1);
        if (!empty($body)) {
            return $body[1];
        }
        return $body;
    }

    public function getDeliveryTag(): bool
    {
        return true;
    }

    public function len($key)
    {
        // TODO: Implement len() method.
    }

    public function append($key, $val): bool
    {
        if (!is_string($val)) {
            $val = json_encode($val);
        }
        $ret = $this->client->rpush($key, $val);
        // TODO: Implement append() method.
        return (bool)$ret;
    }

    public function hSet($key, $hashKey, $value)
    {
        return $this->client->hset($key, $hashKey, $value);
    }

    public function hGet($key, $hashKey)
    {
        return $this->client->hget($key, $hashKey);
    }

    public function hDel($key, $hashKey)
    {
        return $this->client->hdel($key, $hashKey);
    }

    public function del($key)
    {
        return $this->client->del($key);
    }

    public function set($key, $val)
    {
        return $this->client->set($key, $val);
    }

    public function setEx($key,$seconds, $val)
    {
        return $this->client->setex($key, $seconds, $val);
    }

    public function sAdd($key, $values)
    {
        return $this->client->sAdd($key, $values);
    }

    public function sRem($key, $values)
    {
        return $this->client->sRem($key, $values);
    }

    public function sMembers($key)
    {
        return $this->client->sMembers($key);
    }

    public function expire($key, $tll)
    {
        return $this->client->expire($key, $tll);
    }

    public function exists($key)
    {

        return $this->client->exists($key);
    }

    public function connect($cfg)
    {
        if ($this->client) {
            return;
        }
        //实例redis
        $drive = isset($cfg['drive']) ? $cfg['drive'] : '';
        if (!empty($drive) && $drive === 'predis') {
            try {
                $this->client = new Client($cfg);
            } catch (PredisException $e) {
                Logger::error('Predis', $e->getMessage());
                throw $e;
            }
        } elseif (!empty($drive) && $drive === 'redis') {
            //连接redis-server
            try {
                $this->client = new \Redis();
                $this->client->pconnect($cfg['host'], $cfg['port']);
            } catch (\RedisException $e) {
                Logger::error('redis', $e->getMessage());
                throw $e;
            }
        } else {
            throw new RedisException("The config missing drive");
        }

        //设置密码
        if (isset($cfg['password']) && !empty($cfg['password'])) {
            $this->client->auth($cfg['password']);
        }
        if (isset($cfg['db']) && !empty($cfg['db'])) {
            $this->client->select($cfg['db']);
        }
    }

    public function close()
    {
        if ($this->cfg['drive'] === 'redis') {
            $this->client->close();
        }
    }
}
