import { AxeBuilder } from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { AUTH_STATE, BROWSER_PASS } from '../fixtures/auth';

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

async function confirmPrivacyChange(page: Page, value: string): Promise<void> {
  const form = page.locator('form#security');
  await form.locator(`input[name="privacy"][value="${value}"]`).check();
  await page.locator('.nx-savebar button[form="security"]').click();
  await expect(page.locator('#ucp-sec-confirm')).toBeVisible();
  await page.locator('#usercp-oldpassword').fill(BROWSER_PASS);
  await page.locator('.nx-savebar button[form="security"]').click();
  await expect(page).toHaveURL(/type=saved/);
  await expect(page.locator('[role="status"]')).toContainText('Settings saved.');
}

test('security cards stay usable across themes and viewports', async ({ page }) => {
  test.setTimeout(300_000);

  await gotoPage(page, '/usercp?action=tracker');
  const originalStylesheet = await trackerForm(page)
    .locator('select[name="stylesheet"]')
    .inputValue();
  const originalTheme = await page.evaluate(() => localStorage.getItem('nx-theme'));

  try {
    for (const pack of STYLESHEETS) {
      await saveStylesheet(page, pack.id);
      for (const scheme of SCHEMES) {
        for (const [viewport, width, height] of [
          ['desktop', 1280, 900],
          ['mobile', 390, 844],
        ] as const) {
          await page.setViewportSize({ width, height });
          await gotoPage(page, '/usercp?action=security');
          await setScheme(page, scheme);

          const geometry = await page.evaluate(() => {
            const qr = document.querySelector('.nx-tfa__qr');
            const code = document.querySelector('#usercp-two-step-code');
            if (!qr || !code) {
              throw new Error('Two-step QR or code input is missing');
            }
            return {
              clientWidth: document.documentElement.clientWidth,
              overflow:
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
              qrRight: qr.getBoundingClientRect().right,
              codeRight: code.getBoundingClientRect().right,
            };
          });
          expect(
            geometry.overflow,
            `${pack.name}/${scheme}/${viewport} document overflow`,
          ).toBeLessThanOrEqual(0);
          expect(
            geometry.qrRight,
            `${pack.name}/${scheme}/${viewport} QR right edge`,
          ).toBeLessThanOrEqual(geometry.clientWidth);
          expect(
            geometry.codeRight,
            `${pack.name}/${scheme}/${viewport} code input right edge`,
          ).toBeLessThanOrEqual(geometry.clientWidth);

          const axe = await new AxeBuilder({ page })
            .include('.nx-setcards')
            .withTags(['wcag2a', 'wcag2aa'])
            .analyze();
          expect(
            axe.violations,
            `${pack.name}/${scheme}/${viewport} Security axe violations: ${JSON.stringify(axe.violations)}`,
          ).toEqual([]);
        }
      }
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await gotoPage(page, '/usercp?action=security');
    let postCount = 0;
    await page.route('**/web/usercp/security**', async (route) => {
      if (route.request().method() === 'POST') {
        postCount++;
        await route.abort();
      } else {
        await route.continue();
      }
    });
    await page.locator('#passkey_create').click();
    await page.waitForTimeout(1500);
    await page.unroute('**/web/usercp/security**');
    expect(postCount, 'Create passkey must not submit the Security form').toBe(0);
    const alertButtons = page.locator('.layui-layer .layui-layer-btn0');
    if (await alertButtons.count()) {
      await alertButtons.last().click();
    }

    await gotoPage(page, '/usercp?action=security');
    const privacy = page.locator('form#security input[name="privacy"]');
    const originalPrivacy = await page
      .locator('form#security input[name="privacy"]:checked')
      .inputValue();
    const alternatePrivacy = await privacy
      .evaluateAll((inputs, current) => {
        const next = inputs.find(
          (input) => (input as HTMLInputElement).value !== current,
        );
        return (next as HTMLInputElement | undefined)?.value ?? '';
      }, originalPrivacy);
    expect(alternatePrivacy, 'another privacy level is available').not.toBe('');

    await confirmPrivacyChange(page, alternatePrivacy);
    await gotoPage(page, '/usercp?action=security');
    await expect(
      page.locator(`form#security input[name="privacy"][value="${alternatePrivacy}"]`),
    ).toBeChecked();

    await confirmPrivacyChange(page, originalPrivacy);
    await gotoPage(page, '/usercp?action=security');
    await expect(
      page.locator(`form#security input[name="privacy"][value="${originalPrivacy}"]`),
    ).toBeChecked();
  } finally {
    await saveStylesheet(page, originalStylesheet);
    await page.evaluate((value) => {
      if (value === null) {
        localStorage.removeItem('nx-theme');
      } else {
        localStorage.setItem('nx-theme', value);
      }
    }, originalTheme);
    await expect(
      trackerForm(page).locator('select[name="stylesheet"]'),
      'original stylesheet restored',
    ).toHaveValue(originalStylesheet);
  }
});
