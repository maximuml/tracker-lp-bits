---
name: testing-tracker-lp-bits-phase17-staff-admin
description: End-to-end testing notes for the Phase 17 staff/admin listing pages (modtask, staffbox, staffmess, contactstaff, forummanage, moforums, faqmanage, faqactions, fields, formats, videoformats).
---

# Phase 17 staff/admin listing pages — end-to-end testing

## Test account
- Use the sysop smoke-test cookie (`smoketoptenadmin`, id=10211, class 15) for routes that require `FORUM_MANAGE` / admin permissions.
- Ensure `basic.BASEURL` is set to `openresty` (or the host used for curl/browser tests) and Laravel/OpenResty caches are cleared before host-side requests.

## Static gates to run

```bash
docker compose -p tracker-lp-bits exec -T php composer validate --strict
git diff --diff-filter=ACMR --name-only origin/php8..HEAD | grep -E '\.php$' | \
  while read f; do [ -f "$f" ] && echo "/var/www/html/$f"; done | \
  tr '\n' '\0' | xargs -0 -P4 -n1 docker compose -p tracker-lp-bits exec -T php php -l
docker compose -p tracker-lp-bits exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=2G <changed-php-files>
docker compose -p tracker-lp-bits exec -T php vendor/bin/phpstan analyse -c phpstan.level6.neon --no-progress --memory-limit=2G <changed-php-files>
docker compose -p tracker-lp-bits exec -T php php -d memory_limit=2G vendor/bin/phpunit --no-coverage
docker compose -p tracker-lp-bits exec -T php php artisan view:cache
docker compose -p tracker-lp-bits exec -T php php artisan route:cache
docker compose -p tracker-lp-bits exec -T php php artisan cleanup:run --force
docker compose -p tracker-lp-bits exec -T openresty openresty -t
```

## Common regressions that have been fixed in this branch

1. **Blade views using unqualified class names** — `SupportContext` / `UserClass` must be referenced as `\App\Support\...` inside raw `<?php` blocks in Blade views.
2. **`legacyAbortResponse` return type** — controllers that call `legacyAbortResponse()` must declare `Response` in their return union (e.g. `View|Response|RedirectResponse`).
3. **`Validators::assertId()`** — does not exist; use `Validators::isId()` and a manual abort.
4. **Double-base redirects** — do not build `redirect($baseUrl . '/page.php')` when `$baseUrl` is just a hostname. Use `redirect('/page.php')` or `redirect('page.php')`.
5. **`fields.php` PageLayout context** — the `view` branch must call `Html::stdhead()` before `Html::stdfoot()`/`Frame::mainFrameClose()`.
6. **`Nexus\Field\Field->buildFieldForm()` language keys** — ensure `lang_fields` contains all keys the form expects.

## Smoke-test checklist

### GET
- `/modtask.php?action=edituser&id=<lower-class-user>`
- `/staffbox.php`
- `/staffbox.php?action=answermessage&answeringto=<id>&receiver=<id>`
- `/staffmess.php`
- `/contactstaff.php`
- `/forummanage.php` and `?action=newforum`
- `/moforums.php?action=forum`
- `/faqmanage.php`
- `/faqactions.php?action=addsection`
- `/faqactions.php?action=additem&inid=<link_id>&langid=<lang_id>`
- `/fields.php` and `?action=add`
- `/formats.php`, `/videoformats.php`
- `/catmanage.php`, `/settings.php` (if reachable/expected)

### POST
- `POST /takestaffmess.php` with `classes[]`, `subject`, `msg`, `sender`, `receiver`
- `POST /takecontact.php` with `subject`, `body`
- `POST /staffbox.php?action=takeanswer` with `receiver`, `answeringto`, `body`
- `POST /forummanage.php?action=addforum` with `name`, `desc`, `overforums`, `moderator`, `readclass`, `writeclass`, `createclass`, `sort`
- `POST /moforums.php?action=addforum` with `name`, `desc`, `viewclass`, `sort`
- `POST /faqactions.php?action=addnewsect` with `title`, `language`, `flag`
- `POST /faqactions.php?action=addnewitem` with `question`, `answer`, `flag`, `categ`, `langid`
- `POST /fields.php?action=submit` with `name`, `label`, `type`, `required`, `is_single_row`, `help`, `options`, `priority`, `display`

## UI/viewport verification

- Capture desktop (1280×900) and mobile (375×812) full-page screenshots for each affected page.
- On mobile, check `document.body.scrollWidth <= window.innerWidth` to confirm no horizontal overflow.

## Devin Secrets Needed
- None beyond the standard `tracker-lp-bits` Docker Compose stack and the sysop smoke-test cookie.
