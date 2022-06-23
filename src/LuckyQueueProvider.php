<?php

namespace PhpLuckyQueue\Queue;

use Illuminate\Support\ServiceProvider;

class LuckyQueueProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

        // 发布配置文件
        $this->publishes([
            __DIR__ . '/config/lucky.php' => config_path('lucky.php'),
            __DIR__ . '/Commands/lucky.php' => app_path('Console/Commands/lucky.php'),
            __DIR__ . '/Queue/TestQueue.php' => app_path('LuckyQueue/TestQueue.php'),
            __DIR__ . '/Queue/OrderQueue.php' => app_path('LuckyQueue/OrderQueue.php'),
            __DIR__ . '/Queue/LogQueue.php' => app_path('LuckyQueue/LogQueue.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('luckyQueue', function ($app) {
            return new LuckyQueue($app['config']);
        });
    }
}
