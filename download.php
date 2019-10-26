<?php

define('PATH_ROOT', dirname(__FILE__).'/');
define('DEFAULT_STREAMS_NUM', 4);

spl_autoload_register(function ($class) {
    $file = PATH_ROOT."classes/{$class}.php";
    if ( file_exists($file) ) require_once $file;
});

$s_num = DEFAULT_STREAMS_NUM;

try
{
    $uID = array_search('-u', $argv);
    if ( !$uID || empty($argv[$uID + 1]) || !$url = trim($argv[$uID + 1]) )
        throw new InvalidArgumentException("The param -u is empty");

    if ( $sID = array_search('-s', $argv) )
    {
        $sID++;
        if ( !empty($argv[$sID]) && !is_numeric($argv[$sID]) )
            throw new InvalidArgumentException("The param -s must be numeric");
        elseif ( $argv[$sID] < 0 )
            throw new InvalidArgumentException("The param -s must be more than zero");

        $s_num = !empty($argv[$sID]) ? intval($argv[$sID]) : $s_num;
    }
}
catch ( InvalidArgumentException $e )
{
    Notifier::ShowError($e->getMessage());
    exit;
}

try
{
    $dwnld = new Downloader($url, $s_num);
    $dwnld->DownloadFile();
}
catch ( DownloaderException $e )
{
    Notifier::ShowError('Error: '.$e->getMessage());
}
catch ( Exception $e )
{
    Notifier::ShowError('Something went wrong');
}
