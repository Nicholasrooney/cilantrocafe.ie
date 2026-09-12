# Setting up the booking database

One-time setup, about ten minutes in hPanel.

## 1 — Create the database

hPanel → **Databases** → **MySQL Databases**. Create a database and a user, and
tick to give that user full access. Hostinger prefixes both with your account
number, so you end up with something like `u123456789_cilantro`.

Write down the database name, username and password.

## 2 — Load the tables

**Databases** → **phpMyAdmin** → open your database → **Import** tab → choose
`db/schema.sql` from this repo → **Go**.

You should end up with three tables: `customers`, `bookings`, `booking_audit`.

## 3 — Give the site the credentials

Credentials never go in Git. Create `includes/secrets/db.php` using Hostinger's
File Manager (the whole folder is gitignored):

```php
<?php
$db['name'] = 'u123456789_cilantro';
$db['user'] = 'u123456789_cilantro';
$db['pass'] = 'the password you chose';
```

## 4 — Set the staff password

Visit `/staff/hash.php`, type the password staff will use, and copy the line it
gives you into that same `includes/secrets/db.php`:

```php
$staff['password_hash'] = '$2y$10$...';
```

Reload `/staff/hash.php` — it should now return "Not found". That is how you
know it took, and it means nobody else can use that page.

## 5 — Bring the old bookings across

Over SSH, or Hostinger's terminal:

```
php db/import-csv.php            # shows what it would do
php db/import-csv.php --commit   # actually does it
```

Safe to run twice — it skips anything already imported.

## 6 — Check it

Open `/staff/`, log in, and you should see the diary. Then make a test booking
through the public form and watch it appear.

## 7 — Turn on the nightly cleanup

hPanel → **Advanced** → **Cron Jobs**. Once a day, any quiet hour:

```
php /home/UXXXXXXX/domains/cilantrocafe.ie/public_html/cron/retention.php
```

This anonymises customers who have not booked in
`$booking['retention_months']` months. Test it first with `--dry-run`.

## Settings you should check

In `includes/config.php`:

| Setting | What it does |
|---|---|
| `max_covers_per_slot` | Most people you will seat in one 30-min slot. **Currently 20 — set it to your real number.** |
| `times` | The bookable slots. Must match your opening hours. |
| `max_guests` | Biggest party the form accepts; larger groups are told to ring. |
| `retention_months` | How long customer details are kept after their last visit. |
| `notify_email` | Where booking alerts go. Empty means nobody is told. |

## If something breaks

- **"The database is not set up yet"** on `/staff/` — steps 1–3 are incomplete.
- **Bookings work but no emails** — `$mail['from']` and `$booking['notify_email']`
  are empty. Check `data/mail.log`.
- **Everything worked and now nothing does** — the most likely cause is a
  Hostinger password rotation. Check `includes/secrets/db.php`.
