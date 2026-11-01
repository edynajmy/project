V45 — Food & Beverage Premium UI

Base: V44 Booking Step Row Fix

Changes:
- Replaced the technical Food & Beverage table with visual menu package cards.
- Menu cards are loaded dynamically from active rows in food_menus, so management-controlled menu data remains the source of truth.
- Each menu card includes image, menu type, name, description, price per pax, pax input, View Details and Select Menu.
- Selected menu is highlighted and appears in the sticky Booking Summary.
- Pax is automatically initialized from Expected Pax for the event and can be adjusted per selected menu.
- Added a View Details modal using the menu description currently stored in food_menus.
- Added Special Dietary Requirements: Vegetarian, Vegan, No Beef, No Pork, Halal Meal and Food Allergies.
- Added an Additional Notes field for chef/catering requests with 500-character counter.
- Dietary selections and food notes are stored inside the existing booking notes using dedicated [FOOD_DIETARY] and [FOOD_NOTES] markers, so no database schema change is required.
- Sticky Booking Summary remains visible on desktop and includes selected menu, pax, rate and food subtotal.
- Food menu images are bundled locally under assets/gallery/food/ so the Food & Beverage UI does not depend on external image URLs.
- Existing venue, facilities, rooms, timeline, draft save/resume and booking calculation workflows are retained.
- PHP and JavaScript syntax checks passed.
