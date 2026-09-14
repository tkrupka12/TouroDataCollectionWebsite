# Touro website tracker

PHP dashboard for touro.org status (sidebar) and Crazy Egg heatmap **snapshots** (upper left). Nginx should serve only the `public/` folder.

## Folder layout

```text
public/                         website (Nginx document root)
  index.php                     entry point: routes then loads the page
  views/dashboard.php           page HTML
  assets/css/style.css
  assets/js/app.js

src/                            PHP the browser should not download
  bootstrap.php                 loads .env
  api/site-status.php           JSON for the live sidebar
  api/heatmap-snapshots.php     JSON for Crazy Egg snapshots
  site-tracker/Tracker.php      checks touro.org
  heatmap/CrazyEggClient.php
  heatmap/CrazyEggStore.php

config/app.php                  site settings
bin/crazyegg.php                command-line snapshot fetch
storage/site-checks/            saved uptime history
storage/heatmap-snapshots/      saved snapshot JSON
.env                            secrets (not in git)
```

## Setup

```bash
cp .env.example .env
```

Put **Heatmap Management** API key and API secret in `.env` (not the Conversion Tracking key).

Crazy Egg: **Options → site Settings → API → Heatmap Management API Credentials**.

## Run locally

Document root must be `public/`:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open http://127.0.0.1:8080

## Nginx

Set `root` to `.../WebsitePracticeInternship/public` (not the repo root). That keeps `.env`, `src/`, and `storage/` private.

```nginx
root /var/www/WebsitePracticeInternship/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

## Commands

```bash
php bin/crazyegg.php list
php bin/crazyegg.php get <snapshot-id>
php bin/crazyegg.php lookup https://www.touro.edu/
php bin/crazyegg.php fetch
php bin/crazyegg.php watch
```

On the dashboard, type a URL in the heatmap panel and click **Look up**. That filters saved snapshots for that page (`http`/`https` and `www` are ignored). JSON is written to `storage/heatmap-snapshots/`. The heatmap panel refreshes every 15 minutes by default.

## What the API returns vs dashboard export

This app stores snapshot **metadata** (id, name, URL, status, visits, dates). It does not draw the colored heatmap. Export CSV/JSON from Crazy Egg for click-level data.

The Conversion Tracking key only POSTs goals to `https://track.crazyegg.com/api/v1`. Do not use it here.
