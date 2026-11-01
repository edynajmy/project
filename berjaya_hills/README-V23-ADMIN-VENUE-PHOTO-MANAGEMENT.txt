V23 - ADMIN VENUE PHOTO MANAGEMENT

Base: v22 Admin Gallery Management

Added:
- Administrator-only Venue Photos management page: admin/venues/photos.php
- Add/upload venue photos (JPG, PNG, WEBP, GIF; max 8 MB)
- Edit/replace venue photo and alt/description text
- Delete venue photos with confirmation
- Set Primary photo for each venue
- Sort order field for venue photos
- Safe deletion of locally uploaded files only
- Venue Management table now shows the current primary photo and photo count
- Public Venues cards continue to use the primary venue image
- Public Venue Detail page now uses the database primary image and shows the venue photo gallery thumbnails
- Audit trail entries for add/update/delete/set-primary actions
- Uploaded venue images are stored under assets/venue-images/

No database schema change is required because venue_images already exists in the project schema.

Access:
Admin Dashboard -> Venue Management -> Manage Photos

Gallery Management remains separate at:
Admin Dashboard -> Gallery Management
