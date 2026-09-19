<?php $f = $filters; ?>
<?= $this->partial('partials.page-head', ['title' => 'Devir ve oturum geçmişi', 'desc' => 'Kim, hangi bölümde, hangi sıfatla, ne zamandan beri. Devir tutanakları aşağıdadır.', 'actions' => can('people.manage') ? '<a class="btn btn-primary" href="' . e(route('handovers.create')) . '"><i class="bi bi-arrow-left-right"></i>Devir yap</a>' : '']) ?>
<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Kapı no veya kişi…">
    <div class="btn-group"><?php foreach (['aktif' => 'Aktif', 'gecmis' => 'Geçmiş', 'tumu' => 'Tümü'] as $k => $v): ?><a class="btn btn-sm <?= $f['durum'] === $k ? 'is-on' : '' ?>" href="<?= e(query_with(['durum' => $k, 'sayfa' => null])) ?>"><?= $v ?></a><?php endforeach; ?></div>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bölüm</th><th>Kişi</th><th>Sıfat</th><th>Sorumluluk</th><th>Başlangıç</th><th>Bitiş</th></tr></thead>
    <tbody><?php foreach ($rows as $o): $active = $o['end_date'] === null || $o['end_date'] >= date('Y-m-d'); ?>
      <tr data-href="<?= e(route('units.show', ['id' => $o['unit_id']])) ?>" class="<?= $active ? '' : 'is-muted' ?>"><td class="primary-cell"><?= e(($o['block_name'] ? $o['block_name'] . ' · ' : '') . 'No ' . $o['door_no']) ?></td><td><?= e($o['company_name'] ?: trim($o['first_name'] . ' ' . $o['last_name'])) ?><?= $o['phone'] ? '<div class="sub-cell mono">' . e(\Aidat\Core\Str::formatPhone($o['phone'])) . '</div>' : '' ?></td><td><span class="pill <?= $o['role'] === 'malik' ? 'info' : 'neutral' ?> no-dot"><?= e(list_label('occupancy_roles', $o['role'])) ?></span></td><td class="small"><?= e(list_label('liability_modes', $o['liability'])) ?></td><td class="small"><?= e(tr_date($o['start_date'])) ?></td><td class="small"><?= $o['end_date'] ? e(tr_date($o['end_date'])) : '<span class="text-ok">Devam ediyor</span>' ?></td></tr>
    <?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="6" class="text-muted centered" style="padding:24px">Kayıt yok.</td></tr><?php endif; ?></tbody></table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
<div class="card mt-4">
  <div class="card-head"><h3><i class="bi bi-arrow-left-right"></i>Devir tutanakları</h3></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tarih</th><th>Bölüm</th><th>Eski taraf</th><th>Yeni taraf</th><th>Bakiye</th><th class="num">Devreden</th></tr></thead>
    <tbody><?php foreach ($handovers as $h): ?>
      <tr data-href="<?= e(route('handovers.show', ['id' => $h['id']])) ?>"><td><?= e(tr_date($h['handover_date'])) ?></td><td class="primary-cell">No <?= e($h['door_no']) ?></td><td><?= e($h['from_company'] ?: trim(($h['from_first'] ?? '') . ' ' . ($h['from_last'] ?? '')) ?: '—') ?></td><td><?= e($h['to_company'] ?: trim(($h['to_first'] ?? '') . ' ' . ($h['to_last'] ?? ''))) ?></td><td><span class="pill <?= $h['balance_mode'] === 'devreder' ? 'warn' : 'neutral' ?> no-dot"><?= $h['balance_mode'] === 'devreder' ? 'Yeni tarafa devretti' : 'Eski tarafta kaldı' ?></span></td><td class="num"><?= e(money($h['transferred_balance'])) ?></td></tr>
    <?php endforeach; ?><?php if ($handovers === []): ?><tr><td colspan="6" class="text-muted centered" style="padding:24px">Devir kaydı yok.</td></tr><?php endif; ?></tbody></table></div>
</div>
