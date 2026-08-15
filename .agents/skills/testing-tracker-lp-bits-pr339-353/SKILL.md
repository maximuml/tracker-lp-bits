---
name: testing-tracker-lp-bits-pr339-353
description: End-to-end testing notes for the Phase 17/18/20 PR integration branch PRs #339-#353 in the local Docker/MeiliSearch stack.
---

# Testing tracker-lp-bits PRs #339-#353 end-to-end

Use this skill when asked to run the integration E2E for PRs #339-#353 (or later Phase 17/18 cleanup + Phase 20 API parity) in `/home/ubuntu/repos/tracker-lp-bits`.

## Scope

- **Phase 17/18 cleanup legacy routes**: `/index.php`, `/mybonus.php`, `/my_bonus.php`, `/forums.php`, `/usercp.php` (and actions), `/usersearch.php`, `/catmanage.php`, `/offers.php`, `/messages.php`, `/sendmessage.php`, `/makepoll.php`, `/polloverview.php`, `/latestcomments.php`, `/shoutbox_history.php`, `/news.php`, `/bitbucketlog.php`, `/friends.php`, `/complains.php`, `/invite.php`, `/log.php`, `/torrents.php`, `/details/3`, `/edit?id=3`, `/userdetails?id=1`, `/viewfilelist?id=3`, `/viewpeerlist?id=3`, `/getusertorrentlistajax.php`, `/ok.php`, `/image.php`.
- **Phase 20 API parity endpoints**: `GET /api/v1/shoutbox`, `GET /api/v1/offers`, `GET /api/v1/usercp/settings`, `POST /api/v1/usercp/forum`.
- **POST/redirect flows**: PM send/delete via `takemessage.php`/`deletemessage.php`; offer add/vote/delete; forum newtopic; index poll vote; usercp personal/forum save; makepoll submit.

## Test environment

- Docker project `tracker-lp-bits`; containers `php`, `openresty`, `mysql`, `redis`, `meilisearch`.
- Web host `http://openresty/`; `basic.BASEURL` should be `openresty`.
- `LegacyRequestMiddleware` must bypass `api`, `livewire`, `filament`, `nexusphp`, `horizon` prefixes so `/nexusphp` and Livewire update routes do not 404.

## Auth cookies and API token

- Generate a `c_secure_pass` cookie for user `id=1` (sysop) and save it, e.g.:
  ```bash
  docker compose -p tracker-lp-bits exec -T -e APP_KEY='<app-key>' php php -r \
    'require "/var/www/html/vendor/autoload.php"; require "/var/www/html/bootstrap/app.php"; echo \App\Support\AuthCookie::buildToken(1, null, time()+3600);' \
    > /home/ubuntu/phase19-cookie.txt
  ```
- `/api/v1/*` routes are protected by `auth:sanctum` and the Sanctum stateful domain `openresty` is not in the default `SANCTUM_STATEFUL_DOMAINS`, so cookie-only API calls 302 to `login.php`. Generate a personal access token for the sysop and call API with `Authorization: Bearer <token>`.
- Offer voting must be done by a user other than the offer creator. Generate a `c_secure_pass` cookie for a secondary user (e.g. `id=10037`) for the vote flow.

## Common pitfalls

- `POST /api/v1/usercp/settings` and `POST /api/v1/usercp/forum` may return `ret:0` but not persist fields that are not in `User::$fillable`. Watch the DB values, not just the JSON status.
- `offers.php?id=<id>&del_offer=1&sure=1` may redirect successfully while leaving the row in `offers` because of variable shadowing in `offers_content.php`.
- `/image.php?action=regimage` may return 404 (`Captcha driver does not support image rendering`) if the captcha `image` driver is not configured; this is usually a pre-existing environment issue, not a regression.
- The legacy partial `makepoll.php` must wrap its `<table>` inside `<form>`; otherwise browsers eject the inputs and the form cannot be submitted.
- `/my_bonus.php` needs `LegacyRequestMiddleware::EXTRA_LANG_FILES` to map `my_bonus` to `mybonus.php` or language labels render empty.

## Quick pass/fail gate

- `composer validate --strict`
- `php -l` on changed PHP files
- `php artisan view:cache` and `php artisan route:cache`
- `phpstan` default, level5.app, level6
- `phpunit --testsuite Unit --no-coverage`
- `openresty -t`
- Headless Playwright regression against `http://openresty/` for all GET routes and POST flows, with DB mutation checks.
