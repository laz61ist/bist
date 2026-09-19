<?php $f = $filters; ?>
<?= $this->partial('partials.page-head', ['title' => 'Tahakkuk planları', 'desc' => 'Toplu borçlandırma planları: taslak → onay → işleme. İşlenen plan her bölüme borç kaydı açar.', 'actions' => can('charges.manage') ? '<a class="btn" href="' . e(route('charges.create')) . '"><i class="bi bi-plus-square"></i>Tekil borç</a><a class="btn btn-primary" href="' . e(route('plans.create')) . '"><i class="bi bi-plus-lg"></i>Yeni plan</a>' : '']) ?>
<div class="card">
  <div class="tabs" style="padding:0 16px">
    <a href="<?= e(route('plans.index')) ?>" class="<?= $f['durum'] === '' ? 'is-active' : '' ?>">Tümü <span class="count"><?= $counts[''] ?? 0 ?></span></a>
    <?php foreach ($plan_statuses as $k => $v): ?><a href="<?= e(route('plans.index', ['durum' => $k])) ?>" class="<?= $f['durum'] === $k ? 'is-active' : '' ?>"><?= e($v) ?> <span class="count"><?= $counts[$k] ?? 0 ?></span></a><?php endforeach; ?>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Plan</th><th>Dönem</th><th class="hide-sm">Kapsam</th><th class="hide-sm">Dağıtım</th><th class="num">Bölüm</th><th class="num">Toplam</th><th>Durum</th><th class="hide-sm">Son işlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr data-href="<?= e(route('plans.show', ['id' => $r['id']])) ?>" class="<?= $r['status'] === 'iptal' ? 'is-cancelled' : '' ?>">
        <td><div class="primary-cell"><?= e($r['name']) ?></div><div class="sub-cell"><?= e(list_label('charge_types', $r['charge_type'])) ?> · <?= e(list_label('recurrences', $r['recurrence'])) ?><?= $r['recurrence'] !== 'tek' && $r['repeat_until'] ? ' → ' . e(tr_period($r['repeat_until'])) : '' ?></div></td>
        <td class="small"><?= e(tr_period($r['period'])) ?><div class="sub-cell">vade <?= e(tr_date($r['due_date'])) ?></div></td>
        <td class="hide-sm small text-2"><?= e($r['block_name'] ?: 'Tüm bloklar') ?><?= $r['fee_group_name'] ? ' · ' . e($r['fee_group_name']) : '' ?></td>
        <td class="hide-sm small text-2"><?= e(list_label('distributions', $r['distribution'])) ?></td>
        <td class="num"><?= (int) $r['line_count'] ?></td>
        <td class="num" style="font-weight:600"><?= e(money($r['line_total'])) ?></td>
        <td><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('plan_statuses', $r['status'])) ?></span><?php if ((int) $r['charge_count'] > 0): ?><div class="sub-cell"><?= (int) $r['charge_count'] ?> borç kaydı</div><?php endif; ?></td>
        <td class="hide-sm small text-muted"><?= $r['last_generated_period'] ? e(tr_period($r['last_generated_period'])) . ' işlendi' : ($r['approved_at'] ? 'Onay ' . e(tr_date(substr((string) $r['approved_at'], 0, 10))) : 'Taslak ' . e(tr_date(substr((string) $r['created_at'], 0, 10)))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="8"><?= $this->partial('partials.empty', ['icon' => 'journal-text', 'title' => 'Plan bulunamadı', 'text' => $f['durum'] !== '' ? 'Bu durumda plan yok.' : 'Aylık aidat, yakıt payı veya demirbaş katkısı için ilk planı oluşturun.', 'action' => can('charges.manage') && $f['durum'] === '' ? '<a class="btn btn-primary" href="' . e(route('plans.create')) . '"><i class="bi bi-plus-lg"></i>Yeni plan</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
