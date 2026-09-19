<div class="table-wrap ledger"><table class="table">
  <thead><tr><th>Tarih</th><th>Açıklama</th><th>Tür</th><th class="num">Borç</th><th class="num">Ödeme</th><th class="num">Bakiye</th></tr></thead>
  <tbody>
    <tr class="is-muted"><td><?= e(tr_date($from ?? '')) ?></td><td colspan="4">Devreden bakiye</td><td class="num"><?= e(money($st['opening'])) ?></td></tr>
    <?php foreach ($st['rows'] as $r): ?>
      <tr><td><?= e(tr_date($r['d'])) ?></td><td><?= e($r['title']) ?></td><td class="small"><?= $r['kind'] === 'charge' ? e(list_label('charge_types', $r['charge_type'])) : 'Tahsilat · ' . e(list_label('payment_methods', $r['charge_type'])) ?></td><td class="num debit"><?= $r['kind'] === 'charge' ? e(money($r['amount'])) : '' ?></td><td class="num credit"><?= $r['kind'] === 'payment' ? e(money($r['amount'])) : '' ?></td><td class="num <?= $r['running'] > 0 ? 'text-bad' : '' ?>"><?= e(money($r['running'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($st['rows'] === []): ?><tr><td colspan="6" class="text-muted centered" style="padding:20px">Bu aralıkta hareket yok.</td></tr><?php endif; ?>
  </tbody>
  <tfoot><tr><td colspan="5">Dönem sonu bakiye <?= $st['closing'] > 0 ? '(borç)' : ($st['closing'] < 0 ? '(alacak/avans)' : '') ?></td><td class="num <?= $st['closing'] > 0 ? 'text-bad' : 'text-ok' ?>"><?= e(money($st['closing'])) ?></td></tr></tfoot>
</table></div>
