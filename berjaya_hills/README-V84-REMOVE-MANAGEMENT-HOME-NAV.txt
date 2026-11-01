V84 — Remove Management Top Home Link

Updated shared includes/header.php. Removed the Home navigation link from the authenticated Management/Admin header. The Berjaya Hills brand remains on the left; signed-in user's name and Sign out remain in the right-side nav actions. Since the shared header is used throughout admin/management routes, this applies consistently across those pages. Sidebar navigation and page content were not intentionally changed.

Validation: PHP syntax checked with php -l.
