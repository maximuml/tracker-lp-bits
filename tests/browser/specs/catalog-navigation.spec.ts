import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });

async function expandSearchPanel(page: Page): Promise<void> {
  const toggle = page.locator('[aria-controls="ksearchboxmain"]');
  await expect(toggle).toBeVisible();
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click();
  }
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#ksearchboxmain')).toBeVisible();
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

  test('category cells navigate to the catalog with their category filter', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    await expandSearchPanel(page);

    const categoryLink = page
      .locator('#ksearchboxmain fieldset')
      .first()
      .locator('a[href]')
      .first();
    await expect(categoryLink).toBeVisible();
    const resolvedHref = await categoryLink.evaluate(
      (anchor: HTMLAnchorElement) => anchor.href,
    );
    const categoryId = new URL(resolvedHref).searchParams.get('cat');
    expect(categoryId).not.toBeNull();

    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.has('cat'),
      ),
      categoryLink.click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get('cat')).toBe(categoryId);
  });

  test('taxonomy cells navigate to the catalog and check their filter', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    await expandSearchPanel(page);

    const taxonomyLink = page
      .locator('#ksearchboxmain fieldset label a[href]')
      .first();
    await expect(taxonomyLink).toBeVisible();
    const resolvedHref = await taxonomyLink.evaluate(
      (anchor: HTMLAnchorElement) => anchor.href,
    );
    const params = [...new URL(resolvedHref).searchParams.entries()];
    expect(params).toHaveLength(1);
    const [field, id] = params[0];

    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get(field) === id,
      ),
      taxonomyLink.click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get(field)).toBe(id);
    await expect(page.locator(`#${field}${id}`)).toBeChecked();
  });

  test('name sort preserves the current category filter', async ({ page }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    await expandSearchPanel(page);

    const categoryHref = await page
      .locator('#ksearchboxmain fieldset')
      .first()
      .locator('a[href]')
      .first()
      .evaluate((anchor: HTMLAnchorElement) => anchor.href);
    const categoryId = new URL(categoryHref).searchParams.get('cat');
    expect(categoryId).not.toBeNull();

    await page.goto(`/web/torrents?cat=${encodeURIComponent(categoryId!)}`, {
      waitUntil: 'networkidle',
    });
    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get('cat') === categoryId &&
          url.searchParams.get('sort') === '1',
      ),
      nameSortLink(page).click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get('cat')).toBe(categoryId);
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
