<!doctype html>
<html lang="tr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Yazdır') ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<style>body{background:var(--bg-deep)}</style>
</head>
<body>
<div class="print-bar no-print">
  <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer"></i>Yazdır / PDF kaydet</button>
  <?php if (!empty($backUrl)): ?><a class="btn" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left"></i>Geri</a><?php endif; ?>
</div>
<?= $this->yield('content') ?>
<script src="<?= asset('assets/vendor/qrcode/qrcode.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if (!empty($autoPrint)): ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print()},300)});</script><?php endif; ?>
</body>
</html>
