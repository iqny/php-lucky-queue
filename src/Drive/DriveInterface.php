<?php
namespace PhpLuckyQueue\Queue\Drive;
interface DriveInterface
{
    public function connect($cfg);
    /**
     * 确认
     * @return mixed
     */
    public function ack($getDeliveryTag):bool;

    /**
     *  队列头部追加
     * @param $key
     * @param $val
     * @return mixed
     */
    public function put($key,$val);

    /**
     * 队列左边获取
     * @param $key
     * @param callable $callable
     * @return mixed
     */
    public function get($key,callable $callable);

    /**
     * 队列长度
     * @param $key
     * @return mixed
     */
    public function len($key);

    /**
     * 队列尾部追加
     * @param $key
     * @param $val
     * @return mixed
     */
    public function append($key,$val);

    public function getDeliveryTag():bool;

    public function getBody();
}
