<?php $f = $filters; $hasFilter = $f['q'] !== '' || $f['kategori'] || $f['tedarikci'] || $f['durum'] !== '' || $f['bas'] !== '' || $f['bit'] !== '' || $f['donem'] !== '';
$actions = '';
if (can('reports.export')) { $actions .= '<a class="btn" href="' . e(query_with(['format' => 'csv', 'sayfa' => null])) . '"><i class="bi bi-filetype-csv"></i>CSV</a>'; if ($xlsx) { $actions .= '<a class="btn" href="' . e(query_with(['format' => 'xlsx', 'sayfa' => null])) . '"><i class="bi bi-file-earmark-spreadsheet"></i>Excel</a>'; } }
if (can('expenses.manage')) { $actions .= '<a class="btn btn-primary" href="' . e(route('expenses.create')) . '"><i class="bi bi-plus-lg"></i>Yeni gider</a>'; }
?>
<?= $this->partial('partials.page-head', ['title' => 'Giderler', 'desc' => number_tr((int) $totals['n']) . ' kayıt · toplam ' . money($totals['amount']) . ' · açık ' . money($totals['open']), 'crumbs' => [['Giderler']], 'actions' => $actions]) ?>
<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Açıklama, belge no veya tedarikçi…">
    <select class="select" name="kategori"><option value="">Tüm kategoriler</option><?php foreach ($categories as $group => $items): ?><optgroup label="<?= e($group) ?>"><?php foreach ($items as $id => $n): ?><option value="<?= $id ?>" <?= $f['kategori'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
    <select class="select" name="tedarikci"><option value="">Tüm tedarikçiler</option><?php foreach ($vendors as $id => $n): ?><option value="<?= $id ?>" <?= $f['tedarikci'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($expense_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <input class="input" type="month" name="donem" value="<?= e($f['donem']) ?>" title="Dönem">
    <input class="input" type="date" name="bas" value="<?= e($f['bas']) ?>" title="Başlangıç tarihi">
    <input class="input" type="date" name="bit" value="<?= e($f['bit']) ?>" title="Bitiş tarihi">
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('expenses.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <?php if ($byParent !== []): ?>
  <div class="flex wrap gap-2 center" style="padding:10px 16px;border-bottom:1px solid var(--rule-soft);background:var(--surface-2)">
    <span class="small text-muted"><i class="bi bi-pie-chart"></i> <?= e(tr_date($sumFrom)) ?> – <?= e(tr_date($sumTo)) ?> kategori özeti:</span>
    <?php foreach ($byParent as $name => $total): ?><a class="tag" href="<?= e(route('categories.index')) ?>" title="<?= e($name) ?>"><?= e(\Aidat\Core\Str::limit((string) $name, 24)) ?> <strong class="num"><?= e(money($total)) ?></strong></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tarih</th><th>Gider</th><th class="hide-sm">Tedarikçi</th><th class="hide-sm">Belge</th><th>Durum</th><th class="num">Tutar</th><th class="num hide-sm">Ödenen</th><th class="num">Kalan</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $open = (int) $r['amount'] - (int) $r['paid_amount']; $late = $r['status'] !== 'odendi' && $r['status'] !== 'iptal' && $r['due_date'] && $r['due_date'] < date('Y-m-d'); ?>
      <tr data-href="<?= e(route('expenses.show', ['id' => $r['id']])) ?>" class="<?= $r['status'] === 'iptal' ? 'is-cancelled' : '' ?>">
        <td class="small nowrap"><?= e(tr_date($r['expense_date'])) ?><div class="sub-cell"><?= e(tr_period($r['period'])) ?></div></td>
        <td><div class="primary-cell"><?= e(\Aidat\Core\Str::limit((string) ($r['description'] ?: $r['category_name'] ?: 'Gider'), 48)) ?></div><div class="sub-cell"><?= e($r['parent_name'] && $r['parent_name'] !== $r['category_name'] ? $r['parent_name'] . ' › ' : '') ?><?= e($r['category_name'] ?: 'Kategorisiz') ?><?= $r['block_name'] ? ' · ' . e($r['block_name']) : '' ?></div></td>
        <td class="hide-sm"><?= $r['vendor_name'] ? '<a href="' . e(route('vendors.show', ['id' => $r['vendor_id']])) . '">' . e($r['vendor_name']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
        <td class="hide-sm small text-2"><?= e(list_label('document_kinds', $r['document_kind'])) ?><?= $r['document_no'] ? ' <span class="mono">' . e($r['document_no']) . '</span>' : '' ?><?= $r['document_id'] ? ' <i class="bi bi-paperclip" title="Belge ekli"></i>' : '' ?></td>
        <td><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('expense_statuses', $r['status'])) ?></span><?php if ($late): ?><div class="sub-cell text-bad">vade <?= e(tr_date($r['due_date'])) ?></div><?php endif; ?></td>
        <td class="num"><?= e(money($r['amount'])) ?><?php if ((int) $r['vat_amount'] > 0): ?><div class="sub-cell">KDV <?= e(money($r['vat_amount'])) ?></div><?php endif; ?></td>
        <td class="num hide-sm text-muted"><?= e(money($r['paid_amount'])) ?></td>
        <td class="num <?= $open > 0 && $r['status'] !== 'iptal' ? '' : 'text-muted' ?>" style="font-weight:600"><?= e(money($r['status'] === 'iptal' ? 0 : $open)) ?></td>
        <td><div class="row-actions"><?php if (can('expenses.manage') && $r['status'] !== 'iptal' && $r['status'] !== 'odendi'): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('expenses.show', ['id' => $r['id']]) . '#odeme') ?>" title="Ödeme yap"><i class="bi bi-cash-coin"></i></a><?php endif; ?><?php if (can('expenses.manage') && $r['status'] !== 'iptal'): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('expenses.edit', ['id' => $r['id']])) ?>" title="Düzenle"><i class="bi bi-pencil"></i></a><?php endif; ?></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="9"><?= $this->partial('partials.empty', ['icon' => 'cart-dash', 'title' => 'Gider bulunamadı', 'text' => $hasFilter ? 'Filtreyi değiştirin veya temizleyin.' : 'Fatura, fiş ve makbuzları girerek gider defterini oluşturun; periyodik giderler otomatik üretilebilir.', 'action' => can('expenses.manage') && !$hasFilter ? '<a class="btn btn-primary" href="' . e(route('expenses.create')) . '"><i class="bi bi-plus-lg"></i>İlk gideri ekle</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?><tfoot><tr><td colspan="5">Toplam (filtre, iptaller hariç)<?php if ((int) $totals['vat'] > 0): ?> <span class="small text-muted" style="font-weight:400">· KDV <?= e(money($totals['vat'])) ?></span><?php endif; ?></td><td class="num"><?= e(money($totals['amount'])) ?></td><td class="num hide-sm"><?= e(money($totals['paid'])) ?></td><td class="num"><?= e(money($totals['open'])) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
