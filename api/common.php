<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
function config(): array {
  $path=__DIR__.'/../private/config.php';
  if (!is_file($path)) return [];
  $v=require $path;
  return is_array($v)?$v:[];
}
function ready(array $cfg):bool {return !empty($cfg['host_in_russia_confirmed']) && !empty($cfg['max_bot_token']) && !empty($cfg['max_chat_id']) && extension_loaded('curl') && extension_loaded('pdo_sqlite');}
function jsonout(int $code,array $value):never {http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
