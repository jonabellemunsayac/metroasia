# Open Play from the admin calendar

In Admin Dashboard, choose the sport, court and date. Click-drag across consecutive available cells in that court's column. The selected cells and time range update while dragging. Dragging stops at booked, held, blocked, unavailable or missing hours and cannot cross courts, sports or dates. Click **Set as Open Play**, enter **Rate Per Player** and **Maximum Players**, then save.

The calendar saves one dated session, displays its full time range and capacity as one block, and prevents its hours from being booked separately. Click the block for session details. Plain clicks still open normal calendar bookings. Shift-click selects a single slot or extends the highlighted range; Cancel selection or Escape clears it. Changing the calendar filters clears the selection.

Players see one session block and choose available numbered circles. Pricing is per selected player for the entire session. Held and Booked reservations occupy capacity; cancelling a player releases their position. Full sessions are disabled. Admin Bookings and My Bookings retain individual player records and the existing payment/receipt/status flow.

## Rates Management and existing data

Open Play fields and tags have been removed from Rates Management. Regular hourly rates, effective dates and holiday rules continue to work separately.

Existing saved Open Play sessions and reservations remain in place with their original date, capacity and price. Old Open Play rate definitions no longer generate recurring sessions and are excluded from regular pricing. Definitions with no saved session must be recreated for the desired date using the calendar. Historical rate metadata remains for compatibility; saving a regular rate for the exact same court/sport/day/hour/effective date replaces that obsolete metadata without removing its sessions or audit payloads.

No new database tables are needed. The existing open_play_sessions fields hold sport, court, date, start/end, capacity and per-player price. The legacy rate_key column is retained as an opaque unique session key, not a rate relationship. Existing numbered reservations and the unique active-seat constraint are reused. Time slots within the saved start/end range belong to the session.

## Deployment

Deploy these changed files together:

- admin/dashboard.php and admin/rates.php
- api.php and includes/open-play-rates.php
- assets/js/app.js and assets/js/open-play.js
- assets/themes/metro/open-play.css

Keep the existing Open Play asset includes, mobile grid integration and My Bookings support from the earlier implementation. Asset URLs already use content hashes. Existing schema initialization remains compatible; do not reimport the database.

## Validation

Run:

- php tests/open-play-rates.php
- node tests/admin-court-conflicts.test.cjs
- node tests/open-play-selection.test.cjs
- php tests/badminton-conflicts.php

The database test rolls back its fixtures. Session creation checks consecutive slots, date, active sport/court, disabled slots, regular reservations, maintenance blocks and other Open Play sessions. Creation and player booking use court-group locks and transactions; numbered seats retain a unique active-seat constraint. Wooden Courts 5-7 share Miami and Wooden Courts 8-10 share Lakers.

## Booking references and confirmation

New Open Play submissions use OP<MMDD>-<start>-<end>-<sequence>, for example OP1009-9-12-01. Each new submission reserves one player position and receives its own reference. The counter starts at 01 for each dated session, is allocated under the existing transaction/session lock, and does not reset when a player cancels. Non-hour time blocks retain minutes in the time portion. Existing reservations retain their previous fallback reference.

The confirmation uses the saved session date, full start/end times, court and sport instead of the refreshed hourly-slot dictionary. Regular booking references and summaries are unchanged.

Deploy ui/member.php and database.sql (fresh installs only) alongside the files above. Existing installations automatically gain open_play_sessions.booking_sequence and open_play_reservations.booking_reference through the existing additive schema helper. Do not reimport database.sql. Run node tests/open-play-confirmation.test.cjs for the refreshed-state summary regression.

## Admin session listing

Open Play bookings are grouped by session in expandable summaries with date, time, court, sport, session rate and capacity. Each summary opens the individual players, references, payment status/proof and existing Confirm/Cancel actions. Pagination shows 10 sessions per page, not 10 players.

Active/Pending includes upcoming sessions and any session with an unconfirmed player. Completed contains sessions whose end time has passed in venue time (UTC+08:00) and whose players are all Booked or Cancelled. Cancelled players do not prevent completion. Grouping refreshes after payment/status actions and checks elapsed end times every 30 seconds while the page is visible; no booking or payment statuses are changed by this grouping.

Admin and Super Admin can use **Cancel Session** in an expanded session or the calendar session dialog. A reason is required. Cancellation disables the session, cancels its Held/Booked players in the same transaction, releases the court hours, and records the staff member, timestamp and reason in the audit log. Previously cancelled player records retain their reasons. The **Cancelled** tab retains session history and displays the reason, including for sessions that had no players. Other roles cannot cancel sessions. Existing databases add the session cancellation columns through the normal API schema initialization.

Run node tests/open-play-session-list.test.cjs for session grouping and completion boundaries.

## Booking concurrency and account sessions

Deploy includes/account-sessions.php with includes/auth.php, ui/login.php, ui/forgot-password.php, api.php, includes/open-play-rates.php, assets/js/app.js and assets/js/open-play.js. Existing installations automatically create the additive account_sessions table on the first authenticated request/login; the database account needs CREATE permission. database.sql includes it for fresh installs only. Do not reimport database.sql over existing data.

Only one browser session per Player/Admin account is accepted. A second login is rejected while the first remains active. Logout releases ownership immediately; the existing eight-hour inactivity timeout also releases it. Tabs sharing the same browser session continue to work. Existing browser sessions adopt ownership on their next authenticated request; any competing session is rejected. Expired session tokens cannot replace a newer login.

Regular bookings, calendar Open Play, legacy Open Play, admin booking overrides/edits, confirmation/cancellation and court blocking now serialize their final checks and writes in transactions. Related parent/wooden courts use the same physical-resource locks. Rate/schema initialization runs before acquiring booking locks so DDL cannot implicitly commit an in-progress booking. Explicit admin override still requires the existing confirmation; newly arriving conflicts are rejected instead of silently cancelled.

Each player may hold/book one position per Open Play session. The session row lock protects member uniqueness and total capacity, while the existing unique active-seat index protects individual circles. Cancellation frees the position and allows rebooking. Existing historical reservation records are not deleted or rewritten. Failed competing requests return HTTP 409, refreshed availability and: "This slot is already taken. Please select another available slot."

Opt-in integration checks against a local Apache/MySQL instance (PowerShell):

```powershell
$env:RUN_DATABASE_CONCURRENCY_TESTS='1'
node tests/booking-concurrency.test.cjs
```

The integration test creates temporary accounts, an isolated court and reservations, then removes them in a finally block. It exercises real parallel HTTP requests and database locks, account/session ownership, regular/admin booking races, Open Play seat/member/capacity races, confirmation and cancellation. PHP_BINARY and TEST_APP_URL can override local defaults.
