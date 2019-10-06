<?php

// ini_set('memory_limit', '128M'); // для теста, удалить

define('PATH_ROOT', dirname(__FILE__).'/');
define('DEFAULT_STREAMS_NUM', 4);

spl_autoload_register(function ($class) {
    $file = PATH_ROOT."classes/{$class}.php";
    if ( file_exists($file) ) require_once $file;
});

$uID = array_search('-u', $argv);
if ( !$uID || empty($argv[$uID + 1]) || !$url = trim($argv[$uID + 1]) )
{
    Notifier::ShowError('The param -u is empty');
    exit;
}

$s_num = DEFAULT_STREAMS_NUM;

if ( $sID = array_search('-s', $argv) )
{
    $sID++;
    if ( !empty($argv[$sID]) && !is_numeric($argv[$sID]) )
    {
        Notifier::ShowError('The param -s must be numeric');
        exit;
    }

    $s_num = !empty($argv[$sID]) ? intval($argv[$sID]) : $s_num;
}

// http://ukrposhta.ua/postindex/upload/postvpz.zip

// https://git.kernel.org/torvalds/t/linux-5.3-rc6.tar.gz

// http://vs1.cdnlast.com/s/b241aebc8976d5b86397583771bb5ed5627882/hd_01/Dark.Phoenix.2019.BDRip.1080p.ukr_480.mp4

try
{
    $dwnld = new Downloader($url, $s_num);
    $dwnld->DownloadFile();
}
catch ( RuntimeException $e )
{
    Notifier::ShowError('Error: '.$e->getMessage());
}
