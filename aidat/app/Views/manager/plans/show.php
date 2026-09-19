<?php use Aidat\Core\Form; $p = $plan; $st = (string) $p['status'];
$actions = '';
if ($st === 'taslak' && can('charges.manage')) {
    $actions .= '<a class="btn" href="' . e(route('plans.create', ['duzenle' => $p['id']])) . '"><i class="bi bi-pencil"></i>Düzenle</a>';
    $actions .= '<form method="post" action="' . e(route('plans.approve', ['id' => $p['id']])) . '" x-data="confirmForm(\'Plan onaylansın mı? Onaylı plan düzenlenemez.\')" @submit="submit($event)" style="display:inline">' . csrf_field() . '<button class="btn btn-primary"><i class="bi bi-check2-circle"></i>Onayla</button></form>';
}
if (can('charges.manage')) {
    $actions .= '<a class="btn btn-ghost" href="' . e(route('plans.create', ['kopya' => $p['id']])) . '" title="Bu planı kopyalayarak yeni plan oluştur"><i class="bi bi-files"></i>Kopyala</a>';
}
if (in_array($st, ['taslak', 'onaylandi'], true) && can('charges.cancel')) {
    $actions .= '<button type="button" class="btn btn-ghost text-bad" @click="ask(\'' . e(route('plans.cancel', ['id' => $p['id']])) . '\', \'Planı iptal et\', \'İptal gerekçesi\')"><i class="bi bi-x-circle"></i>İptal</button>';
}
$paidTotal = array_sum(array_map(static fn ($x) => (int) $x['paid'], $periods));
$chargeTotal = array_sum(array_map(static fn ($x) => (int) $x['total'], $periods));
?>
<div x-data="reasonModal">
<?= $this->partial('partials.page-head', ['title' => $p['name'], 'titleSuffix' => '<span class="pill ' . status_tone($st) . '">' . e(list_label('plan_statuses', $st)) . '</span>', 'crumbs' => [['Tahakkuk planları', route('plans.index')], [$p['name']]], 'desc' => list_label('charge_types', $p['charge_type']) . ' · ' . tr_period($p['period']) . ' · vade ' . tr_date($p['due_date']) . ' · ' . list_label('recurrences', $p['recurrence']) . ' · ' . list_label('distributions', $p['distribution']), 'actions' => $actions]) ?>

<?php if ($st === 'iptal'): ?><div class="alert bad mb-4"><i class="bi bi-x-octagon"></i><div><strong>İptal edildi</strong> · <?= e(tr_datetime($p['cancelled_at'])) ?><?= $p['cancel_reason'] ? ' · ' . e($p['cancel_reason']) : '' ?></div></div><?php endif; ?>
<?php if ($st === 'taslak'): ?><div class="alert info mb-4"><i class="bi bi-info-circle"></i><div>Taslak plan borç oluşturmaz. Satırları kontrol edip <strong>Onayla</strong>, ardından <strong>İşle</strong> ile borç kayıtlarını üretin.</div></div><?php endif; ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k">Plan toplamı</div><div class="v"><?= e(money($sum, false)) ?> <small>₺</small></div><div class="d"><?= count($included) ?> bölüm · <?= e(list_label('vat_modes', $p['vat_mode'])) ?><?= (float) $p['vat_rate'] > 0 ? ' %' . number_tr((float) $p['vat_rate'], 1) : '' ?></div></div>
  <div class="stat"><div class="k">Üretilen borç</div><div class="v"><?= e(money($chargeTotal, false)) ?> <small>₺</small></div><div class="d"><?= count($periods) ?> dönem · <?= array_sum(array_map(static fn ($x) => (int) $x['cnt'], $periods)) ?> kayıt</div></div>
  <div class="stat tone-ok"><div class="k">Tahsil edilen</div><div class="v"><?= e(money($paidTotal, false)) ?> <small>₺</small></div><div class="d"><?= $chargeTotal > 0 ? e(percent_tr($paidTotal / $chargeTotal * 100)) . ' tahsilat oranı' : 'Henüz borç üretilmedi' ?></div></div>
  <div class="stat"><div class="k">Sorumluluk</div><div class="v" style="font-family:var(--font-sans);font-size:18px"><?= $p['liability'] === 'bolum' ? 'Bölüm ayarına göre' : e(list_label('liability_modes', $p['liability'])) ?></div><div class="d"><?= e($p['block_name'] ?: 'Tüm bloklar') ?><?= $p['fee_group_name'] ? ' · ' . e($p['fee_group_name']) : '' ?></div></div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <?php if ($st === 'onaylandi' && can('charges.manage')): ?>
    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-play-circle"></i>İşle</h3><div class="sub"><?= $p['recurrence'] === 'tek' ? 'Plan satırlarından borç kayıtları oluşturulur' : 'Tekrarlı plan: her dönem için bir kez işlenir, mükerrer dönem engellenir' ?></div></div></div>
      <div class="card-body">
        <?php if ($p['recurrence'] === 'tek'): ?>
          <form method="post" action="<?= e(route('plans.process', ['id' => $p['id']])) ?>" x-data="confirmForm('<?= count($included) ?> bölüme borç kaydı oluşturulacak. Devam edilsin mi?')" @submit="submit($event)" class="flex center gap-3 wrap">
            <?= csrf_field() ?>
            <div class="grow small text-2"><?= e(tr_period($p['period'])) ?> dönemi · vade <?= e(tr_date($p['due_date'])) ?> · <?= count($included) ?> bölüm · <?= e(money($sum)) ?></div>
            <button class="btn btn-primary"><i class="bi bi-play-fill"></i>Planı işle</button>
          </form>
        <?php else: ?>
          <form method="post" action="<?= e(route('plans.process', ['id' => $p['id']])) ?>" x-data="confirmForm('Seçilen dönem için borç kayıtları oluşturulacak. Devam edilsin mi?')" @submit="submit($event)" class="form-grid">
            <?= csrf_field() ?>
            <div class="field c4"><label for="f_period">İşlenecek dönem</label><input type="month" class="input" id="f_period" name="period" value="<?= e($nextPeriod) ?>" required><div class="help"><?= $p['last_generated_period'] ? 'Son işlenen: ' . e(tr_period($p['last_generated_period'])) : 'Henüz işlenmedi' ?><?= $p['repeat_until'] ? ' · bitiş ' . e(tr_period($p['repeat_until'])) : '' ?></div></div>
            <div class="field c8" style="justify-content:flex-end"><div class="form-actions" style="margin:0;padding:0;border:0"><button class="btn btn-primary"><i class="bi bi-skip-forward-fill"></i>Sonraki dönemi işle (<?= e(tr_period($nextPeriod)) ?>)</button></div></div>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-list-check"></i>Plan satırları</h3><div class="sub"><?= count($included) ?> dahil bölüm · <?= e(list_label('distributions', $p['distribution'])) ?></div></div></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Bölüm</th><th class="hide-sm">Tür</th><th class="hide-sm num"><?= $p['distribution'] === 'm2' ? 'm²' : ($p['distribution'] === 'arsa_payi' ? 'Arsa payı' : ($p['distribution'] === 'grup' ? 'Grup' : 'Pay')) ?></th><th class="num">Tutar</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
          <tr class="<?= (int) $l['included'] === 1 ? '' : 'is-muted' ?>" data-href="<?= e(route('units.show', ['id' => $l['unit_id']])) ?>">
            <td><div class="primary-cell"><?= e(($l['block_name'] ? $l['block_name'] . ' · ' : '') . 'No ' . $l['door_no']) ?></div><?php if ($l['fee_group_name']): ?><div class="sub-cell"><?= e($l['fee_group_name']) ?></div><?php endif; ?></td>
            <td class="hide-sm small text-2"><?= e(list_label('unit_types', $l['unit_type'])) ?></td>
            <td class="hide-sm num small text-muted"><?php if ($p['distribution'] === 'grup'): ?><?= e($l['fee_group_name'] ?: '—') ?><?php elseif ($l['share_value'] !== null): ?><?= number_tr((float) $l['share_value'], $p['distribution'] === 'esit' ? 0 : 2) ?><?php else: ?>—<?php endif; ?></td>
            <td class="num" style="font-weight:600"><?= e(money($l['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($lines === []): ?><tr><td colspan="4"><?= $this->partial('partials.empty', ['icon' => 'door-open', 'title' => 'Satır yok', 'text' => 'Bu planda bölüm satırı bulunmuyor.']) ?></td></tr><?php endif; ?>
        </tbody>
        <?php if ($lines !== []): ?><tfoot><tr><td colspan="3">Toplam · <?= count($included) ?> bölüm</td><td class="num"><?= e(money($sum)) ?></td></tr></tfoot><?php endif; ?>
      </table></div>
    </div>

    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-receipt"></i>Üretilen borç kayıtları</h3><div class="sub">Dönem bazında</div></div><a class="btn btn-sm btn-ghost" href="<?= e(route('charges.index', ['plan' => $p['id']])) ?>">Borç kayıtları <i class="bi bi-arrow-right"></i></a></div>
      <div class="table-wrap"><table class="table compact">
        <thead><tr><th>Dönem</th><th class="num">Kayıt</th><th class="num">Tutar</th><th class="num">Tahsil</th><th class="num">Açık</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($periods as $pr): ?>
          <tr data-href="<?= e(route('charges.index', ['plan' => $p['id'], 'donem' => $pr['period']])) ?>"><td class="primary-cell"><?= e(tr_period($pr['period'])) ?></td><td class="num"><?= (int) $pr['cnt'] ?><?= (int) $pr['cancelled'] > 0 ? ' <span class="small text-muted">(' . (int) $pr['cancelled'] . ' iptal)</span>' : '' ?></td><td class="num"><?= e(money($pr['total'])) ?></td><td class="num text-ok"><?= e(money($pr['paid'])) ?></td><td class="num <?= (int) $pr['total'] - (int) $pr['paid'] > 0 ? 'text-bad' : 'text-muted' ?>"><?= e(money((int) $pr['total'] - (int) $pr['paid'])) ?></td><td></td></tr>
        <?php endforeach; ?>
        <?php if ($periods === []): ?><tr><td colspan="6" class="centered text-muted" style="padding:20px">Henüz borç üretilmedi.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-info-circle"></i>Plan bilgileri</h3></div>
      <div class="card-body"><dl class="dl">
        <dt>Borç türü</dt><dd><?= e(list_label('charge_types', $p['charge_type'])) ?></dd>
        <dt>Dönem</dt><dd><?= e(tr_period($p['period'])) ?></dd>
        <dt>Vade</dt><dd><?= e(tr_date($p['due_date'])) ?> <span class="small text-muted">(ayın <?= (int) $p['due_day'] ?>. günü)</span></dd>
        <dt>Tekrar</dt><dd><?= e(list_label('recurrences', $p['recurrence'])) ?><?= $p['repeat_until'] ? ' · ' . e(tr_period($p['repeat_until'])) . ' dönemine kadar' : '' ?></dd>
        <?php if ($p['recurrence'] !== 'tek'): ?><dt>Son işlenen</dt><dd><?= $p['last_generated_period'] ? e(tr_period($p['last_generated_period'])) : '—' ?></dd><?php endif; ?>
        <dt>Dağıtım</dt><dd><?= e(list_label('distributions', $p['distribution'])) ?>
          <?php if (in_array($p['distribution'], ['esit', 'm2', 'arsa_payi'], true)): ?><div class="small text-muted">toplam <?= e(money($p['total_amount'])) ?></div>
          <?php elseif ($p['distribution'] === 'sabit'): ?><div class="small text-muted">birim <?= e(money($p['unit_amount'])) ?></div>
          <?php elseif ($p['distribution'] === 'grup'): ?><?php foreach ($p['group_amounts_arr'] as $gid => $k): ?><div class="small text-muted"><?= e($groups[(int) $gid] ?? ('Grup #' . $gid)) ?>: <?= e(money($k)) ?></div><?php endforeach; ?><?php endif; ?>
        </dd>
        <dt>Kapsam</dt><dd><?= e($p['block_name'] ?: 'Tüm bloklar') ?><?= $p['fee_group_name'] ? ' · ' . e($p['fee_group_name']) : '' ?></dd>
        <dt>KDV</dt><dd><?= e(list_label('vat_modes', $p['vat_mode'])) ?><?= (float) $p['vat_rate'] > 0 ? ' · %' . number_tr((float) $p['vat_rate'], 1) : '' ?></dd>
        <?php if ($p['description']): ?><dt>Açıklama</dt><dd><?= nl2br(e($p['description'])) ?></dd><?php endif; ?>
      </dl></div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>Durum geçmişi</h3></div>
      <div class="card-body">
        <ul class="timeline">
          <li><span class="pt ok"></span><div class="tt">Taslak oluşturuldu</div><div class="tm"><?= e(tr_datetime($p['created_at'])) ?> · <?= e($p['created_by_name'] ?: 'sistem') ?></div></li>
          <?php if ($p['approved_at']): ?><li><span class="pt ok"></span><div class="tt">Onaylandı</div><div class="tm"><?= e(tr_datetime($p['approved_at'])) ?> · <?= e($p['approved_by_name'] ?: 'sistem') ?></div></li><?php else: ?><li><span class="pt"></span><div class="tt text-muted">Onay bekliyor</div></li><?php endif; ?>
          <?php if ($p['processed_at']): ?><li><span class="pt ok"></span><div class="tt">İşlendi</div><div class="tm"><?= e(tr_datetime($p['processed_at'])) ?></div></li><?php elseif ($st === 'onaylandi'): ?><li><span class="pt"></span><div class="tt text-muted"><?= $p['recurrence'] === 'tek' ? 'İşlenmeyi bekliyor' : 'Tekrarlı: dönem dönem işlenir' ?></div><?= $p['last_generated_period'] ? '<div class="tm">Son: ' . e(tr_period($p['last_generated_period'])) . '</div>' : '' ?></li><?php endif; ?>
          <?php if ($p['cancelled_at']): ?><li><span class="pt bad"></span><div class="tt">İptal edildi</div><div class="tm"><?= e(tr_datetime($p['cancelled_at'])) ?><?= $p['cancel_reason'] ? ' · ' . e($p['cancel_reason']) : '' ?></div></li><?php endif; ?>
        </ul>
        <?php if ($timeline !== []): ?>
        <details class="mt-3"><summary class="small text-muted" style="cursor:pointer">İşlem izi (<?= count($timeline) ?>)</summary>
          <ul class="timeline mt-2"><?php foreach ($timeline as $t): ?><li><span class="pt <?= str_contains($t['action'], 'cancel') ? 'bad' : '' ?>"></span><div class="tt small"><?= e($t['summary'] ?: $t['action']) ?></div><div class="tm"><?= e(tr_datetime($t['created_at'])) ?> · <?= e($t['user_name'] ?: 'sistem') ?></div></li><?php endforeach; ?></ul>
        </details>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?= $this->partial('partials.reason-modal') ?>
</div>
