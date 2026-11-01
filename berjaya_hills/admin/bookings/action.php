<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u=require_role('Administrator','Management','Event');
verify_csrf($_POST['csrf']??'');
$id=(int)$_POST['id'];
$action=$_POST['action']??'';
$reason=trim($_POST['reason']??'');
$pdo=db();
$s=$pdo->prepare('SELECT * FROM vw_booking_summary WHERE id=?');
$s->execute([$id]);
$b=$s->fetch();
if(!$b) exit('Not found');
if(!in_array($action,['approve','reject','hold'],true)) redirect('admin/bookings/index.php');
try{
  $pdo->beginTransaction();
  if($action==='approve'){
    $q=$pdo->prepare("SELECT COUNT(*) FROM event_venues ev JOIN events e ON e.id=ev.event_id JOIN bookings b2 ON b2.event_id=e.id WHERE ev.venue_id=(SELECT venue_id FROM event_venues WHERE event_id=? LIMIT 1) AND b2.id<>? AND e.status IN ('pending','confirmed') AND e.start_at < ? AND e.end_at > ?");
    $q->execute([$b['event_id']??0,$id,$b['end_at'],$b['start_at']]);
    $new='confirmed'; $note='Booking approved.';
  } elseif($action==='hold') {
    if($reason==='') throw new RuntimeException('Please provide a clarification note.');
    $new='pending_info'; $note=$reason;
  } else {
    if($reason==='') throw new RuntimeException('Please provide a rejection reason.');
    $new='rejected'; $note=$reason;
  }
  $pdo->prepare('UPDATE bookings SET status=?,reviewed_at=NOW(),reviewed_by=?,rejection_reason=? WHERE id=?')->execute([$new,$u['id'],$action==='reject'?$reason:null,$id]);
  $eventStatus = $action==='approve' ? 'confirmed' : ($action==='reject' ? 'rejected' : 'pending');
  $pdo->prepare('UPDATE events SET status=? WHERE id=?')->execute([$eventStatus,$b['event_id']]);
  $pdo->prepare('INSERT INTO booking_status_history(booking_id,new_status,changed_by,note) VALUES(?,?,?,?)')->execute([$id,$new,$u['id'],$note]);
  $title = $action==='approve' ? 'Booking approved' : ($action==='reject' ? 'Booking rejected' : 'Booking needs clarification');
  $message = $action==='approve' ? 'Your booking '.$b['booking_ref'].' has been approved.' : ($action==='reject' ? 'Your booking '.$b['booking_ref'].' was rejected. Reason: '.$reason : 'Your booking '.$b['booking_ref'].' is on hold. Please review the clarification request: '.$reason);
  $pdo->prepare('INSERT INTO notifications(user_id,type,title,message,link_url) VALUES(?,?,?,?,?)')->execute([$b['user_id'],'booking',$title,$message,url('user/bookings/view.php?id='.$id)]);
  audit('booking_'.$action,'booking',$id);
  $pdo->commit();
  flash('success','Booking updated.');
  redirect('admin/bookings/view.php?id='.$id);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  flash('error',$e->getMessage());
  redirect('admin/bookings/view.php?id='.$id);
}
