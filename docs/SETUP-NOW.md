# What to do next, in order

The site is live at https://cilantrocafe.ie with SSL. The domain, nameservers
and MX records are all pointing at Hostinger already.

Do these in order — each one depends on the one before it.

---

## Step 1 — The database (blocks everything else)

**Nothing can be booked until this is done.** Right now the booking form will
show an error, and `/staff/` will show a "not set up yet" screen.

1. hPanel → **Databases → MySQL Databases**. Create a database and a user, tick
   full access. You get names like `u123456789_cilantro`.
2. hPanel → **phpMyAdmin** → open that database → **Import** → upload
   `db/schema.sql` → **Go**. You should get three tables.
3. Create the credentials file (step 2 below puts everything in one file).

---

## Step 2 — One secrets file

Using hPanel → **File Manager**, create:

```
public_html/includes/secrets/db.php
```

Paste this, filling in the four values marked:

```php
<?php
// Database — from step 1
$db['name'] = 'u123456789_cilantro';        // <- yours
$db['user'] = 'u123456789_cilantro';        // <- yours
$db['pass'] = 'the database password';      // <- yours

// Staff login for /staff/  (this hash is the password 12345)
$staff['password_hash'] = '$2y$10$/VD.TPCsTuEqPMswIGOFHecPC4ve/02AIGKQdPY7ic8mEO.j6xJ/a';

// Email — from step 3
$mail['smtp']['pass'] = 'the mailbox password';   // <- yours
```

This file is gitignored and blocked from the web, so it never reaches GitHub and
nobody can read it. It is the only place a password should ever go.

**On the staff password.** 12345 is set up as you asked, and the throttle (5
tries then a 15-minute lockout) makes it impractical to grind through. But it is
the single most-guessed password there is, and the diary holds customers' names,
phone numbers and emails — a GDPR problem if it gets out. Something like
`cilantro-tacos-24` is just as easy to tell a new staff member and vastly
harder to guess. Say the word and I will send you the hash for whatever you pick.

---

## Step 3 — Email

Your MX records already point at Hostinger, so the mailbox will work as soon as
you make it.

1. hPanel → **Emails → Email Accounts** → create a mailbox. I would use
   **`bookings@cilantrocafe.ie`**.
2. Put its password into `includes/secrets/db.php` as shown above.
3. **Tell me the address** and I will set it in `config.php` and push. Those are
   not secrets, so they belong in the repo:
   - `$mail['from']` — what confirmations are sent from
   - `$mail['smtp']['user']` — the same address
   - `$mail['smtp']['host']` — `smtp.hostinger.com`, port 587
   - `$site['email']` — shown in the footer and the privacy notice
   - `$booking['notify_email']` — where booking alerts land

4. **SPF and DKIM.** hPanel → **Emails → Email Accounts → DNS settings** and
   make sure both records exist. Without them, confirmations go to spam. Hostinger
   usually adds them automatically when the mailbox is created — worth checking.

Once that is done, every website booking sends a confirmation to the customer
and an alert to the café. Anything that goes wrong is written to `data/mail.log`
and never costs you the booking.

### What I still need from you
- The mailbox address (e.g. `bookings@cilantrocafe.ie`)
- Whether booking alerts should go to that same address or somewhere else
- The café's **phone number** — the footer, the privacy notice and the
  "more than 10 people" line are all still blank without it

---

## Step 4 — Google Calendar

Full walkthrough in `docs/google-calendar-setup.md`. About ten minutes, and it
has to be you because it needs your Google login.

The short version:
1. console.cloud.google.com → new project → enable the **Google Calendar API**
2. Create a **service account**, download its **JSON key**
3. In Google Calendar, make a calendar called **Cilantro Bookings**, share it
   with the service account's email address, permission **"Make changes to
   events"**
4. Upload the JSON key to `public_html/includes/secrets/google-service-account.json`
   via File Manager — **not Git**
5. **Send me the calendar ID** (Settings → Integrate calendar). It looks like
   `c_a1b2c3@group.calendar.google.com`. That one is safe to put in the repo.
6. I set `enabled => true` and push, then you run
   `/calendar-test.php?token=...` which creates and deletes a test event to
   prove the whole path works.

Test calendar is your own account, `nicholas.rooney2010@gmail.com`, as agreed.
When the café gets its own Google account you repeat the share step and change
one line — no code changes.

### What I still need from you
- The calendar ID, once step 3 is done

---

## Step 5 — Off-site (the bit that actually brings people in)

1. **Google Business Profile.** For a café this matters more than everything on
   the website put together. Claim it, use the exact address, set the hours
   (Tue–Fri 09:00–16:00, Sat–Sun 09:00–17:00, closed Monday), add photos.
2. **Google Search Console** — verify cilantrocafe.ie, submit
   `https://cilantrocafe.ie/sitemap.xml`.
3. **Reviews.** Nothing moves local rankings faster.
4. Make sure the address matches exactly everywhere — Instagram, Facebook,
   JustEat, Deliveroo. **Deliveroo currently says Deansgrange** while the
   company registration says Blackrock; inconsistent addresses hurt.

---

## Still open

- **Is it Blackrock or Deansgrange?** I used Blackrock, from the CRO
  registration. It is now in the page copy, the schema and the map link — tell me
  and I will change all three together.
- **`max_covers_per_slot` is a guess of 20.** How many people can you actually
  seat in one half-hour? The form starts refusing bookings at that number.
- **The exact map pin.** Right-click the café in Google Maps, copy the
  coordinates, send them over. "Unit 7" in a shopping centre is exactly the
  address a map app drops a pin in the wrong place for.
- **A logo file.** Drop `logo.png` into `images/` and the header swaps from text
  to the real logo on its own.
