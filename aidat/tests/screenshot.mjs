// Ekran görüntüsü aracı: node aidat/tests/screenshot.mjs [baseUrl] [outDir]
import { chromium } from 'playwright';
const base = process.argv[2] || 'http://127.0.0.1:8090';
const out = process.argv[3] || 'aidat/var/ekran';
const pages = (process.argv[4] || '/giris,/yonetim,/yonetim/bolumler,/yonetim/bolumler/1,/yonetim/kisiler').split(',');
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'tr-TR' });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', e => errors.push('pageerror: ' + e.message));
page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
await page.goto(base + '/giris');
await page.fill('input[name=email]', process.env.AIDAT_USER || 'demo.yonetici@aidat.local');
await page.fill('input[name=password]', process.env.AIDAT_PASS || 'Demo1234!');
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
for (const p of pages) {
  await page.goto(base + p, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  const name = p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'root';
  await page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
  console.log('ok', p, '->', `${out}/${name}.png`);
}
// Mobil görünüm
const m = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, locale: 'tr-TR' });
const mp = await m.newPage();
await mp.goto(base + '/giris');
await mp.fill('input[name=email]', process.env.AIDAT_USER || 'demo.yonetici@aidat.local');
await mp.fill('input[name=password]', process.env.AIDAT_PASS || 'Demo1234!');
await mp.click('button[type=submit]');
await mp.waitForLoadState('networkidle');
await mp.goto(base + pages[1] || '/yonetim', { waitUntil: 'networkidle' });
await mp.screenshot({ path: `${out}/mobil.png`, fullPage: true });
const overflow = await mp.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
console.log('mobil yatay taşma:', overflow ? 'VAR' : 'yok');
if (errors.length) console.log('JS hataları:\n' + errors.join('\n')); else console.log('JS hatası yok');
await browser.close();
