import { chromium } from 'playwright';
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const page = await ctx.newPage();
await page.goto('http://127.0.0.1:8090/giris');
await page.fill('input[name=email]', 'demo.yonetici@aidat.local'); await page.fill('input[name=password]', 'Demo1234!'); await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
for (const u of ['/yonetim', '/yonetim/bolumler', '/yonetim/bolumler/1', '/giris']) {
  await page.goto('http://127.0.0.1:8090' + u, { waitUntil: 'networkidle' });
  const r = await page.evaluate(() => { const w = document.documentElement.clientWidth; const out = []; document.querySelectorAll('body *').forEach(el => { const r = el.getBoundingClientRect(); if (r.right > w + 1 && r.width > 0 && getComputedStyle(el).position !== 'fixed') out.push(el.tagName + '.' + (el.className && el.className.baseVal === undefined ? el.className : '') + ' right=' + Math.round(r.right)); }); return { sw: document.documentElement.scrollWidth, w, out: out.slice(0, 8) }; });
  console.log(u, JSON.stringify(r));
}
await browser.close();
