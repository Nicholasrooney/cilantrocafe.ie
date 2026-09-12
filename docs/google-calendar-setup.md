# Connecting bookings to a Google Calendar

Roughly ten minutes, done once. You need to do this yourself — it involves
signing into your Google account, which nobody else should do for you.

The result: every booking made on the website appears on a calendar in your
Google account, on your phone, within seconds.

## How it works

The website signs in as a **service account** — a robot Google account that
belongs to the site, not to a person. You create a calendar, share it with the
robot, and the robot writes bookings onto it.

This is better than connecting your own Google login because there is no
password on the server, nothing to re-approve every few months, and if the site
is ever compromised the attacker gets access to one calendar rather than your
whole Google account.

**Sync is one way: website → Google.** Moving an event in Google Calendar does
not change the booking on the site. Until the staff calendar is built, the
website's records are the ones that count.

---

## Step 1 — Create the Google Cloud project

1. Go to <https://console.cloud.google.com/>
2. Top-left project dropdown → **New Project**
3. Name it `Cilantro Cafe Bookings`, click **Create**
4. Make sure that new project is selected before continuing

## Step 2 — Turn on the Calendar API

1. Search the top bar for **Google Calendar API**
2. Open it and click **Enable**

## Step 3 — Create the service account

1. Left menu → **IAM & Admin** → **Service Accounts**
2. **Create service account**
3. Name: `cilantro-website`. Click **Create and continue**, then **Done**
   (skip the optional role and user steps — it needs no project permissions)
4. You now have a row with an email like
   `cilantro-website@cilantro-cafe-bookings.iam.gserviceaccount.com`

   **Copy that email address. You need it in step 5.**

## Step 4 — Download the key file

1. Click the service account → **Keys** tab
2. **Add key** → **Create new key** → **JSON** → **Create**
3. A `.json` file downloads

**Treat this file like a password.** Anyone holding it can write to the calendar
you share. Do not email it, do not put it in Git, do not paste it into a chat.

## Step 5 — Make the calendar and share it with the robot

1. Open <https://calendar.google.com> on a computer
2. Left side, next to **Other calendars**, click **+** → **Create new calendar**
3. Name it `Cilantro Bookings`, set the timezone to **Dublin**, click
   **Create calendar**
4. Left menu → **Settings for my calendars** → **Cilantro Bookings**
5. **Share with specific people or groups** → **Add people**
6. Paste the service account email from step 3
7. Set permission to **Make changes to events** — this matters, "See all event
   details" is not enough
8. Click **Send**

## Step 6 — Get the calendar ID

Still in that calendar's settings, scroll to **Integrate calendar**. Copy the
**Calendar ID**. It looks like:

```
c_a1b2c3d4e5f6@group.calendar.google.com
```

## Step 7 — Put the pieces on the server

1. Rename the downloaded JSON file to `google-service-account.json`
2. Upload it, using Hostinger's File Manager (**not** Git), to:

   ```
   public_html/includes/secrets/google-service-account.json
   ```

3. Edit `includes/config.php` and set:

   ```php
   $calendar = [
       'enabled'     => true,
       'calendar_id' => 'c_a1b2c3d4e5f6@group.calendar.google.com',   // yours from step 6
       ...
       'test_token'  => 'pick-a-long-random-string-here',
   ];
   ```

## Step 8 — Test it

Visit:

```
https://cilantrocafe.ie/calendar-test.php?token=pick-a-long-random-string-here
```

You get a plain checklist. It creates a test event tomorrow at 15:00 and deletes
it again, so a clean run proves the whole path works end to end.

If everything passes, **blank `test_token` in config.php** so the page goes back
to returning "Not found".

---

## If something fails

The test page prints the actual reason. The usual ones:

| Message | Cause |
|---|---|
| `Service account key not readable` | File is in the wrong place, or named wrong |
| HTTP 404, "check the calendar ID" | Calendar ID typo, or step 5 was skipped |
| HTTP 403, "lacks write access" | Shared as "See all event details" instead of **Make changes to events** |
| `Google refused the token request` | The JSON key was corrupted — download a fresh one |

Sync problems are also written to `data/calendar-sync.log`, which is not
publicly readable. Ask me to check it any time.

## Good to know

- **A failed sync never costs a booking.** If Google is down or the key is
  wrong, the booking still saves and the customer still sees their
  confirmation. The failure goes to the log.
- **Only new bookings sync.** The ones already in `data/bookings.csv` were made
  before this existed and will not appear.
- Events are titled like `4 guests — Nicholas Rooney`, with the phone, email,
  seating and any notes in the description.
- A table is held for 90 minutes in the calendar. Change `duration_minutes` in
  config.php if that is wrong.

## Rotating the key

If the key is ever exposed, go to the service account → **Keys**, delete the old
key, create a new one, and upload it over the old file. Nothing else changes.
