<?php
/* CORS: the Collectibles page may be served from GitHub Pages while this file
   keeps running here. No cookies are involved — only image data. */
$hosts = ['gret521x-coder.github.io', 'unaux.com', 'localhost', '127.0.0.1'];
$o = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($o !== '') {
    $h = parse_url($o, PHP_URL_HOST);
    foreach ($hosts as $x) {
        if (is_string($h) && ($h === $x || substr($h, -strlen('.'.$x)) === '.'.$x)) {
            header('Access-Control-Allow-Origin: ' . $o);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            break;
        }
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$d=__DIR__.'/collectibles';
if(!is_dir($d))@mkdir($d);
if(isset($_GET['l']))exit(json_encode(array_map('basename',array_reverse(glob("$d/*.jpg")))));
if(isset($_GET['v'])){$f="$d/".basename($_GET['v']);if(is_file($f)){header('Content-Type: image/jpeg');readfile($f);}exit;}
if(isset($_POST['del'])){@unlink("$d/".basename($_POST['del']));exit('ok');}
$b=base64_decode(preg_replace('#^data:image/\w+;base64,#','',$_POST['i']??''));
if($b&&@getimagesizefromstring($b)){file_put_contents("$d/".date('Ymd_His').'.jpg',$b);echo 'ok';}