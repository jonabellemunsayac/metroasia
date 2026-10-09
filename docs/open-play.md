# Open Play through Rates Management

In **Admin → Rates**, keep the usual sport, court, day, start/end and effective-date fields. Enable **Open Play**, then set **Maximum Players** and **Rate Per Player**. The hourly price remains available for the existing rate workflow; Open Play checkout charges the per-player price once for the entire session.

The booking grid merges the session's hourly cells into one block. Selecting that block opens numbered player positions. Each selected position creates its own reservation using the existing payment, receipt, confirmation and cancellation flow. All positions selected in one submission succeed or fail together. A held or booked player occupies a position; cancellation releases it.

Admin Bookings includes an **OPEN PLAY bookings** table with individual players, session time, court/sport, session capacity, per-player charge, payment proof and confirmation/cancellation actions. Existing regular booking lists remain intact.

## Deployment

Deploy these files together:

- `api.php`
- `admin/rates.php`, `admin/setup.php`
- `includes/open-play-rates.php`, `includes/header.php`, `includes/footer.php`
- `assets/js/app.js`, `assets/js/mobile-booking.js`, `assets/js/open-play.js`
- `assets/themes/metro/open-play.css`
- `database.sql` for fresh installations

The API applies the additive schema update automatically on first load, using the existing database account. Setup also applies it. Do not reimport the database to update an existing installation.

Existing `rates` rows gain nullable session metadata. Existing `open_play_sessions` gain rate, court, sport and time references. Existing `open_play_reservations` gain a player-position number and a unique active-position constraint. Regular rates and legacy Open Play reservations remain valid.

Effective dates and day/holiday priority follow regular rate selection. Booked sessions retain their capacity, price and occupied court even if their rate is superseded or deleted; those retained sessions stop accepting new players. Cancel their player reservations before adding a maintenance block over them.

The booking transaction locks the session and its physical court group. Wooden Courts 5–7 share Miami; Wooden Courts 8–10 share Lakers. This prevents duplicate positions and concurrent bookings exceeding capacity, and keeps regular bookings out of Open Play blocks.

## Verification

Run `php tests/open-play-rates.php`, `php tests/badminton-conflicts.php`, and `node tests/admin-court-conflicts.test.cjs`. The Open Play database test uses a transaction and rolls its fixtures back. It requires an existing local database with the standard hourly slots.
