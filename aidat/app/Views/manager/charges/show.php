<?php $open = (int) $c['amount'] - (int) $c['paid_amount']; $overdue = in_array($c['status'], ['odenmedi', 'kismi'], true) && $c['due_date'] < $today;
$actions = '';
if (can('payments.create') && $open > 0 && $c['status'] !== 'iptal') { $actions .= '<a class="btn btn-primary" href="' . e(route('payments.create', ['bolum' => $c['unit_id']])) . '"><i class="bi bi-cash-coin"></i>Tahsilat gir</a>'; }
$actions .= '<a class="btn" href="' . e(route('units.show', ['id' => $c['unit_id']])) . '"><i class="bi bi-door-open"></i>Bölüm kartı</a>';
if (can('charges.cancel') && (int) $c['paid_amount'] === 0 && $c['status'] !== 'iptal') { $actions .= '<button type="button" class="btn btn-ghost text-bad" @click="ask(\'' . e(route('charges.cancel', ['id' => $c['id']])) . '\', \'Borcu iptal et\', \'İptal gerekçesi\')"><i class="bi bi-x-circle"></i>İptal (ters kayıt)</button>'; }
?>
<div x-data="reasonModal">
<?= $this->partial('partials.page-head', ['title' => $c['title'], 'titleSuffix' => '<span class="pill ' . status_tone($c['status']) . '">' . e(list_label('charge_statuses', $c['status'])) . '</span>', 'crumbs' => [['Borç kayıtları', route('charges.index')], ['#' . $c['id']]], 'desc' => list_label('charge_types', $c['charge_type']) . ' · ' . tr_period($c['period']) . ' · vade ' . tr_date($c['due_date']) . ' · ' . ($c['block_name'] ? $c['block_name'] . ' · ' : '') . 'No ' . $c['door_no'], 'actions' => $actions]) ?>

<?php if ($c['status'] === 'iptal'): ?><div class="alert bad mb-4"><i class="bi bi-x-octagon"></i><div><strong>İptal edildi</strong> · <?= e(tr_datetime($c['cancelled_at'])) ?><?= $c['cancelled_by_name'] ? ' · ' . e($c['cancelled_by_name']) : '' ?><?= $c['cancel_reason'] ? ' · ' . e($c['cancel_reason']) : '' ?></div></div>
<?php elseif ($overdue): ?><div class="alert warn mb-4"><i class="bi bi-hourglass-split"></i><div>Vadesi <strong><?= \Aidat\Core\Dates::daysBetween($c['due_date'], $today) ?> gün</strong> geçti.<?= (int) $c['late_fee_exempt'] === 1 ? ' Bu borç gecikme tazminatından muaf.' : (can('latefee.manage') ? ' <a href="' . e(route('latefee.index', ['bolum' => $c['unit_id']])) . '">Gecikme tazminatını hesapla</a>.' : '') ?></div></div><?php endif; ?>
<?php if ((int) $c['paid_amount'] > 0 && $c['status'] !== 'iptal' && can('charges.cancel')): ?><div class="alert info mb-4"><i class="bi bi-info-circle"></i><div>Bu borca tahsilat eşleştiği için iptal edilemez. Önce ilgili tahsilatı iptal edin.</div></div><?php endif; ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k">Tutar</div><div class="v"><?= e(money($c['amount'], false)) ?> <small>₺</small></div><div class="d"><?= e(list_label('liability_modes', $c['liability'])) ?></div></div>
  <div class="stat tone-ok"><div class="k">Ödenen</div><div class="v"><?= e(money($c['paid_amount'], false)) ?> <small>₺</small></div><div class="d"><?= count($allocations) ?> eşleştirme</div></div>
  <div class="stat <?= $open > 0 && $c['status'] !== 'iptal' ? ($overdue ? 'tone-bad' : 'tone-warn') : '' ?>"><div class="k">Kalan</div><div class="v"><?= $c['status'] === 'iptal' ? '—' : e(money($open, false)) . ' <small>₺</small>' ?></div><div class="d"><?= $overdue ? 'vadesi geçti' : ($open > 0 ? 'vade ' . e(tr_date($c['due_date'])) : 'kapandı') ?></div></div>
  <div class="balance-box <?= $balance['debt'] > 0 ? 'bad' : 'ok' ?>"><div><div class="l">Bölümün toplam açık borcu</div><div class="v"><?= e(money($balance['debt'])) ?></div></div></div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-cash-coin"></i>Tahsilat eşleştirmeleri</h3><div class="sub">Bu borca düşen tahsilat payları</div></div></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Makbuz</th><th>Tarih</th><th class="hide-sm">Yöntem</th><th>Durum</th><th class="num hide-sm">Tahsilat</th><th class="num">Bu borca</th></tr></thead>
        <tbody>
        <?php foreach ($allocations as $a): ?>
          <tr data-href="<?= e(route('payments.show', ['id' => $a['payment_id']])) ?>" class="<?= $a['payment_status'] !== 'gecerli' ? 'is-cancelled' : '' ?>"><td class="mono primary-cell"><?= e($a['receipt_no'] ?: '#' . $a['payment_id']) ?></td><td class="small"><?= e(tr_date($a['payment_date'])) ?></td><td class="hide-sm small"><?= e(list_label('payment_methods', $a['method'])) ?></td><td><span class="pill <?= status_tone($a['payment_status']) ?>"><?= e(list_label('payment_statuses', $a['payment_status'])) ?></span></td><td class="num hide-sm text-muted"><?= e(money($a['payment_amount'])) ?></td><td class="num" style="font-weight:600"><?= e(money($a['amount'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($allocations === []): ?><tr><td colspan="6" class="centered text-muted" style="padding:20px">Henüz tahsilat eşleşmedi.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($allocations !== []): ?><tfoot><tr><td colspan="5">Toplam ödenen</td><td class="num"><?= e(money($c['paid_amount'])) ?></td></tr></tfoot><?php endif; ?>
      </table></div>
    </div>

    <?php if ($children !== [] || $c['charge_type'] !== 'gecikme'): ?>
    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-hourglass-split"></i>Bağlı gecikme tazminatları</h3><div class="sub">Bu borca bağlı alt kayıtlar</div></div></div>
      <div class="table-wrap"><table class="table compact">
        <tbody>
        <?php foreach ($children as $ch): ?>
          <tr data-href="<?= e(route('charges.show', ['id' => $ch['id']])) ?>" class="<?= $ch['status'] === 'iptal' ? 'is-cancelled' : '' ?>"><td><div class="primary-cell"><?= e($ch['title']) ?></div><div class="sub-cell"><?= e(tr_date($ch['due_date'])) ?><?= $ch['late_fee_accrued_to'] ? ' · ' . e(tr_date($ch['late_fee_accrued_to'])) . ' tarihine kadar' : '' ?></div></td><td><span class="pill <?= status_tone($ch['status']) ?>"><?= e(list_label('charge_statuses', $ch['status'])) ?></span></td><td class="num"><?= e(money($ch['amount'])) ?></td><td class="num text-muted"><?= e(money((int) $ch['amount'] - (int) $ch['paid_amount'])) ?> kalan</td></tr>
        <?php endforeach; ?>
        <?php if ($children === []): ?><tr><td class="centered text-muted" style="padding:20px"><?= (int) $c['late_fee_exempt'] === 1 ? 'Bu borç gecikme tazminatından muaf.' : 'Gecikme tazminatı tahakkuk etmedi.' ?></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>İşlem izi</h3></div>
      <div class="card-body">
        <ul class="timeline">
          <?php foreach ($audit as $t): ?><li><span class="pt <?= str_contains($t['action'], 'cancel') ? 'bad' : (str_contains($t['action'], 'create') ? 'ok' : '') ?>"></span><div class="tt"><?= e($t['summary'] ?: $t['action']) ?></div><div class="tm"><?= e(tr_datetime($t['created_at'])) ?> · <?= e($t['user_name'] ?: 'sistem') ?></div></li><?php endforeach; ?>
          <?php if ($audit === []): ?><li><span class="pt"></span><div class="tt text-muted">Kayıtlı işlem yok.</div></li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-door-open"></i>Bölüm ve sorumlu</h3></div>
      <div class="card-body"><dl class="dl">
        <dt>Bölüm</dt><dd><a href="<?= e(route('units.show', ['id' => $c['unit_id']])) ?>"><?= e(($c['block_name'] ? $c['block_name'] . ' · ' : '') . 'No ' . $c['door_no']) ?></a> <span class="small text-muted"><?= e(list_label('unit_types', $c['unit_type'])) ?></span></dd>
        <dt>Sorumlu</dt><dd><?php if ($personName): ?><a href="<?= e(route('people.show', ['id' => $c['pid']])) ?>"><?= e($personName) ?></a><?= $c['phone'] ? '<div class="small text-muted mono">' . e(\Aidat\Core\Str::formatPhone($c['phone'])) . '</div>' : '' ?><?php else: ?><span class="text-muted">Kayıt anında sorumlu kişi bulunamadı</span><?php endif; ?></dd>
        <dt>Sorumluluk</dt><dd><?= e(list_label('liability_modes', $c['liability'])) ?> <span class="small text-muted">(bölüm ayarı: <?= e(list_label('liability_modes', $c['liability_mode'])) ?>)</span></dd>
        <dt>Bakiye</dt><dd>açık <?= e(money($balance['debt'])) ?><?= $balance['advance'] > 0 ? ' · avans ' . e(money($balance['advance'])) : '' ?></dd>
      </dl></div>
    </div>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-info-circle"></i>Kayıt bilgileri</h3></div>
      <div class="card-body"><dl class="dl">
        <dt>Tür</dt><dd><?= e(list_label('charge_types', $c['charge_type'])) ?></dd>
        <dt>Dönem</dt><dd><?= e(tr_period($c['period'])) ?></dd>
        <dt>Vade</dt><dd class="<?= $overdue ? 'text-bad' : '' ?>"><?= e(tr_date($c['due_date'])) ?></dd>
        <dt>Kaynak</dt><dd><?php if ($c['plan_id']): ?><a href="<?= e(route('plans.show', ['id' => $c['plan_id']])) ?>"><?= e($c['plan_name'] ?: 'Plan #' . $c['plan_id']) ?></a><?php elseif ($c['parent_charge_id']): ?><a href="<?= e(route('charges.show', ['id' => $c['parent_charge_id']])) ?>">Ana borç: <?= e($c['parent_title'] ?: '#' . $c['parent_charge_id']) ?></a><?php else: ?>Tekil borçlandırma<?php endif; ?></dd>
        <dt>Gecikme</dt><dd><?= (int) $c['late_fee_exempt'] === 1 ? 'Muaf' : 'Uygulanır' ?><?= $c['late_fee_accrued_to'] ? ' · ' . e(tr_date($c['late_fee_accrued_to'])) . ' tarihine kadar hesaplandı' : '' ?></dd>
        <dt>Oluşturan</dt><dd><?= e($c['created_by_name'] ?: 'sistem') ?> · <?= e(tr_datetime($c['created_at'])) ?></dd>
        <?php if ($c['description']): ?><dt>Açıklama</dt><dd><?= nl2br(e($c['description'])) ?></dd><?php endif; ?>
      </dl></div>
    </div>
  </div>
</div>
<?= $this->partial('partials.reason-modal') ?>
</div>
