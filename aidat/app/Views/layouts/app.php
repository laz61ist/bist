<?php
/** @var string $title */
$area = $area ?? 'manager';
$appName = config('app.name');
$user = $currentUser ?? auth_user();
$building = $building ?? null;
$pageTitle = trim(($title !== '' ? $title . ' · ' : '') . ($building['name'] ?? $appName));
$menu = config('menu.' . $area, []);
$shortcuts = [];
$pages = [];
foreach ($menu as $group) {
    foreach ($group['items'] as $item) {
        if (isset($item['can']) && $item['can'] !== null && !can($item['can'])) {
            continue;
        }
        if (!app()->router()->hasRoute($item['route'])) {
            continue;
        }
        $u = route($item['route']);
        $pages[] = ['t' => $item['label'], 'u' => $u, 'i' => $item['icon'], 'key' => $item['key'] ?? ''];
        if (!empty($item['key'])) {
            $shortcuts[str_replace(' ', '', $item['key'])] = $u;
        }
    }
}
?>
<!doctype html>
<html lang="tr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description ?? ($title !== '' ? $title . ' — ' . $appName : $appName . ' — apartman ve site aidat, tahsilat, gelir-gider ve şeffaflık yazılımı')) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:type" content="website">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F3EFE6">
<link rel="icon" href="<?= asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="preload" href="/assets/vendor/fonts/fraunces/fraunces-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/vendor/fonts/ibm-plex-sans/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script>try{var p=localStorage.getItem('aidat.theme')||'auto';var t=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<script>window.AIDAT_PAGES=<?= json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;window.AIDAT_SHORTCUTS=<?= json_encode($shortcuts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?= $this->yield('head') ?>
</head>
<body>
<div class="shell" x-data="shell" :class="{'is-collapsed': collapsed, 'menu-open': menuOpen}" x-cloak>
  <div class="overlay" x-show="menuOpen" x-transition.opacity @click="menuOpen=false" style="z-index:39"></div>
  <?= $this->partial('partials.sidebar', ['area' => $area, 'menu' => $menu, 'building' => $building, 'user' => $user]) ?>
  <div class="main">
    <?= $this->partial('partials.topbar', ['area' => $area, 'building' => $building, 'user' => $user]) ?>
    <main class="content" id="icerik">
      <?= $this->yield('content') ?>
    </main>
  </div>
  <?= $this->partial('partials.mobile-bar', ['area' => $area]) ?>
  <?= $this->partial('partials.cmdk', ['area' => $area]) ?>
</div>
<div class="toasts" id="toasts"></div>
<?= $this->partial('partials.flash') ?>
<script src="<?= asset('assets/vendor/chartjs/chart.umd.js') ?>"></script>
<script src="<?= asset('assets/vendor/qrcode/qrcode.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script defer src="<?= asset('assets/vendor/alpine/alpine.min.js') ?>"></script>
<?= $this->yield('scripts') ?>
</body>
</html>
