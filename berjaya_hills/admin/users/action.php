<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator');
verify_csrf($_POST['csrf'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
if ($id < 1) { flash('error','Invalid user.'); redirect('admin/users/index.php'); }

if ($action === 'delete') {
    if ($id === (int)(current_user()['id'] ?? 0)) {
        flash('error','You cannot delete your own administrator account.');
        redirect('admin/users/index.php');
    }
    $check = db()->prepare("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.name='Administrator'");
    $check->execute([$id]);
    if ((int)$check->fetchColumn() > 0) {
        flash('error','Administrator accounts cannot be deleted from this screen.');
        redirect('admin/users/index.php');
    }
    try {
        $del = db()->prepare('DELETE FROM users WHERE id=?');
        $del->execute([$id]);
        flash('success', $del->rowCount() ? 'User account deleted.' : 'User account not found.');
        audit('user_deleted','user',$id);
    } catch (Throwable $e) {
        flash('error','Unable to delete this account because related records still depend on it. Remove or reassign those records first.');
    }
    redirect('admin/users/index.php');
}

$map = ['approve'=>'approved','reject'=>'rejected','status_pending'=>'pending'];
if (!isset($map[$action])) { flash('error','Invalid action.'); redirect('admin/users/index.php'); }
$status = $map[$action];
$reason = $status === 'rejected' ? 'Account access set to rejected by administrator.' : null;
$upd = db()->prepare('UPDATE users SET account_status=?, rejection_reason=? WHERE id=?');
$upd->execute([$status,$reason,$id]);
$title = 'Account status updated';
$msg = 'Your account status is now '.ucfirst($status).'.';
db()->prepare('INSERT INTO notifications(user_id,type,title,message) VALUES(?,?,?,?)')->execute([$id,'account',$title,$msg]);
audit('user_status_changed','user',$id);
flash('success','User status updated.');
redirect('admin/users/index.php');
