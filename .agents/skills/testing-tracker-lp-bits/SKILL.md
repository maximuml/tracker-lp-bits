---
name: testing-tracker-lp-bits
description: How to run end-to-end tests for tracker-lp-bits in the local Docker/MeiliSearch stack.
---

# Testing tracker-lp-bits end-to-end

Use this skill when asked to test the tracker-lp-bits app in the local Docker Compose stack.

## Test environment prerequisites

1. Docker Compose stack must be running: `docker compose up -d`.
2. The `php` container is `php:8.4-fpm-alpine` and `/var/www/html` maps to the repo.
3. A sysop test user must exist. If not, create one and promote it to `UC_SYSOP` with `uploadpos = 'yes'` and `clear_user_cache(<id>)`.
4. The `c_secure_pass` cookie can be generated for browser/curl use with:
   ```php
   App\Support\AuthCookie::buildToken($user->id, $user->auth_key, time() + 86400);
   ```

## Common environment gotchas

- `basic.BASEURL` may be empty in `settings`, producing `http:///announce.php` and breaking `/announce.php`. Set it to `localhost` (or the real host) and clear Redis settings cache.
- `agent_allowed_family` seed for BiglyBT may contain an invalid regex such as `^BiglyBT\ /3...`. Use a valid regex like `/^BiglyBT\/3\.([0-9])\.([0-9])\.([0-9])/` to avoid `preg_match` warnings from `/announce.php`.
- `php artisan meilisearch:import` may report success but swap to an empty `torrents` index because of `MeiliSearchRepository::doImportFromDatabase()`. Verify the document count with:
  ```
  curl -s 'http://localhost:7700/indexes/torrents/stats'
  ```
  If the count is 0, manually add the test torrents to MeiliSearch or fix the import logic.

## Test flow for setlist upload PRs

1. Open `/upload.php` with a logged-in sysop user.
2. Fill **Torrent name** with a name like `Linkin Park - Hamburg, Germany, Volksparkstadion (03.06.2026)`.
3. Click **Fill setlist** — the button should switch to `Loading...` and become disabled, then the **Description** textarea should be populated.
4. Verify `/setlist_lookup.php?name=<name>` returns JSON with `success=true`, `data` (artist/venue/date/sets/source) and `text` (formatted BBCode).
5. Upload torrents via `takeupload.php` (browser file inputs may not work under automation) using the setlist text from the previous step.
6. Verify `/torrents.php` listings and search (`?search=Linkin+Park`, `?search=Hamburg`, `?search=Volksparkstadion`).
7. Verify `/announce.php` returns valid bencode and `details.php` shows seeders/peers.
8. Verify edit/promote/delete flows.
9. Run `composer validate` and `vendor/bin/phpstan analyse`.
10. Check `docker logs nexusphp-php` for new fatals or deprecation warnings.

## Test flow for `torrents2.php` grid PRs

1. Open `/torrents2.php` (default `card` view). Verify 5 active torrents render in a grid and no JS errors appear in the console.
2. Switch to `?view=table` and click each sort header (Name, Size, Seeders, etc.); verify `view=table` and `sort`/`type` are preserved in the URL.
3. Switch to `?view=compact` and verify the compact list renders.
4. Test the view switcher with active filters (`incldead`, `spstate`, `cat`, `search`, `pageSize`) and confirm the current filter state is preserved.
5. Test category filtering:
   - Using the search-box checkbox (`cat401=1`) should preserve `view`.
   - Clicking a category icon should also preserve `view` and `pageSize`.
6. Test keyword search (`?search=Linkin&search_area=0&search_mode=0`) and the **including dead** vs **active** dropdown.
7. Test dropdowns: `incldead`, `spstate`, `inclbookmarked`.
8. Test numeric ranges: `size_begin/end`, `seeders_begin/end`, `leechers_begin/end`, `times_completed_begin/end`, `added_begin/end`.
9. Test pagination with `pageSize=2`:
   - Direct URLs `?view=card&pageSize=2` and `?view=card&pageSize=2&page=1` work.
   - `Prev`/`Next`/numbered pager links must preserve both `view` and `pageSize`.
10. Hover/click card cover, card title, and table title links and verify each lands on `details.php?id=<id>&hit=1` with no 500.
11. Click the **Search Box** header and verify the filter body expands/collapses.
12. Run static checks inside the `nexusphp-php` container:
    - `php -l public/torrents2.php app/Support/TorrentGrid.php app/Support/TorrentTable.php`
    - `composer validate --strict`
    - PHPStan default, `level5`, `level5.app`, `level6`
    - `vendor/bin/phpunit --testsuite Unit --no-coverage`
13. Check `docker compose logs --tail=100 php` for new `WARNING`/`ERROR`/`NOTICE` lines when browsing `torrents2.php`.

## Automation notes for this page

- Native mouse clicks on the small view-switcher buttons and **Go!** button may miss due to coordinate scaling; prefer `document.querySelector(...).click()` or direct `window.location` navigation while the recording captures the resulting page state.
- Table sort links include `&` in the `href`; scope selectors to the table header row and match the full `sort=1&type=desc` substring to avoid clicking the wrong link.
- Table title links are inside a `<b>` with `title="<torrent name>"`; use `document.querySelector('a[title="CriticalPathTest"]').click()` rather than a generic `details.php` selector, which can match the uploader/userdetails link instead.

## Known limitations

- The legacy autocomplete on `/torrents.php` may not register native keystrokes in headless automation; trigger `suggest(0, '<term>')` from the console to verify it.
- GitHub Actions CI may not start due to account billing/spending limits; rely on local Docker verification when that happens.
- `torrents2.php` hot-search results are cached globally under the Redis key `en_hot_search_torrents2`. The cached HTML stores the `view`/`pageSize` that was active when the cache was first populated, so switching views can display stale hot-search links. Clear the cache (`redis-cli DEL en_hot_search_torrents2`) before verifying hot-search link preservation, and retest on a second view.
- The `torrents2.php` search-box form only has a hidden `<input name="view">`, so submitting the form from a `pageSize=2` URL drops `pageSize`. Add a hidden `pageSize` input if preserving page size through form submissions is required.
- Category icon links and pagination links now preserve `view`/`pageSize` in PR #166, but the fixes must be verified against the Redis hot-search cache and the search-box form limitations above.