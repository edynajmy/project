BERJAYA HILLS – V25 HOMEPAGE UI REFRESH

Base: v24 Venue Detail Experience

Homepage updates:
- Reworked public homepage to follow the approved generated UI reference.
- Cinematic hero image slider with 4 Berjaya Hills visuals, autoplay, dots and previous/next controls.
- Stronger gold primary CTA and clearer secondary CTA.
- Four visual USP / why-choose-us cards.
- Horizontal venue carousel with venue image, type, capacity and quick view button.
- Capacity is shown directly on venue cards.
- Real venue data is read from the existing venues table.
- Primary venue images continue to use the existing venue_image_url() helper and Admin Venue Photo Management.
- Statistics strip uses system-derived / workflow-oriented values rather than fabricated customer claims.
- Reviews section is shown only when approved venue reviews exist. No fake testimonials or ratings are inserted.
- Upcoming events section is shown only when public upcoming events exist; empty-state message is removed from the homepage.
- Event planning journey section retained and visually upgraded.
- Scenic final CTA section added.
- Responsive layout for desktop, tablet and mobile.

Notes:
- Hero/background visuals use the existing real Berjaya Hills image URLs already used by the project.
- The hero is an image carousel rather than a video because no licensed Berjaya Hills video asset was supplied in the project.
- Corporate logos and testimonial claims were not fabricated. They can be added later if the admin/content database contains approved assets and content.
- If venue_reviews has not been installed, the homepage continues to work normally; the review section simply does not appear.
