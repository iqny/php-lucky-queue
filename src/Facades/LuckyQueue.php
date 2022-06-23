<?php

namespace PhpLuckyQueue\Queue\Facades;

use Illuminate\Support\Facades\Facade;

class LuckyQueue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'luckyQueue';
    }
}
