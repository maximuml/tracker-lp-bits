import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { watchIssues } from '../helpers';

/**
 * Guest auth pages (login / signup / recover): the nx-auth card layout,
 * the preserved field contract, the password-visibility toggle, mobile
 * fit and axe. Guests always get the Classic stylesheet, so the theme
 * axis is light/dark only.
 */
const pages: Array<{ name: string; url: string; info: boolean; fields: string[] }> = [
  {
    name: 'login',
    info: true,
    url: '/login',
    fields: [
      'input[name="username"]',
      'input[name="password"]',
      'input[name="two_step_code"]',
      'input[name="logout"]',
      'form#login-form[action="/login"]',
    ],
  },
  {
    name: 'signup',
    info: false, // the cookies note is the page sub-line, not a plaque
    url: '/signup',
    fields: [
      'input[name="wantusername"]',
      'input.wantpassword',
      'input.passagain',
      'input[name="email"]',
      'select[name="country"]',
      'input[name="gender"]',
      'input[name="rulesverify"]',
      'input[name="faqverify"]',
      'input[name="ageverify"]',
      '#submit-btn',
      'input[type="hidden"][name="wantpassword"]',
      'input[type="hidden"][name="passagain"]',
    ],
  },
  {
    name: 'recover',
    info: true,
    url: '/recover',
    fields: ['input[name="email"]'],
  },
];

for (const p of pages) {
  test(`${p.name} renders the auth card structure with its field contract`, async ({ page }) => {
    const issues = watchIssues(page);
    const response = await page.goto(p.url, { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);

    await expect(page.locator('.nx-auth h1').first()).toBeVisible();
    if (p.info) {
      await expect(page.locator('.nx-auth__info').first()).toBeVisible();
    }
    await expect(page.locator('.nx-auth__lang select[name="sitelanguage"]')).toBeVisible();
    for (const field of p.fields) {
      await expect(page.locator(field).first(), `missing ${field}`).toHaveCount(1);
    }
    expect(issues.pageErrors).toEqual([]);
  });
}

test('login shows divider, passkey action and grouped help links', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await expect(page.locator('.nx-auth__divider')).toBeVisible();
  await expect(page.locator('.nx-auth__links')).toBeVisible();
  await expect(page.locator('.nx-auth__links a[href="/signup"]')).toBeVisible();
  await expect(page.locator('.nx-auth__links a[href="/recover"]')).toBeVisible();
});

test('password visibility toggle flips the input and aria state', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'networkidle' });
  const input = page.locator('#login-form input[name="password"]');
  const toggle = page.locator('#login-form [data-pw-toggle]');

  await expect(input).toHaveAttribute('type', 'password');
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');
  await toggle.click();
  await expect(input).toHaveAttribute('type', 'text');
  await expect(toggle).toHaveAttribute('aria-pressed', 'true');
  await toggle.click();
  await expect(input).toHaveAttribute('type', 'password');
});

test('signup eye toggle does not trigger the hash submit handler', async ({ page }) => {
  const issues = watchIssues(page);
  let dialogSeen = false;
  page.on('dialog', (dialog) => {
    dialogSeen = true;
    void dialog.dismiss();
  });

  await page.goto('/signup', { waitUntil: 'networkidle' });
  const input = page.locator('input.wantpassword');
  const toggle = page.locator('[data-pw-toggle]').first();

  await input.fill('some-password');
  await toggle.click();
  await expect(input).toHaveAttribute('type', 'text');
  await page.waitForTimeout(300);

  expect(dialogSeen, 'auth-form submit handler must ignore the eye button').toBeFalsy();
  expect(page.url(), 'toggle must not submit the form').toContain('/signup');
  expect(issues.pageErrors).toEqual([]);
});

for (const p of pages) {
  test(`${p.name} fits a 390px viewport without horizontal scroll`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(p.url, { waitUntil: 'networkidle' });
    const metrics = await page.evaluate(() => ({
      docW: document.documentElement.scrollWidth,
      vw: window.innerWidth,
    }));
    expect(metrics.docW, `${p.name} wider than the viewport`).toBeLessThanOrEqual(metrics.vw);
  });
}

for (const p of pages) {
  for (const theme of ['light', 'dark']) {
    test(`${p.name} passes axe in ${theme}`, async ({ page }) => {
      await page.goto(p.url, { waitUntil: 'networkidle' });
      await page.evaluate((value) => {
        document.documentElement.dataset.theme = value;
      }, theme);

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include('.nx-auth')
        .analyze();
      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  }
}
