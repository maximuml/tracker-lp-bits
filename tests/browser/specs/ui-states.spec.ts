import { AxeBuilder } from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { AUTH_STATE } from '../fixtures/auth';

/**
 * Guards the shared disabled/invalid/highlight state recipe from #1248/#1250
 * and visible keyboard focus indicators on the header controls.
 */
test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

const STYLESHEETS = [
  { id: '4', name: 'Classic' },
  { id: '5', name: 'Unshatter' },
] as const;
const SCHEMES = ['light', 'dark'] as const;
const SCREENSHOT_DIR = process.env.UI_STATES_SCREENSHOT_DIR;
const SCREENSHOT_PHASE = process.env.UI_STATES_SCREENSHOT_PHASE;

async function gotoPage(page: Page, url: string): Promise<void> {
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
}

function trackerForm(page: Page) {
  return page.locator('form[action="/web/usercp/tracker"]');
}

async function saveTrackerSettings(
  page: Page,
  values: { stylesheet?: string },
): Promise<void> {
  await gotoPage(page, '/usercp?action=tracker');
  const form = trackerForm(page);
  await expect(form).toBeVisible();
  if (values.stylesheet !== undefined) {
    await form.locator('select[name="stylesheet"]').selectOption(values.stylesheet);
  }
  const saved = page.waitForResponse(
    (response) =>
      response.url().includes('/web/usercp/tracker') &&
      response.request().method() === 'POST',
  );
  await form.locator('input[type=submit]').click();
  expect((await saved).status(), 'tracker settings save').toBeLessThan(400);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
}

async function forEachPackScheme(
  page: Page,
  check: (pack: (typeof STYLESHEETS)[number], scheme: (typeof SCHEMES)[number]) => Promise<void>,
): Promise<void> {
  await gotoPage(page, '/usercp?action=tracker');
  const originalStylesheet = await trackerForm(page)
    .locator('select[name="stylesheet"]')
    .inputValue();

  try {
    for (const pack of STYLESHEETS) {
      await saveTrackerSettings(page, { stylesheet: pack.id });
      for (const scheme of SCHEMES) {
        await check(pack, scheme);
      }
    }
  } finally {
    await saveTrackerSettings(page, { stylesheet: originalStylesheet });
    await expect(
      trackerForm(page).locator('select[name="stylesheet"]'),
      'original stylesheet restored',
    ).toHaveValue(originalStylesheet);
  }
}

async function setScheme(page: Page, scheme: (typeof SCHEMES)[number]): Promise<void> {
  await page.evaluate(
    (value) => document.documentElement.setAttribute('data-theme', value),
    scheme,
  );
}

async function focusWithTab(page: Page, selector: string): Promise<void> {
  const target = page.locator(selector).first();
  await page.locator('body').click({ position: { x: 1, y: 1 } });
  for (let i = 0; i < 80; i++) {
    await page.keyboard.press('Tab');
    if (await target.evaluate((element) => element === document.activeElement)) {
      return;
    }
  }
  throw new Error(`Tab did not reach ${selector}`);
}

async function captureHeaderFocus(
  page: Page,
  pack: (typeof STYLESHEETS)[number],
  scheme: (typeof SCHEMES)[number],
): Promise<void> {
  if (!SCREENSHOT_DIR || !SCREENSHOT_PHASE) {
    return;
  }
  mkdirSync(SCREENSHOT_DIR, { recursive: true });
  await page.addInitScript(() => {
    const theme = localStorage.getItem('nxm-theme');
    if (theme === 'light' || theme === 'dark') {
      document.documentElement.setAttribute('data-theme', theme);
    }
  });
  await page.setViewportSize({ width: 1280, height: 900 });
  await gotoPage(page, '/web/torrents');
  await page.evaluate((theme) => localStorage.setItem('nxm-theme', theme), scheme);
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  await setScheme(page, scheme);
  await focusWithTab(page, '.nxm-nav__list > li > a');
  await page.screenshot({
    path: `${SCREENSHOT_DIR}/${SCREENSHOT_PHASE}-${pack.name}-${scheme}-nav.png`,
  });

  await focusWithTab(page, '.nxm-theme-toggle:visible');
  const header = await page.locator('.nxm-header').boundingBox();
  if (!header) {
    throw new Error('Header is not visible for focus screenshot');
  }
  await page.screenshot({
    path: `${SCREENSHOT_DIR}/${SCREENSHOT_PHASE}-${pack.name}-${scheme}-theme-toggle.png`,
    clip: header,
  });
}

async function inspectTabStops(
  page: Page,
  path: string,
  visited: Set<string>,
): Promise<string[]> {
  await page.locator('body').click({ position: { x: 1, y: 1 } });
  const failures = new Set<string>();
  let moreOpened = false;

  for (let i = 0; i < 80; i++) {
    await page.keyboard.press('Tab');
    const active = await page.evaluate(() => {
      const element = document.activeElement;
      if (!(element instanceof HTMLElement)) {
        return null;
      }
      if (
        element === document.body ||
        element.tagName === 'IFRAME' ||
        element.matches('input[type="date"], input[type="datetime-local"], input[type="time"]')
      ) {
        return null;
      }

      const indicator =
        element instanceof HTMLInputElement ? element.closest('.nxm-search') ?? element : element;
      const style = getComputedStyle(indicator);
      const classes = typeof element.className === 'string'
        ? element.className.trim().split(/\s+/).filter(Boolean).map((name) => `.${name}`).join('')
        : '';
      const type = element instanceof HTMLInputElement || element instanceof HTMLButtonElement
        ? `[${element.getAttribute('type') ?? ''}]`
        : '';
      const descriptor = `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${classes}${type}`;
      const pathParts: string[] = [];
      let current: Element | null = element;
      while (current && current !== document.body) {
        let index = 1;
        for (
          let sibling = current.previousElementSibling;
          sibling;
          sibling = sibling.previousElementSibling
        ) {
          if (sibling.tagName === current.tagName) {
            index++;
          }
        }
        pathParts.unshift(`${current.tagName.toLowerCase()}:nth-of-type(${index})`);
        current = current.parentElement;
      }

      return {
        descriptor,
        domPath: pathParts.join('>'),
        outlineStyle: style.outlineStyle,
        outlineWidth: Number.parseFloat(style.outlineWidth),
        isMoreSummary: element.matches('.nxm-more > summary'),
      };
    });

    if (!active) {
      continue;
    }
    visited.add(`${path}:${active.domPath}`);
    if (active.outlineStyle === 'none' || active.outlineWidth < 2) {
      failures.add(
        `${active.descriptor} (outline ${active.outlineStyle} ${active.outlineWidth}px)`,
      );
    }
    if (active.isMoreSummary && !moreOpened) {
      await page.keyboard.press('Enter');
      moreOpened = true;
    }
  }

  return [...failures];
}

test('every keyboard-focusable control shows a focus indicator', async ({ page }) => {
  test.setTimeout(180_000);
  const visited = new Set<string>();
  const focusFailures: { label: string; failures: string[] }[] = [];

  await forEachPackScheme(page, async (pack, scheme) => {
    await captureHeaderFocus(page, pack, scheme);
    await page.setViewportSize({ width: 1280, height: 900 });
    for (const path of ['/web/torrents', '/web/upload', '/usercp?action=tracker']) {
      await gotoPage(page, path);
      await setScheme(page, scheme);
      focusFailures.push({
        label: `${pack.name}/${scheme} ${path}`,
        failures: await inspectTabStops(page, path, visited),
      });
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await gotoPage(page, '/web/torrents');
    await setScheme(page, scheme);
    focusFailures.push({
      label: `${pack.name}/${scheme} /web/torrents (mobile)`,
      failures: await inspectTabStops(page, '/web/torrents (mobile)', visited),
    });
  });

  for (const { label, failures } of focusFailures) {
    expect(failures, label).toEqual([]);
  }
  expect(visited.size, 'distinct focus stops').toBeGreaterThanOrEqual(20);
});

test('disabled and invalid states use the shared tokens', async ({ page }) => {
  test.setTimeout(180_000);
  await forEachPackScheme(page, async (pack, scheme) => {
    for (const path of ['/web/upload', '/web/torrents']) {
      await gotoPage(page, path);
      await setScheme(page, scheme);
      if (path === '/web/torrents') {
        const details = page.locator('#ksearchboxmain');
        if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) {
          await page.locator('#ksearchboxmain > summary').click();
        }
        await expect
          .poll(() => details.evaluate((element) => (element as HTMLDetailsElement).open))
          .toBe(true);
      }
      const state = await page.evaluate((currentPath) => {
        const main = document.querySelector('main');
        if (!main) {
          throw new Error('Main content is missing');
        }
        const visible = (element: Element) => {
          const style = getComputedStyle(element);
          return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
        };
        const findVisible = (selector: string) =>
          [...main.querySelectorAll(selector)].find(visible) ?? null;
        const candidates = [
          ['text input', 'input[type="text"]'],
          ['textarea', 'textarea'],
          ['select', 'select'],
          ['submit input', 'input[type="submit"]'],
          ...(currentPath === '/web/torrents' ? [['button', '.nx-btn'] as const] : []),
        ];
        const seen = new Set<Element>();
        const controls = candidates.flatMap(([label, selector]) => {
          const element = findVisible(selector);
          if (!element || seen.has(element)) {
            return [];
          }
          seen.add(element);
          const originalDisabled = element.getAttribute('disabled');
          element.setAttribute('disabled', '');
          const opacity = getComputedStyle(element).opacity;
          if (originalDisabled === null) {
            element.removeAttribute('disabled');
          } else {
            element.setAttribute('disabled', originalDisabled);
          }
          const id = element.id ? `#${element.id}` : '';
          return [{ label: `${label}${id}`, opacity }];
        });
        const invalid = [...main.querySelectorAll('input[type="text"], textarea, select')]
          .filter(visible)
          .map((element) => {
            const originalInvalid = element.getAttribute('aria-invalid');
            const normalBorder = getComputedStyle(element).borderTopColor;
            element.setAttribute('aria-invalid', 'true');
            const invalidBorder = getComputedStyle(element).borderTopColor;
            (element as HTMLElement).focus();
            const focusedBorder = getComputedStyle(element).borderTopColor;
            if (originalInvalid === null) {
              element.removeAttribute('aria-invalid');
            } else {
              element.setAttribute('aria-invalid', originalInvalid);
            }
            const id = element.id ? `#${element.id}` : '';
            return { label: `${element.tagName.toLowerCase()}${id}`, normalBorder, invalidBorder, focusedBorder };
          });
        const root = getComputedStyle(document.documentElement);
        const disabledOpacity = Number.parseFloat(root.getPropertyValue('--nxm-disabled-opacity'));
        const probe = document.createElement('span');
        probe.style.color = 'var(--nxm-danger)';
        document.body.append(probe);
        const danger = getComputedStyle(probe).color;
        probe.remove();

        return { disabledOpacity, danger, controls, invalid };
      }, path);

      expect(state.controls.length, `${pack.name}/${scheme} ${path} disabled controls`).toBeGreaterThan(0);
      for (const control of state.controls) {
        expect(
          Number.parseFloat(control.opacity),
          `${pack.name}/${scheme} ${path} ${control.label} disabled opacity`,
        ).toBeCloseTo(state.disabledOpacity, 3);
      }
      expect(state.invalid.length, `${pack.name}/${scheme} ${path} invalid controls`).toBeGreaterThan(0);
      for (const field of state.invalid) {
        expect(
          field.normalBorder,
          `${pack.name}/${scheme} ${path} ${field.label} danger differs from normal`,
        ).not.toBe(state.danger);
        expect(
          field.invalidBorder,
          `${pack.name}/${scheme} ${path} ${field.label} invalid border`,
        ).toBe(state.danger);
        expect(
          field.focusedBorder,
          `${pack.name}/${scheme} ${path} ${field.label} focused invalid border`,
        ).toBe(state.danger);
      }
    }
  });
});

test('highlighted rows keep text contrast', async ({ page }) => {
  test.setTimeout(180_000);
  await forEachPackScheme(page, async (pack, scheme) => {
    await gotoPage(page, '/web/torrents');
    await setScheme(page, scheme);
    const rows = page.locator('table[data-nx="data"] tbody tr').filter({
      has: page.locator('td'),
    });
    expect(await rows.count()).toBeGreaterThan(1);
    const highlights = [
      { className: 'bg-nxm-highlight', index: 0 },
      { className: 'bg-nxm-highlight-ok', index: 1 },
    ];
    for (const highlight of highlights) {
      const row = rows.nth(highlight.index);
      const before = await row.evaluate((element) => getComputedStyle(element).backgroundColor);
      await row.evaluate((element, className) => {
        element.classList.add(className);
        element.setAttribute('data-hl', '');
      }, highlight.className);
      const after = await row.evaluate((element) => getComputedStyle(element).backgroundColor);
      expect(after, `${pack.name}/${scheme} ${highlight.className} background`).not.toBe(before);
    }
    const results = await new AxeBuilder({ page })
      .include('[data-hl]')
      .withRules(['color-contrast'])
      .analyze();
    expect(
      results.violations,
      `${pack.name}/${scheme} highlighted-row contrast`,
    ).toEqual([]);
  });
});
