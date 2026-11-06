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
