GALLERY V22 — ADMIN PHOTO MANAGEMENT

Base: v21 GALLERY APPROVED UI.

Admin access:
- /admin/gallery/index.php
- Administrator role can create albums and add, replace/edit, delete, and set album-cover photos.
- Gallery uploads are stored in assets/gallery/.
- Allowed formats: JPG, PNG, WEBP, GIF. Maximum upload size: 8 MB.
- Local uploads are automatically used by the public Gallery page through the existing gallery album/image tables.
- Existing external gallery images remain supported.
- Changes are audited through the existing audit_logs table.

Database:
- No new tables are required.
- Existing gallery_albums and gallery_images tables are used.
- If the permanent gallery album SQL has not been run, run database/add_gallery_permanent_albums.sql after the main schema to seed the standard albums.

Public Gallery:
- Existing v21 visual design remains the public gallery design.
- Admin changes to database albums/images are reflected automatically.
