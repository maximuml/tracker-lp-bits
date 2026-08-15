---
name: testing-tracker-lp-bits-action-pages
description: How to end-to-end test the legacy action pages (delete, fastdelete, takereseed, takeupdate, takeinvite) that were moved to Laravel controllers in PR #318.
---

# Testing tracker-lp-bits action pages (PR #318)

Use this skill when asked to end-to-end test the migrated legacy action pages on the local Docker Compose stack.

## Environment prerequisites

1. Docker Compose stack must be running (`docker compose up -d`).
2. Authenticate as a sysop test user (`id=10211`, class 15) using the smoke `c_secure_pass` cookie or `/login.php`.
3. Ensure the user has `uploadpos = 'yes'` and at least one invite if testing `/takeinvite.php`.
4. For `/takeinvite.php` to persist an invite row locally:
   - Set `main.invitesystem = yes`.
   - Set `smtp.smtptype = none` (or `default` with a working local mailer).
   - Replace `/usr/sbin/sendmail` inside the `php` container with an exit-0 script so `mail()` returns `true` (or configure `sendmail` to discard).

## Creating test torrents

Use the `/upload.php` UI or generate a minimal `.torrent` and POST to `/takeupload.php`. For PR #318 you need three torrents owned by the sysop user with `seeders = 0`:

- One for the normal `delete.php` flow.
- One for `fastdelete.php`.
- One for `takereseed.php` and the `reports.php`/`takeupdate.php` flow.

## Individual action pages

### `/delete.php` — torrent delete with reason

1. Open `/details.php?id=<id>` and click **Edit** to reach `/edit.php?id=<id>`.
2. At the bottom of the edit page, use the delete form (`action="delete.php"`, `name="reasontype"`, `name="reason[]"`).
3. Select reason type 5 and enter a reason like `test`.
4. Submit via the visible button; expect a `Torrent deleted.` success page.
5. Verify the torrent row is removed and `/details.php?id=<id>` returns 404.

Note: The delete form is on `/edit.php`, not `/details.php`.

### `/fastdelete.php` — staff fast delete

1. GET `/fastdelete.php?id=<id>` as sysop (no `sure`).
2. Confirm the page contains the confirmation link `/fastdelete.php?id=<id>&sure=1`.
3. Follow the confirmation link; expect redirect to `/torrents.php` and the torrent removed.

### `/takereseed.php` — reseed request dispatch

1. Use a torrent with `seeders = 0`.
2. Visit `/takereseed.php?reseedid=<id>` (the details page links may use `reseedid`).
3. Expect a `Reseed request sent.` success page and `torrents.last_reseed` updated.

### `/reports.php` + `/takeupdate.php` — report batch actions

1. Create or locate a report row in `reports` (e.g. by reporting a torrent).
2. Visit `/reports.php` as staff; confirm the report checkbox `name="delreport[]"` with the correct `value`.
3. Check the box and click:
   - `setdealt` → marks `dealtwith = 1` and `dealtby = <staffId>`, then redirects to `/reports.php`.
   - `delete` → removes the report row, then redirects to `/reports.php`.

Important: Submitting the form with `form.submit()` in automation does not include the clicked submit button value; use a native click on the `setdealt`/`delete` button.

### `/invite.php` + `/takeinvite.php` — invite email + hash handling

1. Visit `/invite.php?id=<uid>&type=new`.
2. Fill `email`, `body`, and choose a `hash`:
   - **Temporary hash** — create a row in `invites` (`inviter`, `hash`, `expired_at` in the future, empty `invitee`) and select it; this avoids the `permanent` branch.
   - **`permanent`** — currently fails with `Undefined array key "passhash"` at `SystemController.php:130` because `SupportContext::getUser()` does not expose `passhash`. Fix the controller before testing this branch.
3. Submit to `/takeinvite.php?id=<uid>` and expect a redirect to `/invite.php?id=<uid>&sent=1`.
4. Verify the invite row is updated (`invitee` set, `time_invited` set) and, for temporary hashes, `invites` count is decremented.

## PR #355 action pages

PR #355 migrated 15 legacy partials into Laravel controllers/services. Key test details:

- `/deletemessage.php` and `/takemessage.php` are now `MessageController`/`MessageService`.
  - `takemessage.php` POST works and returns a redirect to `/messages.php`.
  - `/deletemessage.php?id=<id>&type=in` returns HTTP 302, but as of the tested branch the message row is **not removed** because `MessageService::deletemessage` loads the row with `first(['receiver','sender','location','saved','unread'])` (missing `id`), so the subsequent `$msg->delete()` and `$msg->update(...)` calls no-op. The fix is to include `'id'` in the selected columns (or switch to `Message::query()->where('id', $id)->delete()`).
- `/bookmark.php?torrentid=<id>` toggles `bookmarks`; the legacy parameter is `torrentid`, **not** `id`.
- `/suggest.php` reads `q`, not `keyword` (the legacy JS sends `suggest.php?q=...`).
- `/attachment.php` (GET upload form and POST multipart) is now `UtilityController::attachment` + `AttachmentLegacyService`. For class 15, `.doc` files are accepted; the uploaded file is stored in `attachments/YYYYMM/` and a row is inserted in `attachments`.
- `/getattachment.php?id=<id>&dlkey=<dlkey>` downloads the file; `/getattachment.php?id=0` returns HTTP 400.
- `/takeflush.php?id=<uid>` returns HTTP 200 with the message `X ghost torrents were sucessfully cleaned.` (note typo in `sucessfully`).
- `/take-increment-bulk.php` POST returns HTTP 302 redirect to `/increment-bulk.php?sent=1&type=<type>` on success. Use `classes[]` to filter by user class.
- `/takeconfirm.php?id=<uid>` returns a staff confirmation page; without selected users it just says there is no buddy to confirm.
- `/torrentrss.php` requires a `passkey`; without it returns HTTP 400 `require passkey`.
- `/confirmemail/<id>/<md5>/<email>` requires `users.editsecret` to match the hashed value. A valid confirmation returns HTTP 302 to `/usercp.php?action=security&type=saved`; invalid returns 404.
- `/shoutbox.php` GET/POST and `/shoutbox_history.php` are now `ShoutboxController`. Posting uses `sent=yes&shbox_text=...`; a 60-second per-user `shoutbox:<uid>` lock in Redis can produce a 429 if hit too frequently.
- `/email-gateway.php` now returns an empty HTTP 200 response.
- `/ajax.php?action=getOffer&params[id]=1` returns a JSON `ret:0` response.
- `/invite.php?id=<uid>` and `?id=<uid>&type=new`, `?id=<uid>&menu=sent|tmp|invitee` are now `InfoController::invite`; ensure `main.invitesystem = yes` and the user has invites > 0.

## Mobile viewport regression

Set viewport to `375x812`, visit the tested action pages, and check `document.body.scrollWidth <= window.innerWidth` to confirm no horizontal overflow.

## Smoke / quality gates

Run in the `php` container:

```bash
docker compose exec -T php php -d memory_limit=2G vendor/bin/phpunit --no-coverage
docker compose exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=2G
docker compose exec -T php vendor/bin/phpstan analyse -c phpstan.level6.neon --no-progress --memory-limit=2G
docker compose exec -T php php artisan view:cache
docker compose exec -T php php artisan route:cache
docker compose exec -T php php artisan cleanup:run --force
docker compose exec -T openresty openresty -t
```

Notes:
- `php artisan test` may exhaust the default 128M CLI memory when the cached routes file is large; use `php -d memory_limit=2G vendor/bin/phpunit` instead.
- The `Feature/CoreTrackerTest::test_web_details_page_renders_torrent` failure with `Invalid search box: 255` is usually a pre-existing schema/factory issue (`categories.mode` is `tinyint unsigned` while `SearchBox` ids can exceed 255) rather than a PR #318 regression.

## Devin Secrets Needed

None.
