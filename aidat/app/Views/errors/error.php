<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($status) ?> · <?= e($title) ?></title>
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script>try{var p=localStorage.getItem('aidat.theme')||'auto';var t=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body>
<div class="error-page">
  <div>
    <div class="code"><?= e($status) ?></div>
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <div class="flex gap-2" style="justify-content:center">
      <a class="btn btn-primary" href="/"><i class="bi bi-house"></i>Ana sayfa</a>
      <a class="btn" href="javascript:history.back()"><i class="bi bi-arrow-left"></i>Geri dön</a>
    </div>
    <?php if (!empty($trace)): ?><pre><?= e($trace) ?></pre><?php endif; ?>
  </div>
</div>
</body>
</html>
