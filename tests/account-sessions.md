# Single active account session

Admin and Player (`member`) accounts use `account_sessions`, with one primary-key row per account and a SHA-256 hash of a random 256-bit token. Only successful authentication can replace that token. The existing `account_sessions` table is migrated in place from the former three-column primary key to `(account_type, account_id)`. Legacy multi-device tokens are cleared during that migration because activity timestamps cannot establish login order; those users must sign in again. Tables already using the two-column primary key retain their rows. Concurrent migration attempts are serialized with a database lock.

Deploy all changed PHP and JavaScript files together. Run `php scripts/migrate-account-sessions.php` with the configured database credentials before serving requests. The application also creates or migrates the same table automatically, matching the existing deployment convention. No account or booking data is changed by this migration.

Validation holds a MySQL named lock through request completion. Logins, validation and logout acquire the same account lock, so a request already authorized completes before a new login takes over; requests after takeover are rejected before dispatch. Simultaneous successful logins are ordered by lock acquisition and the last claim wins. Lock failures and database failures fail closed. All application servers must connect to the same MySQL primary for authentication. Persistent PDO connections are not used.

Logout deletes only the matching token; an old browser cannot log out the winner. Idle expiry remains eight hours. Refresh validates the same token. Notification polling checks every ten seconds and on focus/page restoration without refreshing activity; browser suspension or disconnection can delay the notification but cannot bypass server validation. Login displays the exact replacement message. API failures return HTTP 401 with `session_replaced` or `session_expired`.

Run from PowerShell with Apache and MySQL available:

```powershell
php tests/account-sessions.php
$env:RUN_DATABASE_CONCURRENCY_TESTS='1'
node tests/account-session-concurrency.test.cjs
node tests/booking-concurrency.test.cjs
```

The PHP test uses a temporary table and verifies migration from duplicate legacy account rows. HTTP tests create and clean up isolated fixture accounts and use independent cookie jars to simulate browsers/devices. They check Admin and Player takeover, five simultaneous logins with one survivor, protected-page redirects, notification text, denied booking/payment/admin mutations, stale logout, refresh and expiry. The existing booking concurrency suite remains a regression check.

Manual browser/device verification: sign in on browser A, then browser B (or a second device) for each account type. A should redirect within ten seconds while active and display the replacement message. B must remain usable after A refreshes or logs out. Repeat while submitting bookings/payments and confirm only authorized operations succeed. Automated integration tests passed on 2026-10-17 after XAMPP was started: `php tests/account-sessions.php`, `node tests/account-session-concurrency.test.cjs`, and `node tests/booking-concurrency.test.cjs` (with `RUN_DATABASE_CONCURRENCY_TESTS=1`). These exercised independent HTTP cookie jars, including five simultaneous logins for each account type. Visual notification timing in real browsers and on physical devices still requires the manual check above.
