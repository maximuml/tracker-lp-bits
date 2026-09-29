---
name: Testing tracker-lp-bits usercp / auth / upload flows
description: How to end-to-end test the migrated usercp, auth, and upload flows in the local Docker stack for tracker-lp-bits.
---

## Devin Secrets Needed

None for the local Docker stack.

## When to use

Use when verifying PRs that migrate `usercp.php`, auth pages, bitbucket/attachment uploads, or legacy partials to the `nexus_legacy` layout.

## Setup

1. Ensure the Docker Compose stack is up: `cd /path/to/tracker-lp-bits && docker compose up -d`
2. Generate a fresh sysop `c_secure_pass` cookie (e.g. from `/home/ubuntu/get_cookie.php` or by logging in via the UI) and store it at a known path.
3. Disable captcha and raise limits for auth testing:
   ```
   docker compose exec php php artisan tinker --execute='\App\Support\Settings::saveBatch(["security.iv" => "", "security.maxip" => 100, "main.maxusers" => 100000, "main.registration" => "yes"]);'
   ```
4. Rebuild caches:
   ```
   docker compose exec php php artisan view:cache
   docker compose exec php php artisan route:cache
   docker compose exec openresty openresty -t
   ```

## Useful test accounts

- `sysop` / `admin123` (class 16, id=1) — class `StaffLeader`.
- New regular users can be created with the `Tests\Feature\CriticalPathTest` flow, or manually through `/signup.php`.

## Important behavior notes

- Legacy `.php` URLs are rewritten by `LegacyRequestMiddleware` to Laravel routes. Use the legacy URLs in the browser/curl.
- `/usercp` is listed in `App\Http\Middleware\VerifyCsrfToken::$except`, so a missing `_token` will **not** return 419. The `form()` helper still injects a token, so authenticated saves succeed when a token is present.
- To test CSRF rejection, POST to `/takelogin.php` (or another non-excluded web route) without `_token`.
- Some legacy partials still call `stdhead()`/`stdfoot()` directly. When wrapped in `layouts.nexus_legacy` this can produce duplicated headers/footers.
- `bitbucket-upload.php` rejects very small (1×1) PNGs with "Sorry, the uploaded png failed processing." Use a 10×10 or larger PNG for upload tests.
- The local `puppeteer-core` build may not expose `Locator.setInputFiles`. Set file inputs via `DataTransfer` in `page.evaluate`:
  ```js
  const base64 = fs.readFileSync('/tmp/test10.png').toString('base64');
  const handle = await page.evaluateHandle((b64, name) => {
    const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const file = new File([bytes], name, { type: 'image/png' });
    const dt = new DataTransfer();
    dt.items.add(file);
    return dt.files;
  }, base64, 'test10.png');
  await page.evaluate((files, sel) => {
    const input = document.querySelector(sel);
    input.files = files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }, handle, 'input[type=file][name="file"]');
  ```

## Key assertions

- `/usercp.php` renders with the Nexus header/footer and shows the usercp menu.
- `/usercp.php?action=personal&type=save` with `_token` redirects to `?action=personal&type=saved` and shows `Saved!`.
- `/bitbucket-upload.php` GET and POST (valid image) render the upload form and result in the `nexus_legacy` layout.
- `/attachment.php` POST returns a `<script>parent.tag_extimage('[attach]...')</script>` snippet.
- `/login.php` GET (the POST route is `/login` — posting to `/login.php` drops the body and returns 419), `/logout.php`, `/signup.php`, `/recover.php`, `/confirm_resend.php` render inside `nexus_legacy` with Nexus card/form/table components.
- Smoke gates: `php artisan test --no-coverage`, `phpstan` default, `phpstan.level6.neon`, `view:cache`, `route:cache`, `openresty -t`.

## Common gotchas

- A new browser context will not have the `c_secure_pass` cookie; set it with `page.setCookie({ name: 'c_secure_pass', value: COOKIE, url: BASE + '/' })` before navigating.
- For sign-up, the form uses client-side password hashing; if testing with `fetch`, either replicate the JS hashing or use a browser `page.click` on the submit button.
- `/comments.php` is not the migrated route; use `/comment/add?type=torrent&pid=1` instead.
- `/recover.php` and `/confirm_resend.php` redirect to `/index.php` when the user is already logged in, so test them in a fresh/incognito context.

## Usercp field-level audit notes (verified Sep 2026)

- Tabs with saveable forms: `personal`, `tracker`, `forum`, `security` (`UsercpController::$allowedActions`); `messenger`/`home` are display-only.
- personal/tracker/forum save via `type=save` → redirect `?type=saved`. Security is **2-step**: `type=save` → confirm page (fill `input.oldpassword` — `auth-form.js` hashes it into `input[name=response]`) → `type=confirm` → `?type=saved&passkey=1&privacy=1` flags.
- Field→column map: personal→parked/acceptpms/deletepms/savepms/commentpm/notifs/gender/country/tracker_url_id/avatar/info; tracker→~20 users columns + `users.notifs` bracket string `[cat401][pm][incldead=1]`; forum→topicsperpage/postsperpage/avatars/signatures/showlastpost/clicktopic/signature; security→privacy/passkey/chpassword/email/two_step.
- **Radio re-render (FIXED)**: tinyint enum columns are mapped via `Enum::tryFrom(int)?->stringValue()` so radios render checked (acceptpms, gender, timetype, appendpromotion, clicktopic, fontsize, tooltip). Saving with no radio checked still writes the `fromStringSafe('')` default — a real form always posts the checked radio.
- **Category notification checkboxes (FIXED)**: `collectNotifPreferences` uses legacy presence semantics (prefix+digits key shape), so checked boxes collect and `updateTracker` rebuilds `users.notifs` correctly.
- **ttlastpost (FIXED)**: absent checkbox → explicit `showlastpost=0` write; unchecked now disables again.
- avatar input accepts ONLY absolute `https?://…(jpg|gif|png|jpeg)` (verified): invalid non-empty → keep current; empty field + `savatar` echo → clears (`savatar` value equal to stored avatar is treated as the echo, not a pick); empty field + different `savatar` → the pick applies (gallery URL or "Nothing" → `pic/default_avatar.png`). Text field non-empty always wins over `savatar`.
- `notifs[option]` checkboxes (topic_reply/hr_reached) write `[option]` keys into `users.notifs`; `pmnotif`/`emailnotif` may be class-gated (absent for lpfan05).
- Checkboxes posting `value=yes` work (`in:yes`/`=== 'yes'`); valueless ones post `'on'` → only `has()`-based fields (deletepms/savepms) work with them.
- Sysop usercp behaves identically to seeded users (same defects, not class-gated).
- mysql recipe: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T mysql sh -c 'mysql -u$MYSQL_USER -p$MYSQL_PASSWORD nexusphp -e "SQL"'` — always qualify `nexusphp` DB.
