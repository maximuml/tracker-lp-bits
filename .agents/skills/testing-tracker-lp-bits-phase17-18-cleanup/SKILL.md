---
name: testing-tracker-lp-bits-phase17-18-cleanup
description: End-to-end testing notes for Phase 17/18 cleanup PR (#356) which drains direct DB queries from app/Support and moves legacy action/mutation code into Laravel controllers/services/repositories.
---

# Testing PR #356 (`devin/phase17-18-cleanup`)

## Scope

This covers the legacy pages migrated into `ForumService`, `OfferService`, `MessageService`, `UsercpController`/`UsercpRepository`, `AdminController::catmanage`, and the headless/headful regression checklist used for the `devin/e2e-pr356` integration branch.

## Test fixture caveats

- The `c_secure_pass` cookie must be freshly generated with `App\Support\AuthCookie::buildToken($userId, $authKey, time() + 86400)`. Tokens are time-bound and will fail after expiry.
- A secondary user (e.g. `id=10037`, `class=1`) is required to exercise offer voting because the offer owner cannot vote on their own offer.
- `catmanage.php?action=del&type=source` expects an `id` from the `sources` table. The `sources.name` column is limited (≤20 chars in the test stack), so generate short unique names like `pr356-<8 random chars>` and query the generated `id` instead of hardcoding `id=7`.
- `users.clientselect` is `tinyint unsigned` (max 255). If the local `agent_allowed_family` table `AUTO_INCREMENT` exceeds 255, `TasksTest::test_cleanup_class_5_performs_all_housekeeping` may fail because test-created `clientselect` values wrap to 255. Reset `agent_allowed_family` auto-increment or truncate accumulated test rows before the PHPUnit gate.
- The legacy `/shoutbox.php` page renders inside an iframe/JS form. For headless POST testing send `sent=yes&shbox_text=...`; for headful demos screenshot the page rather than trying to `page.fill()` a top-level input.

## Key routes and parameters

- Forum new topic: `GET /forums.php?action=newtopic&forumid=<id>` submits `POST /forums.php` with `action=post&id=<forumid>&type=new&subject=...&body=...`. Expect redirect to `/forums.php?action=viewtopic&topicid=<topicid>&page=last#pid<postid>`.
- Offer add: `GET /offers.php?add_offer=1` then `POST /offers.php` with `new_offer=1&type=<catid>&name=...&body=...`. Redirect to `/offers.php?id=<offerid>&off_details=1`.
- Offer vote: `GET /offers.php?id=<offerid>&vote=yeah|against` as a non-owner user.
- Offer delete: `POST /offers.php` with `id=<offerid>&del_offer=1&sure=1&reason=...` as the offer owner or staff. Expect redirect to `/offers.php` and row removed.
- PM send: `POST /takemessage.php` with `receiver=<id>&subject=...&body=...&save=yes|no`.
- PM delete: `GET /deletemessage.php?id=<msgid>&type=in|out`. `type=in` for an unsaved message removes the row; for a saved message it moves it to the outbox (`location=0`, `saved=yes`). `type=out` then removes the saved row.
- Usercp save forms: `POST /usercp.php` with `action=personal|forum|tracker&type=save&...`. Personal includes `gender`, `acceptpms`, `country`; forum includes `topicsperpage`, `postsperpage`; tracker includes `torrentsperpage`, `pmnum`, `sbnum`, `sbrefresh`, etc. `action=security` is GET-only in this flow.
- Catmanage: `GET /catmanage.php?action=view&type=source|category|...` and `GET /catmanage.php?action=del&type=...&id=<id>`. Expect 302 back to `action=view` and row removed.
- Shoutbox: `GET /shoutbox.php`, `POST /shoutbox.php?sent=yes&shbox_text=...`, `GET /shoutbox_history.php`.

## Regression scripts used

- `/home/ubuntu/pr356-smoke.sh` — authenticated `curl`/DB-mutation smoke.
- `/home/ubuntu/pr356-demo.mjs` — headful Playwright demo.
- `/home/ubuntu/pr356-regression-results.json` — combined static + smoke results.

## Common gotchas

- `set -u` in bash smoke scripts can mask uninitialized variables; ensure dynamic fixture IDs are captured from the DB.
- Legacy forms often use `input[name="subject"]` and `textarea[name="body"]`. The BBCode editor is just a `textarea` named `body`.
- The `/nexusphp` Filament dashboard and resource pages (`/nexusphp/user/users`, `/nexusphp/torrent/torrents`) should render without the `404 Not Found` card.
