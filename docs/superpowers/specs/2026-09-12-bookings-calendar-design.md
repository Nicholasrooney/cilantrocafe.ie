# Cilantro Café — staff bookings calendar & customer database

**Date:** 2026-09-12
**Status:** Approved design, ready for implementation planning

## 1. Problem

The site takes table bookings and appends them to `data/bookings.csv`. Nobody is
notified, nobody can read the file without FTP, there is no way to log the phone
bookings a café actually lives on, and nothing stops three parties of eight
booking the same slot.

## 2. Goals

1. A staff calendar at `/staff/`, behind a password, that works on a phone,
   tablet or laptop and acts as the real service diary.
2. A customer database — one row per person, not one per booking — with their
   history and staff notes.
3. Capacity control so the public form stops taking bookings a slot cannot hold.
4. Booking emails: confirmation to the customer, alert to the café.

## 3. Non-goals

Explicitly out of scope. Listed so nobody builds them by accident.

- **Google Calendar sync.** Deferred by decision (§4.1). The schema and a
  no-op interface are in place so it drops in later without migration.
- **Two-way sync with any external calendar.** Site is always the source of
  truth. Ruled out permanently — conflict resolution is not worth it when staff
  have the real tool one tap away.
- **A table plan.** Capacity is seats per slot, not "table 4 at 19:00".
- **Online payment or deposits.**
- **Named staff logins.** Deferred, not designed out — see §4.2.

## 4. Decisions

### 4.1 Self-hosted, with Google deferred

MySQL on Hostinger is the source of truth. The staff calendar reads and writes
it directly.

Google Calendar sync was considered and deferred. Two mechanisms were compared:

| | ICS feed | Service account + Calendar API |
|---|---|---|
| Setup | Paste a secret URL into Google | Service account, JSON key, share a calendar with it |
| Latency | 8–24+ hours (Google's own refresh schedule) | Seconds |
| Direction | Read-only in Google | Read/write |

If sync is revived, **use the service account API**; the ICS refresh delay makes
it useless for running today's service. The café owner creates a calendar in
their own Google account and shares it with the service-account email, so it
works with a plain `@gmail.com` and needs no Composer (the JWT is ~40 lines of
`openssl_sign` + curl).

Until then `bookings.google_event_id` stays nullable and unused, and
`includes/calendar-sync.php` exposes no-op functions that the booking write path
already calls. Wiring it up later is filling in three function bodies.

### 4.2 Shared password now, named logins later without a migration

One café password, chosen by the owner. The trade-off is accepted: anyone who
has ever worked there can open the diary until the password changes, and status
changes cannot be attributed to a person.

To keep the upgrade path free, every write records `changed_by`, which stores
the literal string `staff` today. Adding named logins later means populating
that column with a real name — a config and login-screen change, not a schema
migration and not a rebuild.

### 4.3 Capacity is seats per slot

A single `max_covers_per_slot` number. No table plan: staff would have to
maintain it, and the café can usually squeeze a table in regardless. Staff can
override the limit when entering a booking themselves; the public form cannot.

## 5. Data model

MySQL, InnoDB, `utf8mb4` throughout (the menu and café name contain `é`; customer
notes will contain emoji).

```sql
CREATE TABLE customers (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(120)  NOT NULL,
    phone                 VARCHAR(32)   NOT NULL,
    phone_key             VARCHAR(20)   NOT NULL,   -- digits only, for matching
    email                 VARCHAR(190)  NOT NULL,
    notes                 TEXT          NULL,       -- staff notes, not customer's
    marketing_consent     TINYINT(1)    NOT NULL DEFAULT 0,
    marketing_consent_at  DATETIME      NULL,
    created_at            DATETIME      NOT NULL,
    updated_at            DATETIME      NOT NULL,
    anonymised_at         DATETIME      NULL,
    UNIQUE KEY uq_phone_key (phone_key),
    KEY idx_email (email),
    KEY idx_name  (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED NOT NULL,
    booking_date    DATE         NOT NULL,
    booking_time    TIME         NOT NULL,
    guests          TINYINT UNSIGNED NOT NULL,
    seating         VARCHAR(40)  NOT NULL,
    notes           TEXT         NULL,              -- the customer's own note
    status          ENUM('requested','confirmed','seated','completed','no_show','cancelled')
                        NOT NULL DEFAULT 'requested',
    source          ENUM('website','phone','walk_in') NOT NULL DEFAULT 'website',
    google_event_id VARCHAR(255) NULL,              -- unused until sync is built
    changed_by      VARCHAR(60)  NOT NULL DEFAULT 'staff',
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    CONSTRAINT fk_bookings_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    KEY idx_date_time (booking_date, booking_time),
    KEY idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_audit (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED NOT NULL,   -- deliberately no FK: audit outlives the booking
    action      VARCHAR(40)  NOT NULL,
    old_status  VARCHAR(20)  NULL,
    new_status  VARCHAR(20)  NULL,
    actor       VARCHAR(60)  NOT NULL,
    ip          VARBINARY(16) NULL,
    at          DATETIME     NOT NULL,
    KEY idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Customer matching.** `phone_key` is the phone reduced to one canonical form by
this exact rule, applied in order:

1. Strip everything that is not a digit.
2. If the result starts with `00353`, replace that prefix with `0`.
3. Else if it starts with `353`, replace that prefix with `0`.
4. Else if it does not start with `0`, prepend `0`.

So `086 123 4567`, `+353 86 123 4567` and `0035386 1234567` all become
`0861234567` and resolve to one person. Non-Irish numbers keep their digits and
simply match themselves, which is correct — they just never merge with an Irish
form of the same number, an acceptable edge case for a Dublin café.

`phone_key` is the unique key. Email is indexed for search but deliberately not
unique, because families and couples share an address.

**`booking_audit` has no foreign key.** If a booking row is ever deleted the
audit trail must survive it. That is the whole point of an audit table.

## 6. Capacity logic

A booking counts against a slot unless it is `cancelled` or `no_show`.

```sql
SELECT booking_time, SUM(guests) AS covers
  FROM bookings
 WHERE booking_date = :date
   AND status NOT IN ('cancelled','no_show')
 GROUP BY booking_time;
```

The public form uses this to grey out full slots when it renders. **That check is
repeated on submit inside a transaction**, because rendering-time checks lose the
race when two people press Book in the same second:

```
START TRANSACTION;
  SELECT SUM(guests) ... FOR UPDATE;   -- gap lock via idx_date_time
  -- reject if SUM + requested > max_covers_per_slot
  INSERT INTO bookings ...;
COMMIT;
```

Skipping the second check is the single most likely way this feature ships
broken, and the failure is invisible until a Saturday goes wrong.

**Who the limit applies to.** The public form enforces it absolutely. The staff
add-booking screen runs the same check but on failure shows a warning with a
"book anyway" confirmation rather than a rejection, and records
`action = 'capacity_override'` in `booking_audit`. Staff can see the room; the
computer cannot.

## 7. Staff app

At `/staff/`, mobile-first — it will mostly be a phone behind the counter, so
tap targets are large and the day view needs no horizontal scrolling.

| Screen | File | Purpose |
|---|---|---|
| Login | `staff/login.php` | Shared password, rate limited |
| Day view | `staff/index.php` | Today's slots; default screen |
| Week view | `staff/week.php` | Seven days at a glance |
| Add / edit booking | `staff/booking-edit.php` | Phone and walk-in bookings |
| Customer lookup | `staff/customers.php` | Search by name or phone; history and notes |

Each booking card shows name, guests, time, seating, the customer's note, the
phone number as a `tel:` link, and status buttons (confirm, seated, no-show,
cancel). Every status change writes a `booking_audit` row.

Plain PHP with a little vanilla JS, matching the existing site. No framework, no
build step, deploys through Git like everything else.

## 8. Public booking form changes

1. Full slots are unavailable in the time dropdown.
2. On submit: find-or-create the customer by `phone_key`, then insert a booking
   with `status = requested`, `source = website`.
3. A separate, **unticked** marketing opt-in box, worded so it is clearly not
   part of booking the table. Booking a table is not consent to be marketed to;
   this checkbox is what makes a future mailing list lawful to use.
4. `data/bookings.csv` is retired. The existing file is imported once, then the
   CSV write path is removed.

## 9. Emails

Two messages per website booking: a confirmation to the customer and an alert to
the café's address (`$booking['notify_email']`, currently empty).

Send via SMTP using the domain's own mailbox rather than bare `mail()`;
deliverability from shared hosting without authenticated SMTP is poor. PHPMailer
is vendored directly into `includes/lib/` — Hostinger has no Composer. `mail()`
remains a fallback if SMTP fails, so a mail problem never costs a booking.

SPF and DKIM must be set for the domain or confirmations will land in spam.

## 10. Configuration

Added to `includes/config.php`:

```php
$db      = ['host' => 'localhost', 'name' => '', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4'];
$staff   = ['password_hash' => '', 'session_hours' => 12, 'max_attempts' => 5, 'lockout_minutes' => 15];
$booking['max_covers_per_slot'] = 20;   // set to the café's real figure
$booking['retention_months']    = 24;
```

**Credentials never enter Git.** `config.php` loads `../private/secrets.php`
(above `public_html`, placed manually once) if it exists, and otherwise
`includes/secrets.php`, which is gitignored and denied by `.htaccess`. The staff
password is stored only as a `password_hash()` digest.

## 11. File layout

```
includes/
  db.php             PDO connection, one per request
  customers.php      find-or-create, search, anonymise
  bookings.php       create, update status, query by date range
  capacity.php       slot availability + the transactional check
  auth.php           staff session, login throttling
  mailer.php         confirmation + café alert
  calendar-sync.php  no-op interface, reserved for Google
  lib/               vendored PHPMailer
staff/               the five screens above, plus .htaccess
cron/retention.php   daily anonymisation
db/schema.sql        the DDL in §5
db/import-csv.php    one-off import of the existing bookings.csv
```

Each `includes/` file has one job and can be read on its own. `booking-handler.php`
keeps only form validation; storage moves to `bookings.php`.

## 12. Security & GDPR

- Staff area behind session auth; login throttled to 5 attempts then a 15-minute
  lockout. Session cookies `httponly`, `secure`, `samesite=Lax`.
- All queries via PDO prepared statements.
- `includes/`, `db/`, `cron/` and `docs/` denied by `.htaccess`; `data/` already is.
- A privacy notice page, linked from the booking form, saying what is collected,
  why, how long it is kept and how to ask for deletion.
- `cron/retention.php` runs daily and anonymises customers whose most recent
  booking is over `retention_months` old. Name and phone are always overwritten
  and `anonymised_at` is stamped. **The email is kept only if
  `marketing_consent = 1`** — consent is its own lawful basis and survives the
  booking's retention period — and is otherwise overwritten too. Booking rows
  survive as anonymous covers so historical cover counts stay intact.
  Withdrawing consent later clears the retained email.
- A staff button to export or delete one customer's full record, so a subject
  access request is a click rather than a scramble.
- Accepted risk: the shared password (§4.2). Change it whenever someone leaves.

## 13. Testing

There is no PHP on the development machine, so nothing can currently be run
before it is deployed. This is the biggest practical risk in the plan — a syntax
error in a data file white-screens a live page.

Mitigations, in order of preference:

1. **Install PHP locally** (extract the Windows zip, add to PATH). Enables
   `php -l` linting and `php -S localhost:8000` to click through the real site.
   Roughly two minutes of setup and it removes the whole class of problem.
2. **A staging subdomain** on Hostinger tracking a `staging` branch, so changes
   are proven on a real server before `main` is deployed.

Each unit in §11 is a plain function over PDO and can be exercised by a small
script under `db/`. `capacity.php` needs a deliberate concurrency test: two
simultaneous submissions for the last seats, where exactly one must win.

## 14. Suggested build order

Three phases, each independently useful and shippable. The café gets working
value after phase 1 rather than waiting for the whole thing.

1. **Database and migration.** Schema, `db.php`, `customers.php`, `bookings.php`,
   import the existing CSV, switch the public form to write to MySQL. Behaviour
   from the customer's side is unchanged — but the data is finally somewhere
   usable.
2. **Staff app.** Login, day view, week view, add/edit booking, customer lookup,
   audit trail. This is the phase that replaces paper.
3. **Capacity and emails.** Slot limits on the public form, the transactional
   check, staff override, confirmation and alert emails, retention cron, privacy
   notice.

## 15. Open items for Nicholas

These block implementation and only he can answer them.

1. The café's real `max_covers_per_slot`.
2. The address booking alerts should go to, and the mailbox to send from.
3. Opening hours, so the time slots match reality (`config.php` hours are empty).
4. Street address, phone and Maps link — still empty, so the footer and the
   "Visit us" block render blank.
5. Confirmation that Hostinger's plan includes MySQL (all current shared plans do).
