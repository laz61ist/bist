<div class="print-sheet">
  <div class="receipt-head"><div><div class="org"><?= e($building['name']) ?></div><div class="small"><?= e($building['address']) ?></div></div><div class="meta"><div class="no">DEVİR TUTANAĞI</div><div>#<?= $h['id'] ?> · <?= e(tr_date($h['handover_date'])) ?></div></div></div>
  <?= $this->partial('manager.occupancies.handover-body', ['h' => $h]) ?>
  <div class="receipt-sign"><div>Devreden</div><div>Devralan</div></div>
  <div class="receipt-sign" style="grid-template-columns:1fr"><div>Yönetici</div></div>
</div>
