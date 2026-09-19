<?php /** @var string $icon @var string $title @var string $text @var string $action html */ ?>
<div class="empty">
  <div class="ic"><i class="bi bi-<?= e($icon ?? 'inbox') ?>"></i></div>
  <h3><?= e($title ?? 'Kayıt yok') ?></h3>
  <?php if (!empty($text)): ?><p><?= e($text) ?></p><?php endif; ?>
  <?= $action ?? '' ?>
</div>
