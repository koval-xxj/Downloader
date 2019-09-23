<?php

define('PATH_ROOT', dirname(__FILE__).'/');

require_once PATH_ROOT.'classes/Downloader.php';

try
{
    $dwnld = new Downloader('http://ukrposhta.ua/postindex/upload/postvpz.zip', 2);
    $dwnld->DownloadFile();
}
catch ( RuntimeException $e )
{
    var_dump('MESSAGE: '.$e->getMessage().', CODE: '.$e->getCode());
}
