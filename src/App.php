<?php

declare(strict_types=1);

namespace Bist;

use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use Bist\Data\MetrikSozlugu;
use Bist\Data\VeriKaynagi;
use Bist\Domain\Kriter;
use Bist\Domain\KriterSeti;
use Bist\Domain\KriterTarama;
use Bist\Domain\Operator;
use Bist\Render\Kokpit;
use Bist\Render\KokpitRenderer;
use InvalidArgumentException;

/** Kokpit uygulamasinin giris noktasi. */
final class App
{
    /**
     * Alan bazli sinirlar (D9 / #13).
     *
     * Govde boyutu sinirlansa bile 64 KB icine binlerce kisa kriter sigar.
     * Bu yuzden alan sayisi ve uzunlugu AYRICA denetlenir. Uzunluklar
     * karakter cinsindendir; Turkce karakterler UTF-8'de cok bayt tutar.
     */
    public const AZAMI_KRITER = 50;
    public const AZAMI_AD = 120;
    public const AZAMI_GEREKCE = 2000;

    private ?KriterDeposu $depo = null;
    private readonly ?\Closure $depoSaglayici;

    /**
     * @param KriterDeposu|\Closure(): KriterDeposu|null $depo
     *        Kapanis verilirse depo TEMBEL kurulur: kokpit istegi DB'ye
     *        hic dokunmaz (D4 / #8, K6).
     */
    public function __construct(
        private readonly VeriKaynagi $kaynak = new BosKaynak(),
        KriterDeposu|\Closure|null $depo = null,
        ?\Closure $depoSaglayici = null,
    ) {
        if ($depo instanceof KriterDeposu) {
            $this->depo = $depo;
            $this->depoSaglayici = null;
        } else {
            $this->depoSaglayici = $depoSaglayici ?? $depo;
        }
    }

    /** Depoyu ilk ihtiyac aninda kurar; kuramazsa null doner. */
    private function depo(): ?KriterDeposu
    {
        if ($this->depo !== null) {
            return $this->depo;
        }

        if ($this->depoSaglayici === null) {
            return null;
        }

        return $this->depo = ($this->depoSaglayici)();
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @return array{durum: int, tur: string, govde: string}
     */
    public function calistir(string $yol, string $yontem = 'GET', array $get = [], array $post = []): array
    {
        $yontem = strtoupper($yontem);

        if ($yol === '/saglik') {
            return $this->saglik();
        }

        if ($yol === '/' || $yol === '/kokpit') {
            return $this->kokpit($get);
        }

        if ($yol === '/kriter') {
            // S5 (#15): metod ayrimi yoktu; PUT/DELETE/PATCH de 200 + tam liste donuyordu
            return match ($yontem) {
                'POST' => $this->kriterKaydet($post),
                'GET', 'HEAD' => $this->kriterListe(),
                default => $this->metodYok(['GET', 'HEAD', 'POST']),
            };
        }

        return ['durum' => 404, 'tur' => 'text/html; charset=utf-8', 'govde' => $this->sayfa404()];
    }

    /** @param array<string, mixed> $get */
    private function kokpit(array $get): array
    {
        // Dizi girdi (?sirket[]=x) once uyari uretiyordu (#8); is_string ile eleniyor.
        $ham = $get['sirket'] ?? null;
        $kod = is_string($ham) ? strtoupper(trim($ham)) : 'TTRAK';

        // \A ve \z kullaniliyor: PCRE'de $ sondaki tek \n'i eslesirdi (#15/S8),
        // yani ?sirket=TTRAK%0A dogrulamayi geciyordu.
        if (!preg_match('/\A[A-Z0-9]{1,10}\z/', $kod)) {
            $kod = 'TTRAK';
        }

        $metrikler = array_map(
            fn (string $anahtar) => $this->kaynak->metrik($kod, $anahtar),
            MetrikSozlugu::anahtarlar(),
        );

        $html = (new KokpitRenderer())->render(new Kokpit(
            sirketKodu: $kod,
            kaynak: $this->kaynak,
            metrikler: $metrikler,
            baslik: 'Kokpit',
            altBaslik: 'Her rakamın yanında kaynağı ve dönemi yazar. Bulunamayan veri gizlenmez.',
        ));

        return ['durum' => 200, 'tur' => 'text/html; charset=utf-8', 'govde' => $html];
    }

    /** @param array<string, mixed> $post */
    private function kriterKaydet(array $post): array
    {
        $depo = $this->depoGuvenli();
        if ($depo === null) {
            return $this->json(['hata' => 'Kriter deposu şu anda kullanılamıyor.'], 503);
        }

        $ad = trim((string) ($post['ad'] ?? ''));
        $gerekce = trim((string) ($post['gerekce'] ?? ''));
        $ham = $post['kriterler'] ?? [];
        $ham = is_array($ham) ? $ham : [];

        if (mb_strlen($ad, 'UTF-8') > self::AZAMI_AD) {
            return $this->json([
                'hata' => sprintf('ad en fazla %d karakter olabilir.', self::AZAMI_AD),
            ], 422);
        }

        if (mb_strlen($gerekce, 'UTF-8') > self::AZAMI_GEREKCE) {
            return $this->json([
                'hata' => sprintf('gerekce en fazla %d karakter olabilir.', self::AZAMI_GEREKCE),
            ], 422);
        }

        if (count($ham) > self::AZAMI_KRITER) {
            return $this->json([
                'hata' => sprintf('En fazla %d kriter kabul edilir.', self::AZAMI_KRITER),
            ], 422);
        }

        $kriterler = [];
        foreach ($ham as $k) {
            $kriterAdi = (string) ($k['ad'] ?? '');

            if (mb_strlen($kriterAdi, 'UTF-8') > self::AZAMI_AD) {
                return $this->json([
                    'hata' => sprintf('Kriter adı en fazla %d karakter olabilir.', self::AZAMI_AD),
                ], 422);
            }

            $operator = Operator::tryFrom((string) ($k['operator'] ?? '<'));
            if ($operator === null) {
                // Istisna mesajini oldugu gibi dondurmek ic namespace'i sizdirir (#15/S4).
                return $this->json([
                    'hata' => 'operator geçersiz.',
                    'kabul_edilen' => array_map(
                        static fn (Operator $o): string => $o->value,
                        Operator::cases(),
                    ),
                ], 422);
            }

            $kriterler[] = new Kriter(
                $kriterAdi,
                (string) ($k['anahtar'] ?? ''),
                $operator,
                (float) ($k['esik'] ?? 0),
            );
        }

        try {
            $id = $depo->kaydet(
                $ad === '' ? 'Adsız set' : $ad,
                new KriterSeti($kriterler),
                $gerekce,
            );
        } catch (InvalidArgumentException $e) {
            return $this->json(['hata' => $e->getMessage()], 422);
        }

        return $this->json(['id' => $id], 201);
    }

    private function kriterListe(): array
    {
        $depo = $this->depoGuvenli();
        if ($depo === null) {
            return $this->json(['hata' => 'Kriter deposu şu anda kullanılamıyor.'], 503);
        }

        return $this->json(['setler' => array_map(static fn ($k): array => [
            'id' => $k->id,
            'ad' => $k->ad,
            'gerekce' => $k->gerekce,
            'kriter_sayisi' => $k->seti->sayi(),
            'olusturma' => $k->olusturma,
        ], $depo->hepsi())]);
    }

    /**
     * Depoyu kurar; kurulamazsa null doner. Istisna disari sizmaz —
     * cagiran 503 uretir, kullanici PDOException gormez (D4 / #8).
     */
    private function depoGuvenli(): ?KriterDeposu
    {
        try {
            return $this->depo();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<string> $izinli */
    private function metodYok(array $izinli): array
    {
        return [
            'durum' => 405,
            'tur' => 'application/json; charset=utf-8',
            'govde' => json_encode(
                ['hata' => 'Bu yol için metod desteklenmiyor.', 'izinli' => $izinli],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            'basliklar' => ['Allow' => implode(', ', $izinli)],
        ];
    }

    /** Saglik denetimi (D5 / #9 icinde gerceklestirilecek). */
    private function saglik(): array
    {
        return $this->json(['durum' => 'ok', 'kaynak' => $this->kaynak->ad()]);
    }

    /** @param array<string, mixed> $veri */
    private function json(array $veri, int $durum = 200): array
    {
        return [
            'durum' => $durum,
            'tur' => 'application/json; charset=utf-8',
            'govde' => json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    private function sayfa404(): string
    {
        return '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
            . '<title>404</title></head><body style="background:#0D0D0D;color:#F2F2F2;'
            . 'font-family:sans-serif;padding:40px"><h1>404</h1>'
            . '<p>Sayfa yok. <a href="/" style="color:#FF6B00">Kokpite dön</a></p></body></html>';
    }
}
