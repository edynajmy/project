V31 — Booking Venue Card Overflow Fix

Fixed the venue selection card horizontal overflow visible at narrower desktop widths.

Changes:
- Prevented venue preview grid from exceeding the booking content column.
- Added min-width:0 and box-sizing safeguards.
- At narrower desktop widths, the venue selector preview uses a responsive 42% / 58% image-copy layout.
- Venue title/description can wrap instead of forcing the card beyond the viewport.
- Existing V30 booking functionality and step-by-step UX are preserved.
