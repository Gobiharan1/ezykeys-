# Ezy Keys Booking Manager — isolated review build

A real PHP + PDO booking application. This does not modify or inject buttons into the existing website or live chat.

## Review locally

The local preview uses SQLite outside this upload folder. It includes clearly labelled demo records. No emails, SMS, push notifications, or payment transactions are sent.

- Sample booking page: http://127.0.0.1:4190/index.php
- Admin: http://127.0.0.1:4190/admin.php
- Local login details are delivered separately; they are not included in the upload ZIP.

## Included

- Sample page with a Book Now button and database-driven lesson/package options.
- Validated booking requests with references, duplicate-submission protection, and pending status.
- Admin login, logout, CSRF checks, request throttling, password hashing, and session expiry (2 hours idle / 12 hours absolute).
- Booking list, filters, learner contacts, private notes, manual payment records, and status changes.
- Schedule each included lesson; overlapping instructor sessions are blocked, including concurrent scheduling requests.
- Melbourne time and daylight saving; database timestamps use UTC.
- Monthly calendar, customer booking history, remaining lessons, and admin activity log.
- Add/edit/archive packages. New bookings use current prices; existing bookings retain their original agreed package details.
- Hampton Park-only offer validation and same-calendar-week scheduling for weekly packages.
- Chat stays separate. No production chat credentials or customer records are copied.

## Deliberate limits for the first review

Customers request preferred dates; there is no instant slot reservation. Admin must contact the learner to agree a time. This build records payments manually and does not charge cards. Notifications are visible in the dashboard; email/SMS delivery is not configured. There is one instructor schedule and one admin role. This admin login is separate from live chat.

Weekly discount cancellation/late-reschedule repricing requires admin review and is not automated. Test-day car hire/check-in times are arranged manually; the $355 bundle tracks three 60-minute lessons, and car-hire time must be coordinated before confirming other lessons. Use admin notes for the test appointment. Do not treat the calendar as a car-hire reservation system yet.

## Install after approval on existing PHP hosting

1. Back up the website. Create a dedicated MySQL database and user in the hosting panel.
2. Upload the ZIP contents into a NEW folder, for example `public_html/booking-test/`. Do not replace the website root or chat folder.
3. Copy `config/example.php` to `config/local.php`. Set the database name, username, and password. Keep `preview` false. Use HTTPS and PHP 8.2+ with PDO MySQL and mbstring enabled.
4. In the hosting terminal, enter the uploaded folder and run these commands. Enter a new admin email and unique password when prompted:

```sh
read -r -p 'Admin email: ' BOOKING_ADMIN_EMAIL
read -r -s -p 'Admin password (minimum 12 characters): ' BOOKING_ADMIN_PASSWORD
printf '\n'
export BOOKING_ADMIN_EMAIL BOOKING_ADMIN_PASSWORD
php bin/setup.php
unset BOOKING_ADMIN_EMAIL BOOKING_ADMIN_PASSWORD
```

The installer creates only tables prefixed `booking_`. It seeds packages only if the package table is empty. It never changes an existing admin password. Do not import a PHP file through phpMyAdmin.

5. Open `/booking-test/index.php` and `/booking-test/admin.php`. Test requests, scheduling, prices, restricted offers, and login/logout. Verify `/booking-test/config/`, `/includes/`, `/bin/`, and `/tests/` return 403 on Apache. The included `.htaccess` rules require Apache-compatible hosting; add equivalent deny rules before deploying on Nginx.
6. Confirm the package terms, notification requirements, test-day car-hire scheduling, and admin workflow before using this with real customers. Only after review should a Book a Lesson button be added elsewhere.

The upload ZIP excludes the local database, local configuration, and preview admin credentials. No deployment to the live site has been performed.

## Checks

Run `php tests/workflow.php` for an isolated disposable SQLite workflow test. It checks price snapshots, restricted/archived packages, conflicts, limits, progress, payments, cancellations, weekly scheduling, audit entries, and DST. It does not use the production database.

No password recovery UI is included. Account provisioning is currently CLI-only. Keep credentials in the private hosting config; never commit `config/local.php` or databases to GitHub.
