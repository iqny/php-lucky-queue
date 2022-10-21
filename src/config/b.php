<?php
//创建连接和channel
$config = [
    'host' => '127.0.0.1',
    'port' => '5672',
    'login' => 'guest',
    'password' =>  'guest',
    'exchange' => 'my_exchange',//交换机名
    'vhost' => '/',//虚拟路径
];
$connect = new AMQPConnection($config);

if (!$connect->connect()) {
    die("Cannot connect to the broker!\n");
}

$channel  = new AMQPChannel($connect);

//**********************创建一个用于存放死信的交换机和队列*************
$deadExchangeName = 'dead_exchange';
$deadQueueName    = 'delayed_order';
$deadRouteKey     = 'delayed_order';

$deadExchange = new AMQPExchange($channel);
$deadExchange->setName($deadExchangeName);
$deadExchange->setType(AMQP_EX_TYPE_DIRECT);
$deadExchange->declareExchange();

$deadQueue = new AMQPQueue($channel);
$deadQueue->setName($deadQueueName);
$deadQueue->declareQueue();
$deadQueue->bind($deadExchange->getName(), $deadRouteKey);


//***********************创建被延迟的交换机和消息队列********************
$exchangeName = 'exchange1';
$queueName    = 'order';
$routeKey     = 'order';
$exchange = new AMQPExchange($channel);
$exchange->setName($exchangeName);
$exchange->setType(AMQP_EX_TYPE_DIRECT);

// 1:不持久化到磁盘，宕机数据消失 2：持久化到磁盘
// $exchange->setFlags(AMQP_DURABLE);

// 声明交换机
$exchange->declareExchange();

// 创建消息队列
$queue = new AMQPQueue($channel);
$queue->setName($queueName);
$arguments = [
    'x-message-ttl' => 6000,
    'x-dead-letter-exchange' => $deadExchangeName, //死信发送的交换机
    'x-dead-letter-routing-key' => $deadRouteKey, //死信routeKey
];

// 设置持久性
// $queue->setFlags(AMQP_DURABLE);

$queue->setArguments($arguments);
// 声明消息队列
$queue->declareQueue();

$queue->bind($exchange->getName(), $routeKey);

// 向服务器队列推送10条消息
$msg = 'hello world 1';
$exchange->publish($msg, $routeKey, AMQP_NOPARAM, ['delivery_mode' => 2]);
