<div class="print-sheet">
  <div class="receipt-head"><div><div class="org"><?= e($building['name']) ?></div><div class="small"><?= e($building['address']) ?></div></div><div class="meta"><div class="no">HESAP EKSTRESİ</div><div>No <?= e($unit['door_no']) ?> · <?= e($owner) ?></div><div><?= e(tr_date($from)) ?> – <?= e(tr_date($to)) ?></div></div></div>
  <?= $this->partial('manager.units.statement-table', ['st' => $st, 'from' => $from]) ?>
  <div class="receipt-foot"><span>Düzenleme: <?= e(tr_datetime(date('Y-m-d H:i:s'))) ?></span><span>Bu ekstre yönetim kayıtlarından üretilmiştir.</span></div>
</div>
