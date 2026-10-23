import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
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

async function useUnshatterStylesheet(page: Page, enabled: boolean): Promise<void> {
  const selector = 'link[rel="stylesheet"][href*="styles/Unshatter"]';
  const unshatter = page.locator(selector);
  if (enabled && (await unshatter.count()) === 0) {
    await page.addStyleTag({ url: '/styles/Unshatter/theme.css' });
  } else if (!enabled) {
    await unshatter.evaluateAll((links) => links.forEach((link) => link.remove()));
  }
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

  test('category and taxonomy chips stay compact in both theme packs', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    const details = page.locator('#ksearchboxmain');

    for (const pack of ['Classic', 'Unshatter'] as const) {
      await useUnshatterStylesheet(page, pack === 'Unshatter');
      for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) {
          await details.locator('summary').click();
        }

        const heights = await page.locator('.nx-chip').evaluateAll((chips) =>
          chips.map((chip) => ({
            label: chip.textContent?.trim() ?? '',
            height: chip.getBoundingClientRect().height,
          })),
        );
        expect(heights.length, `${pack} at ${width}px should render chips`).toBeGreaterThan(0);
        const outOfRange = heights.filter(({ height }) => height < 30 || height > 36);
        expect(
          outOfRange,
          `${pack} at ${width}px chip heights: ${JSON.stringify(outOfRange)}`,
        ).toEqual([]);
      }
    }
  });

  test('filter summary spans its panel and count badge stays compact', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/web/torrents?medium9=1', { waitUntil: 'networkidle' });
    const details = page.locator('#ksearchboxmain');
    await expandSearchPanel(page);
    await expect(details.locator('.nxm-filters__count')).toBeVisible();

    for (const pack of ['Classic', 'Unshatter'] as const) {
      await useUnshatterStylesheet(page, pack === 'Unshatter');
      for (const scheme of ['light', 'dark'] as const) {
        await page.evaluate((theme) => {
          document.documentElement.setAttribute('data-theme', theme);
        }, scheme);
        const metrics = await details.evaluate((element) => {
          const summary = element.querySelector('summary')!;
          const badge = element.querySelector('.nxm-filters__count')!;
          const summaryStyle = getComputedStyle(summary);
          const badgeStyle = getComputedStyle(badge);
          const summaryRect = summary.getBoundingClientRect();
          const badgeRect = badge.getBoundingClientRect();

          return {
            summaryWidth: summaryRect.width,
            detailsInnerWidth: element.clientWidth,
            summaryCssWidth: summaryStyle.width,
            summaryDisplay: summaryStyle.display,
            summaryPadding: summaryStyle.padding,
            summaryBackground: summaryStyle.backgroundColor,
            summaryMarginBottom: summaryStyle.marginBottom,
            badgeHeight: badgeRect.height,
            badgeLineHeight: badgeStyle.lineHeight,
            badgePadding: badgeStyle.padding,
          };
        });
        const context = `${pack}/${scheme}: ${JSON.stringify(metrics)}`;
        expect(
          Math.abs(metrics.summaryWidth - metrics.detailsInnerWidth),
          context,
        ).toBeLessThanOrEqual(4);
        expect(metrics.badgeHeight, context).toBeLessThanOrEqual(22);
      }
    }
  });

  test('table frame, sorting, pager sizing, focus, and accessibility follow tokens', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/web/torrents?sort=4&type=desc', { waitUntil: 'networkidle' });
    const table = page.locator('table.nx-torrents');

    for (const pack of ['Classic', 'Unshatter'] as const) {
      await useUnshatterStylesheet(page, pack === 'Unshatter');
      for (const theme of ['light', 'dark'] as const) {
        await page.evaluate((value) => {
          document.documentElement.dataset.theme = value;
        }, theme);
        const frame = await table.evaluate((element) => ({
          radius: parseFloat(getComputedStyle(element).borderTopLeftRadius),
          radiusToken: parseFloat(
            getComputedStyle(document.documentElement).getPropertyValue('--nxm-radius'),
          ),
          overflow: getComputedStyle(element).overflow,
          firstHeader: getComputedStyle(element.querySelector('th')!).backgroundColor,
        }));
        expect(frame.radius).toBe(frame.radiusToken);
        if (frame.radiusToken > 0) {
          expect(frame.radius).toBeGreaterThan(0);
        }
        expect(frame.overflow).toBe('hidden');
        expect(frame.firstHeader).not.toBe('rgba(0, 0, 0, 0)');
        const sorted = page.locator('.nx-torrents th.nxm-th--sorted');
        await expect(sorted).toHaveAttribute('aria-sort', 'descending');
        const arrow = await sorted.evaluate((element) => getComputedStyle(element, '::after').content);
        expect(arrow).toContain('▼');
        const axe = await new AxeBuilder({ page }).include('.nx-torrents').analyze();
        expect(axe.violations).toEqual([]);
      }
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => {
      document.documentElement.dataset.theme = 'light';
    });
    const mobile = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth > window.innerWidth,
      border: getComputedStyle(document.querySelector('table.nx-torrents')!).borderTopWidth,
    }));
    expect(mobile.overflow).toBe(false);
    expect(mobile.border).toBe('0px');

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/web/log?action=dailylog', { waitUntil: 'networkidle' });
    const pagerLinks = page.locator('nav[aria-label^="Pagination"] a');
    await expect(pagerLinks.first()).toBeVisible();
    await expect(page.locator('nav[aria-label="Pagination bottom"] a[rel="next"]')).toBeVisible();
    for (const item of await pagerLinks.all()) {
      const box = await item.boundingBox();
      expect(box?.height ?? 0).toBeGreaterThanOrEqual(32);
    }
    await pagerLinks.first().focus();
    const outline = await pagerLinks.first().evaluate((element) => {
      const style = getComputedStyle(element);
      return { visible: element.matches(':focus-visible'), width: style.outlineWidth, style: style.outlineStyle };
    });
    expect(outline.visible).toBe(true);
    expect(outline.style).not.toBe('none');
    expect(parseFloat(outline.width)).toBeGreaterThan(0);
  });

  test('category chips auto-submit and preserve taxonomy picks, filters, and sorting', async ({
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
          url.searchParams.get(pendingName!) === '1' &&
          url.searchParams.get('sort') === '4' &&
          url.searchParams.get('type') === 'desc',
      ),
      category.locator('xpath=..').click(),
    ]);

    const url = new URL(page.url());
    expect(url.pathname).toBe('/web/torrents');
    expect(url.searchParams.get(field!)).toBe('1');
    expect(url.searchParams.get('medium1')).toBe('1');
    expect(url.searchParams.get(pendingName!)).toBe('1');
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

  test('applied-filter chips remove filters, support Enter, and reset all', async ({
    page,
  }) => {
    const query = '/web/torrents?incldead=2&spstate=1&sort=4&type=desc';
    await page.goto(query, { waitUntil: 'networkidle' });
    const chips = page.locator('.nxm-applied__chip');
    await expect(chips).toHaveCount(2);
    await Promise.all([
      page.waitForURL((url) =>
        url.pathname === '/web/torrents' &&
        !url.searchParams.has('incldead') &&
        url.searchParams.get('spstate') === '1' &&
        url.searchParams.get('sort') === '4' &&
        url.searchParams.get('type') === 'desc',
      ),
      chips.first().click(),
    ]);
    await expect(page.locator('.nxm-applied__chip')).toHaveCount(1);

    await page.waitForTimeout(1500);
    await page.goto(query, { waitUntil: 'networkidle' });
    const keyboardChip = page.locator('.nxm-applied__chip').first();
    await keyboardChip.focus();
    await Promise.all([
      page.waitForURL((url) => url.pathname === '/web/torrents' && !url.searchParams.has('incldead')),
      page.keyboard.press('Enter'),
    ]);
    await expect(page.locator('.nxm-applied__chip')).toHaveCount(1);

    await page.waitForTimeout(1500);
    await page.goto(query, { waitUntil: 'networkidle' });
    await Promise.all([
      page.waitForURL((url) => url.pathname === '/web/torrents' && url.search === ''),
      page.locator('.nxm-applied').getByRole('link', { name: 'Reset all' }).click(),
    ]);
    await expect(page.locator('.nxm-applied')).toHaveCount(0);
  });

  test('applied-filter row passes axe in both theme packs and wraps on mobile', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/web/torrents?incldead=2&size_begin=1&size_end=10', { waitUntil: 'networkidle' });
    for (const pack of ['Classic', 'Unshatter'] as const) {
      await useUnshatterStylesheet(page, pack === 'Unshatter');
      for (const theme of ['light', 'dark'] as const) {
        await page.evaluate((value) => {
          document.documentElement.dataset.theme = value;
        }, theme);
        const axe = await new AxeBuilder({ page }).include('.nxm-applied').analyze();
        expect(axe.violations, `${pack}/${theme}`).toEqual([]);
      }
    }
    await page.setViewportSize({ width: 390, height: 844 });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth,
    );
    expect(overflow).toBe(false);
  });

  test('Search submission carries pending taxonomy picks and range values', async ({
    page,
  }) => {
    await page.goto('/web/torrents', { waitUntil: 'networkidle' });
    await expandSearchPanel(page);

    const taxonomy = page
      .locator('#ksearchboxmain .nxm-taxonomy-chip__input[name^="medium"]')
      .first();
    const field = await taxonomy.getAttribute('name');
    expect(field).toMatch(/^medium\d+$/);
    if (!(await taxonomy.isChecked())) {
      await taxonomy.locator('xpath=..').click();
    }
    const initialUrl = page.url();
    await expect(taxonomy).toBeChecked();
    expect(page.url()).toBe(initialUrl);

    const range = page.locator('input[name="size_begin"]');
    await range.fill('1');
    await Promise.all([
      page.waitForURL(
        (url) =>
          url.pathname === '/web/torrents' &&
          url.searchParams.get(field!) === '1' &&
          url.searchParams.get('size_begin') === '1',
      ),
      range.press('Enter'),
    ]);
    const url = new URL(page.url());
    expect(url.searchParams.get(field!)).toBe('1');
    expect(url.searchParams.get('size_begin')).toBe('1');
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

  test('empty result offers a reset link, passes axe, and stays contained', async ({
    page,
  }) => {
    await page.goto('/web/torrents?incldead=2&search=zzz-no-such-torrent-browser', {
      waitUntil: 'networkidle',
    });
    const empty = page.locator('.nxm-empty');
    await expect(empty).toBeVisible();
    const reset = empty.getByRole('link', { name: 'Reset all' });
    await expect(reset).toBeVisible();

    const axe = await new AxeBuilder({ page }).include('.nxm-empty').analyze();
    expect(axe.violations).toEqual([]);

    await page.setViewportSize({ width: 390, height: 844 });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth,
    );
    expect(overflow).toBe(false);

    await Promise.all([
      page.waitForURL((url) => url.search === ''),
      reset.click(),
    ]);
    await expect(page.locator('.nxm-applied')).toHaveCount(0);
  });
});
