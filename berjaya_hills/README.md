V26 — CONTACT PAGE EXPERIENCE

Base: V25 HOMEPAGE APPROVED UI

Implemented:
- Cinematic Contact hero using Berjaya Hills imagery with dark overlay.
- Clear CTA to send an enquiry and view contact details.
- Dynamic public contact form with:
  * Full name
  * Company / organisation
  * Email
  * Phone
  * Event type
  * Estimated guests
  * Preferred date
  * Contact preference
  * Enquiry message
- Server-side validation + CSRF protection.
- Contact submissions are stored in contact_inquiries.
- Admin > Contact Enquiries page for Administrator and Management roles.
- Admin can update enquiry status: New, In progress, Responded, Closed.
- Admin can reply using mailto.
- Audit log entry is created for new contact submissions and status updates.
- Clickable email and phone links.
- Planning office hours displayed.
- Embedded Google Maps location for Berjaya Hills and Open Map link.
- FAQ shortlist with 4 common planning questions.
- Scenic closing CTA linking to Venues and Calendar.

Database:
Import database/add_contact_inquiries.sql once into the existing database.
The SQL uses IF NOT EXISTS, so it is safe to run on an existing installation.

Important:
- The contact email/phone shown remain the values already present in the project (events@berjayahills.local and +60 3 0000 0000). Replace them with the resort's verified contact details before production use.
- FAQ wording avoids inventing venue-specific policies; final availability, catering and parking details are confirmed by the resort team.
