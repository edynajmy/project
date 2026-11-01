-- V47: Reset the current test/demo booking shown in the screenshots.
-- This removes ONLY booking BHR-260930-5C7B1D and its linked event/data.
-- It does not delete venues, menus, facilities, users, gallery content, or other bookings.

START TRANSACTION;
DELETE FROM bookings WHERE booking_ref = 'BHR-260930-5C7B1D';
COMMIT;

-- Because the schema uses ON DELETE CASCADE, the linked event, venue link,
-- facilities, food, rooms, timeline items, status history and change requests
-- for this booking are removed automatically.
