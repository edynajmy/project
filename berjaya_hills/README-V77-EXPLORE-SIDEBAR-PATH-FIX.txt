Berjaya Hills v77 — Explore Sidebar Path Fix

Fixes authenticated Explore navigation detection for local installations where SCRIPT_NAME includes a project folder prefix, such as /berjaya_hills/public/calendar/index.php.

When logged in, Event Calendar, Venues, Gallery, About and Contact now use the shared collapsible account sidebar and omit the public top navigation links. The account name and Sign out remain in the header.

Updated cache version to app.css?v=77.

After replacing the project, hard refresh the browser with Ctrl+F5.
