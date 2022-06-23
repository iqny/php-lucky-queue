<?php
/**
 * https://github.com/rstgroup/php-signal-handler
 * It is a good practice to keep php processes (i.e workers/consumers) under control.
 * Usually, system administrators write their own scripts which ask services about current status or performs some desired actions.
 * Usually request is sent via UNIX signals.
 * Because amqp consume method is blocking, pcntl extension seems to be useless.
 * php-signal-handler extension uses signal syscall, so it will work even if blocking method was executed.
 * Some use cases are presented on extension's github page and examples are available here.
 */

namespace PhpLuckyQueue\Queue\Drive\Rabbitmq;

use PhpLuckyQueue\Queue\Drive\DriveInterface;
use PhpLuckyQueue\Queue\Logger;

class Rabbitmq implements DriveInterface
{

    private $channel = null;
    private $conn = null;
    /**
     * @var \AMQPQueue
     */
    private $QMAPQueue = null;
    private $getDeliveryTag = '';
    private $ex = null;
    private $cfg;

    public function __construct($cfg)
    {
        $this->cfg = $cfg;
        $this->connect($cfg);
    }

    /**
     * @throws \AMQPExchangeException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function connect($cfg)
    {
        try {
            $this->conn = new \AMQPConnection($cfg);
            $this->conn->connect();
            $this->channel = new \AMQPChannel($this->conn);//创建交换机
            $this->ex = new \AMQPExchange($this->channel);
            $this->ex->setName($cfg['exchange']);
            $this->ex->setType(AMQP_EX_TYPE_DIRECT);
            $this->ex->setFlags(AMQP_DURABLE);//持久化
            $this->ex->declareExchange();
        } catch (\AMQPConnectionException | \AMQPChannelException | \AMQPExchangeException $e) {
            Logger::error('rabbitmq', $e->getMessage());
            throw $e;
        }

    }

    /**
     * @return bool|mixed
     */
    public function ack($getDeliveryTag = true): bool
    {
        if ($getDeliveryTag) {
            return $this->QMAPQueue->ack($getDeliveryTag);
        }
        return true;
        //$this->close();
    }

    public function put($key, $val)
    {
    }

    public function get($key, callable $callable)
    {
        $this->setQueue($key);
        /*$body = '';
        if ($messages) {
            $body = $messages->getBody();
            $this->getDeliveryTag = $messages->getDeliveryTag();
        }
        return $body;*/
        //$msg = $arr->getBody();
        //var_dump($msg);
        //$res = $q->ack($arr->getDeliveryTag());
        try {
            $envelope = $this->QMAPQueue->get();
            $callable($envelope, $this);
            //$this->QMAPQueue->consume($callable);
        } catch (\AMQPQueueException $e) {
            Logger::error('rabbitmq', '[' . $e->getLine() . ']' . $e->getMessage());
            try {
                $this->connect($this->cfg);
            } catch (\AMQPChannelException | \AMQPConnectionException | \AMQPExchangeException $e) {
                Logger::error('rabbitmq', '[' . $e->getLine() . ']' . $e->getMessage());
            }
        }
        /*$this->QMAPQueue->consume(function ($envelope, $queue) {
            $body = $envelope->getBody();
            //var_dump($body);
            $this->body = $body;
            //$ret = yield ($body);
            //if ($ret=='success'){
            $queue->ack($envelope->getDeliveryTag());
            //     yield $body;
            //   }
        });*/
    }

    public function len($key)
    {
        // TODO: Implement len() method.
    }

    public function append($key, $val)
    {
        $this->setQueue($key);
        $routingKey = $key;
        //发送消息到交换机，并返回发送结果
        //delivery_mode:2声明消息持久，持久的队列+持久的消息在RabbitMQ重启后才不会丢失
        if (!is_string($val)) {
            $val = json_encode($val);
        }
        $this->ex->publish($val, $routingKey, AMQP_NOPARAM, array('delivery_mode' => 2));
        //代码执行完毕后进程会自动退出
        // TODO: Implement append() method.
    }

    public function close()
    {
        $this->channel->close();
        $this->conn->disconnect();
    }

    public function getDeliveryTag(): bool
    {
        return true;
    }

    public function getBody()
    {
        // TODO: Implement getBody() method.

    }

    private function setQueue($key)
    {
        //声明路由键
        $routingKey = $key;
        //echo "Exchange Status:" . $ex->declareExchange() . "\n";
        try {
            $this->QMAPQueue = new \AMQPQueue($this->channel);
            $this->QMAPQueue->setName($key);
            $this->QMAPQueue->setFlags(AMQP_DURABLE);
            $this->QMAPQueue->declareQueue();
            $this->QMAPQueue->bind($this->ex->getName(), $routingKey);
            $this->getDeliveryTag = '';
        } catch (\AMQPConnectionException $e) {
            try {
                $this->connect($this->cfg);
            } catch (\AMQPChannelException | \AMQPConnectionException | \AMQPExchangeException $e) {
                Logger::error('rabbitmq', '[' . $e->getLine() . ']' . $e->getMessage());
            }
        } catch (\AMQPQueueException $e) {
        }

    }
}
