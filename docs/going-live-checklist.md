# Going live — what's left

Everything below is something only you can do. The code is done and tested.

## Must do before the site goes public

**1. Register cilantrocafe.ie.** It does not exist in DNS yet. Then add it as a
website in Hostinger and connect the Git repo
(`https://github.com/Nicholasrooney/cilantrocafe.ie.git`, branch `main`,
directory **blank**).

**2. Fill in `includes/config.php`:**

| Setting | Why it matters |
|---|---|
| `hours` | **Empty.** The hours block does not render anywhere, and no opening hours go to Google. I deliberately did not guess these. |
| `phone` | Empty, so the footer and the "large group" line are blank |
| `email` | Empty, so there is no contact address anywhere |
| `coords` | Optional but recommended — see below |
| `max_covers_per_slot` | **Currently 20.** Set it to the café's real figure or the booking form will take too many or too few. |
| `notify_email` | Empty means nobody is told when a booking arrives |

**3. Set up the database** — `docs/database-setup.md`. Until this is done the
booking form cannot save anything.

**4. Set the staff password** — visit `/staff/hash.php`, then paste the hash
into `includes/secrets/db.php`.

**5. Set up email** — create a mailbox in hPanel, put it in `$mail`, and add
**SPF and DKIM** records. Without those, confirmations go to spam.

## Recommended

**The exact map pin.** Right-click the café in Google Maps, copy the
coordinates, put them in `$site['coords']`. "Unit 7" in a shopping centre is
exactly the address a map app drops a pin 200m away from.

**Google Calendar** — `docs/google-calendar-setup.md`, about ten minutes.

**A logo.** Drop a `logo.png` into `images/` and the header swaps from text to
the real logo automatically. The one on your Cold Drinks menu sheet is a photo
of a printout — the original file would be much better.

## Off-site SEO — the part that actually drives traffic

The code side is done, but the sites of yours that rank do so partly because of
this list. None of it can be done from the repo.

1. **Google Business Profile.** For a café this outranks everything else on the
   page. Claim it, set the exact address and hours, add photos, and link to
   cilantrocafe.ie. Most "Mexican restaurant near me" traffic comes through here,
   not through the website.
2. **Google Search Console** — verify the domain, submit
   `https://cilantrocafe.ie/sitemap.xml`.
3. **Reviews.** Ask happy customers. Review count and recency move local rankings
   more than anything on the site.
4. **Consistent name, address and phone** everywhere — Google, Facebook,
   Instagram, TripAdvisor, JustEat, Deliveroo. They must match the website
   exactly, character for character.
5. **Fix the Deliveroo listing** if it is wrong: it says Deansgrange while the
   company registration says Blackrock. Inconsistent addresses hurt local ranking.

## One thing to confirm

I used **Blackrock** as the locality, from the CRO company registration and the
Instagram posts. A Deliveroo listing says Deansgrange. If Deansgrange is right,
tell me and I will change the address, the schema and the page copy together.
