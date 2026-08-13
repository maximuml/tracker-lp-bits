---
name: Testing accessibility and theme-switching in tracker-lp-bits
description: How to verify skip links, focus-visible, ARIA landmarks, reduced-motion, and per-theme screenshots in the local Docker stack.
---

## Devin Secrets Needed

None for the local Docker stack.

## When to use

Use when testing PRs that touch `resources/views/layouts/nexus.blade.php`, `resources/views/layouts/nexus_legacy.blade.php`, `x-nexus.*` components, `public/styles/nexus-legacy-compat.css`, or `resources/css/app.css` accessibility/theme changes.

## Setup

1. Ensure the Docker Compose stack is up:
   ```
   cd /path/to/tracker-lp-bits && docker compose up -d
   ```
2. Generate or reuse a sysop `c_secure_pass` cookie and store it at `/tmp/pr262-265-sysop-cookie.txt`.
3. Rebuild caches after any Blade or route change:
   ```
   docker compose exec php php artisan view:cache
   docker compose exec php php artisan route:cache
   docker compose exec openresty openresty -t
   ```

## Switching the legacy theme for screenshots

`nexus_legacy` now loads the logged-in user's stylesheet (`SupportContext::getUser()['stylesheet']`, falling back to the site default `main.defstylesheet`). To switch the theme under test, update the user's stylesheet row and clear the relevant caches:

```
docker compose exec -T php php artisan tinker --execute="App\Models\User::where('id', 1)->update(['stylesheet' => 6])"
docker compose exec -T php php artisan cache:clear
```

Restore the original theme after testing:

```
docker compose exec -T php php artisan tinker --execute="App\Models\User::where('id', 1)->update(['stylesheet' => 4])"
docker compose exec -T php php artisan cache:clear
```

Available stylesheet IDs:
- `2` Blue Gene
- `3` Blasphemy Orange
- `4` Classic
- `6` Dark Passion
- `7` Bamboo Green

## Useful puppeteer snippets

### Skip link focus check

```js
// Tab to the skip link, press Enter, then inspect where focus lands.
await page.keyboard.press('Tab');
await page.keyboard.press('Enter');
const { hash, active, tabindex } = await page.evaluate(() => ({
  hash: window.location.hash,
  active: document.activeElement?.tagName + (document.activeElement?.id ? '#' + document.activeElement.id : ''),
  tabindex: document.querySelector('main#main-content')?.getAttribute('tabindex') ?? 'none'
}));
```

If `active` is `BODY` and `tabindex` is `none`, the `<main>` element needs `tabindex="-1"` so the skip link moves focus into the content.

### Focus-visible ring check

```js
await page.keyboard.press('Tab'); // repeat until a button/link is active
const style = await page.evaluate(() => {
  const el = document.activeElement;
  const s = window.getComputedStyle(el);
  return { outlineStyle: s.outlineStyle, outlineColor: s.outlineColor, boxShadow: s.boxShadow };
});
```

A working implementation shows a `box-shadow` or `outline` whose color is the theme primary (`rgb(77, 108, 153)` for Classic).

### Emulate reduced motion

```js
await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
await page.goto('http://openresty/ui-preview');
const transition = await page.evaluate(() =>
  window.getComputedStyle(document.querySelector('main button')).transitionDuration
);
// transition should be 0s, 0.001s, or 1e-05s (effectively zero)
```

### Mobile hamburger ARIA check

```js
const btn = await page.$('button[aria-label="Toggle navigation"]');
const expanded = await btn.evaluate(b => b.getAttribute('aria-expanded'));
const controls = await btn.evaluate(b => b.getAttribute('aria-controls'));
const menu = await page.$(`#${controls}`);
const menuRole = await menu.evaluate(m => m.getAttribute('role'));
const menuLabel = await menu.evaluate(m => m.getAttribute('aria-label'));
```

Expected: `aria-expanded` toggles `false` -> `true` -> `false`; `aria-controls === 'mobile-nav'`; menu has `role="region"` and `aria-label="Mobile navigation"`.

## Common gotchas

- The skip link will only move focus to `<main>` if the target has `tabindex="-1"`. Without it, the browser scrolls but focus stays on `BODY`.
- Passing `role="contentinfo"` (or any attribute) to a Blade component works only if the component uses `$attributes->merge([...])`.
- `nexus_legacy` theme switching respects `SupportContext::getUser()['stylesheet']` and falls back to `main.defstylesheet`. The `/usercp.php?action=tracker` stylesheet selector updates the user row and legacy pages now load the chosen theme.
- `prefers-reduced-motion: reduce` sets `transition-duration: 0.01ms !important`; Chrome may report this as `1e-05s` and `parseFloat` returns `0.00001` (effectively zero).
