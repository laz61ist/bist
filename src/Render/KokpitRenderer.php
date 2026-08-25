<?php

declare(strict_types=1);

namespace Bist\Render;

use Bist\Domain\Etiket;
use Bist\Domain\Metrik;

/**
 * Kokpiti tek dosya HTML olarak uretir.
 *
 * proje-talimati.md BOLUM 2 kokpit cercevesi:
 * - G-21 Tek HTML dosyasi; CSS ve JS iceride
 * - G-22 localStorage/sessionStorage KULLANILMAZ; durum JS degiskeninde
 * - G-24 Tasarim tokenleri birebir
 * - G-12 Kontrol degisince sonuc buton beklemeden guncellenir
 * - G-13 Her rakamin yaninda "ne demek" satiri
 * - G-14 Eksik veri "—" ve nedeni
 * - G-18 Alt bilgi zorunlu
 */
final class KokpitRenderer
{
    private const ALT_BILGI = 'Bu pano analiz çıktısıdır, yatırım tavsiyesi değildir. '
        . 'Kriterleri kullanıcı belirler.';

    public function render(Kokpit $k): string
    {
        $kod = $this->k($k->sirketKodu);
        $durum = $this->veriDurumu($k);
        $kartlar = $this->kartlar($k->metrikler);
        $stil = $this->stil();
        $script = $this->script();
        $altBilgi = $this->k(self::ALT_BILGI);
        $baslik = $this->k($k->baslik);
        $altBaslik = $this->k($k->altBaslik);
        $kaynakAdi = $this->k($k->kaynak->ad());

        return <<<HTML
        <!doctype html>
        <html lang="tr"><head><meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>{$kod} · {$baslik}</title>
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%230D0D0D'/%3E%3Ctext x='16' y='23' font-family='monospace' font-size='17' font-weight='700' fill='%23FF6B00' text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap">
        <style>{$stil}</style></head>
        <body>
        <header class="ust">
          <div class="eyebrow">BIST KOKPİT · KAYNAKLI ANALİZ</div>
          <h1>{$kod} <span class="vurgu">{$baslik}</span></h1>
          <p class="alt-baslik">{$altBaslik}</p>
        </header>

        <div class="grid">
          <aside class="kontrol" aria-label="Kontroller">
            <div class="kart-baslik">KONTROLLER</div>

            <label for="esik">F/K üst eşiği <span class="mono" id="esik-deger">12,0</span></label>
            <input type="range" id="esik" min="1" max="40" step="0.5" value="12">

            <label for="donem">Dönem</label>
            <select id="donem">
              <option value="yillik">Yıllık</option>
              <option value="ceyreklik">Çeyreklik</option>
            </select>

            <label class="onay"><input type="checkbox" id="eksikleri-gizle"> Eksik metrikleri listeden çıkar</label>

            <p class="not">Eşikleri siz belirlersiniz. Bu pano kendi eşiğini üretmez.
            Kaynak: <span class="mono">{$kaynakAdi}</span></p>
          </aside>

          <main class="sonuc">
            {$durum}
            <section class="kartlar" id="kartlar">
              {$kartlar}
            </section>
          </main>
        </div>

        <footer class="dip">{$altBilgi}</footer>
        <script>{$script}</script>
        </body></html>
        HTML;
    }

    private function veriDurumu(Kokpit $k): string
    {
        $eksik = count(array_filter($k->metrikler, static fn (Metrik $m): bool => !$m->vardir()));
        $toplam = count($k->metrikler);
        $ad = $this->k($k->kaynak->ad());

        if (!$k->kaynak->bagli()) {
            return '<div class="veri-durumu uyari" role="status">'
                . '<strong>Veri kaynağı bağlı değil</strong> (' . $ad . '). '
                . 'Panodaki metrikler temsili değildir; bulunamayan her değer '
                . '<span class="mono">—</span> olarak gösterilir ve nedeni yazılır.'
                . '</div>';
        }

        return '<div class="veri-durumu" role="status">Kaynak: <span class="mono">' . $ad
            . '</span> · ' . ($toplam - $eksik) . '/' . $toplam . ' metrik dolu</div>';
    }

    /** @param list<Metrik> $metrikler */
    private function kartlar(array $metrikler): string
    {
        $parcalar = [];

        foreach ($metrikler as $m) {
            $etiketler = implode('', array_map(
                fn (Etiket $e): string => '<span class="etiket ' . $e->renk() . '">'
                    . $this->k($e->metin()) . '</span>',
                $m->etiketler(),
            ));

            $altSatir = $m->vardir()
                ? '<div class="kaynak mono">' . $this->k($m->kaynakSatiri()) . '</div>'
                : '<div class="kaynak mono eksik">' . $this->k((string) $m->eksikNedeni()) . '</div>';

            $bos = $m->vardir() ? '' : ' bos';

            $parcalar[] = '<article class="kart' . $bos . '" data-dolu="' . ($m->vardir() ? '1' : '0') . '">'
                . '<div class="kart-baslik">' . $this->k($m->ad) . '</div>'
                . '<div class="deger mono">' . $this->k($m->gorunenDeger()) . '</div>'
                . '<div class="ne-demek">' . $this->k($m->neDemek) . '</div>'
                . $altSatir
                . '<div class="etiketler">' . $etiketler . '</div>'
                . '</article>';
        }

        return implode("\n", $parcalar);
    }

    private function stil(): string
    {
        return <<<'CSS'
        :root{
          --bg:#0D0D0D; --panel:#141414; --panel-2:#1A1A1A; --line:#282828;
          --acc:#FF6B00; --acc-soft:rgba(255,107,0,.14); --tx:#F2F2F2; --mut:#8A8A8A;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--tx);
          font:16px/1.6 Inter,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
        .mono{font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace}
        .ust{padding:34px 28px 18px;max-width:1320px;margin:0 auto}
        .eyebrow{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:11px;
          letter-spacing:.18em;text-transform:uppercase;color:var(--acc);margin-bottom:10px}
        h1{font-size:34px;font-weight:800;letter-spacing:-.03em;margin:0 0 6px}
        h1 .vurgu{color:var(--acc)}
        .alt-baslik{color:var(--mut);margin:0}
        .grid{display:grid;grid-template-columns:340px 1fr;gap:18px;
          max-width:1320px;margin:0 auto;padding:10px 28px 28px;align-items:start}
        .kontrol,.kart,.veri-durumu{background:var(--panel);border:1px solid var(--line);
          border-radius:14px;padding:20px}
        .kart-baslik{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:11px;
          text-transform:uppercase;letter-spacing:.1em;color:var(--mut);margin-bottom:12px}
        .kontrol label{display:block;font-size:13px;color:var(--mut);margin:14px 0 6px}
        .kontrol label.onay{display:flex;align-items:center;gap:8px;color:var(--tx);font-size:14px}
        input[type=range]{width:100%;accent-color:var(--acc)}
        select,input[type=range],input[type=checkbox]{background:var(--panel-2);
          color:var(--tx);border:1px solid var(--line);border-radius:8px}
        select{width:100%;padding:9px 10px}
        select:focus,input:focus{outline:2px solid var(--acc);outline-offset:2px;
          box-shadow:0 0 0 4px var(--acc-soft);border-color:var(--acc)}
        .not{font-size:12px;color:var(--mut);margin:18px 0 0;line-height:1.5}
        .veri-durumu{margin-bottom:16px;font-size:14px;padding:14px 18px}
        .veri-durumu.uyari{border-color:var(--acc);background:var(--acc-soft)}
        .kartlar{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
        .kart.bos{opacity:.85}
        .deger{font-size:30px;font-weight:700;letter-spacing:-.02em;margin-bottom:8px}
        .kart.bos .deger{color:var(--mut)}
        .ne-demek{font-size:13px;color:var(--mut);margin-bottom:10px}
        .kaynak{font-size:10px;color:var(--mut)}
        .kaynak.eksik{color:var(--acc)}
        .etiketler{margin-top:12px;display:flex;flex-wrap:wrap;gap:6px}
        .etiket{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:10px;
          letter-spacing:.06em;padding:3px 8px;border-radius:999px;border:1px solid var(--line)}
        .etiket.turuncu{color:var(--acc);border-color:var(--acc);background:var(--acc-soft)}
        .etiket.gri{color:var(--mut)}
        .etiket.yesil{color:#7BD88F;border-color:#2F5B3A}
        .dip{max-width:1320px;margin:0 auto;padding:20px 28px 40px;color:var(--mut);
          font-size:12px;border-top:1px solid var(--line)}
        @media (max-width:900px){
          .grid{grid-template-columns:1fr;padding:10px 16px 20px}
          .ust{padding:24px 16px 12px}
          h1{font-size:26px}
        }
        CSS;
    }

    private function script(): string
    {
        // Durum yalnizca JS degiskeninde tutulur; tarayici depolamasi kullanilmaz (G-22).
        return <<<'JS'
        (function () {
          var durum = { esik: 12, donem: 'yillik', eksikleriGizle: false };

          var esik = document.getElementById('esik');
          var esikDeger = document.getElementById('esik-deger');
          var donem = document.getElementById('donem');
          var gizle = document.getElementById('eksikleri-gizle');

          function ciz() {
            esikDeger.textContent = durum.esik.toLocaleString('tr-TR', { minimumFractionDigits: 1 });
            var kartlar = document.querySelectorAll('#kartlar .kart');
            for (var i = 0; i < kartlar.length; i++) {
              var dolu = kartlar[i].getAttribute('data-dolu') === '1';
              kartlar[i].style.display = (durum.eksikleriGizle && !dolu) ? 'none' : '';
            }
            document.body.setAttribute('data-donem', durum.donem);
          }

          esik.addEventListener('input', function (e) {
            durum.esik = parseFloat(e.target.value);
            ciz();
          });
          donem.addEventListener('change', function (e) {
            durum.donem = e.target.value;
            ciz();
          });
          gizle.addEventListener('change', function (e) {
            durum.eksikleriGizle = e.target.checked;
            ciz();
          });

          ciz();
        })();
        JS;
    }

    private function k(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
