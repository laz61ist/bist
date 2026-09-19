<?php $f = $filters; ?>
<?= $this->partial('partials.page-head', ['title' => 'Bağımsız bölümler', 'desc' => $summary['total'] . ' bölüm · ' . $summary['occupied'] . ' dolu · ' . $summary['empty'] . ' boş', 'actions' => can('units.manage') ? '<a class="btn" href="' . e(route('units.bulk')) . '"><i class="bi bi-magic"></i>Toplu oluştur</a><a class="btn btn-primary" href="' . e(route('units.create')) . '"><i class="bi bi-plus-lg"></i>Yeni bölüm</a>' : '']) ?>
<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Kapı no veya sakin adı…">
    <select class="select" name="blok"><option value="">Tüm bloklar</option><?php foreach ($blocks as $id => $n): ?><option value="<?= $id ?>" <?= $f['blok'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($unit_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <label class="check" style="padding:0"><input type="checkbox" name="borclu" value="1" <?= $f['borclu'] ? 'checked' : '' ?> onchange="this.form.submit()"><span>Yalnızca borçlu</span></label>
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($f['q'] !== '' || $f['blok'] || $f['durum'] !== '' || $f['borclu']): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('units.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bölüm</th><th>Malik</th><th>Kiracı</th><th class="hide-sm">Tür / m² / arsa payı</th><th>Durum</th><th class="num">Açık borç</th><th class="num">Vadesi geçmiş</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr data-href="<?= e(route('units.show', ['id' => $u['id']])) ?>">
        <td><div class="primary-cell"><?= e(($u['block_name'] ? $u['block_name'] . ' · ' : '') . 'No ' . $u['door_no']) ?></div><div class="sub-cell"><?= $u['floor'] !== null && $u['floor'] !== '' ? 'Kat ' . e($u['floor']) : '' ?><?= $u['fee_group_name'] ? ' · ' . e($u['fee_group_name']) : '' ?></div></td>
        <td><?= $u['owner_name'] ? e(trim($u['owner_name'])) : '<span class="text-muted">—</span>' ?></td>
        <td><?= $u['tenant_name'] ? e(trim($u['tenant_name'])) : '<span class="text-muted">—</span>' ?></td>
        <td class="hide-sm small text-2"><?= e(list_label('unit_types', $u['type'])) ?><?= $u['gross_m2'] ? ' · ' . number_tr((float) $u['gross_m2']) . ' m²' : '' ?><?= $u['land_share'] ? ' · ' . number_tr((float) $u['land_share'], 2) : '' ?></td>
        <td><span class="pill <?= status_tone($u['status']) ?>"><?= e(list_label('unit_statuses', $u['status'])) ?></span></td>
        <td class="num <?= $u['open_debt'] > 0 ? '' : 'text-muted' ?>"><?= e(money($u['open_debt'])) ?></td>
        <td class="num <?= $u['overdue'] > 0 ? 'text-bad' : 'text-muted' ?>"><?= e(money($u['overdue'])) ?></td>
        <td><div class="row-actions"><?php if (can('payments.create')): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('payments.create', ['bolum' => $u['id']])) ?>" title="Tahsilat gir"><i class="bi bi-cash-coin"></i></a><?php endif; ?><a class="btn btn-sm btn-ghost" href="<?= e(route('units.statement', ['id' => $u['id']])) ?>" title="Ekstre"><i class="bi bi-journal-text"></i></a></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="8"><?= $this->partial('partials.empty', ['icon' => 'door-open', 'title' => 'Bölüm bulunamadı', 'text' => $f['q'] !== '' || $f['blok'] || $f['durum'] !== '' ? 'Filtreyi değiştirin.' : 'Toplu oluşturma sihirbazıyla A Blok 1–20 gibi aralıkları tek adımda ekleyin.', 'action' => can('units.manage') && $summary['total'] === 0 ? '<a class="btn btn-primary" href="' . e(route('units.bulk')) . '"><i class="bi bi-magic"></i>Toplu oluştur</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
