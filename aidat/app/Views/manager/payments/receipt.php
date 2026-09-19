<?php
$valid = $r['status'] === 'gecerli';
$unitLabel = $r['door_no'] ? (($r['block_name'] ? $r['block_name'] . ' · ' : '') . 'No ' . $r['door_no'] . ' (' . list_label('unit_types', $r['unit_type']) . ')') : 'Bölüm belirtilmemiş';
$orgLine = array_filter([
    $r['building_address'] ? $r['building_address'] : null,
    $r['building_tax_no'] ? 'VKN ' . $r['building_tax_no'] : null,
    $r['building_iban'] ? 'IBAN ' . \Aidat\Core\Str::formatIban($r['building_iban']) : null,
]);
$allocTotal = array_sum(array_map(static fn ($a) => (int) $a['amount'], $r['allocations']));
$printedAt = tr_datetime(date('Y-m-d H:i:s'));
?>
<div class="print-sheet receipt">
<?php for ($i = 0; $i < $copies; $i++): ?>
  <?php if ($i > 0): ?><div class="cut-line"></div><?php endif; ?>
  <div class="receipt-copy" style="break-inside:avoid">
    <div class="receipt-copy-label"><?= e($copyLabels[$i] ?? 'Ek nüsha') ?> · <?= $i + 1 ?>/<?= $copies ?></div>
    <div class="receipt-head">
      <div>
        <div class="org"><?= e($r['building_name']) ?></div>
        <?php foreach ($orgLine as $line): ?><div class="small" style="color:#444"><?= e($line) ?></div><?php endforeach; ?>
      </div>
      <div class="meta">
        <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#666">Tahsilat makbuzu</div>
        <div class="no"><?= e($r['receipt_no']) ?></div>
        <div><?= e(tr_date($r['payment_date'])) ?><?= $r['payment_time'] ? ' · ' . e($r['payment_time']) : '' ?></div>
      </div>
    </div>

    <div style="display:flex;gap:16px;align-items:flex-start">
      <div style="flex:1;min-width:0">
        <div class="receipt-grid">
          <div><span>Ödeyen</span><?= e($r['payer_name'] !== '' ? $r['payer_name'] : '—') ?></div>
          <div><span>Bağımsız bölüm</span><?= e($unitLabel) ?></div>
          <div><span>Ödeme yöntemi</span><?= e(list_label('payment_methods', $r['method'])) ?><?= $r['reference_no'] ? ' · <span class="mono" style="display:inline;font-size:12px;text-transform:none;letter-spacing:0;color:#111">' . e($r['reference_no']) . '</span>' : '' ?></div>
          <div><span>Tahsil edilen hesap</span><?= e($r['account_name'] ?: '—') ?><?= $r['account_type'] ? ' (' . e(list_label('account_types', $r['account_type'])) . ')' : '' ?></div>
        </div>
      </div>
      <?php if (!$valid): ?><span class="seal"><?= $r['status'] === 'iade' ? 'İade edildi' : 'İptal edildi' ?></span><?php endif; ?>
    </div>

    <div class="receipt-total"><span>Tahsil edilen tutar</span><span class="amt"><?= e(money($r['amount'])) ?></span></div>
    <div class="receipt-words">Yalnız <?= e($r['amount_words']) ?>.</div>

    <?php if ($r['allocations'] !== [] || (int) $r['unallocated_amount'] > 0): ?>
    <table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-top:12px">
      <thead><tr style="border-bottom:1px solid #111"><th style="text-align:left;padding:4px 0;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#666">Dönem</th><th style="text-align:left;padding:4px 0;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#666">Borç kalemi</th><th style="text-align:right;padding:4px 0;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#666">Düşülen</th></tr></thead>
      <tbody>
        <?php foreach ($r['allocations'] as $a): ?><tr style="border-bottom:1px solid #e5e5e5"><td style="padding:4px 0"><?= e(tr_period($a['period'])) ?></td><td style="padding:4px 0"><?= e($a['title']) ?></td><td class="num" style="text-align:right;padding:4px 0"><?= e(money($a['amount'])) ?></td></tr><?php endforeach; ?>
        <?php if ((int) $r['unallocated_amount'] > 0): ?><tr style="border-bottom:1px solid #e5e5e5"><td style="padding:4px 0">—</td><td style="padding:4px 0;font-style:italic">Avansa alınan (sonraki borçta mahsup edilir)</td><td class="num" style="text-align:right;padding:4px 0"><?= e(money($r['unallocated_amount'])) ?></td></tr><?php endif; ?>
      </tbody>
      <?php if (count($r['allocations']) > 1): ?><tfoot><tr><td colspan="2" style="padding:4px 0;font-weight:600">Borca düşen toplam</td><td class="num" style="text-align:right;padding:4px 0;font-weight:600"><?= e(money($allocTotal)) ?></td></tr></tfoot><?php endif; ?>
    </table>
    <?php endif; ?>

    <?php if ($r['unit_id']): ?>
    <div class="receipt-grid" style="margin-top:12px">
      <div><span>Bu makbuz sonrası kalan borç</span><strong class="num"><?= e(money($r['remaining_debt'])) ?></strong></div>
      <div><span>Bekleyen avans</span><strong class="num"><?= e(money($r['advance'])) ?></strong></div>
    </div>
    <?php endif; ?>

    <div class="receipt-sign">
      <div>Ödeyen<br><span style="font-size:11px;color:#888"><?= e($r['payer_name'] !== '' ? $r['payer_name'] : ' ') ?></span></div>
      <div><?= e($signerTitle) ?><br><span style="font-size:11px;color:#888"><?= e($r['collector_name'] ?: ' ') ?></span></div>
    </div>

    <div class="receipt-foot">
      <div style="display:flex;gap:10px;align-items:flex-end">
        <div data-qr="<?= e($verifyUrl) ?>" style="width:64px;height:64px;flex:0 0 64px" aria-label="Makbuz doğrulama karekodu"></div>
        <div><div>Doğrulama: karekodu okutun veya</div><div class="mono" style="font-size:10px;word-break:break-all;max-width:260px"><?= e($verifyUrl) ?></div></div>
      </div>
      <div style="text-align:right"><?php if ($footer !== ''): ?><div><?= e($footer) ?></div><?php endif; ?><div>Düzenleme: <?= e($printedAt) ?><?= $r['collector_name'] ? ' · ' . e($r['collector_name']) : '' ?></div></div>
    </div>
  </div>
<?php endfor; ?>
</div>
