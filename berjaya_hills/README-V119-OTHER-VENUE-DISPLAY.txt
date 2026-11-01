V119 - Other Venue Display

This update ensures that when a user selects "Others" during booking and enters a custom venue/space name, that exact name is displayed instead of "—", "TBA", or a generic venue placeholder in venue-related listings and details.

Run:
database/migrations/v119_other_venue_display.sql

The migration recreates vw_calendar_events and vw_upcoming_public_events with other_venue_name available to the application.
