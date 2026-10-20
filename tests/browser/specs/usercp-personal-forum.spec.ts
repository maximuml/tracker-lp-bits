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
const TABS = [
  {
    name: 'personal',
    path: '/usercp?action=personal',
    sections: ['account', 'profile', 'avatar', 'about'],
  },
  {
    name: 'forum',
    path: '/usercp?action=forum',
    sections: ['forum-pages', 'forum-display', 'signature'],
  },
] as const;

async function gotoPage(page: Page, url: string): Promise<void> {
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
}

function trackerForm(page: Page) {
  return page.locator('form[action="/web/usercp/tracker"]');
}

function settingsForm(page: Page, tab: (typeof TABS)[number]['name']) {
  return page.locator(`form[action="/web/usercp/${tab}"]`);
}

async function saveStylesheet(page: Page, stylesheet: string): Promise<void> {
  await gotoPage(page, '/usercp?action=tracker');
  const form = trackerForm(page);
  await form.locator('select[name="stylesheet"]').selectOption(stylesheet);
  const saved = page.waitForResponse(
    (response) =>
      response.url().includes('/web/usercp/tracker') &&
      response.request().method() === 'POST',
  );
  await form.locator('[type=submit]').click();
  expect((await saved).status()).toBeLessThan(400);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
}

async function setScheme(page: Page, scheme: (typeof SCHEMES)[number]): Promise<void> {
  await page.evaluate((value) => {
    localStorage.setItem('nx-theme', value);
    document.documentElement.dataset.theme = value;
  }, scheme);
}

test('personal and forum cards stay usable across themes and viewports', async ({ page }) => {
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
          for (const tab of TABS) {
            await gotoPage(page, tab.path);
            await setScheme(page, scheme);
            const form = settingsForm(page, tab.name);
            await expect(form).toBeVisible();
            for (const section of tab.sections) {
              await expect(
                form.locator(`#ucp-sec-${section}`),
                `${pack.name}/${scheme}/${viewport} ${tab.name}/${section} section`,
              ).toBeVisible();
            }

            const geometry = await page.evaluate(() => ({
              overflow:
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
              cards: document.querySelectorAll('.nx-setcard').length,
            }));
            expect(
              geometry.overflow,
              `${pack.name}/${scheme}/${viewport} ${tab.name} horizontal overflow`,
            ).toBeLessThanOrEqual(0);
            expect(
              geometry.cards,
              `${pack.name}/${scheme}/${viewport} ${tab.name} cards`,
            ).toBe(tab.sections.length);

            const axe = await new AxeBuilder({ page })
              .include(`form[action="/web/usercp/${tab.name}"]`)
              .withTags(['wcag2a', 'wcag2aa'])
              .analyze();
            expect(
              axe.violations,
              `${pack.name}/${scheme}/${viewport} ${tab.name} axe violations: ${JSON.stringify(axe.violations)}`,
            ).toEqual([]);
          }
        }
      }
    }

    await saveStylesheet(page, '4');
    await page.setViewportSize({ width: 1280, height: 900 });
    await gotoPage(page, '/usercp?action=personal');
    const personal = settingsForm(page, 'personal');
    const avatar = personal.locator('img[name="avatarimg"]');
    const avatarSelect = personal.locator('select[name="savatar"]');
    const secondAvatar = await avatarSelect.locator('option').nth(1).getAttribute('value');
    await avatar.evaluate((image) => {
      image.setAttribute('src', `${image.getAttribute('src')}?preview-probe=before`);
    });
    await avatarSelect.selectOption({ index: 1 });
    await expect.poll(() => avatar.getAttribute('src')).toBe(secondAvatar);

    await gotoPage(page, '/usercp?action=forum');
    const forum = settingsForm(page, 'forum');
    const signatures = forum.locator('input[name="signatures"]');
    const originalSignatures = await signatures.isChecked();
    await signatures.setChecked(!originalSignatures);
    await forum.locator('[type=submit]').click();
    await expect(page).toHaveURL(/type=saved/);
    await expect(page.locator('[role="status"]')).toContainText('Settings saved.');
    await gotoPage(page, '/usercp?action=forum');
    await expect(settingsForm(page, 'forum').locator('input[name="signatures"]')).toHaveJSProperty(
      'checked',
      !originalSignatures,
    );
    await settingsForm(page, 'forum').locator('input[name="signatures"]').setChecked(originalSignatures);
    await settingsForm(page, 'forum').locator('[type=submit]').click();
    await expect(page).toHaveURL(/type=saved/);

    await gotoPage(page, '/usercp?action=personal');
    const gender = settingsForm(page, 'personal').locator('input[name="gender"]:checked');
    const originalGender = await gender.inputValue();
    const alternateGender = originalGender === 'Male' ? 'Female' : 'Male';
    await settingsForm(page, 'personal').locator(`input[name="gender"][value="${alternateGender}"]`).check();
    await settingsForm(page, 'personal').locator('[type=submit]').click();
    await expect(page).toHaveURL(/type=saved/);
    await expect(page.locator('[role="status"]')).toContainText('Settings saved.');
    await gotoPage(page, '/usercp?action=personal');
    await expect(settingsForm(page, 'personal').locator('input[name="gender"]:checked')).toHaveValue(alternateGender);
    await settingsForm(page, 'personal').locator(`input[name="gender"][value="${originalGender}"]`).check();
    await settingsForm(page, 'personal').locator('[type=submit]').click();
    await expect(page).toHaveURL(/type=saved/);
  } finally {
    await saveStylesheet(page, originalStylesheet);
    await expect(
      trackerForm(page).locator('select[name="stylesheet"]'),
      'original stylesheet restored',
    ).toHaveValue(originalStylesheet);
  }
});
