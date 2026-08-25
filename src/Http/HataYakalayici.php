<?php

declare(strict_types=1);

namespace Bist\Http;

use Bist\Config\Ortam;
use Closure;
use Throwable;

/**
 * Yakalanmamis istisnalari kullaniciya sizdirmadan cevaba cevirir.
 *
 * D4 (#8): Onceden hicbir istisna yakalayicisi yoktu. Iki kanitlanmis sonuc:
 *  - DB acilamayinca tam PDOException yigin izi doniyordu, ustelik HTTP 200 OK
 *    ile — cunku http_response_code() satirina hic ulasilmiyordu. Yani izleme
 *    sistemi instance'i "saglikli" goruyordu.
 *  - GET /?sirket[]=x -> "Warning: Array to string conversion in /.../src/App.php
 *    on line 47" ile kaynak dosya yolu ifsa oluyordu.
 *
 * Tasarim: kullaniciya olay kimligi + jenerik mesaj, log'a tam ayrinti.
 * Kullanici destege kimligi soyler, log'da ayni kimlik bulunur.
 */
final class HataYakalayici
{
    private readonly Closure $loglayici;

    public function __construct(
        private readonly Ortam $ortam,
        ?Closure $loglayici = null,
    ) {
        $this->loglayici = $loglayici ?? static function (string $mesaj): void {
            error_log($mesaj);
        };
    }

    /**
     * PHP'nin global kancalarina bagla.
     *
     * Uyari bastirilmaz, istisnaya cevrilir: "Array to string conversion"
     * gibi bir uyari sessizce gecmemeli, cunku o noktada girdi zaten
     * beklenmedik tiptedir.
     */
    public function bagla(): void
    {
        ini_set('display_errors', $this->ortam->hataDetayiGoster() ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_exception_handler(function (Throwable $t): void {
            $this->yaz($this->cevaba($t));
        });

        set_error_handler(static function (int $tur, string $mesaj, string $dosya, int $satir): bool {
            throw new \ErrorException($mesaj, 0, $tur, $dosya, $satir);
        });
    }

    /**
     * Istisnayi HTTP cevabina cevirir ve loglar.
     *
     * @return array{durum: int, tur: string, govde: string}
     */
    public function cevaba(Throwable $t): array
    {
        $olayKimligi = bin2hex(random_bytes(6));

        ($this->loglayici)(sprintf(
            '[%s] %s: %s @ %s:%d%s%s',
            $olayKimligi,
            $t::class,
            $t->getMessage(),
            $t->getFile(),
            $t->getLine(),
            PHP_EOL,
            $t->getTraceAsString(),
        ));

        $govde = $this->ortam->hataDetayiGoster()
            ? $this->ayrintiliGovde($t, $olayKimligi)
            : $this->jenerikGovde($olayKimligi);

        return [
            'durum' => 500,
            'tur' => 'text/html; charset=utf-8',
            'govde' => $govde,
        ];
    }

    private function jenerikGovde(string $olayKimligi): string
    {
        return $this->sayfa(
            'Beklenmeyen bir hata oluştu',
            '<p>İstek tamamlanamadı. Sorun kayda alındı.</p>'
            . '<p class="kimlik">Olay kimliği: <code>' . $olayKimligi . '</code></p>'
            . '<p class="mut">Destekle iletişime geçerseniz bu kimliği belirtin.</p>',
        );
    }

    private function ayrintiliGovde(Throwable $t, string $olayKimligi): string
    {
        $k = static fn (string $s): string
            => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->sayfa(
            'Hata (geliştirme ortamı)',
            '<p class="kimlik">Olay kimliği: <code>' . $olayKimligi . '</code></p>'
            . '<p><strong>' . $k($t::class) . '</strong></p>'
            . '<p>' . $k($t->getMessage()) . '</p>'
            . '<p class="mut">' . $k($t->getFile()) . ':' . $t->getLine() . '</p>'
            . '<pre>' . $k($t->getTraceAsString()) . '</pre>',
        );
    }

    private function sayfa(string $baslik, string $govde): string
    {
        $b = htmlspecialchars($baslik, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $b . '</title><style>'
            . 'body{background:#0D0D0D;color:#F2F2F2;font:16px/1.6 Inter,system-ui,sans-serif;'
            . 'margin:0;padding:48px 24px;max-width:820px}'
            . 'h1{color:#FF6B00;font-size:24px;letter-spacing:-.02em}'
            . 'code{font-family:ui-monospace,monospace;background:#1A1A1A;padding:2px 6px;border-radius:4px}'
            . 'pre{background:#141414;border:1px solid #282828;border-radius:8px;padding:16px;'
            . 'overflow-x:auto;font-size:12px}'
            . '.mut{color:#8A8A8A;font-size:13px}.kimlik{margin-top:20px}'
            . '</style></head><body><h1>' . $b . '</h1>' . $govde . '</body></html>';
    }

    /** @param array{durum: int, tur: string, govde: string} $cevap */
    private function yaz(array $cevap): void
    {
        if (!headers_sent()) {
            http_response_code($cevap['durum']);
            header('Content-Type: ' . $cevap['tur']);
            header('X-Content-Type-Options: nosniff');
        }

        echo $cevap['govde'];
    }
}
