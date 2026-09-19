<?php
$k = $kpi;
$rateTone = $k['rate'] >= 85 ? 'ok' : ($k['rate'] >= 60 ? 'warn' : 'bad');
$periodOptions = [];
for ($i = 0; $i < 18; $i++) { $pp = \Aidat\Core\Dates::addMonths(date('Y-m'), -$i); $periodOptions[$pp] = tr_period($pp); }
$actions = '<form method="get" data-autosubmit class="flex gap-2 center"><select class="select" name="donem" style="width:auto">';
foreach ($periodOptions as $val => $lab) { $actions .= '<option value="' . e($val) . '"' . ($val === $period ? ' selected' : '') . '>' . e($lab) . '</option>'; }
$actions .= '</select></form>';
if (can('payments.create')) { $actions .= '<a class="btn btn-primary" href="' . e(route('payments.create')) . '"><i class="bi bi-plus-lg"></i>Tahsilat gir</a>'; }
?>
<?= $this->partial('partials.page-head', ['title' => 'Genel bakış', 'desc' => $building['name'] . ' · ' . tr_period($period) . ' dönemi' . ($periodClosed ? ' · dönem kapalı' : ''), 'actions' => $actions]) ?>

<?php $alerts = []; ?>
<?php if ($debt['debtor_units'] > 0): ?><?php $alerts[] = ['bad', 'exclamation-diamond', $debt['debtor_units'] . ' bölümde vadesi geçmiş borç var: ' . money($debt['overdue']), route('debtors.index'), 'Borçlu listesi']; ?><?php endif; ?>
<?php if ($pendingPlans > 0): ?><?php $alerts[] = ['warn', 'journal-text', $pendingPlans . ' tahakkuk planı onay/işlem bekliyor', route('plans.index'), 'Planlar']; ?><?php endif; ?>
<?php if ($pendingImports > 0): ?><?php $alerts[] = ['warn', 'bank', $pendingImports . ' banka hareketi eşleştirme bekliyor', route('imports.index'), 'Eşleştir']; ?><?php endif; ?>
<?php if ($openRequests > 0): ?><?php $alerts[] = ['info', 'tools', $openRequests . ' açık talep / iş emri', route('requests.index'), 'Talepler']; ?><?php endif; ?>
<?php foreach ($contractsEnding as $c): ?><?php $alerts[] = ['warn', 'file-earmark-ruled', $c['vendor_name'] . ' sözleşmesi ' . tr_date($c['end_date']) . ' tarihinde bitiyor', route('contracts.index'), 'Sözleşmeler']; ?><?php endforeach; ?>
<?php if ($alerts !== []): ?>
<div class="grid grid-2 mb-4">
  <?php foreach (array_slice($alerts, 0, 4) as $a): ?>
    <div class="alert <?= $a[0] ?>"><i class="bi bi-<?= $a[1] ?>"></i><div class="grow"><?= e($a[2]) ?></div><a class="btn btn-sm" href="<?= e($a[3]) ?>"><?= e($a[4]) ?></a></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="stat-row mb-4">
  <div class="stat tone-<?= $rateTone ?>"><div class="k">Tahsilat oranı<i class="bi bi-info-circle" title="Dönem tahakkukunun tahsil edilen kısmı"></i></div><div class="v"><?= e(percent_tr($k['rate'])) ?></div><div class="progress thin mt-2 <?= $rateTone === 'ok' ? '' : $rateTone ?>"><i style="width:<?= min(100, $k['rate']) ?>%"></i></div><div class="d"><?= e(money($k['collectedForPeriod'])) ?> / <?= e(money($k['accrued'])) ?></div></div>
  <div class="stat"><div class="k">Dönem tahakkuku</div><div class="v"><?= e(money($k['accrued'], false)) ?> <small>₺</small></div><div class="d"><?= $k['unitCount'] ?> bağımsız bölüm</div></div>
  <div class="stat tone-ok"><div class="k">Dönemde tahsil edilen</div><div class="v"><?= e(money($k['collectedInPeriod'], false)) ?> <small>₺</small></div><div class="d">+ diğer gelir <?= e(money($k['otherIncome'])) ?></div></div>
  <div class="stat"><div class="k">Dönem gideri</div><div class="v"><?= e(money($k['expensesInPeriod'], false)) ?> <small>₺</small></div><div class="d"><?php $net = $k['collectedInPeriod'] + $k['otherIncome'] - $k['expensesInPeriod']; ?><span class="<?= $net >= 0 ? 'up' : 'down' ?>"><i class="bi bi-<?= $net >= 0 ? 'arrow-up-right' : 'arrow-down-right' ?>"></i> net <?= e(money($net)) ?></span></div></div>
  <div class="stat tone-bad"><div class="k">Vadesi geçmiş borç</div><div class="v"><?= e(money($debt['overdue'], false)) ?> <small>₺</small></div><div class="d"><?= $debt['debtor_units'] ?> bölüm · toplam açık <?= e(money($debt['open'])) ?></div></div>
  <div class="stat"><div class="k">Kasa + banka</div><div class="v"><?= e(money($totalBalance, false)) ?> <small>₺</small></div><div class="d"><?= count($accounts) ?> hesap · avans <?= e(money($debt['advance'])) ?></div></div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h3>Son 12 ay: tahakkuk, tahsilat ve gider</h3><div class="sub">Aylık toplamlar · ₺</div></div><div class="legend"><span><i style="background:var(--ink-4)"></i>Tahakkuk</span><span><i style="background:var(--moss)"></i>Tahsilat</span><span><i style="background:var(--clay)"></i>Gider</span></div></div>
      <div class="card-body"><div class="chart-box"><canvas data-chart='<?= e(json_encode(['type' => 'bar', 'money' => true, 'data' => ['labels' => $chart['labels'], 'datasets' => [['label' => 'Tahakkuk', 'data' => $chart['accrued'], 'color' => 'ink4', 'type' => 'line', 'borderWidth' => 1.5, 'borderDash' => [4, 3], 'fill' => false, 'pointRadius' => 0], ['label' => 'Tahsilat', 'data' => $chart['collected'], 'color' => 'moss'], ['label' => 'Gider', 'data' => $chart['expense'], 'color' => 'clay']]], 'options' => ['plugins' => ['legend' => ['display' => false]]]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div>
    </div>

    <div class="card">
      <div class="card-head"><div><h3>Blok bazlı durum</h3><div class="sub">Vadesi geçmiş borç ve borçlu bölüm sayısı</div></div><a class="btn btn-sm btn-ghost" href="<?= e(route('debtors.index')) ?>">Tümü <i class="bi bi-arrow-right"></i></a></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Blok</th><th class="num">Bölüm</th><th class="num">Borçlu bölüm</th><th class="num">Vadesi geçmiş</th><th style="width:30%">Oran</th></tr></thead>
        <tbody>
        <?php foreach ($blocks as $bl): $pct = $bl['units'] > 0 ? round($bl['debtor_units'] / $bl['units'] * 100) : 0; ?>
          <tr><td class="primary-cell"><?= e($bl['name']) ?></td><td class="num"><?= $bl['units'] ?></td><td class="num"><?= $bl['debtor_units'] ?></td><td class="num <?= $bl['overdue'] > 0 ? 'text-bad' : '' ?>"><?= e(money($bl['overdue'])) ?></td><td><div class="flex center gap-2"><div class="progress thin grow <?= $pct > 30 ? 'bad' : ($pct > 10 ? 'warn' : '') ?>"><i style="width:<?= $pct ?>%"></i></div><span class="small num"><?= $pct ?>%</span></div></td></tr>
        <?php endforeach; ?>
        <?php if ($blocks === []): ?><tr><td colspan="5"><?= $this->partial('partials.empty', ['icon' => 'door-open', 'title' => 'Henüz bağımsız bölüm yok', 'text' => 'Toplu oluşturma sihirbazıyla dakikalar içinde tüm bölümleri ekleyin.', 'action' => can('units.manage') ? '<a class="btn btn-primary" href="' . e(route('units.bulk')) . '"><i class="bi bi-magic"></i>Toplu oluştur</a>' : '']) ?></td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="grid grid-2">
      <div class="card">
        <div class="card-head"><h3>Son tahsilatlar</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('payments.index')) ?>">Tümü <i class="bi bi-arrow-right"></i></a></div>
        <div class="table-wrap"><table class="table compact">
          <tbody>
          <?php foreach ($recentPayments as $pm): ?>
            <tr data-href="<?= e(route('payments.show', ['id' => $pm['id']])) ?>" class="<?= $pm['status'] !== 'gecerli' ? 'is-cancelled' : '' ?>"><td><div class="primary-cell mono"><?= e($pm['receipt_no']) ?></div><div class="sub-cell"><?= $pm['door_no'] ? 'No ' . e($pm['door_no']) : 'Bölümsüz' ?> · <?= e(list_label('payment_methods', $pm['method'])) ?></div></td><td class="small text-muted"><?= e(tr_date($pm['payment_date'])) ?></td><td class="num"><?= e(money($pm['amount'])) ?></td></tr>
          <?php endforeach; ?>
          <?php if ($recentPayments === []): ?><tr><td class="text-muted centered" style="padding:24px">Henüz tahsilat yok.</td></tr><?php endif; ?>
          </tbody></table></div>
      </div>
      <div class="card">
        <div class="card-head"><h3>Son giderler</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('expenses.index')) ?>">Tümü <i class="bi bi-arrow-right"></i></a></div>
        <div class="table-wrap"><table class="table compact">
          <tbody>
          <?php foreach ($recentExpenses as $ex): ?>
            <tr data-href="<?= e(route('expenses.show', ['id' => $ex['id']])) ?>" class="<?= $ex['status'] === 'iptal' ? 'is-cancelled' : '' ?>"><td><div class="primary-cell"><?= e(\Aidat\Core\Str::limit((string) ($ex['description'] ?: $ex['category_name'] ?: 'Gider'), 34)) ?></div><div class="sub-cell"><?= e($ex['category_name'] ?: 'Kategorisiz') ?></div></td><td class="small text-muted"><?= e(tr_date($ex['expense_date'])) ?></td><td class="num"><?= e(money($ex['amount'])) ?></td></tr>
          <?php endforeach; ?>
          <?php if ($recentExpenses === []): ?><tr><td class="text-muted centered" style="padding:24px">Henüz gider yok.</td></tr><?php endif; ?>
          </tbody></table></div>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3>Kasa ve banka</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('accounts.index')) ?>">Hesaplar</a></div>
      <div class="card-body tight">
        <?php foreach ($accounts as $a): ?>
          <div class="flex between center" style="padding:10px 18px;border-bottom:1px solid var(--rule-soft)"><div><div class="primary-cell"><i class="bi bi-<?= $a['type'] === 'banka' ? 'bank' : 'cash-stack' ?> text-muted"></i> <?= e($a['name']) ?></div><?php if ($a['iban']): ?><div class="sub-cell mono"><?= e(\Aidat\Core\Str::formatIban($a['iban'])) ?></div><?php endif; ?></div><div class="num <?= $a['balance'] < 0 ? 'text-bad' : '' ?>" style="font-weight:600"><?= e(money($a['balance'])) ?></div></div>
        <?php endforeach; ?>
        <div class="flex between center" style="padding:12px 18px;background:var(--surface-2)"><span class="eyebrow">Toplam</span><span class="num" style="font-weight:600;font-size:16px"><?= e(money($totalBalance)) ?></span></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>En yüksek borçlular</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('debtors.index')) ?>">Liste</a></div>
      <div class="table-wrap"><table class="table compact">
        <tbody>
        <?php foreach ($topDebtors as $d): ?>
          <tr data-href="<?= e(route('units.show', ['id' => $d['unit_id']])) ?>"><td><div class="primary-cell"><?= e(($d['block_name'] ? $d['block_name'] . ' · ' : '') . 'No ' . $d['door_no']) ?></div><div class="sub-cell"><?= e($d['responsible']) ?> · <?= $d['days'] ?> gün</div></td><td class="num text-bad"><?= e(money($d['overdue'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($topDebtors === []): ?><tr><td class="centered" style="padding:20px"><span class="pill ok">Vadesi geçmiş borç yok</span></td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Yaklaşan ödemeler</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('expenses.index', ['durum' => 'planlandi'])) ?>">Planlanan</a></div>
      <div class="table-wrap"><table class="table compact"><tbody>
        <?php foreach ($upcoming as $u): $late = ($u['due_date'] ?? $u['expense_date']) < date('Y-m-d'); ?>
          <tr data-href="<?= e(route('expenses.show', ['id' => $u['id']])) ?>"><td><div class="primary-cell"><?= e(\Aidat\Core\Str::limit((string) ($u['description'] ?: $u['category_name'] ?: 'Gider'), 30)) ?></div><div class="sub-cell <?= $late ? 'text-bad' : '' ?>"><?= $late ? 'Gecikti · ' : 'Vade · ' ?><?= e(tr_date($u['due_date'] ?? $u['expense_date'])) ?></div></td><td class="num"><?= e(money((int) $u['amount'] - (int) $u['paid_amount'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($upcoming === []): ?><tr><td class="text-muted centered" style="padding:20px">Bekleyen ödeme yok.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Gider dağılımı</h3><span class="small text-muted">son 6 ay</span></div>
      <div class="card-body">
        <?php if ($byCategory !== []): ?>
          <div class="chart-box sm"><canvas data-chart='<?= e(json_encode(['type' => 'doughnut', 'money' => true, 'data' => ['labels' => array_column($byCategory, 'name'), 'datasets' => [['data' => array_map('intval', array_column($byCategory, 'total')), 'borderWidth' => 2]]], 'options' => ['cutout' => '68%', 'plugins' => ['legend' => ['position' => 'right']]]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div>
        <?php else: ?><p class="text-muted small centered">Gider kaydı oluştukça dağılım burada görünür.</p><?php endif; ?>
      </div>
    </div>

    <?php if ($urgentRequests !== [] || $announcements !== []): ?>
    <div class="card">
      <div class="card-head"><h3>Öncelikli işler ve duyurular</h3></div>
      <div class="card-body">
        <ul class="timeline">
          <?php foreach ($urgentRequests as $r): ?><li><span class="pt <?= $r['priority'] === 'acil' ? 'bad' : 'warn' ?>"></span><div class="tt"><a href="<?= e(route('requests.show', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a></div><div class="tm"><?= e(list_label('request_priorities', $r['priority'])) ?> · <?= e(list_label('request_statuses', $r['status'])) ?></div></li><?php endforeach; ?>
          <?php foreach ($announcements as $an): ?><li><span class="pt <?= $an['priority'] === 'acil' ? 'bad' : ($an['priority'] === 'onemli' ? 'warn' : 'ok') ?>"></span><div class="tt"><a href="<?= e(route('announcements.show', ['id' => $an['id']])) ?>"><?= e($an['title']) ?></a></div><div class="tm">Duyuru · <?= e(tr_date($an['published_at'])) ?></div></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
