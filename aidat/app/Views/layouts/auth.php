<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title !== '' ? $title . ' · ' : '') . config('app.name')) ?></title>
<meta name="description" content="<?= e(config('app.name')) ?> — apartman ve site aidat, tahsilat, gelir-gider ve şeffaflık yazılımı">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script>try{var p=localStorage.getItem('aidat.theme')||'auto';var t=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body>
<div class="auth">
  <aside class="auth-side">
    <div class="brand"><div class="brand-mark">A</div><div class="brand-text"><div class="name" style="color:inherit"><?= e(config('app.name')) ?></div><div class="sub" style="color:rgba(247,243,234,.6)">Mühürlü hesap defteri</div></div></div>
    <div>
      <h2>Her kuruş <em>kayıtlı</em>, her kayıt <em>görünür.</em></h2>
      <div class="pts">
        <div><i class="bi bi-check2-circle"></i><span>Aidat, borç, tahsilat ve makbuz tek defterde; finansal kayıt silinmez, ters kayıtla düzeltilir.</span></div>
        <div><i class="bi bi-check2-circle"></i><span>Malik ve kiracı kendi borcunu, yapının gelir-giderini ve belgelerini kendi ekranından görür.</span></div>
        <div><i class="bi bi-check2-circle"></i><span>Denetçi salt okunur izler: kim, neyi, ne zaman değiştirdi.</span></div>
        <div><i class="bi bi-check2-circle"></i><span>Kredi kartı veya sanal POS yok. Nakit, havale/EFT ve banka ekstresi eşleştirme.</span></div>
      </div>
    </div>
    <div class="foot">© <?= date('Y') ?> · Pure PHP 8.3 MVC · KMK 634 md. 20, 35, 37 uyumlu kayıt düzeni</div>
  </aside>
  <main class="auth-form">
    <div class="auth-card fade-up">
      <?= $this->yield('content') ?>
    </div>
  </main>
</div>
<div class="toasts" id="toasts"></div>
<?= $this->partial('partials.flash') ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script defer src="<?= asset('assets/vendor/alpine/alpine.min.js') ?>"></script>
</body>
</html>
