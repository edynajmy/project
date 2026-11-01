<?php
declare(strict_types=1);
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path=''): string { return BASE_URL.ltrim($path,'/'); }

function venue_image_url(?string $path, ?string $venueName=null): ?string {
    if ($path) {
        if (preg_match('#^https?://#i', $path)) return $path;
        return url(ltrim($path, '/'));
    }

    // Fallback images for the current local-development venue cards.
    // These are real Berjaya Hills / The Chateau venue or resort images.
    $fallbacks = [
        'glass hall' => 'https://www.berjayahills.com/assets/img/La-Serre-Glasshouse-600x400.jpg',
        'outdoor garden' => 'https://www.berjayahills.org/img/japanese-village-garden.jpg',
        'japanese garden' => 'https://www.berjayahills.org/img/japanese-village-garden.jpg',
        'japanese village' => 'https://www.berjayahills.org/img/japanese-village-garden.jpg',
        'the chateau ballroom' => 'https://b2b.travelerts.com.tw/eWeb_ts/IMGDB/000112/000335/00005701.JPG',
        'the chateau spa & wellness resort' => 'https://b2b.travelerts.com.tw/eWeb_ts/IMGDB/000112/000335/00005701.JPG',
        'the chateau' => 'https://b2b.travelerts.com.tw/eWeb_ts/IMGDB/000112/000335/00005701.JPG',
        'conference room a' => 'https://www.berjayahills.com/assets/img/Le-Chevalier-600x400.jpg',
        'function room' => 'https://www.berjayahills.com/assets/img/Le-Chevalier-600x400.jpg',

        // Berjaya Hills / Colmar Tropicale location images.
        'colmar tropicale resort' => 'https://1.bp.blogspot.com/-WBHVo4mMPXs/XmHzsOQfFUI/AAAAAAAAOGk/TUWsT9gCNNUrhlBVYNov9vHA-4HnS4XxACEwYBhgL/s640/Berjaya%2BHills%2BResort%2B5.jpg',
        'berjaya hills golf & country club' => 'https://www.pahangtourism.org.my/images/2024/10/15/42.JPG',
        'rabbit park' => 'https://4.bp.blogspot.com/-a1RgQajHckE/UGhPQa7nuVI/AAAAAAAAATs/fxWIcl9JQ2Y/s1600/photo-61.jpg',
        'berjaya hills adventure park' => 'https://petualang.travelingyuk.com/uploads/2017/03/Flying-Fox.jpg',
        'colmar tropicale lookout point tower' => 'https://1.bp.blogspot.com/-WBHVo4mMPXs/XmHzsOQfFUI/AAAAAAAAOGk/TUWsT9gCNNUrhlBVYNov9vHA-4HnS4XxACEwYBhgL/s640/Berjaya%2BHills%2BResort%2B5.jpg',
        'colmar tropicale convention center' => 'https://www.berjayahills.com/assets/img/Le-Chevalier-600x400.jpg',
        'masjid berjaya hills resort' => 'https://photos.wikimapia.org/p/00/05/07/95/80_full.jpg',
        'botanical garden' => 'https://berjayahills.org/img/botanical-garden-walk.jpg',
        'berjaya hills horse trails' => 'https://1.bp.blogspot.com/-i2BNkIU6bMw/YMYNQaWfzjI/AAAAAAAAIFE/ECOIUQs-9Z8jSOFUCSvt76VsbjYytdxHQCLcBGAsYHQ/s600/Berjaya-Hills-Leisure-Horse-Riding_1.jpg',
        'coniferous forest' => 'https://helengray.net/_Media/img_0963_med.jpeg',
        '9d motion adventure' => 'https://1.bp.blogspot.com/-WBHVo4mMPXs/XmHzsOQfFUI/AAAAAAAAOGk/TUWsT9gCNNUrhlBVYNov9vHA-4HnS4XxACEwYBhgL/s640/Berjaya%2BHills%2BResort%2B5.jpg',
    ];

    $key = strtolower(trim((string)$venueName));
    return $fallbacks[$key] ?? null;
}
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(?string $token): void { if(!$token || !hash_equals($_SESSION['csrf']??'', $token)) { http_response_code(419); exit('Invalid CSRF token.'); } }
function flash(string $type,string $message): void { $_SESSION['flash'][]=[$type,$message]; }
function get_flashes(): array { $f=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $f; }
function current_user(): ?array { if(empty($_SESSION['user_id'])) return null; static $u; if($u!==null) return $u; $s=db()->prepare("SELECT u.*, GROUP_CONCAT(r.name) roles FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.id=? GROUP BY u.id"); $s->execute([$_SESSION['user_id']]); return $u=$s->fetch() ?: null; }
function login_user(int $id): void { session_regenerate_id(true); $_SESSION['user_id']=$id; }
function logout_user(): void { $_SESSION=[]; if(ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']); } session_destroy(); }
function require_login(): array { $u=current_user(); if(!$u) redirect('public/auth/login.php'); return $u; }
function has_role(string ...$roles): bool { $u=current_user(); if(!$u) return false; $mine=explode(',',$u['roles']??''); return (bool)array_intersect($roles,$mine); }
function require_role(string ...$roles): array { $u=require_login(); if(!has_role(...$roles)) { http_response_code(403); require __DIR__.'/403.php'; exit; } return $u; }
function audit(string $action,string $entityType,?int $entityId=null,?array $old=null,?array $new=null): void { $u=current_user(); $s=db()->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,old_data,new_data,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)'); $s->execute([$u['id']??null,$action,$entityType,$entityId,$old?json_encode($old):null,$new?json_encode($new):null,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]); }
