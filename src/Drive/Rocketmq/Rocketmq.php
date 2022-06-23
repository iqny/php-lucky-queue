<?php

namespace PhpLuckyQueue\Queue\Drive\Rocketmq;

use MQ\Exception\MQException;
use MQ\MQConsumer;
use MQ\MQProducer;
use PhpLuckyQueue\Queue\Drive\DriveInterface;
use MQ\MQClient;
use PhpLuckyQueue\Queue\Logger;
use MQ\Exception\MessageNotExistException;
use MQ\Exception\AckMessageException;

class Rocketmq implements DriveInterface
{

    /**
     * @var  MQClient
     */
    private $conn = null;
    private $cfg = null;
    private $receiptHandles = [];
    /**
     * @var MQConsumer
     */
    private $consumer = null;
    /**
     * @var MQProducer
     */
    private $producer = null;

    public function __construct($cfg)
    {
        $this->cfg = $cfg;
        $this->connect($cfg);
    }

    public function connect($cfg)
    {
        // TODO: Implement connect() method.
        try {
            $this->conn = new MQClient($this->cfg['host'], $this->cfg['access_key'], $this->cfg['secret_key']);
        } catch (MQException $mq) {
            Logger::error('rocket', $mq->getMessage());
            throw $mq;
        }
    }

    public function ack($getDeliveryTag): bool
    {
        // TODO: Implement ack() method.
        try {
            $this->consumer->ackMessage($this->receiptHandles);
            return true;
        }catch (AckMessageException $e){
            // 某些消息的句柄可能超时了会导致确认不成功
            Logger::error('rocket', '[' . $e->getLine() . ']' . sprintf("Ack Error, RequestId:%s\n", $e->getRequestId()));
            foreach ($e->getAckMessageErrorItems() ?? [] as $errorItem) {
                Logger::error('rocket', sprintf("\tReceiptHandle:%s, ErrorCode:%s, ErrorMsg:%s\n", $errorItem->getReceiptHandle(), $errorItem->getErrorCode(), $errorItem->getErrorCode()));
            }
        } catch (\Exception $e) {
            Logger::error('rocket', '[' . $e->getLine() . ']' . sprintf("Ack Exception, RequestId:%s\n", $e->getRequestId()));
        }
        return false;
    }

    public function put($key, $val)
    {
        // TODO: Implement put() method.
    }

    public function get($key, callable $callable)
    {
        $this->consumer();
        // TODO: Implement get() method.
        $this->key = $key;
        $this->keyErr = $key . '_error';
        $callable($this, $this);
    }

    public function getBody()
    {

        try {
            $numOfMessages = $this->cfg['num_of_messages']??1;
            $waitSeconds = $this->cfg['wait_seconds']??1;
            $messages = $this->consumer->consumeMessage(
                $numOfMessages, // 一次最多消费5条(最多可设置为16条)
                $waitSeconds // 长轮询时间1秒（最多可设置为30秒）
            );
            $this->receiptHandles = [];
            foreach ($messages as $message) {
                $this->receiptHandles[] = $message->getReceiptHandle();
                $messageBody[] = $message->getMessageBody();
            }
            return json_encode($messageBody,JSON_UNESCAPED_UNICODE);
        } catch (MessageNotExistException $e) {
            $this->consumer=null;
            //重新连接
            $this->consumer();
            sleep(2);
            return false;
        }catch (\Exception $e){
            Logger::error('rocket', $e->getMessage());
        }
        return '';
    }

    public function len($key)
    {
        // TODO: Implement len() method.
    }

    public function append($key, $val)
    {
        // TODO: Implement append() method.
        try {
            $this->producer();
            $publishMessage = new TopicMessage(
                $val// 消息内容
            );
            $publishMessage->putProperty("pro", 3);// 设置属性
            $publishMessage->setMessageKey($key);        // 设置消息KEY
            $publishMessage->setStartDeliverTime(time() * 1000 + 10 * 1000);
            $this->producer->publishMessage($publishMessage);
            /*print "Send mq message success. msgId is:" . $result->getMessageId() .
                ", bodyMD5 is:" .$result->getMessageBodyMD5() . "\n";*/
        } catch (\Exception $e) {
            $this->producer=null;
            Logger::error('rocket', $e->getMessage());
        }
    }

    /**
     * @return MQConsumer
     */
    public function consumer()
    {
        try {
            if ($this->consumer==null) {
                $this->consumer = $this->conn->getConsumer($this->cfg['instance_id'], $this->cfg['topic'], $this->cfg['group_id']);
            }
        } catch (MQException $mq) {
            Logger::error('rocket', $mq->getMessage());
            throw $mq;
        }

    }
    public function getDeliveryTag():bool{
        return true;
    }

    public function producer(){
        try {
            if ($this->producer==null) {
                $this->producer = $this->conn->getProducer($this->cfg['instance_id'], $this->cfg['topic']);
            }
        } catch (MQException $mq) {
            Logger::error('rocket', $mq->getMessage());
            throw $mq;
        }
    }
}
