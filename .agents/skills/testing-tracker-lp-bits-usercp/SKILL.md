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
- `/login.php` GET/POST, `/logout.php`, `/signup.php`, `/recover.php`, `/confirm_resend.php` render inside `nexus_legacy` with Nexus card/form/table components.
- Smoke gates: `php artisan test --no-coverage`, `phpstan` default, `phpstan.level6.neon`, `view:cache`, `route:cache`, `openresty -t`.

## Common gotchas

- A new browser context will not have the `c_secure_pass` cookie; set it with `page.setCookie({ name: 'c_secure_pass', value: COOKIE, url: BASE + '/' })` before navigating.
- For sign-up, the form uses client-side password hashing; if testing with `fetch`, either replicate the JS hashing or use a browser `page.click` on the submit button.
- `/comments.php` is not the migrated route; use `/comment/add?type=torrent&pid=1` instead.
- `/recover.php` and `/confirm_resend.php` redirect to `/index.php` when the user is already logged in, so test them in a fresh/incognito context.
