<?php /** @var \Aidat\Core\Paginator $p */ if ($p->total === 0) { return; } ?>
<div class="pagination">
  <span class="info"><?= $p->from() ?>–<?= $p->to() ?> / <?= number_tr($p->total) ?> kayıt</span>
  <?php if ($p->hasPages()): ?>
    <?php if ($p->page > 1): ?><a href="<?= e(query_with(['sayfa' => $p->page - 1])) ?>" aria-label="Önceki"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
    <?php foreach ($p->window() as $w): ?>
      <?php if ($w === '…'): ?><span class="pg">…</span>
      <?php elseif ($w === $p->page): ?><span class="pg is-current"><?= $w ?></span>
      <?php else: ?><a href="<?= e(query_with(['sayfa' => $w])) ?>"><?= $w ?></a><?php endif; ?>
    <?php endforeach; ?>
    <?php if ($p->page < $p->lastPage): ?><a href="<?= e(query_with(['sayfa' => $p->page + 1])) ?>" aria-label="Sonraki"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
  <?php endif; ?>
</div>
