<?php $p = $payment; $valid = $p['status'] === 'gecerli';
$unitLabel = $p['door_no'] ? (($p['block_name'] ? $p['block_name'] . ' · ' : '') . 'No ' . $p['door_no']) : null;
$actions = '<a class="btn btn-primary" target="_blank" rel="noopener" href="' . e(route('payments.receipt', ['id' => $p['id']])) . '"><i class="bi bi-printer"></i>Makbuzu yazdır</a>';
if ($valid && can('payments.cancel')) {
    $actions .= '<button type="button" class="btn" @click="ask(\'' . e(route('payments.refund', ['id' => $p['id']])) . '\', \'Tahsilatı iade et\', \'İade gerekçesi\')"><i class="bi bi-arrow-return-left"></i>İade</button>';
    $actions .= '<button type="button" class="btn btn-danger" @click="ask(\'' . e(route('payments.cancel', ['id' => $p['id']])) . '\', \'Tahsilatı iptal et\', \'İptal gerekçesi\')"><i class="bi bi-x-circle"></i>İptal</button>';
}
if (can('payments.create') && $p['unit_id']) {
    $actions .= '<a class="btn btn-ghost" href="' . e(route('payments.create', ['bolum' => $p['unit_id']])) . '"><i class="bi bi-plus-lg"></i>Yeni tahsilat</a>';
}
$allocTotal = array_sum(array_map(static fn ($a) => (int) $a['amount'], $allocations));
?>
<div x-data="reasonModal">
<?= $this->partial('partials.page-head', ['title' => 'Makbuz ' . $p['receipt_no'], 'crumbs' => [['Tahsilatlar', route('payments.index')], [$p['receipt_no']]], 'desc' => tr_date($p['payment_date']) . ($p['payment_time'] ? ' ' . $p['payment_time'] : '') . ' · ' . list_label('payment_methods', $p['method']) . ' · ' . $p['account_name'], 'actions' => $actions]) ?>

<div class="stat-row mb-4">
  <div class="balance-box <?= $valid ? 'ok' : 'bad' ?>"><div><div class="l">Tahsilat tutarı</div><div class="v"><?= e(money($p['amount'])) ?></div></div><span class="seal sm <?= $valid ? 'ok' : '' ?>" style="margin-left:auto"><?= e(list_label('payment_statuses', $p['status'])) ?></span></div>
  <div class="stat tone-ok"><div class="k"><i class="bi bi-diagram-3"></i>Borca düşen</div><div class="v"><?= e(money($allocTotal, false)) ?> <small>₺</small></div><div class="d"><?= count($allocations) ?> borç kalemi · <?= e(list_label('allocation_modes', $p['allocation_mode'])) ?></div></div>
  <div class="stat"><div class="k"><i class="bi bi-piggy-bank"></i>Avansa kalan</div><div class="v"><?= e(money($p['unallocated_amount'], false)) ?> <small>₺</small></div><div class="d"><?= $valid ? 'Yeni borçta otomatik mahsup edilir' : 'İptal ile serbest bırakıldı' ?></div></div>
  <?php if ($balance): ?><div class="stat <?= $balance['debt'] > 0 ? 'tone-bad' : 'tone-ok' ?>"><div class="k"><i class="bi bi-door-open"></i>Bölümün güncel borcu</div><div class="v"><?= e(money($balance['debt'], false)) ?> <small>₺</small></div><div class="d">Vadesi geçmiş <?= e(money($balance['overdue'])) ?> · avans <?= e(money($balance['advance'])) ?></div></div><?php endif; ?>
</div>

<?php if (!$valid): ?>
<div class="alert bad mb-4"><i class="bi bi-x-octagon"></i><div><span class="t"><?= $p['status'] === 'iade' ? 'Bu tahsilat iade edildi.' : 'Bu tahsilat iptal edildi.' ?></span> <?= e(tr_datetime($p['cancelled_at'])) ?> · <?= e($p['canceller_name'] ?: 'sistem') ?><br>Gerekçe: <?= e($p['cancel_reason']) ?>. Makbuz numarası (<?= e($p['receipt_no']) ?>) makbuz defterinde korunur; borçlar yeniden açıldı ve defterde ters kayıt oluştu.</div></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-receipt"></i>Borç eşleştirmeleri</h3><div class="sub">Bu tahsilatın kapattığı borç kalemleri</div></div></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Borç</th><th>Dönem</th><th class="hide-sm">Vade</th><th class="num">Borç tutarı</th><th class="num">Düşülen</th></tr></thead>
        <tbody>
        <?php foreach ($allocations as $a): ?>
          <tr data-href="<?= e(route('charges.show', ['id' => $a['charge_id']])) ?>"><td><div class="primary-cell"><a href="<?= e(route('charges.show', ['id' => $a['charge_id']])) ?>"><?= e($a['title']) ?></a></div><div class="sub-cell"><?= e(list_label('charge_types', $a['charge_type'])) ?></div></td><td class="small"><?= e(tr_period($a['period'])) ?></td><td class="small hide-sm"><?= e(tr_date($a['due_date'])) ?></td><td class="num text-muted"><?= e(money($a['charge_amount'])) ?></td><td class="num" style="font-weight:600"><?= e(money($a['amount'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($allocations === []): ?><tr><td colspan="5" class="centered text-muted" style="padding:20px"><?= $valid ? ($p['allocation_mode'] === 'avans' ? 'Avans olarak kaydedildi; borca dağıtılmadı.' : 'Açık borç bulunmadığından tutar avansa alındı.') : 'İptal/iade ile eşleştirmeler kaldırıldı.' ?></td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="4">Borca düşen toplam</td><td class="num"><?= e(money($allocTotal)) ?></td></tr><tr><td colspan="4">Avansa kalan</td><td class="num"><?= e(money($p['unallocated_amount'])) ?></td></tr></tfoot>
      </table></div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-journal-text"></i>Defter hareketleri</h3></div>
      <div class="table-wrap"><table class="table compact">
        <thead><tr><th>Tarih</th><th>Hesap</th><th>Açıklama</th><th class="num">Giriş</th><th class="num">Çıkış</th></tr></thead>
        <tbody>
        <?php foreach ($ledger as $l): ?><tr><td class="small"><?= e(tr_date($l['entry_date'])) ?></td><td><a href="<?= e(route('accounts.show', ['id' => $l['account_id']])) ?>"><?= e($l['account_name']) ?></a></td><td class="small"><?= e($l['description']) ?></td><td class="num text-ok"><?= $l['direction'] === 'in' ? e(money($l['amount'])) : '' ?></td><td class="num text-bad"><?= $l['direction'] === 'out' ? e(money($l['amount'])) : '' ?></td></tr><?php endforeach; ?>
        <?php if ($ledger === []): ?><tr><td colspan="5" class="centered text-muted" style="padding:16px">Defter hareketi yok.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>İşlem izi</h3></div>
      <div class="card-body">
        <ul class="timeline">
          <?php foreach ($timeline as $t): ?><li><span class="pt <?= str_contains($t['action'], 'cancel') || str_contains($t['action'], 'refund') ? 'bad' : 'ok' ?>"></span><div class="tt"><?= e($t['summary'] ?: $t['action']) ?></div><div class="tm"><?= e(tr_datetime($t['created_at'])) ?> · <?= e($t['user_name'] ?: 'sistem') ?><?= $t['ip'] ? ' · ' . e($t['ip']) : '' ?></div></li><?php endforeach; ?>
          <?php if ($timeline === []): ?><li><span class="pt"></span><div class="tb text-muted">İz kaydı yok.</div></li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-info-circle"></i>Makbuz bilgileri</h3></div>
      <div class="card-body"><dl class="dl">
        <dt>Makbuz no</dt><dd class="mono" style="font-weight:600"><?= e($p['receipt_no']) ?></dd>
        <dt>Durum</dt><dd><span class="pill <?= status_tone($p['status']) ?>"><?= e(list_label('payment_statuses', $p['status'])) ?></span></dd>
        <dt>Tarih</dt><dd><?= e(tr_date($p['payment_date'])) ?><?= $p['payment_time'] ? ' <span class="mono">' . e($p['payment_time']) . '</span>' : '' ?></dd>
        <dt>Tutar</dt><dd class="num" style="font-weight:600"><?= e(money($p['amount'])) ?></dd>
        <dt>Yöntem</dt><dd><?= e(list_label('payment_methods', $p['method'])) ?></dd>
        <dt>Hesap</dt><dd><a href="<?= e(route('accounts.show', ['id' => $p['account_id']])) ?>"><?= e($p['account_name']) ?></a> <span class="small text-muted"><?= e(list_label('account_types', $p['account_type'])) ?></span></dd>
        <dt>Referans</dt><dd class="mono"><?= e($p['reference_no'] ?: '—') ?></dd>
        <dt>Bölüm</dt><dd><?= $unitLabel ? '<a href="' . e(route('units.show', ['id' => $p['unit_id']])) . '">' . e($unitLabel) . '</a>' : '<span class="text-muted">Bölümsüz</span>' ?></dd>
        <dt>Ödeyen</dt><dd><?= $p['person_id'] ? '<a href="' . e(route('people.show', ['id' => $p['person_id']])) . '">' . e($p['payer_name'] ?: '—') . '</a>' : '<span class="text-muted">—</span>' ?></dd>
        <dt>Tahsil eden</dt><dd><?= e($p['collector_name'] ?: 'sistem') ?></dd>
        <dt>Kayıt</dt><dd class="small"><?= e(tr_datetime($p['created_at'])) ?></dd>
        <?php if ($p['import_row_id']): ?><dt>Kaynak</dt><dd><a href="<?= e(route('imports.show', ['id' => $p['import_id']])) ?>"><i class="bi bi-bank"></i> Banka ekstresi #<?= (int) $p['import_id'] ?></a></dd><?php endif; ?>
        <?php if ($p['description']): ?><dt>Açıklama</dt><dd><?= nl2br(e($p['description'])) ?></dd><?php endif; ?>
      </dl></div>
      <div class="card-foot"><a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(route('payments.receipt', ['id' => $p['id']])) ?>"><i class="bi bi-printer"></i>Makbuz</a><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="<?= e(route('receipt.verify', ['code' => $p['verify_code']])) ?>"><i class="bi bi-qr-code"></i>Doğrulama sayfası</a></div>
    </div>
    <?php if ($valid && can('payments.cancel')): ?>
    <div class="alert info"><i class="bi bi-shield-check"></i><div><span class="t">İptal ve iade farkı</span><br><strong>İptal:</strong> hatalı giriş; borçlar geri açılır, defterde ters kayıt oluşur.<br><strong>İade:</strong> para sakine geri verildi; aynı ters kayıt "iade" olarak işaretlenir. Her iki durumda makbuz numarası korunur.</div></div>
    <?php endif; ?>
  </div>
</div>
<?= $this->partial('partials.reason-modal') ?>
</div>
