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
const SECTIONS = [
  'catalog',
  'appearance',
  'messages',
  'torrent-list',
  'torrent-page',
] as const;

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
  await form.locator('[type=submit]').click();
  expect((await saved).status(), 'tracker settings save').toBeLessThan(400);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
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

test('tracker cards stay usable across themes and viewports', async ({ page }) => {
  test.setTimeout(240_000);

  await gotoPage(page, '/usercp?action=tracker');
  const originalStylesheet = await trackerForm(page)
    .locator('select[name="stylesheet"]')
    .inputValue();

  try {
    for (const pack of STYLESHEETS) {
      await saveStylesheet(page, pack.id);
      for (const scheme of SCHEMES) {
        for (const [viewport, width, height] of [
          ['desktop', 1280, 900],
          ['mobile', 390, 844],
        ] as const) {
          await page.setViewportSize({ width, height });
          await gotoPage(page, '/usercp?action=tracker');
          await setScheme(page, scheme);

          const form = trackerForm(page);
          await expect(form).toBeVisible();
          for (const section of SECTIONS) {
            await expect(
              form.locator(`#ucp-sec-${section}`),
              `${pack.name}/${scheme}/${viewport} ${section} section`,
            ).toBeVisible();
          }

          const geometry = await page.evaluate(() => {
            const savebar = document.querySelector('.nx-savebar');
            if (!savebar) {
              throw new Error('Save bar is missing');
            }
            return {
              viewportHeight: window.innerHeight,
              savebarBottom: savebar.getBoundingClientRect().bottom,
              overflow:
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
            };
          });
          expect(
            geometry.savebarBottom,
            `${pack.name}/${scheme}/${viewport} save bar at scroll 0`,
          ).toBeLessThanOrEqual(geometry.viewportHeight);
          expect(
            geometry.overflow,
            `${pack.name}/${scheme}/${viewport} horizontal overflow`,
          ).toBeLessThanOrEqual(0);

          const axe = await new AxeBuilder({ page })
            .include('form[action="/web/usercp/tracker"]')
            .withTags(['wcag2a', 'wcag2aa'])
            .analyze();
          expect(
            axe.violations,
            `${pack.name}/${scheme}/${viewport} tracker form axe violations: ${JSON.stringify(axe.violations)}`,
          ).toEqual([]);
        }
      }
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await gotoPage(page, '/usercp?action=tracker');
    const form = trackerForm(page);
    const chip = form.locator('.nxm-chiprow .nx-chip').first();
    const checkbox = chip.locator('input[type="checkbox"]');
    const initialChipState = await checkbox.isChecked();
    await chip.click();
    expect(await checkbox.isChecked(), 'clicking category chip toggles checkbox').toBe(!initialChipState);
    await chip.click();
    expect(await checkbox.isChecked(), 'category chip state restored').toBe(initialChipState);

    await focusWithTab(page, '.nxm-chiprow .nx-chip__input');
    const chipFocus = await chip.evaluate((element) => {
      const style = getComputedStyle(element);
      return { outlineStyle: style.outlineStyle, outlineWidth: Number.parseFloat(style.outlineWidth) };
    });
    expect(chipFocus.outlineStyle).not.toBe('none');
    expect(chipFocus.outlineWidth).toBeGreaterThanOrEqual(2);

    const description = form.locator('input[name="showdescription"]');
    const originalDescription = await description.isChecked();
    await description.setChecked(!originalDescription);
    await form.locator('[type=submit]').click();
    await expect(page).toHaveURL(/type=saved/);
    await expect(page.locator('[role="status"]')).toContainText('Settings saved.');

    await gotoPage(page, '/usercp?action=tracker');
    const persistedDescription = trackerForm(page).locator('input[name="showdescription"]');
    await expect(persistedDescription).toBeChecked({ checked: !originalDescription });
    await persistedDescription.setChecked(originalDescription);
    await trackerForm(page).locator('[type=submit]').click();
    await expect(page).toHaveURL(/type=saved/);

    await gotoPage(page, '/usercp?action=tracker');
    const submit = trackerForm(page).locator('[data-submit-once]');
    await page.evaluate(() => {
      document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
          return;
        }
        window.name = Array.from(form.querySelectorAll('[data-submit-once]')).every(
          (button) => button.disabled,
        )
          ? 'disabled'
          : 'enabled';
      });
    });
    await page.route('**/web/usercp/tracker', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 1500));
      await route.continue();
    });
    const requestStarted = page.waitForRequest(
      (request) =>
        request.url().includes('/web/usercp/tracker') &&
        request.method() === 'POST',
    );
    const submittedAt = Date.now();
    const delayedSubmit = submit.click();
    await requestStarted;
    await delayedSubmit;
    await page.unroute('**/web/usercp/tracker');
    await expect(page).toHaveURL(/type=saved/);
    expect(Date.now() - submittedAt, 'POST interception delayed the submission').toBeGreaterThanOrEqual(1400);
    expect(await page.evaluate(() => window.name), 'save button disabled at submit').toBe('disabled');
  } finally {
    await saveStylesheet(page, originalStylesheet);
    await expect(
      trackerForm(page).locator('select[name="stylesheet"]'),
      'original stylesheet restored',
    ).toHaveValue(originalStylesheet);
  }
});
