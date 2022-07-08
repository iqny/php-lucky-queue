<?php
return [
    'connect' => [
        'drive' => env('QUEUE_DRIVE', 'rabbitmq'),
    ],
    'counter'=>[
        'precisions'=>['5' => '5s','60' => '1m', '300' => '5m', '3600' => '1h', '18000' => '2h', '86400' => '24h', '172800' => '48h'],
        'sample_count'=>20,
    ],
    'redis' => [
        'drive' => env('QUEUE_REDIS_DRIVE', 'redis'),//predis
        'host' => env('QUEUE_REDIS_HOST', '127.0.0.1'),//127.0.0.1
        'port' => env('QUEUE_REDIS_PORT', '6379'),
        'password' => env('QUEUE_REDIS_PASSWORD', ''),
        'db' =>env('QUEUE_REDIS_DB',0)
    ],
    'rabbitmq' => [
        'host' => env('QUEUE_RABBITMQ_HOST', '127.0.0.1'),
        'port' => env('QUEUE_RABBITMQ_PORT', '5672'),
        'login' => env('QUEUE_RABBITMQ_LOGIN', ''),
        'password' => env('QUEUE_RABBITMQ_PASSWORD', ''),
        'exchange' => env('QUEUE_RABBITMQ_EXCHANGE', 'my_exchange'),//交换机名
        'vhost' => env('QUEUE_RABBITMQ_VHOST', '/'),//虚拟路径
    ],
    'rocketmq' => [
        'host' => env('QUEUE_ROCKETMQ_HOST', '127.0.0.1'),
        'access_key'=>env('QUEUE_ROCKETMQ_ACCESS_KEY',''),
        'secret_key'=>env('QUEUE_ROCKETMQ_SECRET_KEY',''),
        'num_of_messages'=>env('QUEUE_ROCKETMQ_NUM_OF_MESSAGES',1),//一次最多消费5条(最多可设置为16条)
        'wait_seconds'=>env('QUEUE_ROCKETMQ_WAIT_SECONDS',1)//长轮询时间1秒（最多可设置为30秒）
    ],
    'queue' => [
        [
            'queue_name' => 'test',
            'class' => 'App\LuckyQueue\TestQueue',
            'run' => true,
            'drive' => '',
            //如果驱动是rocketmq，以下需要配置
            'rocketmq'=>[
                'instance_id'=>'',
                'topic'=>'',
                'group_id'=>'',
            ],
            'worker_count' => 2,
            'max_exe_count' => 10000,
            'qos'=>20,
        ],
        [
            'queue_name' => 'order',
            'class' => 'App\LuckyQueue\OrderQueue',
            'run' => true,
            'drive' => '',
            //如果驱动是rocketmq，以下需要配置
            'rocketmq'=>[
                'instance_id'=>'',
                'topic'=>'',
                'group_id'=>'',
            ],
            'worker_count' => 2,
            'max_exe_count' => 10000,
            'qos'=>20,
        ],
        [
            'queue_name' => 'log',
            'class' => 'App\LuckyQueue\LogQueue',
            'run' => false,
            'drive' => 'redis',
            //如果驱动是rocketmq，以下需要配置
            'rocketmq'=>[
                'instance_id'=>'',
                'topic'=>'',
                'group_id'=>'',
            ],
            'worker_count' => 1,
            'max_exe_count' => 10000,
            'qos'=>20,
        ]
    ]
];
