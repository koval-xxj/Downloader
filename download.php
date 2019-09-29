<?php

define('PATH_ROOT', dirname(__FILE__).'/');

spl_autoload_register(function ($class) {
    $file = PATH_ROOT."classes/{$class}.php";
    if ( file_exists($file) ) require_once $file;
});

// Notifier::ShowNotify('message');
// Notifier::ShowError('Error');
// Notifier::ShowWarning('Warning');
// Notifier::ShowNotice('Notice');
// Notifier::ShowSuccess('Success');
// Notifier::ShowNotify('message');

// exit;

try
{
    $dwnld = new Downloader('http://ukrposhta.ua/postindex/upload/postvpz.zip', 2);
    $dwnld->DownloadFile();
}
catch ( RuntimeException $e )
{
    var_dump('MESSAGE: '.$e->getMessage().', CODE: '.$e->getCode());
}
