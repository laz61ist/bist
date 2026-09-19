<?php

declare(strict_types=1);

/** Tüm GET rotalarını örnek parametrelerle (id=1, slug=donem-ozeti…) yazdırır; duman testi için. */
require dirname(__DIR__) . '/app/bootstrap.php';
$router = app()->router();
$skip = ['/dosya/{token}', '/makbuz-dogrula/{code}', '/sifre-sifirla/{token}', '/yedek/indir/{name}', '/yonetim/raporlar/{slug}/disa-aktar'];
$reports = ['donem-ozeti', 'tahakkuk-tahsilat', 'borclu-yaslandirma', 'bolum-ekstresi', 'kisi-ekstresi', 'kasa-defteri', 'gunluk-hareket', 'gelir-gider', 'kategori-dagilimi', 'nakit-akisi', 'butce-gerceklesen', 'rezerv-fonu', 'tedarikci', 'sozlesme-vade', 'sayac-tuketim', 'malik-kiraci-sorumluluk', 'devir-bakiye', 'avans-mahsup', 'iptal-iade', 'tahsilat-yontemi', 'sakin-listesi', 'denetim-izi'];
$seen = [];
foreach ($router->routes() as $r) {
    if ($r->method !== 'GET' || in_array($r->pattern, $skip, true)) {
        continue;
    }
    if (str_contains($r->pattern, '{slug}')) {
        foreach ($reports as $slug) {
            $seen[] = str_replace('{slug}', $slug, $r->pattern);
        }
        continue;
    }
    $path = preg_replace('/\{[a-z]+(?::[^}]+)?\}/', '1', $r->pattern);
    $seen[] = $path;
}
$extra = ['/yonetim/bolumler/1/ekstre?yazdir=1', '/yonetim/ara?q=demo', '/yonetim/ayarlar?sekme=gecikme', '/yonetim/ayarlar?sekme=makbuz', '/yonetim/ayarlar?sekme=seffaflik', '/yonetim/tahsilatlar/1/makbuz', '/yonetim/borclular?format=csv'];
foreach (array_unique(array_merge($seen, $extra)) as $p) {
    echo $p, "\n";
}
