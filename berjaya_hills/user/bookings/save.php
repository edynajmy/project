<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u=require_login();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('user/bookings/create.php');
verify_csrf($_POST['csrf']??'');

$title=trim($_POST['title']??'');
$start=$_POST['start_at']??trim(($_POST['start_date']??'').' '.($_POST['start_time']??'').':00');
$end=$_POST['end_at']??trim(($_POST['end_date']??'').' '.($_POST['end_time']??'').':00');
$venueRaw=$_POST['venue_id']??'';
$venue=($venueRaw==='other')?0:(int)$venueRaw;
$otherVenue=trim($_POST['other_venue_name']??'');
$action=$_POST['action']??'draft';
$draftId=(int)($_POST['draft_id']??0);
$pax=max(1,(int)($_POST['expected_pax']??0));

if(!$title||!$start||!$end||(!$venue && $otherVenue==='')){flash('error','Please complete the required event fields.');redirect($draftId?'user/bookings/create.php?draft_id='.$draftId:'user/bookings/create.php');}
$today=date('Y-m-d');
if(substr($start,0,10)<$today || substr($end,0,10)<$today){flash('error','Past dates are not available. Please choose today or a future date.');redirect($draftId?'user/bookings/create.php?draft_id='.$draftId:'user/bookings/create.php');}
if(strtotime($end)<=strtotime($start)){flash('error','End time must be after start time.');redirect($draftId?'user/bookings/create.php?draft_id='.$draftId:'user/bookings/create.php');}

$pdo=db();
try{
  $pdo->beginTransaction();
  if($venue){
    $q=$pdo->prepare("SELECT capacity, rate, status FROM venues WHERE id=? LIMIT 1");
    $q->execute([$venue]);
    $venueRow=$q->fetch();
    if(!$venueRow || $venueRow['status']!=='available') throw new RuntimeException('The selected venue is not currently available.');
    if($pax>(int)$venueRow['capacity']) throw new RuntimeException('Expected participants exceed the selected venue capacity of '.(int)$venueRow['capacity'].' pax.');

    $q=$pdo->prepare("SELECT COUNT(*) FROM event_venues ev JOIN events e ON e.id=ev.event_id JOIN bookings b ON b.event_id=e.id WHERE ev.venue_id=? AND e.status IN ('pending','confirmed') AND e.start_at < ? AND e.end_at > ?");
    $q->execute([$venue,$end,$start]);
    if((int)$q->fetchColumn()>0) throw new RuntimeException('The selected venue is not available for that time window.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM venue_maintenance WHERE venue_id=? AND status IN ('scheduled','active') AND starts_at < ? AND ends_at > ?");
    $q->execute([$venue,$end,$start]);
    if((int)$q->fetchColumn()>0) throw new RuntimeException('The selected venue has a maintenance period during that time.');
  }

  $status=$action==='submit'?'pending_review':'draft';
  $eventStatus=$status==='pending_review'?'pending':'draft';
  $category=(int)($_POST['category_id']??0)?:null;
  $notes=$_POST['notes']??null;
  $bid=0; $eid=0;

  if($draftId>0){
    $q=$pdo->prepare("SELECT b.id,b.event_id FROM bookings b JOIN events e ON e.id=b.event_id WHERE b.id=? AND b.user_id=? AND b.status='draft' LIMIT 1");
    $q->execute([$draftId,$u['id']]);
    $existing=$q->fetch();
    if(!$existing) throw new RuntimeException('This draft could not be found or is no longer editable.');
    $bid=(int)$existing['id']; $eid=(int)$existing['event_id'];

    $pdo->prepare('UPDATE events SET category_id=?,title=?,description=?,other_venue_name=?,start_at=?,end_at=?,expected_pax=?,status=? WHERE id=? AND owner_user_id=?')->execute([$category,$title,$_POST['description']??null,$otherVenue?:null,$start,$end,$pax,$eventStatus,$eid,$u['id']]);
    $pdo->prepare('DELETE FROM event_venues WHERE event_id=?')->execute([$eid]);
    if($venue) $pdo->prepare('INSERT INTO event_venues(event_id,venue_id) VALUES(?,?)')->execute([$eid,$venue]);
    $pdo->prepare('UPDATE bookings SET status=?,submitted_at=?,notes=? WHERE id=? AND user_id=?')->execute([$status,$status==='pending_review'?date('Y-m-d H:i:s'):null,$notes,$bid,$u['id']]);
    $pdo->prepare('DELETE FROM booking_rooms WHERE booking_id=?')->execute([$bid]);
    $pdo->prepare('DELETE FROM booking_facilities WHERE booking_id=?')->execute([$bid]);
    $pdo->prepare('DELETE FROM booking_food WHERE booking_id=?')->execute([$bid]);
    $pdo->prepare('DELETE FROM event_timeline_items WHERE event_id=?')->execute([$eid]);
  } else {
    $q=$pdo->prepare('INSERT INTO events(owner_user_id,category_id,title,description,other_venue_name,start_at,end_at,expected_pax,status,is_public) VALUES(?,?,?,?,?,?,?,?,?,0)');
    $q->execute([$u['id'],$category,$title,$_POST['description']??null,$otherVenue?:null,$start,$end,$pax,$eventStatus]);
    $eid=(int)$pdo->lastInsertId();
    if($venue) $pdo->prepare('INSERT INTO event_venues(event_id,venue_id) VALUES(?,?)')->execute([$eid,$venue]);
    $q=$pdo->prepare('INSERT INTO bookings(booking_ref,user_id,event_id,status,submitted_at,notes) VALUES(?,?,?,?,?,?)');
    $q->execute(['BHR-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),$u['id'],$eid,$status,$status==='pending_review'?date('Y-m-d H:i:s'):null,$notes]);
    $bid=(int)$pdo->lastInsertId();
  }

  $rt=(int)($_POST['room_type_id']??0); $rq=max(0,(int)($_POST['room_qty']??0));
  if($rt&&$rq)$pdo->prepare('INSERT INTO booking_rooms(booking_id,room_type_id,quantity,nights,rate) SELECT ?,id,?,?,nightly_rate FROM room_types WHERE id=?')->execute([$bid,$rq,(int)($_POST['nights']??1),$rt]);
  $facilityIds=$_POST['facility_ids']??[];
  $facilityQty=$_POST['facility_qty']??[];
  if(is_array($facilityIds)){
    $facilityInsert=$pdo->prepare('INSERT INTO booking_facilities(booking_id,facility_id,quantity,rate) SELECT ?,id,?,unit_rate FROM facilities WHERE id=?');
    foreach($facilityIds as $fidRaw){
      $fid=(int)$fidRaw; $fq=max(0,(int)($facilityQty[$fid]??0));
      if($fid&&$fq)$facilityInsert->execute([$bid,$fq,$fid]);
    }
  } else {
    // Backward compatibility with older draft/form payloads.
    $fid=(int)($_POST['facility_id']??0); $fq=max(0,(int)($_POST['facility_qty']??0));
    if($fid&&$fq)$pdo->prepare('INSERT INTO booking_facilities(booking_id,facility_id,quantity,rate) SELECT ?,id,?,unit_rate FROM facilities WHERE id=?')->execute([$bid,$fq,$fid]);
  }
  $mid=(int)($_POST['food_menu_id']??0); $fp=max(0,(int)($_POST['food_pax']??0));
  if($mid&&$fp)$pdo->prepare('INSERT INTO booking_food(booking_id,food_menu_id,pax,rate) SELECT ?,id,?,price_per_pax FROM food_menus WHERE id=?')->execute([$bid,$fp,$mid]);

  $timeline=trim($_POST['timeline_compiled']??'');
  if($timeline){
    $sort=0;
    foreach(preg_split('/\R/', $timeline) as $line){
      if(!preg_match('/^(\d{1,2}:\d{2})\s+—\s+(.+)$/u',$line,$m)) continue;
      $itemTime=date('Y-m-d',strtotime($start)).' '.$m[1].':00';
      $pdo->prepare('INSERT INTO event_timeline_items(event_id,item_time,title,description,sort_order) VALUES(?,?,?,?,?)')->execute([$eid,$itemTime,trim($m[2]),null,$sort++]);
    }
  }

  $total=(float)$venueRow['rate'];
  $q=$pdo->prepare('SELECT COALESCE(SUM(quantity*nights*rate),0) FROM booking_rooms WHERE booking_id=?');$q->execute([$bid]);$total+=(float)$q->fetchColumn();
  $q=$pdo->prepare('SELECT COALESCE(SUM(quantity*rate),0) FROM booking_facilities WHERE booking_id=?');$q->execute([$bid]);$total+=(float)$q->fetchColumn();
  $q=$pdo->prepare('SELECT COALESCE(SUM(pax*rate),0) FROM booking_food WHERE booking_id=?');$q->execute([$bid]);$total+=(float)$q->fetchColumn();
  $pdo->prepare('UPDATE bookings SET total_estimate=? WHERE id=?')->execute([$total,$bid]);

  $note=$status==='draft'?'Draft saved':'Booking submitted for management review';
  $pdo->prepare('INSERT INTO booking_status_history(booking_id,old_status,new_status,changed_by,note) VALUES(?,?,?,?,?)')->execute([$bid,$status==='draft'?'draft':'draft',$status,$u['id'],$note]);
  audit($status==='draft'?'booking_draft_saved':'booking_submitted','booking',$bid);
  $pdo->commit();
  flash('success',$status==='draft'?'Draft saved successfully.':'Booking submitted for management review.');
  redirect('user/bookings/view.php?id='.$bid);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  flash('error',$e->getMessage());
  redirect($draftId?'user/bookings/create.php?draft_id='.$draftId:'user/bookings/create.php');
}
