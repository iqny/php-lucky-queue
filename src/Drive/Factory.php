<?php
namespace PhpLuckyQueue\Queue\Drive;
interface Factory{
    public static function createClient($cfg):DriveInterface;
}
