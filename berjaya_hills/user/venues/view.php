<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u=require_login();
$userUnreadNotifications=0;
try{$n=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$n->execute([$u['id']]);$userUnreadNotifications=(int)$n->fetchColumn();}catch(Throwable $e){}
require __DIR__.'/../../public/venues/view.php';
