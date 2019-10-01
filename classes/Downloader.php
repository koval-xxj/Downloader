<?php

class Downloader
{
    // Attempts to create a stream
    const CONN_ATTEMTS_NUM = 3;

    private $downloadPath;
    private $logPath;

    private $ip;
    private $host;
    private $port;
    private $path;

    private $streamsNum;

    private $fileInfo = [];
    private $bar = null;

    public function __construct($url, $streamsNum = 4)
    {
        ini_set('memory_limit', '128M'); // для теста, удалить

        if ( empty($url) || !$parsed_url = parse_url($url) )
            throw new RuntimeException("The param url must not be empty");

        if ( !defined('PATH_ROOT') )
            throw new RuntimeException("The constant 'PATH_ROOT' is not defined");

        $this->downloadPath = PATH_ROOT.'downloads/';
        $this->logPath = PATH_ROOT.'log/';

        if ( !file_exists($this->downloadPath) && !mkdir($this->downloadPath) )
            throw new RuntimeException("Unable to create the directory {$this->downloadPath}");

        if ( !file_exists($this->logPath) && !mkdir($this->logPath) )
            throw new RuntimeException("Unable to create the directory {$this->logPath}");

        $this->ip = gethostbyname($parsed_url['host']);
        $this->host = $parsed_url['host'];
        $this->port = getservbyname($parsed_url['scheme'], 'tcp');
        $this->path = $parsed_url['path'];

        $this->fileInfo = $this->GetFileData($url);
        $this->streamsNum = is_integer($streamsNum) && $streamsNum > 1 && $this->fileInfo['acceptRanges'] ? $streamsNum : 1;
        $this->bar = $this->fileInfo['length'] ? new ProgressBar($this->fileInfo['length']) : null;
    }

    public function DownloadFile()
    {
        $streams_r = $streams = $streams_data = [];
        $pieces = $this->CountDownloadPiecesNum();

        $dwld_file = "{$this->downloadPath}.download.file.{$this->fileInfo['type']}";

        $this->Log('*** fileInfo ***', 'download');
        $this->Log($this->fileInfo, 'download');
        $this->Log('*** pieces ***', 'download');
        $this->Log($pieces, 'download');

        if ( ($file = fopen($dwld_file, 'w')) === false )
            throw new RuntimeException("Error to open the file {$dwld_file}");

        $this->bar->interupt('Creating streams');

        $downloaded = 0;
        while ( true )
        {
            $num = count($streams);

            if ( $streams_data )
            {
                $min = min(array_keys($streams_data));
                foreach ( $streams_data as $sid => $d )
                    if ( $d['isDone'] && $sid == $min )
                    {
                        $this->Log("{$sid} - data", 'min2');
                        fwrite($file, $d['data']);
                        unset($streams_data[$sid]);
                    }
            }

            if ( $num < $this->streamsNum && count($streams_r) < $this->streamsNum && $pieces['pieces'] )
            {
                $new_stream = $this->CreateStream();
                $id = $num+1;
                $streams[$id] = $new_stream;

                $streams_data[$id]['skipped_headers'] = false;
                $streams_data[$id]['length'] = 0;
                $streams_data[$id]['data'] = '';
                $streams_data[$id]['isDone'] = false;

                $to = $pieces['size_per_stream'] * $id;
                $streams_data[$id]['from'] = $pieces['size_per_stream'] * ($id - 1);
                $streams_data[$id]['to'] = $to > $this->fileInfo['length'] ? $this->fileInfo['length'] : $to;

                if ( $streams_data[$id]['from'] ) $streams_data[$id]['from']++;

                $streams_data[$id]['qty'] = $streams_data[$id]['to'] - $streams_data[$id]['from'];
                if ( $streams_data[$id]['to'] != $this->fileInfo['length'] )
                    $streams_data[$id]['qty']++;

                $this->Log('*** streams_size ***', 'download');
                $this->Log($streams_data, 'download');

                $pieces['pieces']--;
                continue;
            }
            elseif ( !$num && !count($streams_r) && !count($streams_data) )
            {
                fclose($file);
                break;
            }

            $st_read = $streams_r;
            $st_write = $streams;
            $e = null;

            if ( !$st_read && !$st_write ) continue;

            stream_select($st_read, $st_write, $e, 2, 1);

            foreach ( $st_write as $sid => $w )
            {
                $header = "GET {$this->path} HTTP/1.1\r\n";
                $header .= "Host: {$this->host}\r\n";
                if ( $this->streamsNum > 1 ) $header .= "Range: bytes=".$streams_data[$sid]['from']."-".$streams_data[$sid]['to']."\r\n";
                $header .= "Accept: */*\r\n\r\n";

                $this->Log('*** header ***', 'download');
                $this->Log($header, 'download');

                fwrite($w, $header);
                $streams_r[$sid] = $streams[$sid];
                unset($streams[$sid]);
            }

            foreach ( $st_read as $sid => $r )
            {
                $row = fread($r, 8192);

                if ( !$streams_data[$sid]['skipped_headers'] )
                {
                    $streams_data[$sid]['data'] .= $row;

                    if ( ( $pos = strpos($streams_data[$sid]['data'], "\r\n\r\n") ) !== false )
                    {
                        $this->Log($streams_data[$sid], "stream_{$sid}");

                        $streams_data[$sid]['data'] = substr($streams_data[$sid]['data'], $pos+4, strlen($streams_data[$sid]['data']));
                        $length = strlen($streams_data[$sid]['data']);
                        $streams_data[$sid]['length'] += $length;
                        $downloaded += $length;
                        $streams_data[$sid]['skipped_headers'] = true;
                    }

                    continue;
                }
                else
                {
                    $length = strlen($row);
                    $streams_data[$sid]['length'] += $length;
                    $downloaded += $length;
                }

                // Записываю данные из потока в очереди
                if ( $sid == min(array_keys($streams_r)) )
                {
                    if ( $streams_data[$sid]['data'] )
                    {
                        fwrite($file, $streams_data[$sid]['data']);
                        $streams_data[$sid]['data'] = '';
                    }
                    $this->Log($sid, 'min2');
                    fwrite($file, $row);
                }
                else $streams_data[$sid]['data'] .= $row;

                // var_dump("{$sid}|{$streams_data[$sid]['qty']}|{$streams_data[$sid]['length']}");
                if ( $this->bar ) $this->bar->update($downloaded);

                if ( feof($r) || $streams_data[$sid]['qty'] == $streams_data[$sid]['length'] )
                {
                    fclose($r);

                    // var_dump('FEOF');
                    // $this->bar->interupt("Downloaded {$downloaded}");
                    $this->Log("*** Data ***", 'download');
                    $this->Log("{$sid} - {$streams_data[$sid]['length']}", 'download');
                    $this->Log($downloaded);

                    // Eсли поток не скачал указанное к-во байт
                    // if ( $streams_data[$sid]['qty'] > $streams_data[$sid]['length'] )
                    // {
                    //     // открыть новый поток и докачать нужное к-во байт
                    //     $streams[$sid] = $this->CreateStream();
                    //     $streams_data[$sid]['skipped_headers'] = false;

                    //     $streams_data[$sid]['from'] = $streams_data[$sid]['from'] + $streams_data[$sid]['length'];
                    //     $streams_data[$id]['qty'] = $streams_data[$sid]['to'] - $streams_data[$id]['from'];
                    //     $streams_data[$sid] = ['length' => 0, 'data' => ''];
                    // }

                    $streams_data[$sid]['isDone'] = true;
                    if ( $streams_data[$sid]['data'] == '' ) unset($streams_data[$sid]);
                    unset($streams_r[$sid]);
                }
            }
        }

        $file = str_replace('.download.', '', $dwld_file);
        rename($dwld_file, $file);

        echo "\nThe file downloading is finished.\nThe file path: {$file}\n";
    }

    private function CreateStream()
    {
        $i = 0;
        while ( $i < self::CONN_ATTEMTS_NUM )
        {
            if ( !$fp = stream_socket_client("tcp://{$this->ip}:{$this->port}", $err_num, $err_msg, 30) )
            {
                $i++;
                continue;
            }

            stream_set_blocking($fp, false);
            stream_set_timeout($fp, 1);
            stream_set_read_buffer($fp, 8192);
            stream_set_write_buffer($fp, 8192);

            return $fp;
        }

        throw new RuntimeException("Error to create stream: {$err_msg}", $err_num);
    }

    private function GetFileData($url)
    {
        if ( !$headers = get_headers($url, true) ) throw new RuntimeException("Error to get headers by the url: {$headers}");
        if ( empty($headers['Content-Type']) ) throw new RuntimeException('Error to identify the file type');

        $file_length = !empty($headers['Content-Length']) ? intval($headers['Content-Length']) : 0;

        return [
            'type' => $this->GetFileExpByMime($headers['Content-Type']),
            'length' => $file_length,
            'acceptRanges' => !empty($headers['Accept-Ranges']) && $headers['Accept-Ranges'] != 'none' && $file_length > 0 ? true : false
        ];
    }

    private function GetFileExpByMime($mime)
    {
        $types = [
            'application/postscript' => 'ai',
            'audio/aiff' => 'aiff',
            'audio/x-aiff' => 'aiff',
            'application/x-navi-animation' => 'ani',
            'application/x-nokia-9000-communicator-add-on-software' => 'aos',
            'application/mime' => 'aps',
            'application/octet-stream' => 'exe',
            'application/arj' => 'arj',
            'image/x-jg' => 'art',
            'video/x-ms-asf' => 'asf',
            'text/x-asm' => 'asm',
            'text/asp' => 'asp',
            'application/x-mplayer2' => 'asx',
            'video/x-ms-asf-plugin' => 'asx',
            'audio/basic' => 'au',
            'audio/x-au' => 'au',
            'application/x-troff-msvideo' => 'avi',
            'video/avi' => 'avi',
            'video/msvideo' => 'avi',
            'video/x-msvideo' => 'avi',
            'application/mac-binary' => 'bin',
            'application/macbinary' => 'bin',
            'application/x-binary' => 'bin',
            'application/x-macbinary' => 'bin',
            'image/bmp' => 'bmp',
            'image/x-windows-bmp' => 'bmp',
            'application/book' => 'book',
            'text/x-c' => 'c',
            'application/clariscad' => 'ccad',
            'application/java' => 'class',
            'application/java-byte-code' => 'class',
            'application/x-java-class' => 'class',
            'text/plain' => 'txt',
            'application/mac-compactpro' => 'cpt',
            'application/x-compactpro' => 'cpt',
            'application/x-cpt' => 'cpt',
            'application/x-pointplus' => 'css',
            'text/css' => 'css',
            'application/x-director' => 'dcr',
            'video/x-dv' => 'dif',
            'video/dl' => 'dl',
            'video/x-dl' => 'dl',
            'application/msword' => 'doc',
            'application/drafting' => 'drw',
            'application/x-dvi' => 'dvi',
            'application/acad' => 'dwg',
            'image/vnd.dwg' => 'dwg',
            'image/x-dwg' => 'dwg',
            'application/dxf' => 'dxf',
            'image/gif' => 'gif',
            'application/x-compressed' => 'gz',
            'application/x-gzip' => 'gz',
            'multipart/x-gzip' => 'gzip',
            'text/x-h' => 'h',
            'application/hlp' => 'hlp',
            'application/x-helpfile' => 'hlp',
            'application/x-winhelp' => 'hlp',
            'text/x-component' => 'htc',
            'text/html' => 'html',
            'text/webviewhtml' => 'htt',
            'x-conference/x-cooltalk' => 'ice',
            'image/x-icon' => 'ico',
            'application/inf' => 'inf',
            'audio/x-jam' => 'jam',
            'text/x-java-source' => 'java',
            'application/x-java-commerce' => 'jcm',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/x-jps' => 'jps',
            'application/x-javascript' => 'js',
            'application/javascript' => 'js',
            'application/ecmascript' => 'js',
            'text/javascript' => 'js',
            'text/ecmascript' => 'js',
            'application/x-latex' => 'latex',
            'application/lha' => 'lha',
            'application/x-lha' => 'lha',
            'application/x-lisp' => 'lsp',
            'text/x-script.lisp' => 'lsp',
            'application/x-lzh' => 'lzh',
            'application/lzx' => 'lzx',
            'application/x-lzx' => 'lzx',
            'audio/x-mpequrl' => 'm3u',
            'application/x-troff-man' => 'man',
            'application/x-midi' => 'midi',
            'audio/midi' => 'midi',
            'audio/x-mid' => 'midi',
            'audio/x-midi' => 'midi',
            'music/crescendo' => 'midi',
            'x-music/x-midi' => 'midi',
            'audio/mod' => 'mod',
            'audio/x-mod' => 'mod',
            'video/quicktime' => 'mov',
            'video/x-sgi-movie' => 'movie',
            'audio/mpeg' => 'mp2',
            'audio/x-mpeg' => 'mp2',
            'video/x-mpeq2a' => 'mp2',
            'audio/mpeg3' => 'mp3',
            'audio/x-mpeg-3' => 'mp3',
            'video/mpeg' => 'mp3',
            'video/x-mpeg' => 'mp3',
            'video/mp4' => 'mp4',
            'text/pascal' => 'pas',
            'application/vnd.hp-pcl' => 'pcl',
            'application/x-pcl' => 'pcl',
            'image/x-pict' => 'pct',
            'image/x-pcx' => 'pcx',
            'application/pdf' => 'pdf',
            'image/pict' => 'pict',
            'text/x-script.perl' => 'pl',
            'image/x-xpixmap' => 'pm',
            'text/x-script.perl-module' => 'pm',
            'application/x-pagemaker' => 'pm5',
            'image/png' => 'png',
            'application/mspowerpoint' => 'ppt',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/powerpoint' => 'ppt',
            'application/x-mspowerpoint' => 'ppt',
            'text/x-script.phyton' => 'py',
            'applicaiton/x-bytecode.python' => 'pyc',
            'image/x-quicktime' => 'qtif',
            'audio/x-pn-realaudio' => 'rm',
            'audio/x-realaudio' => 'ra',
            'application/vnd.rn-realmedia' => 'rm',
            'audio/x-pn-realaudio-plugin' => 'rpm',
            'application/rtf' => 'rtf',
            'application/x-rtf' => 'rtf',
            'text/richtext' => 'rtx',
            'video/vnd.rn-realvideo' => 'rv',
            'text/sgml' => 'sgml',
            'text/x-sgml' => 'sgml',
            'application/x-bsh' => 'sh',
            'application/x-sh' => 'sh',
            'application/x-shar' => 'sh',
            'text/x-script.sh' => 'sh',
            'text/x-server-parsed-html' => 'shtml',
            'application/x-tar' => 'tar',
            'application/x-tcl' => 'tcl',
            'text/x-script.tcl' => 'tcl',
            'application/plain' => 'text',
            'application/gnutar' => 'tgz',
            'image/tiff' => 'tiff',
            'image/x-tiff' => 'tiff',
            'text/uri-list' => 'uri',
            'application/x-cdlink' => 'vcd',
            'application/vocaltec-media-desc' => 'vmd',
            'application/x-vrml' => 'vrml',
            'model/vrml' => 'vrml',
            'x-world/x-vrml' => 'vrml',
            'application/x-visio' => 'vsw',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'windows/metafile' => 'wmf',
            'application/excel' => 'xls',
            'application/vnd.ms-excel' => 'xls',
            'application/x-excel' => 'xls',
            'application/x-msexcel' => 'xls',
            'audio/xm' => 'xm',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'application/x-compress' => 'z',
            'application/x-zip-compressed' => 'zip',
            'application/zip' => 'zip',
            'multipart/x-zip' => 'zip',
        ];

        return isset($types[$mime]) ? $types[$mime] : 'txt';
    }

    private function CountDownloadPiecesNum()
    {
        if ( $this->streamsNum > 1 )
        {
            $memory_limit = $this->GetMemoryLimit();
            if ( $memory_limit == -1 || $memory_limit > $this->fileInfo['length'] )
                return ['size_per_stream' => intval(ceil($this->fileInfo['length']/$this->streamsNum)), 'pieces' => $this->streamsNum];

            $pieces = ceil($this->fileInfo['length']/$memory_limit);
            return ['size_per_stream' => intval(ceil($this->fileInfo['length']/$pieces)), 'pieces' => $pieces];
        }
        else
            return ['size_per_stream' => $this->fileInfo['length'], 'pieces' => 1];
    }

    public function Log($data, $filename = 'debug')
    {
        if ( empty($filename) || !is_dir($this->logPath) ) return false;
        $filename = $this->logPath.$filename.".log";
        if ( !is_string($data) ) $data = var_export($data, true);
        if ( !$fptr = fopen($filename, "a") ) return false;
        if ( !flock($fptr, LOCK_EX) )
        {
            fclose($fptr);
            return false;
        }
        if ( (fileperms($filename) & 0777) != 0666 ) chmod($filename, 0666);
        $date = strftime("%Y-%m-%d %H:%M:%S ", time());
        fputs($fptr, $date.str_replace("\n", "\n$date", $data)."\n");
        flock($fptr, LOCK_UN);
        fclose($fptr);
        return true;
    }

    public function GetMemoryLimit()
    {
        $reserve = 20971520;

        $limit = ini_get('memory_limit');
        if ( $limit == -1 ) return $limit;

        $last = strtolower($limit[strlen($limit)-1]);
        $limit = intval($limit);

        switch ( $last )
        {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }

        if ( ($limit - $reserve) < 0 )
            throw new RuntimeException("Error: Out of the memory");

        return $limit;
    }

}
