<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Data\BosKaynak;
use Bist\Domain\Etiket;
use Bist\Domain\Metrik;
use Bist\Domain\Tms29Durum;
use Bist\Render\Kokpit;
use Bist\Render\KokpitRenderer;
use PHPUnit\Framework\TestCase;

/**
 * proje-talimati.md BOLUM 2 (kokpit cercevesi) ve
 * docs/REHBER-SAYFA-HARITASI.md G-12..G-15, G-18, G-21..G-24
 */
final class KokpitRendererTest extends TestCase
{
    private function kokpit(): Kokpit
    {
        $kaynak = new BosKaynak();

        return new Kokpit(
            sirketKodu: 'TTRAK',
            kaynak: $kaynak,
            metrikler: [
                new Metrik(
                    ad: 'Net satış',
                    neDemek: 'Şirketin sattığı mal ve hizmetin toplam tutarı.',
                    deger: 12_400_000_000.0,
                    birim: 'TL',
                    donem: '2025/12',
                    kaynak: 'KAP',
                    tms29: Tms29Durum::DUZELTILMIS,
                ),
                $kaynak->metrik('TTRAK', 'fk'),
            ],
        );
    }

    private function html(): string
    {
        return (new KokpitRenderer())->render($this->kokpit());
    }

    // --- G-21: tek dosya, disa bagimlilik yok ---

    public function testTekDosyaHtmlUretilir(): void
    {
        $h = $this->html();

        self::assertStringStartsWith('<!doctype html>', $h);
        self::assertStringContainsString('</html>', $h);
        self::assertStringContainsString('<style>', $h);
        self::assertStringContainsString('<script>', $h);
    }

    public function testHarciScriptVeyaStylesheetYuklenmez(): void
    {
        $h = $this->html();

        self::assertDoesNotMatchRegularExpression('/<script[^>]+src=/i', $h);
        // Google Fonts disinda dis stylesheet olmamali
        preg_match_all('/<link[^>]+href="([^"]+)"/i', $h, $m);
        foreach ($m[1] as $href) {
            self::assertMatchesRegularExpression(
                '#^(data:|https://fonts\.(googleapis|gstatic)\.com)#',
                $href,
                "İzin verilmeyen dış kaynak: {$href}",
            );
        }
    }

    public function testFaviconGomuludurVeAyriIstekUretmez(): void
    {
        $h = $this->html();

        self::assertMatchesRegularExpression(
            '/<link[^>]+rel="icon"[^>]+href="data:/i',
            $h,
            'Favicon gömülü değil; tarayıcı /favicon.ico isteyip 404 alır.',
        );
    }

    // --- G-22: localStorage / sessionStorage kullanilmaz ---

    public function testTarayiciDepolamasiKullanilmaz(): void
    {
        $h = $this->html();

        self::assertStringNotContainsString('localStorage', $h);
        self::assertStringNotContainsString('sessionStorage', $h);
        self::assertStringNotContainsString('indexedDB', $h);
    }

    // --- G-24: tasarim tokenleri birebir ---

    public function testTasarimTokenleriProjeTalimatiylaAynidir(): void
    {
        $h = $this->html();

        foreach ([
            '--bg:#0D0D0D', '--panel:#141414', '--panel-2:#1A1A1A',
            '--line:#282828', '--acc:#FF6B00', '--tx:#F2F2F2', '--mut:#8A8A8A',
        ] as $token) {
            self::assertStringContainsString($token, str_replace(' ', '', $h), "Eksik token: {$token}");
        }

        self::assertStringContainsString('rgba(255,107,0,.14)', str_replace(' ', '', $h));
    }

    public function testFontlarInterVeJetBrainsMonodur(): void
    {
        $h = $this->html();

        self::assertStringContainsString('Inter', $h);
        self::assertStringContainsString('JetBrains Mono', $h);
    }

    // --- G-02, G-13, G-14: her metrik satiri ---

    public function testDoluMetrikDegerNeDemekVeKaynakSatiriniBirlikteGosterir(): void
    {
        $h = $this->html();

        self::assertStringContainsString('12,40 milyar TL', $h);
        self::assertStringContainsString('Şirketin sattığı mal ve hizmetin toplam tutarı.', $h);
        self::assertStringContainsString('KAP · 2025/12', $h);
    }

    public function testEksikMetrikTireIleGosterilirVeNedeniYazar(): void
    {
        $h = $this->html();

        self::assertStringContainsString('—', $h);
        self::assertStringContainsString('Veri kaynağı bağlanmadı.', $h);
        self::assertStringContainsString(Etiket::KAYNAKSIZ->metin(), $h);
    }

    // --- G-15: kaynak bagli degilse ustte yazar ---

    public function testKaynakBagliDegilseEnUsttdeUyariGorunur(): void
    {
        $h = $this->html();

        self::assertStringContainsString('veri-durumu', $h);
        self::assertStringContainsString('bağlı kaynak yok', $h);
    }

    // --- G-12: etkilesim ---

    public function testEnAzBirEtkilesimliKontrolVardir(): void
    {
        $h = $this->html();

        self::assertMatchesRegularExpression('/<(input|select|button)[^>]*>/i', $h);
        // kontrol degisince aninda guncelleme: buton beklemeden dinleyici
        self::assertMatchesRegularExpression('/addEventListener\(\s*[\'"](input|change)[\'"]/', $h);
    }

    // --- G-18: alt bilgi zorunlu ---

    public function testAltBilgiZorunluMetniIcerir(): void
    {
        $h = $this->html();

        self::assertStringContainsString(
            'Bu pano analiz çıktısıdır, yatırım tavsiyesi değildir.',
            $h,
        );
        self::assertStringContainsString('Kriterleri kullanıcı belirler.', $h);
    }

    // --- G-09: tavsiye dili yok ---

    public function testCiktidaTavsiyeDiliBulunmaz(): void
    {
        $h = mb_strtolower($this->html(), 'UTF-8');

        foreach (['cazip', 'fırsat', 'ucuz', 'pahalı', 'hedef fiyat', 'öneriyoruz', 'tavsiye ederiz'] as $yasak) {
            self::assertStringNotContainsString($yasak, $h, "Yasak ifade: {$yasak}");
        }
    }

    // --- guvenlik: HTML kacisi ---

    public function testMetrikAdiHtmlOlarakKacirilir(): void
    {
        $kaynak = new BosKaynak();
        $k = new Kokpit(
            sirketKodu: '<script>alert(1)</script>',
            kaynak: $kaynak,
            metrikler: [new Metrik(
                ad: '<img src=x onerror=alert(1)>',
                neDemek: 'Açıklama.',
                eksikNedeni: 'Yok.',
            )],
        );

        $h = (new KokpitRenderer())->render($k);

        self::assertStringNotContainsString('<script>alert(1)</script>', $h);
        self::assertStringNotContainsString('<img src=x', $h);
        self::assertStringContainsString('&lt;script&gt;', $h);
    }

    // --- G-23: mobil ---

    public function testMobilGorunumIcinMedyaSorgusuVardir(): void
    {
        $h = $this->html();

        self::assertStringContainsString('viewport', $h);
        self::assertStringContainsString('@media', $h);
        self::assertStringContainsString('900px', $h);
    }

    public function testKlavyeOdagiGorunur(): void
    {
        self::assertStringContainsString(':focus', $this->html());
    }
}
