/**
 * Kokpit E2E testi.
 *
 * Once sunucuyu baslat:
 *   E2E_DB_PATH=/tmp/e2e.sqlite php -S 127.0.0.1:8200 -t public tests/E2E/AppServerRouter.php
 * Sonra:
 *   node tests/E2E/kokpit.e2e.mjs
 */
import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;

const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8200';
const OUT = process.env.E2E_SHOT_DIR || '/tmp';
const CHROME = process.env.E2E_CHROME || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

const hatalar = [];
let gecen = 0;
function kontrol(kosul, ad) {
  if (kosul) { gecen++; console.log(`  OK   ${ad}`); }
  else { hatalar.push(ad); console.log(`  HATA ${ad}`); }
}

const b = await chromium.launch({ executablePath: CHROME });
const p = await b.newPage({ viewport: { width: 1400, height: 950 } });
// Konsol hatalari yalnizca sayfa yukleme evresinde toplanir; testin kendi
// 404/422 fetch cagrilari kasitlidir ve sayilmaz. Google Fonts bu sandbox'ta
// erisilemez oldugu icin ag hatasi haric tutulur (sayfa font fallback ile calisir).
let konsolTopla = true;
const konsol = [];
const beklenen = /fonts\.(googleapis|gstatic)\.com|ERR_CONNECTION_RESET|ERR_BLOCKED/;
p.on('console', m => {
  if (konsolTopla && m.type() === 'error' && !beklenen.test(m.text())) konsol.push(m.text());
});
p.on('pageerror', e => { if (konsolTopla) konsol.push('PAGEERROR ' + e.message); });

console.log('\n[1] Kokpit yukleniyor');
const cevap = await p.goto(BASE + '/', { waitUntil: 'networkidle' });
kontrol(cevap.status() === 200, 'HTTP 200');
kontrol((await p.title()).includes('TTRAK'), 'Baslikta sirket kodu var');

console.log('\n[2] G-15 · veri kaynagi durumu ustte gorunur');
const durum = await p.locator('.veri-durumu').first().innerText();
kontrol(durum.includes('Veri kaynağı bağlı değil'), 'Kaynak bagli degil uyarisi gorunuyor');
kontrol(durum.includes('temsili değildir'), 'Temsili veri uretilmedigi yaziyor');

console.log('\n[3] G-14 · eksik veri gizlenmiyor');
const kartSayisi = await p.locator('.kart').count();
const tireSayisi = await p.locator('.kart .deger', { hasText: '—' }).count();
kontrol(kartSayisi === 12, `12 metrik karti var (bulunan: ${kartSayisi})`);
kontrol(tireSayisi === kartSayisi, `Tum metrikler "—" (bulunan: ${tireSayisi})`);
const ilkNeden = await p.locator('.kart .kaynak.eksik').first().innerText();
kontrol(ilkNeden.trim().length > 0, 'Her eksik metrigin nedeni yaziyor');

console.log('\n[4] G-13 · her rakamin yaninda "ne demek" satiri');
const neDemekSayisi = await p.locator('.kart .ne-demek').count();
kontrol(neDemekSayisi === kartSayisi, `Her kartta ne-demek satiri (${neDemekSayisi}/${kartSayisi})`);
const bosNeDemek = await p.locator('.kart .ne-demek').evaluateAll(
  els => els.filter(e => e.textContent.trim().length < 10).length);
kontrol(bosNeDemek === 0, 'Hicbir ne-demek satiri bos degil');

console.log('\n[5] G-12 · kontrol degisince sonuc BUTON BEKLEMEDEN guncelleniyor');
const oncekiEsik = await p.locator('#esik-deger').innerText();
await p.locator('#esik').fill('20');
await p.locator('#esik').dispatchEvent('input');
const sonrakiEsik = await p.locator('#esik-deger').innerText();
kontrol(oncekiEsik !== sonrakiEsik, `Esik aninda guncellendi (${oncekiEsik} -> ${sonrakiEsik})`);

const goruntuOnce = await p.locator('.kart:visible').count();
await p.locator('#eksikleri-gizle').check();
await p.waitForTimeout(120);
const goruntuSonra = await p.locator('.kart:visible').count();
kontrol(goruntuSonra < goruntuOnce, `Onay kutusu listeyi aninda degistirdi (${goruntuOnce} -> ${goruntuSonra})`);
await p.locator('#eksikleri-gizle').uncheck();

console.log('\n[6] G-22 · tarayici depolamasi kullanilmiyor');
const depolama = await p.evaluate(() => ({
  ls: window.localStorage.length,
  ss: window.sessionStorage.length,
}));
kontrol(depolama.ls === 0 && depolama.ss === 0, 'localStorage ve sessionStorage bos');

console.log('\n[7] G-18 · alt bilgi zorunlu metni');
const dip = await p.locator('.dip').innerText();
kontrol(dip.includes('yatırım tavsiyesi değildir'), 'Yatirim tavsiyesi degildir ibaresi var');
kontrol(dip.includes('Kriterleri kullanıcı belirler'), 'Kriterleri kullanici belirler ibaresi var');

console.log('\n[8] G-09 · tavsiye dili yok');
const govde = (await p.locator('body').innerText()).toLowerCase();
// Alt dizi degil KELIME siniri: "al" kelimesi "mal ve hizmet" icinde eslesmemeli.
const yasakKelime = ['cazip', 'fırsat', 'ucuz', 'pahalı', 'al', 'sat', 'tut', 'öneri', 'tavsiyemiz'];
const bulunan = yasakKelime.filter(k => new RegExp(`(^|[^\\p{L}])${k}([^\\p{L}]|$)`, 'u').test(govde));
const yasakObek = ['hedef fiyat', 'alım için', 'satış için'].filter(k => govde.includes(k));
kontrol(bulunan.length === 0 && yasakObek.length === 0,
  `Yasak ifade yok (bulunan: ${[...bulunan, ...yasakObek].join(', ') || 'yok'})`);

console.log('\n[9] G-24 · tasarim tokenleri');
const tokenlar = await p.evaluate(() => {
  const s = getComputedStyle(document.documentElement);
  return { bg: s.getPropertyValue('--bg').trim(), acc: s.getPropertyValue('--acc').trim() };
});
kontrol(tokenlar.bg === '#0D0D0D', `--bg dogru (${tokenlar.bg})`);
kontrol(tokenlar.acc === '#FF6B00', `--acc dogru (${tokenlar.acc})`);

console.log('\n[10] G-23 · klavye odagi gorunur');
await p.locator('#esik').focus();
const odak = await p.evaluate(() => {
  const s = getComputedStyle(document.activeElement);
  return { id: document.activeElement.id, outline: s.outlineWidth };
});
kontrol(odak.id === 'esik' && odak.outline !== '0px', `Odak gorunur (${odak.outline})`);

await p.screenshot({ path: `${OUT}/e2e-01-masaustu.png` });

console.log('\n[11] G-23 · mobil gorunum');
await p.setViewportSize({ width: 390, height: 844 });
await p.waitForTimeout(150);
const tasma = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
kontrol(!tasma, 'Mobilde yatay tasma yok');
await p.screenshot({ path: `${OUT}/e2e-02-mobil.png`, fullPage: false });
await p.setViewportSize({ width: 1400, height: 950 });

konsolTopla = false; // bundan sonraki adimlar kasitli 404/413/422/405 uretiyor
console.log('\n[12] sqlite · kriter seti kaydediliyor (G-19 hesap verebilirlik)');
const kayit = await p.evaluate(async (base) => {
  const r = await fetch(base + '/kriter', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      ad: 'Muhafazakâr set',
      gerekce: 'Borçlu şirket istemiyorum.',
      kriterler: [
        { ad: 'F/K', anahtar: 'fk', operator: '<', esik: 12 },
        { ad: 'Net borç/FAVÖK', anahtar: 'net_borc_favok', operator: '<', esik: 2 },
      ],
    }),
  });
  return { durum: r.status, govde: await r.json() };
}, BASE);
kontrol(kayit.durum === 201, `Kriter seti kaydedildi (HTTP ${kayit.durum})`);
kontrol(typeof kayit.govde.id === 'number', 'Kayit id dondu');

const liste = await p.evaluate(async (base) => (await fetch(base + '/kriter')).json(), BASE);
const kaydimiz = liste.setler.find(s => s.id === kayit.govde.id);
kontrol(kaydimiz !== undefined, 'Kaydedilen set listede bulundu');
kontrol(kaydimiz?.gerekce === 'Borçlu şirket istemiyorum.', 'Kullanicinin gerekcesi saklandi');
kontrol(kaydimiz?.kriter_sayisi === 2, 'Iki kriter saklandi');

console.log('\n[13] G-11 · bos kriter seti reddedilir');
const bos = await p.evaluate(async (base) => {
  const r = await fetch(base + '/kriter', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ad: 'Boş', kriterler: [] }),
  });
  return { durum: r.status, govde: await r.json() };
}, BASE);
kontrol(bos.durum === 422, `Bos set reddedildi (HTTP ${bos.durum})`);
kontrol((bos.govde.hata || '').includes('eşik'), 'Red gerekcesi esik yoklugu');

console.log('\n[14] D9 (#13) · govde boyutu siniri');
const buyuk = await p.evaluate(async (base) => {
  const kriter = { ad: 'F/K', anahtar: 'fk', operator: '<', esik: 1 };
  const r = await fetch(base + '/kriter', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ad: 'saldiri', kriterler: Array(200000).fill(kriter) }),
  });
  return r.status;
}, BASE);
kontrol(buyuk === 413, `12 MB govde reddedildi (HTTP ${buyuk})`);

const cokKriter = await p.evaluate(async (base) => {
  const kriter = { ad: 'F/K', anahtar: 'fk', operator: '<', esik: 1 };
  const r = await fetch(base + '/kriter', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ad: 'cok', kriterler: Array(51).fill(kriter) }),
  });
  return r.status;
}, BASE);
kontrol(cokKriter === 422, `51 kriter reddedildi (HTTP ${cokKriter})`);

const kotuOp = await p.evaluate(async (base) => {
  const r = await fetch(base + '/kriter', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ad: 'x', kriterler: [{ ad: 'F/K', anahtar: 'fk', operator: 'DROP', esik: 1 }] }),
  });
  return { durum: r.status, govde: await r.text() };
}, BASE);
kontrol(kotuOp.durum === 422 && !kotuOp.govde.includes('Bist\\'),
  'Gecersiz operator ic namespace sizdirmiyor');

console.log('\n[15] D4 (#8) · metod ayrimi ve yol ifsasi');
for (const m of ['PUT', 'DELETE', 'PATCH']) {
  const d = await p.evaluate(async (a) => (await fetch(a.base + '/kriter', { method: a.m })).status,
    { base: BASE, m });
  kontrol(d === 405, `${m} -> 405 (once 200 + tam liste doniyordu)`);
}

const dizi = await p.goto(BASE + '/?sirket[]=x', { waitUntil: 'networkidle' });
const diziGovde = await p.locator('body').innerText();
kontrol(dizi.status() === 200 && !/Warning|Array to string|\/home\//.test(diziGovde),
  '?sirket[]=x uyari veya dosya yolu sizdirmiyor');

const satirSonu = await p.evaluate(async (base) =>
  (await fetch(base + '/?sirket=TTRAK%0A')).status, BASE);
kontrol(satirSonu === 200, `?sirket=TTRAK%0A guvenli isleniyor (HTTP ${satirSonu})`);

await p.goto(BASE + '/', { waitUntil: 'networkidle' });

console.log('\n[16] D5 (#9) · saglik denetimi DB\'ye gercekten dokunuyor');
const saglik = await p.evaluate(async (base) => {
  const r = await fetch(base + '/saglik');
  return { durum: r.status, cache: r.headers.get('cache-control'), govde: await r.json() };
}, BASE);
kontrol(saglik.durum === 200, `saglikli DB -> 200 (HTTP ${saglik.durum})`);
kontrol(saglik.govde.bilesenler?.db === 'ok', 'db bileseni raporlaniyor');
kontrol(saglik.cache === 'no-store', `Cache-Control: no-store (${saglik.cache})`);

console.log('\n[17] konsol temizligi');
kontrol(konsol.length === 0, `Konsol hatasi yok (${konsol.join(' | ') || 'temiz'})`);

await b.close();

console.log(`\n${'='.repeat(52)}`);
console.log(`GECEN: ${gecen}   KALAN: ${hatalar.length}`);
if (hatalar.length) { hatalar.forEach(h => console.log('  - ' + h)); process.exit(1); }
console.log('Tum E2E kontrolleri gecti.');
