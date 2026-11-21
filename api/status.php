<?php
declare(strict_types=1);
require __DIR__.'/common.php';
if($_SERVER['REQUEST_METHOD']!=='GET')jsonout(405,['ok'=>false]);
$cfg=config();$url=(string)($cfg['max_contact_url']??'');
if($url && (!str_starts_with($url,'https://max.ru/') || preg_match('~["<>]~',$url))) $url='';
jsonout(200,['ready'=>ready($cfg),'max_contact_url'=>$url]);
