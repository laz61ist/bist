<?php use Aidat\Core\Form; $canManage = can('meters.manage'); $totalMeters = array_sum(array_map('intval', $counts)); ?>
<?= $this->partial('partials.page-head', ['title' => 'Sayaçlar', 'desc' => $totalMeters . ' sayaç · ' . count($active) . ' aktif ' . list_label('meter_types', $tab) . ' sayacı · seçili dönem ' . tr_period($period), 'actions' => can('charges.view') ? '<a class="btn" href="' . e(route('plans.index')) . '"><i class="bi bi-journal-text"></i>Tahakkuk planları</a>' : '']) ?>
<div class="tabs">
  <?php foreach ($meter_types as $k => $v): ?><a href="<?= e(route('meters.index', ['tur' => $k, 'donem' => $period])) ?>" class="<?= $tab === $k ? 'is-active' : '' ?>"><?= e($v) ?><span class="count"><?= (int) ($counts[$k] ?? 0) ?></span></a><?php endforeach; ?>
</div>
<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <form class="table-toolbar" method="get" data-autosubmit><input type="hidden" name="tur" value="<?= e($tab) ?>">
        <label class="small text-muted" for="f_donem_filter">Dönem</label><input class="input" type="month" id="f_donem_filter" name="donem" value="<?= e($period) ?>">
        <span class="spacer"></span><span class="count"><?= count($rows) ?> sayaç</span>
      </form>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Bölüm</th><th>Seri no</th><th class="num hide-sm">Çarpan</th><th class="num">Son okuma</th><th class="num">Tüketim (<?= e(tr_period($period)) ?>)</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $m): ?>
          <tr class="<?= (int) $m['is_active'] === 1 ? '' : 'is-muted' ?>" x-data="{f:false}">
            <td><div class="primary-cell"><?= $m['unit_id'] ? '<a href="' . e(route('units.show', ['id' => $m['unit_id']])) . '">' . e(($m['block_name'] ? $m['block_name'] . ' · ' : '') . 'No ' . $m['door_no']) . '</a>' : '<span class="text-muted">Ortak alan</span>' ?></div><div class="sub-cell"><?= (int) $m['is_active'] === 1 ? e(list_label('meter_types', $m['type'])) : '<span class="pill neutral no-dot">Pasif</span>' ?><?= $m['notes'] ? ' · ' . e(\Aidat\Core\Str::limit((string) $m['notes'], 40)) : '' ?></div></td>
            <td class="mono small"><?= e($m['serial_no'] ?: '—') ?></td>
            <td class="num hide-sm small"><?= e(number_tr((float) $m['multiplier'], 2)) ?></td>
            <td class="num"><?= $m['last_value'] !== null ? number_tr((float) $m['last_value'], 2) . '<div class="sub-cell">' . e(tr_period($m['last_period'])) . '</div>' : '<span class="text-muted">—</span>' ?></td>
            <td class="num"><?= $m['period_consumption'] !== null ? '<strong>' . number_tr((float) $m['period_consumption'], 2) . '</strong>' : '<span class="text-muted">okuma yok</span>' ?></td>
            <td>
              <?php if ($canManage && (int) $m['is_active'] === 1): ?><div class="row-actions"><button type="button" class="btn btn-sm btn-ghost" title="Tekil okuma gir" @click="f=!f"><i class="bi bi-pencil-square"></i></button></div>
              <form x-show="f" x-cloak method="post" action="<?= e(route('meters.reading', ['id' => $m['id']])) ?>" class="flex gap-1 mt-1 wrap" style="min-width:260px"><?= csrf_field() ?>
                <input type="month" class="input" name="period" value="<?= e($period) ?>" style="height:30px;width:130px" required><input type="date" class="input" name="reading_date" value="<?= e($today) ?>" style="height:30px;width:140px" required><input type="number" step="0.01" min="0" class="input num" name="value" placeholder="Değer" style="height:30px;width:110px" required><button class="btn btn-sm btn-primary">Kaydet</button>
              </form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?><tr><td colspan="6"><?= $this->partial('partials.empty', ['icon' => 'speedometer2', 'title' => list_label('meter_types', $tab) . ' sayacı yok', 'text' => 'Sağdaki formdan bölümlere sayaç tanımlayın; ardından toplu okuma girip tüketimi dağıtabilirsiniz.']) ?></td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <?php if ($canManage && $active !== []): ?>
    <div class="card">
      <div class="card-head"><div><h3><i class="bi bi-list-check"></i>Toplu okuma · <?= e(tr_period($period)) ?></h3><div class="sub">Boş bırakılan satırlar atlanır. Önceki okumadan küçük değer kabul edilmez; hatalı satırlar raporlanır, diğerleri kaydedilir.</div></div></div>
      <form method="post" action="<?= e(route('meters.bulk_reading')) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($tab) ?>">
        <div class="card-body" style="padding-bottom:0"><div class="form-grid">
          <?= Form::month('period', 'Dönem', ['value' => $period, 'required' => true, 'col' => 'c6', 'id' => 'bulk_period', 'no_old' => true]) ?>
          <?= Form::date('reading_date', 'Okuma tarihi', ['value' => $today, 'required' => true, 'col' => 'c6', 'id' => 'bulk_date', 'no_old' => true]) ?>
        </div></div>
        <div class="table-wrap"><table class="table compact">
          <thead><tr><th>Bölüm</th><th class="hide-sm">Seri no</th><th class="num">Önceki</th><th class="num" style="width:160px">Yeni okuma</th></tr></thead>
          <tbody>
          <?php foreach ($active as $m): ?>
            <tr>
              <td class="primary-cell"><?= $m['unit_id'] ? e(($m['block_name'] ? $m['block_name'] . ' · ' : '') . 'No ' . $m['door_no']) : '<span class="text-muted">Ortak alan</span>' ?></td>
              <td class="mono small hide-sm"><?= e($m['serial_no'] ?: '—') ?></td>
              <td class="num text-muted"><?= $m['prev_value'] !== null ? number_tr((float) $m['prev_value'], 2) : '—' ?></td>
              <td class="num"><input class="input num" type="number" step="0.01" min="<?= $m['prev_value'] !== null ? e((string) $m['prev_value']) : '0' ?>" name="readings[<?= $m['id'] ?>]" value="<?= $m['period_value'] !== null ? e((string) $m['period_value']) : '' ?>" placeholder="<?= $m['period_value'] !== null ? '' : 'değer' ?>" style="height:32px"></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <div class="card-foot"><span class="small text-muted"><?= count($active) ?> aktif sayaç</span><button class="btn btn-primary"><i class="bi bi-check2-all"></i>Okumaları kaydet</button></div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if ($canManage): ?>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-plus-circle"></i>Sayaç ekle</h3></div>
      <form method="post" action="<?= e(route('meters.store')) ?>" novalidate><?= csrf_field() ?>
        <div class="card-body"><div class="form-grid">
          <?= Form::select('type', 'Tür', $meter_types, ['value' => $tab, 'required' => true, 'col' => 'c6']) ?>
          <?= Form::select('unit_id', 'Bağımsız bölüm', $units, ['placeholder' => 'Ortak alan (bölümsüz)', 'col' => 'c6', 'help' => 'Bölümsüz sayaçlar dağıtıma girmez']) ?>
          <?= Form::text('serial_no', 'Seri no', ['col' => 'c6', 'class' => 'mono', 'placeholder' => 'SU-0025']) ?>
          <?= Form::number('multiplier', 'Çarpan', ['value' => '1', 'col' => 'c6', 'step' => '0.01', 'min' => 0, 'help' => 'Tüketim = fark × çarpan']) ?>
          <?= Form::text('notes', 'Not', ['placeholder' => 'Konum, marka…']) ?>
        </div></div>
        <div class="card-foot"><span></span><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i>Ekle</button></div>
      </form>
    </div>

    <div class="card accent-amber">
      <div class="card-head"><div><h3><i class="bi bi-diagram-3"></i>Tüketimi dağıt</h3><div class="sub">Dönem faturası, okunan tüketime oranlanır; sabit pay eşit bölünür. Sonuç taslak tahakkuk planıdır.</div></div></div>
      <form method="post" action="<?= e(route('meters.distribute')) ?>" novalidate><?= csrf_field() ?>
        <div class="card-body"><div class="form-grid">
          <?= Form::select('type', 'Tür', $meter_types, ['value' => $tab, 'required' => true, 'col' => 'c6', 'id' => 'dist_type']) ?>
          <?= Form::month('period', 'Dönem', ['value' => $period, 'required' => true, 'col' => 'c6', 'id' => 'dist_period', 'no_old' => true]) ?>
          <?= Form::money('total_amount', 'Toplam fatura', ['required' => true, 'col' => 'c6']) ?>
          <?= Form::number('fixed_share_percent', 'Sabit pay (%)', ['value' => '0', 'col' => 'c6', 'min' => 0, 'max' => 100, 'step' => '0.5', 'help' => 'Sayaç kirası, ortak kayıp vb.']) ?>
          <?= Form::date('due_date', 'Vade', ['value' => date('Y-m-d', strtotime('+15 days')), 'required' => true, 'col' => 'c6']) ?>
        </div></div>
        <div class="card-foot"><span class="small text-muted">Kiracı öder · KDV yok</span><button class="btn btn-primary btn-sm"><i class="bi bi-arrow-right-circle"></i>Plan oluştur</button></div>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>Son dağıtımlar</h3></div>
      <div class="table-wrap"><table class="table compact"><tbody>
        <?php foreach ($distributions as $dist): ?>
          <tr <?= $dist['plan_id'] && can('charges.view') ? 'data-href="' . e(route('plans.show', ['id' => $dist['plan_id']])) . '"' : '' ?>><td><div class="primary-cell"><?= e(tr_period($dist['period'])) ?></div><div class="sub-cell"><?= e(number_tr((float) $dist['total_consumption'], 2)) ?> birim · <?= e(number_tr((float) $dist['unit_price'], 4)) ?> ₺/birim<?= (float) $dist['fixed_share_percent'] > 0 ? ' · sabit %' . e(number_tr((float) $dist['fixed_share_percent'], 1)) : '' ?></div></td><td class="num"><?= e(money($dist['total_amount'])) ?><div class="sub-cell"><span class="pill <?= status_tone($dist['status']) ?>" style="padding:0 6px;font-size:11px"><?= e(list_label('plan_statuses', $dist['status'])) ?></span></div></td></tr>
        <?php endforeach; ?>
        <?php if ($distributions === []): ?><tr><td class="text-muted centered" style="padding:20px">Bu tür için dağıtım yapılmadı.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
