<?php use Aidat\Core\Form; use Aidat\Core\Money;
$dist = (string) $d['distribution'];
$weighted = in_array($dist, ['esit', 'm2', 'arsa_payi'], true);
$incSet = array_fill_keys($includedIds, true);
$unitsJs = array_map(static fn (array $u) => ['id' => (int) $u['id'], 'm2' => (float) ($u['gross_m2'] ?: $u['net_m2'] ?: 0), 'land' => (float) ($u['land_share'] ?: 0), 'group' => (int) ($u['fee_group_id'] ?? 0)], $units);
$includedJs = [];
$manualJs = [];
foreach ($units as $u) {
    $includedJs[(int) $u['id']] = isset($incSet[(int) $u['id']]);
    $manualJs[(int) $u['id']] = isset($manual[(int) $u['id']]) ? Money::input($manual[(int) $u['id']]) : '';
}
$groupAmountsJs = [];
foreach ($plan['group_amounts'] as $gid => $k) { $groupAmountsJs[(string) $gid] = Money::input($k); }
$init = 'distribution=' . json_encode($dist) . ';total=' . json_encode(Money::input($plan['total_amount'])) . ';unitAmount=' . json_encode(Money::input($plan['unit_amount'])) . ';groupAmounts=' . json_encode($groupAmountsJs ?: new stdClass()) . ';included=' . json_encode($includedJs ?: new stdClass()) . ';manual=' . json_encode($manualJs ?: new stdClass());
$missing = 0;
foreach ($units as $u) {
    if ($dist === 'm2' && (float) ($u['gross_m2'] ?: $u['net_m2'] ?: 0) <= 0) { $missing++; }
    if ($dist === 'arsa_payi' && (float) ($u['land_share'] ?: 0) <= 0) { $missing++; }
    if ($dist === 'grup' && (int) ($u['fee_group_id'] ?? 0) === 0) { $missing++; }
}
$scope = ($d['block_id'] ? ($blocks[$d['block_id']] ?? 'Blok') : 'Tüm bloklar') . ' · ' . ($d['fee_group_id'] ? ($groups[$d['fee_group_id']] ?? 'Grup') : 'tüm gruplar');
?>
<?= $this->partial('partials.page-head', ['title' => 'Önizleme · ' . $d['name'], 'crumbs' => [['Tahakkuk planları', route('plans.index')], ['Yeni', route('plans.create')], ['Önizleme']], 'desc' => list_label('charge_types', $d['charge_type']) . ' · ' . tr_period($d['period']) . ' · vade ' . tr_date($d['due_date']) . ' · ' . list_label('distributions', $dist) . ' · ' . $scope]) ?>

<form method="post" action="<?= e(route('plans.store')) ?>" novalidate x-data='planPreview(<?= e(json_encode($unitsJs)) ?>)' x-init="<?= e($init) ?>">
  <?= csrf_field() ?>
  <?php foreach (['name', 'charge_type', 'block_id', 'fee_group_id', 'period', 'due_date', 'recurrence', 'repeat_until', 'distribution', 'liability', 'vat_mode', 'vat_rate', 'description', 'plan_id'] as $k): ?>
    <?= Form::hidden($k, $d[$k] ?? '') ?>
  <?php endforeach; ?>
  <?= Form::hidden('total_amount', Money::input($d['total_amount'] ?? 0)) ?>
  <?= Form::hidden('unit_amount', Money::input($d['unit_amount'] ?? 0)) ?>
  <?php foreach ($d['group_amounts'] as $gid => $k): ?><?= Form::hidden('group_amounts[' . $gid . ']', Money::input($k)) ?><?php endforeach; ?>

  <div class="stat-row mb-4">
    <div class="stat"><div class="k">Plan toplamı</div><div class="v"><span x-text="fmt(sum())"><?= e(money($sum)) ?></span></div><div class="d"><?= $vatAdded > 0 ? 'KDV dahil (' . e(money($vatAdded)) . ' KDV eklendi)' : list_label('vat_modes', $d['vat_mode']) ?></div></div>
    <div class="stat"><div class="k">Dahil bölüm</div><div class="v"><span x-text="activeUnits().length"><?= $count ?></span> <small>/ <?= count($units) ?></small></div><div class="d"><?= e($scope) ?></div></div>
    <div class="stat <?= $roundingDiff !== 0 ? 'tone-warn' : '' ?>"><div class="k">Yuvarlama farkı</div><div class="v"><?= e(Money::format($roundingDiff, true, true)) ?></div><div class="d"><?= $weighted ? 'Son satıra eklendi; toplam korunur' : 'Bu dağıtımda yuvarlama yok' ?></div></div>
    <div class="stat"><div class="k">Tekrar</div><div class="v" style="font-family:var(--font-sans);font-size:18px"><?= e(list_label('recurrences', $d['recurrence'])) ?></div><div class="d"><?= $d['recurrence'] !== 'tek' ? ($d['repeat_until'] ? tr_period($d['repeat_until']) . ' dönemine kadar' : 'İptal edilene kadar') : 'Tek seferlik tahakkuk' ?></div></div>
  </div>

  <?php if ($missing > 0): ?><div class="alert warn mb-4"><i class="bi bi-exclamation-triangle"></i><div><strong><?= $missing ?> bölümde</strong> <?= $dist === 'm2' ? 'm² bilgisi' : ($dist === 'arsa_payi' ? 'arsa payı' : 'aidat grubu') ?> eksik; bu bölümlere 0 ₺ düşer. Hariç tutun ya da bölüm kartından tamamlayın.</div></div><?php endif; ?>
  <?php if ($units === []): ?><div class="alert bad mb-4"><i class="bi bi-x-octagon"></i><div>Seçilen kapsamda aktif bölüm yok. Blok / grup filtresini değiştirin.</div></div><?php endif; ?>

  <div class="card">
    <div class="card-head"><div><h3><i class="bi bi-list-check"></i>Bölüm satırları</h3><div class="sub">İşareti kaldırılan bölümler plana dahil edilmez<?= $dist === 'manuel' ? ' · tutarları bölüm bazında girin' : '' ?></div></div><div class="tools"><button class="btn btn-sm" formaction="<?= e(route('plans.preview')) ?>"><i class="bi bi-arrow-repeat"></i>Sunucuda yeniden hesapla</button></div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr>
        <th class="check-cell"><input type="checkbox" data-check-all <?= count($includedIds) === count($units) && $units !== [] ? 'checked' : '' ?> title="Tümünü seç / bırak"></th>
        <th>Blok</th><th>Kapı no</th><th class="hide-sm">Tür</th>
        <th class="num hide-sm"><?= $dist === 'm2' ? 'm²' : ($dist === 'arsa_payi' ? 'Arsa payı' : ($dist === 'grup' ? 'Grup' : 'm² / arsa payı')) ?></th>
        <?php if ($weighted): ?><th class="num">Pay</th><?php endif; ?>
        <?php if ($dist === 'manuel'): ?><th class="num" style="width:180px">Tutar (manuel)</th><?php endif; ?>
        <th class="num">Tutar</th>
      </tr></thead>
      <tbody>
      <?php foreach ($units as $u): $uid = (int) $u['id']; $line = $lines[$uid] ?? null; $inc = isset($incSet[$uid]); $m2 = (float) ($u['gross_m2'] ?: $u['net_m2'] ?: 0); ?>
        <tr :class="{'is-muted': !included[<?= $uid ?>]}" class="<?= $inc ? '' : 'is-muted' ?>">
          <td class="check-cell"><input type="checkbox" name="included[]" value="<?= $uid ?>" data-check-item <?= $inc ? 'checked' : '' ?> x-model="included[<?= $uid ?>]" aria-label="No <?= e($u['door_no']) ?> dahil"></td>
          <td class="small text-2"><?= e($u['block_name'] ?: '—') ?></td>
          <td><div class="primary-cell">No <?= e($u['door_no']) ?></div><?php if ($u['fee_group_name'] && $dist !== 'grup'): ?><div class="sub-cell"><?= e($u['fee_group_name']) ?></div><?php endif; ?></td>
          <td class="hide-sm small text-2"><?= e(list_label('unit_types', $u['type'])) ?></td>
          <td class="num hide-sm small">
            <?php if ($dist === 'm2'): ?><?= $m2 > 0 ? number_tr($m2, 2) : '<span class="text-bad">eksik</span>' ?>
            <?php elseif ($dist === 'arsa_payi'): ?><?= $u['land_share'] ? number_tr((float) $u['land_share'], 4) : '<span class="text-bad">eksik</span>' ?>
            <?php elseif ($dist === 'grup'): ?><?= $u['fee_group_name'] ? e($u['fee_group_name']) : '<span class="text-bad">grup yok</span>' ?>
            <?php else: ?><span class="text-muted"><?= $m2 > 0 ? number_tr($m2) . ' m²' : '—' ?><?= $u['land_share'] ? ' · ' . number_tr((float) $u['land_share'], 2) : '' ?></span><?php endif; ?>
          </td>
          <?php if ($weighted): ?><td class="num small text-muted"><?= $line && $line['share'] !== null ? number_tr((float) $line['share'], $dist === 'esit' ? 0 : 2) : '—' ?></td><?php endif; ?>
          <?php if ($dist === 'manuel'): ?><td class="num"><div class="input-group"><input class="input num" type="text" name="manual[<?= $uid ?>]" value="<?= e($manualJs[$uid]) ?>" data-money inputmode="decimal" autocomplete="off" placeholder="0,00" x-model="manual[<?= $uid ?>]" style="width:120px"><span class="addon">₺</span></div></td><?php endif; ?>
          <td class="num" style="font-weight:600"><span x-text="included[<?= $uid ?>] ? fmt(rows()[<?= $uid ?>] || 0) : '—'"><?= $line ? e(money($line['amount'])) : '—' ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($units === []): ?><tr><td colspan="8"><?= $this->partial('partials.empty', ['icon' => 'door-open', 'title' => 'Kapsamda bölüm yok', 'text' => 'Blok veya grup filtresini değiştirip yeniden önizleyin.']) ?></td></tr><?php endif; ?>
      </tbody>
      <?php if ($units !== []): ?>
      <tfoot><tr>
        <td colspan="<?= 4 + ($weighted ? 1 : 0) + ($dist === 'manuel' ? 1 : 0) ?>">Toplam · <span x-text="activeUnits().length"><?= $count ?></span> bölüm<?= $roundingDiff !== 0 ? ' · yuvarlama farkı ' . e(Money::format($roundingDiff, true, true)) : '' ?></td>
        <td class="num" style="font-weight:600"><span x-text="fmt(sum())"><?= e(money($sum)) ?></span></td>
      </tr></tfoot>
      <?php endif; ?>
    </table></div>
    <div class="card-foot">
      <a class="btn" href="<?= e(route('plans.create', ['duzenle' => $d['plan_id'] ?? null])) ?>" onclick="if (history.length > 1) { history.back(); return false; }"><i class="bi bi-arrow-left"></i>Forma dön</a>
      <button class="btn" formaction="<?= e(route('plans.preview')) ?>"><i class="bi bi-arrow-repeat"></i>Yeniden hesapla</button>
      <span class="ml-auto"></span>
      <button class="btn btn-primary btn-lg" <?= $units === [] ? 'disabled' : '' ?>><i class="bi bi-save"></i><?= !empty($d['plan_id']) ? 'Taslağı güncelle' : 'Planı kaydet (taslak)' ?></button>
    </div>
  </div>
</form>
