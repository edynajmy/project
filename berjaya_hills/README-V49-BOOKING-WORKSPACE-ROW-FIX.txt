V49 – Booking Workspace Row Fix

Fixed the New Booking workspace layout so the three main areas stay together on desktop:
1. Booking Steps sidebar
2. Active booking form
3. Booking Summary

The previous V48 CSS allowed a medium-width media query to move the Booking Summary into a second row, producing a large empty area and making the summary appear detached.

V49 forces a flex row on desktop and only stacks the sections below 900px viewport width.

No booking data or database records are changed by this UI-only fix.
