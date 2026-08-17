---
name: Testing PR #367 / Phase 23 code quality
description: End-to-end testing notes for the Phase 23 legacy service/type-widening cleanup PR, covering static gates, Forum/Message/Offer/Attachment/Captcha lifecycles, login flow, and headful Playwright gotchas.
---

## Preconditions

- Docker Compose stack `tracker-lp-bits` is up (`nexusphp-openresty`, `nexusphp-php`, `nexusphp-mysql`, `nexusphp-redis`, `nexusphp-meilisearch`).
- `basic.BASEURL` is set to `openresty`.
- A sysop user exists (`id=10211`, username `devintest`) and a normal user (`id=10037`) exists.
- `security.iv` may be temporarily set to `yes` during the captcha login test; reset to empty afterwards.
- Generate `c_secure_pass` cookies inside the `nexusphp-php` container using `App\Support\AuthCookie::buildToken($userId, $authKey, time() + 86400)`.

## Static gates

Run inside `nexusphp-php` from `/var/www/html`:

- `composer validate --strict`
- `php -l` on changed `app/` and `routes/` files
- `php vendor/bin/phpstan analyse --no-progress --memory-limit=2G`
- `php vendor/bin/phpstan analyse -c phpstan.level5.app.neon --no-progress --memory-limit=2G`
- `php vendor/bin/phpstan analyse -c phpstan.level6.neon --no-progress --memory-limit=2G`
- `php vendor/bin/phpstan analyse -c phpstan.level7.neon --no-progress --memory-limit=2G`
- `php vendor/bin/phpstan analyse -c phpstan.level8.neon --no-progress --memory-limit=2G`
- `php -d memory_limit=2G vendor/bin/phpunit --testsuite Unit --no-coverage`
- `php artisan view:cache && php artisan route:cache`
- `docker exec -i nexusphp-openresty openresty -t`

## Smoke URLs to check

With a valid sysop `c_secure_pass` cookie:

- `/index.php`
- `/login.php`
- `/torrents.php`
- `/topten.php`
- `/forums.php`
- `/messages.php`
- `/offers.php`
- `/usercp.php?action=personal`
- `/shoutbox.php`
- `/attachment`
- `/image?action=regimage&imagehash=<fresh_hash>` (must return `Content-Type: image/png`)

All should return HTTP 200 and contain no `Fatal error`, `Whoops`, `Page Expired`, or `Internal Server Error`.

## Important route/method caveats

- Legacy `.php` GET URLs route through `LegacyRequestMiddleware` and work for page loads.
- Legacy **POST** mutations are routed through the Laravel controllers. Use the non-`.php` paths for POSTs:
  - `/forums` for `action=post`, `movetopic`, `deletetopic`, `deletepost`
  - `/takemessage` to send a PM
  - `/offers` for `new_offer`, `allow_offer`, `finish_offer`, `del_offer`
  - `/usercp` for all user control panel saves
  - `/attachment` for file uploads
- `VerifyCsrfToken` exempts `forums`, `offers`, `takemessage`, `usercp`, `attachment`, `shoutbox`, etc., but **not** `/messages`. Use GET-based `messages.php?action=moveordel&...` mutations to avoid CSRF handling, or provide an `X-XSRF-TOKEN` header for `/messages` POSTs.
- `ForumService::handleMoveTopic` reads `topicid` from the query string, not the POST body. Send `POST /forums?topicid=<id>` with body `action=movetopic&forumid=<dest>`.
- `OfferService::handleFinish` respects `minoffervotes` (default 15). A single `vote=yeah` is not enough to finish/allow. To test staff allow, use `allow_offer=1&offerid=<id>` instead.
- `OfferService::handleDelete` requires `del_offer=1` and `sure=1`.
- `/takelogin.php` is not the correct POST target; the Laravel `/login` route requires `_token`, `username`, `password`, `imagehash`, and `imagestring`.

## Captcha

- The login form still renders `<img src="image.php?action=regimage&imagehash=...">`, which returns 404 because the actual route is `/image`.
- The captcha still functions for verification: generate a fresh `imagehash` with `CaptchaManager::driver('image')->issue()`, query `regimages.imagestring`, and POST to `/login`.
- `/image?action=regimage&imagehash=<hash>` returns a PNG and tests `ImageCaptchaDriver`.

## Attachment / thumbnail

- `AttachmentLegacyService` creates a `.thumb.jpg` only when the source image is larger than the configured thumbnail dimensions. Use a PNG larger than the default (e.g., 600×600) to guarantee thumbnail generation.
- The thumbnail file is `{location}.thumb.jpg` under `/var/www/html/attachments/`.

## Login and session persistence

- POST `/login` with `_token`, username, password, `imagehash`, and `imagestring` returns 302 to `index.php` and sets the `c_secure_pass` cookie.
- Loading `/index.php` with that cookie renders the authenticated username in the header.

## Headful / Playwright notes

- `chromium.connectOverCDP('http://localhost:29229')` works with the `--remote-debugging-port=29229` Chrome launch script.
- Use `context.addCookies` with `domain: 'openresty'` and `path: '/'`.
- The `usercp` personal form uses radio inputs (`gender`), not a `<select>`.
- The `attachment` form is inside an invalid `<table><form>` structure; `document.forms[0]` may be the wrong form. Select the form by `name="attachment"` or `action*="attachment.php"`.
- The `login` form in `login.blade.php` has `action="takelogin.php"`, which returns 419 under `POST` from curl. For Playwright, override the action to `/login` and submit dynamically with the CSRF token and captcha string.
- The `messages.php` page may cause Playwright screenshot timeouts with `networkidle`; prefer `domcontentloaded` and disable animations.

## Factory admin user

If the sysop password is not available, create a factory admin user for the login/captcha test:

```php
$user = App\Models\User::factory()->admin()->create(['class' => 15]);
$token = App\Support\AuthCookie::buildToken($user->id, $user->auth_key, time() + 86400);
```

Use the default factory password (typically `123456`) through `WebAuthService::validatePassword`.

## Devin Secrets Needed

None.
