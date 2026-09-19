<dl class="dl">
  <dt>Bölüm</dt><dd><?= e(($h['block_name'] ? $h['block_name'] . ' · ' : '') . 'No ' . $h['door_no']) ?></dd>
  <dt>Devir tarihi</dt><dd><?= e(tr_date($h['handover_date'])) ?></dd>
  <dt>Eski taraf</dt><dd><?= e($h['from_company'] ?: trim(($h['from_first'] ?? '') . ' ' . ($h['from_last'] ?? '')) ?: '—') ?></dd>
  <dt>Yeni taraf</dt><dd><?= e($h['to_company'] ?: trim(($h['to_first'] ?? '') . ' ' . ($h['to_last'] ?? ''))) ?></dd>
  <dt>Açık bakiye</dt><dd><?= $h['balance_mode'] === 'devreder' ? 'Yeni tarafa devretti: <strong class="num">' . e(money($h['transferred_balance'])) . '</strong>' : 'Eski tarafta kaldı' ?></dd>
  <dt>Depozito / avans</dt><dd class="num" style="text-align:left"><?= e(money($h['deposit_transferred'])) ?></dd>
  <?php if ($h['notes']): ?><dt>Tutanak notu</dt><dd><?= nl2br(e($h['notes'])) ?></dd><?php endif; ?>
  <dt>Kaydeden</dt><dd><?= e($h['created_by_name'] ?? '—') ?> · <?= e(tr_datetime($h['created_at'])) ?></dd>
</dl>
