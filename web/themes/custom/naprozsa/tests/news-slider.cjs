// Run with Playwright installed: node tests/news-slider.cjs [site URL].
const { chromium } = require('playwright');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.CHROME_PATH || undefined,
    args: ['--no-sandbox'],
  });
  const page = await browser.newPage({
    ignoreHTTPSErrors: true,
    viewport: process.env.MOBILE ? { width: 390, height: 844 } : { width: 1440, height: 900 },
  });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.text().includes('options not found')) errors.push(message.text());
  });
  try {
    await page.goto(process.argv[2] || 'https://naprozsad11.lndo.site/', { waitUntil: 'load' });
    const slider = '#views_slideshow_cycle_teaser_section_news-block';
    const pager = '.views_slideshow_pager_field_item';
    assert.ok(await page.locator(pager).count() >= 2, 'Slider needs at least two published news items');
    try {
      await page.waitForFunction(selector => Boolean(window.jQuery?.(selector).data('cycle.opts')), slider, { timeout: 8000 });
    } catch (error) {
      throw new Error(`Slider did not initialize. JavaScript errors: ${errors.join('; ')}`, { cause: error });
    }
    const initial = await page.evaluate(selector => window.jQuery(selector).data('cycle.opts').currSlide, slider);
    const target = initial === 0 ? 1 : 0;
    await page.locator(pager).nth(target).click();
    await page.waitForFunction(({ selector, target }) => window.jQuery(selector).data('cycle.opts')?.currSlide === target, { selector: slider, target }, { timeout: 5000 });
    await page.mouse.move(1, 1);
    await page.waitForFunction(({ selector, target }) => window.jQuery(selector).data('cycle.opts')?.currSlide !== target, { selector: slider, target }, { timeout: 10000 });
    assert.deepEqual(errors, [], `JavaScript errors: ${errors.join('; ')}`);
    console.log(`PASS: pager changed slide ${initial} -> ${target}, autoplay advanced, no JavaScript errors`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
