<?php
declare(strict_types=1);
require __DIR__.'/common.php';
if($_SERVER['REQUEST_METHOD']!=='POST')jsonout(405,['ok'=>false,'message'=>'Метод недоступен.']);
$cfg=config();
if(!ready($cfg))jsonout(503,['ok'=>false,'message'=>'Приём заявок пока не подключён. Позвоните по телефону.']);
$origin=$_SERVER['HTTP_ORIGIN']??'';
if($origin && !in_array($origin,['https://bioservice58.ru','https://www.bioservice58.ru'],true))jsonout(403,['ok'=>false,'message'=>'Источник запроса отклонён.']);
$ref=$_SERVER['HTTP_REFERER']??'';
if(!$origin && $ref && !preg_match('~^https://(www\.)?bioservice58\.ru/~',$ref))jsonout(403,['ok'=>false,'message'=>'Источник отклонён.']);
if(!empty($_POST['website']))jsonout(200,['ok'=>true]);
foreach(['name','phone','service','comment'] as $field){if(isset($_POST[$field]) && !is_string($_POST[$field]))jsonout(400,['ok'=>false,'message'=>'Неверные данные формы.']);}
$name=trim((string)($_POST['name']??''));$phone=trim((string)($_POST['phone']??''));$service=trim((string)($_POST['service']??''));$comment=trim((string)($_POST['comment']??''));
$whitelist=['Биотуалеты', 'Грязезащитные ковры', 'Химчистка мебели', 'Клининг', 'Услуги илососа', 'Откачка септиков', 'Устранение засоров', 'Для бизнеса'];
if(!in_array($service,$whitelist,true) || mb_strlen($name)>80 || mb_strlen($comment)>700 || mb_strlen($name)<2 || mb_strlen($phone)>24 || !preg_match('/^[+0-9\s()\-]{10,24}$/u',$phone) || ($_POST['personal_consent']??'')!=='1')jsonout(422,['ok'=>false,'message'=>'Проверьте имя, телефон, услугу и согласие.']);
// Ограничение частоты на сеанс. Без записи IP в журналы приложения.
ini_set('session.use_strict_mode','1');ini_set('session.cookie_httponly','1');ini_set('session.cookie_samesite','Lax');
if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');
session_start();
if(time()-(int)($_SESSION['last_lead']??0)<45)jsonout(429,['ok'=>false,'message'=>'Повторите отправку немного позднее.']);
// Первичная запись заявки в SQLite у российского хостинг-провайдера: базу невозможно скачать по HTTP.
try{
 $db=new PDO('sqlite:'.__DIR__.'/../private/leads.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('CREATE TABLE IF NOT EXISTS leads (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, name TEXT NOT NULL, phone TEXT NOT NULL, service TEXT NOT NULL, comment TEXT, consent_at TEXT NOT NULL, consent_version TEXT NOT NULL, delivery_status TEXT NOT NULL DEFAULT "pending")');
 $stmt=$db->prepare('INSERT INTO leads (created_at,name,phone,service,comment,consent_at,consent_version,delivery_status) VALUES (datetime("now"),?,?,?,?,datetime("now"),?,"pending")');
 $stmt->execute([$name,$phone,$service,$comment,'2026-11-20']);$id=(int)$db->lastInsertId();
}catch(Throwable $ex){error_log('KOM58 database error');jsonout(503,['ok'=>false,'message'=>'Не удалось сохранить заявку. Позвоните нам.']);}
$text="Новая заявка с bioservice58.ru\n№ {$id}\nУслуга: {$service}\nИмя: {$name}\nТелефон: {$phone}\nКомментарий: {$comment}";
$endpoint='https://platform-api2.max.ru/messages?chat_id='.rawurlencode((string)$cfg['max_chat_id']);
$ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: '.$cfg['max_bot_token'],'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['text'=>$text],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_SSL_VERIFYPEER=>true]);
$res=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if($res===false||$status<200||$status>=300){error_log('KOM58 MAX delivery failed status='.$status.' lead_id='.$id);jsonout(503,['ok'=>false,'message'=>'Заявка сохранена, но отправка в MAX не подтвердилась. Пожалуйста, позвоните нам.']);}
$db->prepare('UPDATE leads SET delivery_status="delivered" WHERE id=?')->execute([$id]);$_SESSION['last_lead']=time();jsonout(200,['ok'=>true]);
