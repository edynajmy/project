<?php
require_once __DIR__.'/../includes/bootstrap.php';

$pageTitle='Contact';
$errors=[];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf'] ?? null);

    $name=trim((string)($_POST['name']??''));
    $company=trim((string)($_POST['company']??''));
    $email=trim((string)($_POST['email']??''));
    $phone=trim((string)($_POST['phone']??''));
    $eventType=trim((string)($_POST['event_type']??''));
    $guests=(int)($_POST['guests']??0);
    $preferredDate=trim((string)($_POST['preferred_date']??''));
    $contactPreference=trim((string)($_POST['contact_preference']??'Email'));
    $message=trim((string)($_POST['message']??''));

    if ($name==='') $errors[]='Please enter your name.';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Please enter a valid email address.';
    if ($eventType==='') $errors[]='Please select an event type.';
    if ($guests<1) $errors[]='Please enter the estimated number of guests.';
    if ($preferredDate!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$preferredDate)) $errors[]='Please choose a valid preferred date.';
    if ($message==='') $errors[]='Please tell us a little about your enquiry.';
    if (!in_array($contactPreference,['Email','Phone','WhatsApp'],true)) $contactPreference='Email';

    if (!$errors) {
        $s=db()->prepare('INSERT INTO contact_inquiries (name,company,email,phone,event_type,estimated_guests,preferred_date,contact_preference,message,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
        $s->execute([$name,$company,$email,$phone,$eventType,$guests,$preferredDate?:null,$contactPreference,$message,'new']);
        audit('create','contact_inquiry',(int)db()->lastInsertId(),null,['name'=>$name,'email'=>$email,'event_type'=>$eventType]);
        flash('success','Thank you. Your enquiry has been submitted to the resort team.');
        redirect('public/contact.php#contact-form');
    }
}

require __DIR__.'/../includes/header.php';
?>

<section class="contact-hero-v2">
  <div class="contact-hero-image" aria-hidden="true"></div>
  <div class="contact-hero-overlay"></div>
  <div class="container contact-hero-inner">
    <div class="eyebrow">Contact</div>
    <h1 class="page-title">Let's plan something exceptional.</h1>
    <p>For event enquiries, venue information and booking support, send the resort team a few details and we'll know how to guide your request.</p>
    <div class="contact-hero-actions">
      <a class="btn btn-gold" href="#contact-form">Send an enquiry →</a>
      <a class="btn contact-hero-outline" href="#planning-office">View contact details</a>
    </div>
  </div>
</section>

<section class="section contact-main-v2">
  <div class="container contact-grid-v2">
    <div class="contact-form-card" id="contact-form">
      <div class="eyebrow">Event enquiry</div>
      <h2>Tell us what you're planning.</h2>
      <p class="contact-lead">Share the basics below. This helps the resort team understand your event before following up.</p>

      <?php if ($errors): ?>
        <div class="contact-error" role="alert"><strong>Please check the following:</strong><ul><?php foreach($errors as $err): ?><li><?=e($err)?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="post" class="contact-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <div class="form-row-2">
          <label>Full name *<input required name="name" value="<?=e($_POST['name']??'')?>" placeholder="Your name"></label>
          <label>Company / organisation<input name="company" value="<?=e($_POST['company']??'')?>" placeholder="Company name"></label>
        </div>
        <div class="form-row-2">
          <label>Email address *<input required type="email" name="email" value="<?=e($_POST['email']??'')?>" placeholder="you@example.com"></label>
          <label>Phone number<input type="tel" name="phone" value="<?=e($_POST['phone']??'')?>" placeholder="+60 ..."></label>
        </div>
        <div class="form-row-2">
          <label>Event type *
            <select required name="event_type">
              <option value="">Select event type</option>
              <?php foreach(['Wedding','Team Building','Corporate Meeting','Conference','Private Celebration','Other'] as $type): ?>
                <option value="<?=e($type)?>" <?=($_POST['event_type']??'')===$type?'selected':''?>><?=e($type)?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Estimated guests *<input required type="number" min="1" name="guests" value="<?=e($_POST['guests']??'')?>" placeholder="e.g. 150"></label>
        </div>
        <div class="form-row-2">
          <label>Preferred date<input type="date" name="preferred_date" value="<?=e($_POST['preferred_date']??'')?>"></label>
          <label>Phone / WhatsApp preference
            <select name="contact_preference"><option>Email</option><option>Phone</option><option>WhatsApp</option></select>
          </label>
        </div>
        <label>Tell us about your enquiry *<textarea required name="message" rows="5" placeholder="Venue preference, event requirements, timing or anything else we should know..."><?=e($_POST['message']??'')?></textarea></label>
        <div class="contact-form-footer"><span>We only use these details to respond to your enquiry.</span><button class="btn btn-primary" type="submit">Submit enquiry →</button></div>
      </form>
    </div>

    <aside class="contact-side-v2" id="planning-office">
      <div class="contact-info-card">
        <div class="eyebrow">Planning office</div>
        <h3>Berjaya Hills Resort</h3>
        <p>Bukit Tinggi, Pahang<br>Malaysia</p>
        <div class="contact-info-list">
          <a href="mailto:events@berjayahills.local"><strong>Email</strong><span>events@berjayahills.local</span></a>
          <a href="tel:+60300000000"><strong>Phone</strong><span>+60 3 0000 0000</span></a>
          <div><strong>Office hours</strong><span>Monday – Friday<br>9:00 AM – 6:00 PM</span></div>
        </div>
        <a class="btn btn-gold contact-account-btn" href="<?=url('public/auth/register.php')?>">Request an account</a>
      </div>
      <div class="contact-map-card">
        <div class="contact-map-heading"><div><div class="eyebrow">Location</div><h3>Find Berjaya Hills</h3></div><a href="https://www.google.com/maps/search/?api=1&query=Berjaya+Hills%2C+Bukit+Tinggi%2C+Pahang" target="_blank" rel="noopener">Open map ↗</a></div>
        <iframe title="Berjaya Hills location map" loading="lazy" src="https://www.google.com/maps?q=Berjaya%20Hills%2C%20Bukit%20Tinggi%2C%20Pahang&output=embed"></iframe>
      </div>
    </aside>
  </div>
</section>

<section class="section contact-faq-section">
  <div class="container">
    <div class="section-heading contact-faq-heading"><div><div class="eyebrow">FAQ</div><h2>Before you send an enquiry.</h2><p>A few common planning questions, with final details confirmed by the resort team for your specific event.</p></div></div>
    <div class="contact-faq-grid">
      <details open><summary>How early should I enquire about a venue?</summary><p>Lead time can vary by venue, event type and date. Submit your preferred date and guest count so the team can advise availability and planning requirements.</p></details>
      <details><summary>Is transportation or shuttle service provided within the resort?</summary><p>Yes, shuttle services are available within the resort for resident guests and group events.</p></details>
      <details><summary>Is parking available for corporate groups?</summary><p>Parking arrangements can vary by venue and event size. Include your estimated guest count and transport needs so the team can advise the appropriate arrangement.</p></details>
      <details><summary>Can I ask about several venues at once?</summary><p>Yes. Use the message field to list your preferred venues and the event team can help compare availability and requirements.</p></details>
    </div>
  </div>
</section>

<section class="contact-final-strip">
  <div class="contact-final-overlay"></div>
  <div class="container contact-final-inner"><div class="eyebrow">Ready when you are</div><h2>Let's make the next occasion memorable.</h2><p>Explore the venues first, then send us your event details when you're ready.</p><div class="contact-final-actions"><a class="btn btn-gold" href="<?=url('public/venues/index.php')?>">Explore venues →</a><a class="btn contact-hero-outline" href="<?=url('public/calendar/index.php')?>">View calendar</a></div></div>
</section>

<?php require __DIR__.'/../includes/footer.php'; ?>
