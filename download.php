<?php

define('PATH_ROOT', dirname(__FILE__).'/');

spl_autoload_register(function ($class) {
    $file = PATH_ROOT."classes/{$class}.php";
    if ( file_exists($file) ) require_once $file;
});

try
{
    $dwnld = new Downloader('http://ukrposhta.ua/postindex/upload/postvpz.zip', 2);
    $dwnld->DownloadFile();
}
catch ( RuntimeException $e )
{
    Notifier::ShowError('Error: '.$e->getMessage());
}
