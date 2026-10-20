import { AxeBuilder } from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

const STYLESHEETS = [
  { id: '4', name: 'Classic' },
  { id: '5', name: 'Unshatter' },
] as const;
const SCHEMES = ['light', 'dark'] as const;

async function gotoPage(page: Page, url: string): Promise<void> {
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
}

function trackerForm(page: Page) {
  return page.locator('form[action="/web/usercp/tracker"]');
}

async function saveStylesheet(page: Page, stylesheet: string): Promise<void> {
  await gotoPage(page, '/usercp?action=tracker');
  const form = trackerForm(page);
  await expect(form).toBeVisible();
  await form.locator('select[name="stylesheet"]').selectOption(stylesheet);
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
  check: (
    pack: (typeof STYLESHEETS)[number],
    scheme: (typeof SCHEMES)[number],
  ) => Promise<void>,
): Promise<void> {
  await gotoPage(page, '/usercp?action=tracker');
  const originalStylesheet = await trackerForm(page)
    .locator('select[name="stylesheet"]')
    .inputValue();

  try {
    for (const pack of STYLESHEETS) {
      await saveStylesheet(page, pack.id);
      for (const scheme of SCHEMES) {
        await check(pack, scheme);
      }
    }
  } finally {
    await saveStylesheet(page, originalStylesheet);
    await expect(
      trackerForm(page).locator('select[name="stylesheet"]'),
      'original stylesheet restored',
    ).toHaveValue(originalStylesheet);
  }
}

async function setScheme(page: Page, scheme: (typeof SCHEMES)[number]): Promise<void> {
  await page.evaluate((value) => {
    localStorage.setItem('nx-theme', value);
    document.documentElement.dataset.theme = value;
  }, scheme);
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

test('usercp tabs stay single-row, accessible, and reveal the active tab', async ({ page }) => {
  test.setTimeout(180_000);

  await forEachPackScheme(page, async (pack, scheme) => {
    for (const [viewport, width, height] of [
      ['desktop', 1280, 900],
      ['mobile', 390, 844],
    ] as const) {
      await page.setViewportSize({ width, height });
      await gotoPage(page, '/usercp?action=security');
      await setScheme(page, scheme);

      const tabs = page.locator('nav.nx-tabs');
      await expect(tabs).toBeVisible();
      const activeTab = tabs.locator('.nx-tabs__link[aria-current="page"]');
      await expect(activeTab).toHaveAttribute('href', /action=security/);

      const geometry = await tabs.evaluate((nav) => {
        const links = [...nav.querySelectorAll<HTMLAnchorElement>('.nx-tabs__link')];
        const list = nav.querySelector<HTMLUListElement>('.nx-tabs__list');
        const active = nav.querySelector<HTMLAnchorElement>(
          '.nx-tabs__link[aria-current="page"]',
        );
        if (!list || !active) {
          throw new Error('Tabs list or active link is missing');
        }

        const activeRect = active.getBoundingClientRect();
        const listRect = list.getBoundingClientRect();
        const probe = document.createElement('span');
        probe.style.color = 'var(--nxm-accent)';
        document.body.append(probe);
        const accentColor = getComputedStyle(probe).color;
        probe.remove();
        const activeStyle = getComputedStyle(active);

        return {
          rows: [...new Set(links.map((link) => link.offsetTop))],
          fontWeight: Number.parseFloat(activeStyle.fontWeight),
          borderBottomColor: activeStyle.borderBottomColor,
          accentColor,
          activeVisible:
            activeRect.left >= listRect.left - 0.5 &&
            activeRect.right <= listRect.right + 0.5,
          documentOverflow:
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
          scrollY: window.scrollY,
        };
      });

      expect(geometry.rows, `${pack.name}/${scheme} ${viewport} tab rows`).toHaveLength(1);
      expect(
        geometry.fontWeight,
        `${pack.name}/${scheme} ${viewport} active weight`,
      ).toBeGreaterThanOrEqual(700);
      expect(
        geometry.borderBottomColor,
        `${pack.name}/${scheme} ${viewport} underline color`,
      ).toBe(geometry.accentColor);

      if (viewport === 'mobile') {
        expect(
          geometry.documentOverflow,
          `${pack.name}/${scheme} mobile document overflow`,
        ).toBeLessThanOrEqual(0);
        expect(
          geometry.activeVisible,
          `${pack.name}/${scheme} mobile active tab visibility`,
        ).toBe(true);
        expect(geometry.scrollY, `${pack.name}/${scheme} mobile page scroll`).toBe(0);
      }

      const axe = await new AxeBuilder({ page }).include('nav.nx-tabs').analyze();
      expect(
        axe.violations.map(({ id }) => id),
        `${pack.name}/${scheme} ${viewport} tab axe violations`,
      ).toEqual([]);

      if (viewport === 'desktop') {
        const trackerTab = 'nav.nx-tabs .nx-tabs__link[href*="action=tracker"]';
        await focusWithTab(page, trackerTab);
        const focusStyle = await page.locator(trackerTab).evaluate((link) => {
          const style = getComputedStyle(link);
          return { outlineStyle: style.outlineStyle, outlineWidth: Number.parseFloat(style.outlineWidth) };
        });
        expect(focusStyle.outlineStyle).not.toBe('none');
        expect(focusStyle.outlineWidth).toBeGreaterThanOrEqual(2);

        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/usercp\?action=tracker/);
        await expect(
          page.locator('nav.nx-tabs .nx-tabs__link[aria-current="page"]'),
        ).toHaveAttribute('href', /action=tracker/);
      }
    }
  });
});
