<?php
/** @var string $title @var ?string $desc @var array $crumbs [[label, url|null]] @var string $actions html */
?>
<div class="page-head">
  <div class="titles">
    <?php if (!empty($crumbs)): ?>
      <div class="crumbs">
        <?php foreach ($crumbs as $i => $c): ?>
          <?php if ($i > 0): ?><i class="bi bi-chevron-right"></i><?php endif; ?>
          <?php if (!empty($c[1])): ?><a href="<?= e($c[1]) ?>"><?= e($c[0]) ?></a><?php else: ?><span><?= e($c[0]) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <h1><?= e($title) ?><?= !empty($titleSuffix) ? ' ' . $titleSuffix : '' ?></h1>
    <?php if (!empty($desc)): ?><div class="desc"><?= e($desc) ?></div><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="actions"><?= $actions ?></div><?php endif; ?>
</div>
