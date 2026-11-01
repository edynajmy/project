V41 - Facilities Quantity & Step Layout Fix

Based on V40.

Changes:
- Each facility now has its own quantity input.
- Selected facilities with quantity > 0 are saved individually.
- Existing draft facility quantities are restored per facility.
- Booking estimate and selected-services summary include all selected facilities.
- Entering a quantity automatically selects that facility; setting quantity to 0 unselects it.
- Facility step action buttons are moved closer to the content and the step is more compact.
- Removed the old single-facility quantity workflow message.
- Backward-compatible handling remains in save.php for older single-facility payloads.
