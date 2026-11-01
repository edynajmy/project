<?php if (!empty($useUserShell) || !empty($useExploreShell)): ?></section></div><?php endif; ?>
</main>
<footer class="site-footer">
  <div class="footer-topline"></div>
  <div class="footer-grid footer-grid-refined">
    <div class="footer-about">
      <a class="brand footer-brand" href="<?= url('index.php') ?>">
        <span class="brand-mark">BH</span>
        <span><b>BERJAYA HILLS</b><small>RESORT · EVENTS & ACTIVITIES</small></span>
      </a>
      <p>Exceptional venues, memorable gatherings and highland experiences — brought together in one place.</p>
      <span class="footer-note">Plan an event. Discover a place. Make a memory.</span>
    </div>
    <div class="footer-links">
      <h4>Discover</h4>
      <a href="<?= url('public/calendar/index.php') ?>">Event Calendar</a>
      <a href="<?= url('public/venues/index.php') ?>">Venues & Places</a>
      <a href="<?= url('public/gallery/index.php') ?>">Gallery</a>
      <a href="<?= url('public/about.php') ?>">About Berjaya Hills</a>
    </div>
    <div class="footer-links">
      <h4>Planning & Support</h4>
      <a href="<?= url('public/contact.php') ?>">Contact</a>
      <a href="<?= url('public/auth/register.php') ?>">Request an account</a>
      <a href="<?= url('public/auth/login.php') ?>">Sign in</a>
      <a href="<?= url('public/venues/index.php') ?>">Explore event spaces</a>
    </div>
    <div class="footer-cta">
      <span class="footer-eyebrow">Your next occasion starts here</span>
      <h3>Let's plan something memorable.</h3>
      <a class="footer-cta-link" href="<?= url('public/contact.php') ?>">Talk to our team <span aria-hidden="true">→</span></a>
    </div>
  </div>
  <div class="footer-bottom footer-bottom-refined">
    <span>© <?= date('Y') ?> Berjaya Hills Resort · Event & Activity Management System</span>
    <a href="<?= url('public/contact.php') ?>">Contact & enquiries</a>
  </div>
</footer>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body></html>
