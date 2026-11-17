# Standard Issue Shirt Co.

Landing page for standardissueshirt.com (Crew Pack + branded team webstores, Jacksonville FL).

- `index.html` is the whole site. The order form is an Airtable form embedded at the bottom
  (base "MLXL Q4 Retail Prospects 2026", table "Inquiries").
- Hosting: Hostinger. The site deploys from the `main` branch into `public_html`.
  With Auto Deployment on, every push to `main` goes live in about a minute.
- To change prices or copy: edit `index.html`, commit to `main`, done.

## Order form
- The form on `index.html` posts to `order.php`, which creates a record in the Airtable
  Inquiries table and saves any logo upload to `/uploads`.
- The Airtable token is NOT in this repo. It lives in `si-config.php` one folder above
  `public_html` on Hostinger:
  `<?php return ['airtable_token' => 'pat...'];`
- If the config is missing or Airtable is down, the page shows a link to the backup
  Airtable form plus the phone number, so no lead is lost.

## Nov 10, 2026: lead capture upgrade
Three ways in, all posting to `order.php` (field `mode`):
- `mockup`: free logo mockup (name, business, email OR mobile, optional logo). Saved with "What do you need?" = Free mockup.
- `order`: the full order form, unchanged.
- `referral`: someone refers a business. The referred business becomes the lead (Source = Referral); the referrer's name/email go in Notes and they get a thank-you email.
Notes always say which form was used ("via Free mockup form"), so offers can be compared in Airtable.

Also added: crew illustration (`img/crew.svg`), share image (`img/og.png`), sticky Text/Free mockup bar on phones,
"Text us your logo" links (open the phone's messages app with the rep code in the text), FAQ, service-area line,
LocalBusiness + FAQ schema, `robots.txt`, `sitemap.xml`.

### Settings (bottom of index.html, `window.SI`)
- `social`: paste Instagram / Facebook / TikTok / Google / LinkedIn URLs. Icons appear in the footer only when filled in.
  Also add the same URLs to `"sameAs": []` in the LocalBusiness schema at the top.
- `ga4`: G-SEFGVHGC4R ("Standard Issue Shirt Co." Analytics property, separate from MLXL). `googleAds`: AW-953663456. order.html has the same tags in its head.
- `cfBeacon`: Cloudflare Web Analytics token (free visit counts). `metaPixel`: fill in when Meta ads start.
  Form submits fire `generate_lead` (GA4) / `Lead` (Meta) automatically.
- `referralReward`: the referral offer text.

### Photos
When real crew photos exist, save one as `img/crew.jpg` and change the hero `<img src="img/crew.svg">` to it.

## Mockup funnel (Nov 2026)
- New mockup or order request: `order.php` saves to Airtable, sends the customer the instant "got it" email, then `notify_art()` (inc/funnel.php) emails **art@mlxlpro.com** and crew@ with the details, logo link and a signed **Send mockup** link. Webstore or 50+ piece requests also go to mbrown@.
- Ally opens the link (`mockup.php`), uploads the mockup (JPG/PNG best, PDF ok), picks the offer, pieces and total, and clicks Send. The customer gets the mockup from crew@ with an **Approve & pay** button (`order.html?lead=...` fills in their contact info). Airtable: Status = Mockup sent, Mockup, Mockup price, Mockup pieces, Mockup sent at.
- Follow-ups (`send_followups()`) go out day 2, 5 and 9 after the mockup, only while Status is still **Mockup sent** and they have not paid. Runs with the existing reminder tick and the Hostinger cron (`pay.php?a=reminders&key=...`). **To stop follow-ups, change Status to Replied, Ordered or Lost.**
- Already-paid orders: the mockup email asks them to reply "approved" and no follow-ups are sent.
- Paid orders of $1,500+ or 50+ pieces, and big mockups, alert mbrown@.
