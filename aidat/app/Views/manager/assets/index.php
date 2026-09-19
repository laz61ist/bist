<?php $f = $filters; $hasFilter = $f['durum'] !== '' || $f['q'] !== ''; ?>
<?= $this->partial('partials.page-head', ['title' => 'Demirbaş', 'desc' => $summary['count'] . ' kayıtlı demirbaş · toplam maliyet ' . money($summary['cost']), 'actions' => '<a class="btn btn-primary" href="' . e(route('assets.create')) . '"><i class="bi bi-plus-lg"></i>Yeni demirbaş</a>']) ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k">Demirbaş sayısı</div><div class="v"><?= $summary['count'] ?></div><div class="d">Hurdaya ayrılanlar hariç</div></div>
  <div class="stat"><div class="k">Toplam maliyet</div><div class="v"><?= e(money($summary['cost'], false)) ?> <small>₺</small></div><div class="d">Alım bedelleri toplamı</div></div>
  <div class="stat <?= $summary['faulty'] > 0 ? 'tone-warn' : '' ?>"><div class="k">Arızalı / bakımda</div><div class="v"><?= $summary['faulty'] ?></div><div class="d"><?php foreach (['arizali', 'bakimda'] as $s): if (!empty($statusCounts[$s])): ?><a class="pill <?= status_tone($s) ?>" href="<?= e(route('assets.index', ['durum' => $s])) ?>"><?= e(list_label('asset_statuses', $s)) ?> <?= (int) $statusCounts[$s] ?></a><?php endif; endforeach; ?></div></div>
  <div class="stat <?= $summary['warranty_soon'] > 0 ? 'tone-warn' : '' ?>"><div class="k">Garantisi bitiyor</div><div class="v"><?= $summary['warranty_soon'] ?></div><div class="d">Önümüzdeki 60 gün içinde</div></div>
</div>

<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Ad, kategori, seri no, konum…">
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($asset_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('assets.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Demirbaş</th><th class="hide-sm">Konum</th><th>Alım</th><th>Garanti</th><th>Durum</th><th class="num">Maliyet</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): $w = $a['warranty_until']; $wTone = $w === null ? '' : ($w < $today ? 'neutral' : ($w <= $soon ? 'warn' : 'ok')); ?>
      <tr data-href="<?= e(route('assets.edit', ['id' => $a['id']])) ?>" class="<?= $a['status'] === 'hurda' ? 'is-muted' : '' ?>">
        <td><div class="primary-cell"><?= e($a['name']) ?></div><div class="sub-cell"><?= e($a['category'] ?: 'Kategorisiz') ?><?= $a['serial_no'] ? ' · <span class="mono">' . e($a['serial_no']) . '</span>' : '' ?><?= $a['vendor_name'] ? ' · ' . e($a['vendor_name']) : '' ?></div></td>
        <td class="hide-sm small"><?= e($a['location'] ?: '—') ?></td>
        <td class="small"><?= $a['purchase_date'] ? e(tr_date($a['purchase_date'])) : '—' ?><?= $a['expense_id'] ? '<div class="sub-cell"><a href="' . e(route('expenses.show', ['id' => $a['expense_id']])) . '">Gider #' . (int) $a['expense_id'] . '</a></div>' : '' ?></td>
        <td class="small"><?= $w === null ? '<span class="text-muted">—</span>' : '<span class="pill ' . $wTone . '">' . ($w < $today ? 'Bitti' : ($w <= $soon ? 'Bitiyor' : 'Garantili')) . ' · ' . e(tr_date($w)) . '</span>' ?></td>
        <td><span class="pill <?= status_tone($a['status']) ?>"><?= e(list_label('asset_statuses', $a['status'])) ?></span></td>
        <td class="num"><?= (int) $a['cost'] > 0 ? e(money($a['cost'])) : '<span class="text-muted">—</span>' ?></td>
        <td><div class="row-actions"><a class="btn btn-sm btn-ghost" href="<?= e(route('assets.edit', ['id' => $a['id']])) ?>" title="Düzenle"><i class="bi bi-pencil"></i></a></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="7"><?= $this->partial('partials.empty', ['icon' => 'box-seam', 'title' => 'Demirbaş bulunamadı', 'text' => $hasFilter ? 'Filtreyi değiştirin.' : 'Hidrofor, kamera sistemi, jeneratör gibi ortak alan ekipmanlarını garanti ve bakım takibi için kaydedin.', 'action' => !$hasFilter ? '<a class="btn btn-primary" href="' . e(route('assets.create')) . '"><i class="bi bi-plus-lg"></i>Yeni demirbaş</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?><tfoot><tr><td colspan="5">Listelenen toplam</td><td class="num"><?= e(money($listTotal)) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table></div>
</div>
