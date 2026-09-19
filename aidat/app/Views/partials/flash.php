<?php foreach (['success' => 'ok', 'error' => 'bad', 'warning' => 'warn', 'info' => 'info'] as $key => $tone): ?>
  <?php $msg = flash($key); if (is_string($msg) && $msg !== ''): ?>
    <span data-flash="<?= e($msg) ?>" data-tone="<?= $tone ?>" hidden></span>
  <?php endif; ?>
<?php endforeach; ?>
