# EdyLife 1.5.5 — Local Personal Dashboard

Branding update: the visible application name is now **EdyLife**. The local folder, database name, and existing URLs remain `noirlife` to preserve compatibility.

PHP + MySQL + HTML + CSS + vanilla JavaScript for XAMPP.

## Features
- Dashboard: add, edit, complete and remove tasks; add, edit and remove activities.
- **Movie & Drama watchlist:** add/edit/remove titles; Movie, Drama, Anime, Series, Documentary or Other; Watching, Completed or Plan to Watch status; optional 0–10 rating, release year, genre, notes and locally uploaded poster.
- Memories & Photos: create, edit and remove folders; upload multiple photos; edit photo caption/date/folder; remove photos; full-screen viewer.
- Dark/light mode toggle with saved preference.
- PDO prepared statements, CSRF tokens, delete confirmation prompts, responsive premium black-and-gold design with dark/light themes.

## Install / upgrade
1. Extract `NoirLife` into `C:\xampp\htdocs\noirlife`.
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin, import `database/noirlife.sql` for a fresh install, then import `database/noirlife_v1_1.sql` and `database/noirlife_v1_4.sql`. If upgrading an existing v1.3 installation, import **only** `database/noirlife_v1_4.sql`.
4. Copy `config/database.example.php` to `config/database.php` and update credentials if needed. If already configured, keep your existing `database.php`.
5. Visit `http://localhost/noirlife/`.

## Upload limits
- Movie posters: JPEG, PNG or WebP; max 5 MB each; stored in `uploads/posters/`.
- Memories: JPEG, PNG or WebP; max 5 MB each and up to 10 per upload; stored in `uploads/photos/`.
- PHP `upload_max_filesize` and `post_max_size` must permit uploads.

## Important
This is a local single-user starter without login yet. Before online deployment, add authentication/authorization, HTTPS, a dedicated least-privilege DB user, safe secret management and backups. Do not expose a development XAMPP instance directly to the internet.


## NoirLife v1.4.3 — Monthly Movie vs Drama comparison
- The Movie & Drama page includes a six-month bar chart comparing completed Movies and Dramas.
- The completion month is recorded when a title is created or changed to `Completed`.
- Existing database: run `database/noirlife_v1_4_3_monthly_comparison.sql` once in phpMyAdmin.
- New database: the `completed_at` column is included in `database/noirlife_v1_4.sql`.
- Previously completed titles are not assigned a guessed historical date; they begin appearing in chart data if their status is changed away from Completed and then marked Completed again.


## v1.4.5 — Calendar & Daily Journal

This update adds a monthly calendar and a daily journal with create, edit, and remove actions. Calendar dates show entry counts and titles; selecting a date opens that day's journal. Journal entries support category, optional mood, title, and notes.

### Database update

Back up your project and database first. In phpMyAdmin, select the existing `noirlife` database and import `database/noirlife_v1_4_5_journal.sql` once. It creates only the new `daily_entries` table and does not drop or overwrite existing data.

### Pages

- `http://localhost/noirlife/pages/calendar.php`
- `http://localhost/noirlife/pages/journal.php`

Keep your existing `config/database.php` and `uploads` folder when updating an existing installation.


## v1.5 Activities + Work Planner
- Activities page: choose a date, add/edit/remove activities, schedule times and notes.
- Work Planner: task list with category, priority, status, start date, due date, search/filter, and Add/Edit/Remove.
- Database migration: import `database/noirlife_v1_5_work_planner.sql` into the existing `noirlife` database once.
- Existing `activities` table is reused; no activities data migration required.

## v1.5.3 update
- Movie & Drama watchlist now supports filtering by media category and watch status while retaining title/genre search.
- Dashboard hero supports uploading a custom JPG, PNG, or WebP cover image (maximum 8 MB). The current cover is stored in `uploads/cover/` and replaced when a new cover is uploaded.
- No database migration is required for these two features.
