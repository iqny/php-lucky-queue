# php-lucky-queue
<p>redis 支持php-redis扩展，predis。默认安装predis,如果选择redis扩展需要自行安装</p>
rabbitmq 支持php-amqp。如果选择rabbitmq需安装php-amqp扩展
<p>按需求安装对应版本：</p>
<p>redis扩展：http://pecl.php.net/package/redis</p>
<p>amqp扩展：http://pecl.php.net/package/amqp</p>
<p>必须安装sysvmsg扩展</p>
<p>ipcs进程间通信，客户端从数据源获取到任务分发到消息队列，work进行消息，再把消息任务id返回给客户端进行确认</p>

```
第一步：composer require php-lucky-queue/queue
```
```
第二步：php artisan vendor:publish --provider="PhpLuckyQueue\Queue\LuckyQueueProvider"
```
```
第三步：在.env文件添加如下配置
QUEUE_DRIVE=rabbitmq
QUEUE_REDIS_HOST=127.0.0.1
QUEUE_REDIS_DRIVE=redis
QUEUE_REDIS_PORT=6379
QUEUE_REDIS_DB=12
QUEUE_REDIS_PASSWORD=null
QUEUE_RABBITMQ_HOST=127.0.0.1
QUEUE_RABBITMQ_PORT=5672
QUEUE_RABBITMQ_LOGIN=
QUEUE_RABBITMQ_PASSWORD=
QUEUE_RABBITMQ_EXCHANGE=my_exchange
QUEUE_RABBITMQ_VHOST="/"
QUEUE_ROCKETMQ_HOST=''
QUEUE_ROCKETMQ_ACCESS_KEY=''
QUEUE_ROCKETMQ_SECRET_KEY=''
QUEUE_ROCKETMQ_INSTANCE_ID=''
QUEUE_ROCKETMQ_TOPIC=''
QUEUE_ROCKETMQ_GROUP_ID=''
QUEUE_ROCKETMQ_NUM_OF_MESSAGES=1
QUEUE_ROCKETMQ_WAIT_SECONDS=1
```
```
第四步：操作完以上步骤，在app/LuckyQueue目录下编写任务
```
### 队列特点：
```
1、支持平滑的重启队列重新读取配置文件
2、在默认驱动情况下，可以配置某个队列启动指定驱动[redis|rabbitmq]
3、默认队列任务每1个小时自动退出1次，防止内存溢出。
4、可以配置队列在执行指定任务次数自动退出，防止内存溢出
5、日志目录在storage/logs/queue，按每天创建目录，
   支持多种日志类型记录：debug|info|alert|notice|warning|critical|emergency|error
```
命令：
```
php artisan queue:lucky start              启动
```
```
php artisan queue:lucky start --daemon=1   守护进程启动
```
```
php artisan queue:lucky stop               停止
```
```
php artisan queue:lucky restart            重启
```

### Example
公共函数：luckyPush($queueName,$msg);
```php
<?php
for ($i = 0; $i < 10000; $i++) {
    $msg = [
        'message_order' => $i
    ];
    luckyPush('order', $msg);
    $msg = [
        'message_test' => $i
    ];
    luckyPush('test', $msg);
    $msg = [
        'message_log' => $i
    ];
    luckyPush('log',$msg);
}
?>
