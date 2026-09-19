<?php $f = $filters; $hasFilter = $f['q'] !== '' || $f['bolum'] || $f['tur'] !== '' || $f['durum'] !== '' || $f['donem'] !== '' || $f['vade'] || $f['plan'];
$desc = $planName ? 'Plan: ' . $planName : 'Tahakkuk planlarından ve tekil borçlandırmadan oluşan tüm borç kayıtları';
?>
<?= $this->partial('partials.page-head', ['title' => 'Borç kayıtları', 'desc' => $desc, 'actions' => (can('charges.manage') ? '<a class="btn btn-primary" href="' . e(route('charges.create', ['bolum' => $f['bolum'] ?: null])) . '"><i class="bi bi-plus-square"></i>Tekil borçlandır</a>' : '') . (can('latefee.manage') ? '<a class="btn" href="' . e(route('latefee.index')) . '"><i class="bi bi-hourglass-split"></i>Gecikme tazminatı</a>' : '')]) ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k">Tahakkuk (filtre)</div><div class="v"><?= e(money($totals['amount'], false)) ?> <small>₺</small></div><div class="d"><?= number_tr($p->total) ?> kayıt · iptaller hariç</div></div>
  <div class="stat tone-ok"><div class="k">Tahsil edilen</div><div class="v"><?= e(money($totals['paid'], false)) ?> <small>₺</small></div><div class="d"><?= (int) $totals['amount'] > 0 ? e(percent_tr((int) $totals['paid'] / (int) $totals['amount'] * 100)) : '—' ?></div></div>
  <div class="stat <?= (int) $totals['open'] > 0 ? 'tone-warn' : '' ?>"><div class="k">Açık</div><div class="v"><?= e(money($totals['open'], false)) ?> <small>₺</small></div><div class="d">ödenmedi + kısmi</div></div>
  <div class="stat <?= (int) $totals['overdue'] > 0 ? 'tone-bad' : '' ?>"><div class="k">Vadesi geçmiş</div><div class="v"><?= e(money($totals['overdue'], false)) ?> <small>₺</small></div><div class="d"><a href="<?= e(query_with(['vade' => 1, 'sayfa' => null])) ?>">Yalnızca vadesi geçenler</a></div></div>
</div>

<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <?php if ($f['plan']): ?><input type="hidden" name="plan" value="<?= (int) $f['plan'] ?>"><?php endif; ?>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Kapı no veya borç başlığı…">
    <select class="select" name="bolum"><option value="">Tüm bölümler</option><?php foreach ($units as $g => $opts): ?><optgroup label="<?= e($g) ?>"><?php foreach ($opts as $id => $n): ?><option value="<?= $id ?>" <?= $f['bolum'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
    <select class="select" name="tur"><option value="">Tüm türler</option><?php foreach ($charge_types as $k => $v): ?><option value="<?= $k ?>" <?= $f['tur'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($charge_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="donem"><option value="">Tüm dönemler</option><?php foreach ($periods as $pp): ?><option value="<?= e($pp) ?>" <?= $f['donem'] === $pp ? 'selected' : '' ?>><?= e(tr_period($pp)) ?></option><?php endforeach; ?></select>
    <label class="check" style="padding:0"><input type="checkbox" name="vade" value="1" <?= $f['vade'] ? 'checked' : '' ?> onchange="this.form.submit()"><span>Vadesi geçmiş</span></label>
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('charges.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Borç</th><th>Bölüm</th><th class="hide-sm">Sorumlu</th><th>Dönem</th><th>Vade</th><th>Durum</th><th class="num">Tutar</th><th class="num hide-sm">Ödenen</th><th class="num">Kalan</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $open = (int) $c['amount'] - (int) $c['paid_amount']; $overdue = in_array($c['status'], ['odenmedi', 'kismi'], true) && $c['due_date'] < $today; ?>
      <tr data-href="<?= e(route('charges.show', ['id' => $c['id']])) ?>" class="<?= $c['status'] === 'iptal' ? 'is-cancelled' : '' ?>">
        <td><div class="primary-cell"><?= e($c['title']) ?></div><div class="sub-cell"><?= e(list_label('charge_types', $c['charge_type'])) ?><?= $c['plan_id'] ? ' · plan' : '' ?><?= $c['parent_charge_id'] ? ' · <i class="bi bi-link-45deg"></i>ana borca bağlı' : '' ?></div></td>
        <td><a href="<?= e(route('units.show', ['id' => $c['unit_id']])) ?>"><?= e(($c['block_name'] ? $c['block_name'] . ' · ' : '') . 'No ' . $c['door_no']) ?></a></td>
        <td class="hide-sm small"><?= $c['person_name'] ? e($c['person_name']) : '<span class="text-muted">—</span>' ?><div class="sub-cell"><?= e(list_label('liability_modes', $c['liability'])) ?></div></td>
        <td class="small"><?= e(tr_period($c['period'])) ?></td>
        <td class="small <?= $overdue ? 'text-bad' : '' ?>"><?= e(tr_date($c['due_date'])) ?><?= $overdue ? ' <i class="bi bi-exclamation-circle" title="Vadesi geçti"></i>' : '' ?></td>
        <td><span class="pill <?= status_tone($c['status']) ?>"><?= e(list_label('charge_statuses', $c['status'])) ?></span></td>
        <td class="num"><?= e(money($c['amount'])) ?></td>
        <td class="num hide-sm text-muted"><?= e(money($c['paid_amount'])) ?></td>
        <td class="num <?= $c['status'] === 'iptal' ? 'text-muted' : ($open > 0 ? 'text-bad' : 'text-ok') ?>" style="font-weight:600"><?= $c['status'] === 'iptal' ? '—' : e(money($open)) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="9"><?= $this->partial('partials.empty', ['icon' => 'receipt', 'title' => 'Borç kaydı bulunamadı', 'text' => $hasFilter ? 'Filtreyi değiştirin veya temizleyin.' : 'Tahakkuk planı işleyerek ya da tekil borçlandırma ile ilk kaydı oluşturun.', 'action' => can('charges.manage') && !$hasFilter ? '<a class="btn btn-primary" href="' . e(route('plans.create')) . '"><i class="bi bi-journal-plus"></i>Tahakkuk planı</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?><tfoot><tr><td colspan="6">Filtre toplamı (iptaller hariç)</td><td class="num"><?= e(money($totals['amount'])) ?></td><td class="num hide-sm"><?= e(money($totals['paid'])) ?></td><td class="num"><?= e(money($totals['open'])) ?></td></tr></tfoot><?php endif; ?>
  </table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
