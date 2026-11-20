// Run with Playwright installed: node tests/flat-gallery.cjs [site URL].
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  const page = await browser.newPage({ ignoreHTTPSErrors: true, viewport: process.env.MOBILE ? { width: 390, height: 844 } : { width: 1440, height: 900 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto((process.argv[2] || 'https://naprozsad11.lndo.site') + '/content/csenkesz-utca', { waitUntil: 'load' });
    const images = page.locator('.node-flat .field--name-field-images .field__item a');
    assert.ok(await images.count() > 1, 'Gallery requires linked thumbnails');
    assert.equal(await page.locator('.flat-page__main h1').innerText(), 'Csenkesz utca');
    assert.ok(await page.locator('.flat-page .header-contact').isVisible());
    assert.ok(await page.locator('.flat-page .front-contact').isVisible());
    const layout = await page.locator('.node-flat .field--name-field-images .field__items').evaluate(element => ({ columns: getComputedStyle(element).gridTemplateColumns.split(' ').length, width: element.scrollWidth, viewport: document.documentElement.clientWidth }));
    assert.equal(layout.columns, process.env.MOBILE ? 3 : 5, 'Responsive gallery column count');
    assert.ok(layout.width <= layout.viewport, 'Gallery should fit viewport');
    const galleries = await images.evaluateAll(links => [...new Set(links.map(link => link.getAttribute('data-gallery')))]);
    assert.equal(galleries.length, 1, 'Images should belong to the same gallery');
    assert.ok(galleries[0], 'Gallery identifier must not be empty');
    assert.ok(!(await images.first().getAttribute('href')).includes('/styles/'), 'Popup should use the original image');
    assert.ok(await page.locator('.map-anchor').isVisible(), 'Property with a map should show the map link');
    await images.first().click();
    await page.locator('.glightbox-container').waitFor({ state: 'visible' });
    const first = await page.locator('.gslide.current img').getAttribute('src');
    await page.locator('.glightbox-container .gnext').click();
    await page.waitForFunction(src => document.querySelector('.gslide.current img')?.getAttribute('src') !== src, first);
    await page.keyboard.press('Escape');
    await page.locator('.glightbox-container').waitFor({ state: 'hidden' });
    assert.equal(await page.locator('.node-flat .field--name-field-weight').count(), 0, 'Weight should not be displayed');
    assert.equal(await page.locator('.node-flat .field--name-field-coordinates').count(), 0, 'Coordinates should not be displayed');
    await page.goto((process.argv[2] || 'https://naprozsad11.lndo.site') + '/content/csenkesz-u-3b', { waitUntil: 'load' });
    assert.equal(await page.locator('.map-anchor, #flat-map').count(), 0, 'Property without a map should not show a broken map link');
    assert.deepEqual(errors, []);
    console.log('PASS: thumbnails open a navigable gallery, Escape closes it, internal fields are hidden');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
