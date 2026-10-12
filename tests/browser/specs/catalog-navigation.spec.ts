import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });

async function expandSearchPanel(page: Page): Promise<void> {
  const details = page.locator('#ksearchboxmain');
  const summary = details.locator('summary');
  if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) {
    await summary.click();
  }
  await expect.poll(() => details.evaluate((element) => (element as HTMLDetailsElement).open)).toBe(true);
}

function nameSortLink(page: Page) {
  return page
    .getByRole('columnheader')
    .filter({ hasText: 'Name' })
    .getByRole('link');
}

test.describe('catalog navigation', () => {
  test('name sort toggles direction and can be activated with the keyboard', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });

    const nameLink = nameSortLink(page);
    await expect(nameLink).toBeVisible();
    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get('sort') === '1' &&
          url.searchParams.get('type') === 'asc',
      ),
      nameLink.click(),
    ]);

    let url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get('sort')).toBe('1');
    expect(url.searchParams.get('type')).toBe('asc');
    await expect(
      page.locator('.nx-torrents thead th.nxm-th--sorted'),
    ).toHaveCount(1);

    await Promise.all([
      page.waitForURL(
        (nextUrl) =>
          nextUrl.pathname === '/web/torrents' &&
          nextUrl.searchParams.get('sort') === '1' &&
          nextUrl.searchParams.get('type') === 'desc',
      ),
      nameLink.click(),
    ]);
    url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get('sort')).toBe('1');
    expect(url.searchParams.get('type')).toBe('desc');

    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    await nameSortLink(page).focus();
    await Promise.all([
      page.waitForURL(
        (nextUrl) =>
          nextUrl.pathname === '/web/torrents' &&
          nextUrl.searchParams.get('sort') === '1',
      ),
      page.keyboard.press('Enter'),
    ]);
    expect(new URL(page.url()).pathname).toBe('/web/torrents');
  });

  test('category chips auto-submit and preserve active filters and sorting', async ({
    page,
  }) => {
    await page.goto(
      '/web/torrents?incldead=0&medium1=1&sort=4&type=desc',
      { waitUntil: 'networkidle' },
    );
    await expandSearchPanel(page);
    const mediaInputs = await page
      .locator('#ksearchboxmain .nxm-taxonomy-chip__input[name^="medium"]')
      .all();
    let pendingTaxonomy: (typeof mediaInputs)[number] | undefined;
    for (const input of mediaInputs) {
      if ((await input.getAttribute('name')) !== 'medium1' && !(await input.isChecked())) {
        pendingTaxonomy = input;
        break;
      }
    }
    expect(pendingTaxonomy).toBeDefined();
    const pendingName = await pendingTaxonomy!.getAttribute('name');
    await pendingTaxonomy!.locator('xpath=..').click();
    await expect(pendingTaxonomy!).toBeChecked();

    const category = page.locator('.nxm-category input[data-autosubmit]').first();
    const field = await category.getAttribute('name');
    expect(field).toMatch(/^cat\d+$/);

    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get(field!) === '1' &&
          url.searchParams.get('incldead') === '0' &&
          url.searchParams.get('medium1') === '1' &&
          !url.searchParams.has(pendingName!) &&
          url.searchParams.get('sort') === '4' &&
          url.searchParams.get('type') === 'desc',
      ),
      category.locator('xpath=..').click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get(field!)).toBe('1');
    expect(url.searchParams.get('medium1')).toBe('1');
    expect(url.searchParams.has(pendingName!)).toBe(false);
    expect(url.searchParams.get('sort')).toBe('4');
  });

  test('taxonomy chips wait for Apply to submit their filter', async ({
    page,
  }) => {
    await page.goto('/web/torrents?sort=4&type=desc', { waitUntil: 'networkidle' });
    await expandSearchPanel(page);

    const inputs = await page.locator('#ksearchboxmain .nxm-taxonomy-chip__input[name^="medium"]').all();
    const taxonomy = inputs[0];
    const field = await taxonomy.getAttribute('name');
    expect(field).toMatch(/^medium\d+$/);
    if (await taxonomy.isChecked()) {
      await taxonomy.locator('xpath=..').click();
    }
    const initialUrl = page.url();
    await taxonomy.locator('xpath=..').click();
    await expect(taxonomy).toBeChecked();
    expect(page.url()).toBe(initialUrl);

    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get(field!) === '1' &&
          url.searchParams.get('sort') === '4' &&
          url.searchParams.get('type') === 'desc',
      ),
      page.getByRole('button', { name: 'Apply' }).click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get(field!)).toBe('1');
    await expect(page.locator(`#${field}`)).toBeChecked();
    await expect(page.locator('.nxm-empty')).toBeVisible();
  });

  test('keyboard activation works for category and taxonomy chips', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    const category = page.locator('.nxm-category input[data-autosubmit]').first();
    const categoryName = await category.getAttribute('name');
    await category.focus();
    await page.keyboard.press('Space');
    await page.waitForURL((url) => url.searchParams.get(categoryName!) === '1');

    await expandSearchPanel(page);
    const taxonomy = page.locator('#ksearchboxmain .nxm-taxonomy-chip__input[name^="medium"]').first();
    const taxonomyName = await taxonomy.getAttribute('name');
    await taxonomy.focus();
    if (await taxonomy.isChecked()) {
      await page.keyboard.press('Space');
    }
    await page.keyboard.press('Space');
    await expect(taxonomy).toBeChecked();
    await page.getByRole('button', { name: 'Apply' }).focus();
    await page.keyboard.press('Enter');
    await page.waitForURL((url) => url.searchParams.get(taxonomyName!) === '1');
  });

  test('name sort preserves the current category filter', async ({ page }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    const categoryName = await page
      .locator('.nxm-category input[data-autosubmit]')
      .first()
      .getAttribute('name');
    expect(categoryName).toMatch(/^cat\d+$/);

    await page.goto(`/web/torrents?${encodeURIComponent(categoryName!)}=1`, {
      waitUntil: 'networkidle',
    });
    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get(categoryName!) === '1' &&
          url.searchParams.get('sort') === '1',
      ),
      nameSortLink(page).click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get(categoryName!)).toBe('1');
    expect(url.searchParams.get('sort')).toBe('1');
  });

  test('query links in the panel, table header, and pager stay on the catalog', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    await expandSearchPanel(page);

    const offCatalogLinks = await page
      .locator(
        '#ksearchboxmain a[href], .nx-torrents thead a[href], nav[aria-label^="Pagination"] a[href]',
      )
      .evaluateAll((anchors) =>
        anchors.flatMap((element) => {
          const anchor = element as HTMLAnchorElement;
          const attributeHref = anchor.getAttribute('href') ?? '';
          if (
            attributeHref.startsWith('#') ||
            /^javascript:/i.test(attributeHref)
          ) {
            return [];
          }

          const resolved = new URL(anchor.href);
          if (!resolved.search || resolved.pathname === '/web/torrents') {
            return [];
          }

          return [{ href: resolved.href, pathname: resolved.pathname }];
        }),
      );

    expect(
      offCatalogLinks,
      `query links resolved outside /web/torrents: ${JSON.stringify(offCatalogLinks)}`,
    ).toEqual([]);
  });
});
