<?php $f = $filters; $hasFilter = $f['q'] !== '' || $f['kategori'] || $f['durum'] !== '' || $f['bas'] !== '' || $f['bit'] !== '';
$actions = '';
if (can('reports.export')) { $actions .= '<a class="btn" href="' . e(query_with(['format' => 'csv', 'sayfa' => null])) . '"><i class="bi bi-filetype-csv"></i>CSV</a>'; if ($xlsx) { $actions .= '<a class="btn" href="' . e(query_with(['format' => 'xlsx', 'sayfa' => null])) . '"><i class="bi bi-file-earmark-spreadsheet"></i>Excel</a>'; } }
if (can('incomes.manage')) { $actions .= '<a class="btn btn-primary" href="' . e(route('incomes.create')) . '"><i class="bi bi-plus-lg"></i>Yeni gelir</a>'; }
?>
<div x-data="reasonModal">
<?= $this->partial('partials.page-head', ['title' => 'Diğer gelirler', 'desc' => 'Aidat dışı gelirler: kira, reklam, faiz, bağış · ' . number_tr((int) $totals['n']) . ' kayıt · geçerli toplam ' . money($totals['amount']), 'crumbs' => [['Diğer gelirler']], 'actions' => $actions]) ?>
<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Açıklama veya belge no…">
    <select class="select" name="kategori"><option value="">Tüm kategoriler</option><?php foreach ($categories as $id => $n): ?><option value="<?= $id ?>" <?= $f['kategori'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <input class="input" type="date" name="bas" value="<?= e($f['bas']) ?>" title="Başlangıç"><input class="input" type="date" name="bit" value="<?= e($f['bit']) ?>" title="Bitiş">
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('incomes.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <?php if ($byCategory !== []): ?>
  <div class="flex wrap gap-2 center" style="padding:10px 16px;border-bottom:1px solid var(--rule-soft);background:var(--surface-2)">
    <span class="small text-muted"><i class="bi bi-pie-chart"></i> <?= e(tr_date($sumFrom)) ?> – <?= e(tr_date($sumTo)) ?>:</span>
    <?php foreach ($byCategory as $c): ?><span class="tag"><?= e($c['name']) ?> <strong class="num"><?= e(money($c['total'])) ?></strong></span><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tarih</th><th>Gelir</th><th class="hide-sm">Kategori</th><th class="hide-sm">Hesap</th><th>Durum</th><th class="num">Tutar</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['status'] !== 'gecerli' ? 'is-cancelled' : '' ?>">
        <td class="small nowrap"><?= e(tr_date($r['income_date'])) ?><div class="sub-cell"><?= e(tr_period($r['period'])) ?></div></td>
        <td><div class="primary-cell"><?= e(\Aidat\Core\Str::limit((string) ($r['description'] ?: $r['category_name'] ?: 'Gelir'), 60)) ?></div><div class="sub-cell"><?= $r['document_no'] ? '<span class="mono">' . e($r['document_no']) . '</span>' : '' ?><?= $r['status'] === 'iptal' && $r['cancel_reason'] ? ' İptal: ' . e($r['cancel_reason']) : '' ?></div></td>
        <td class="hide-sm"><?= e($r['category_name'] ?: '—') ?></td>
        <td class="hide-sm small"><i class="bi bi-<?= $r['account_type'] === 'banka' ? 'bank' : 'cash-stack' ?> text-muted"></i> <?= e($r['account_name'] ?: '—') ?></td>
        <td><span class="pill <?= status_tone($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
        <td class="num" style="font-weight:600"><?= e(money($r['amount'])) ?></td>
        <td><?php if (can('incomes.manage') && $r['status'] === 'gecerli'): ?><div class="row-actions"><button type="button" class="btn btn-sm btn-ghost text-bad" title="İptal (ters kayıt)" @click="ask('<?= e(route('incomes.cancel', ['id' => $r['id']])) ?>', 'Geliri iptal et', 'İptal gerekçesi')"><i class="bi bi-x-circle"></i></button></div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="7"><?= $this->partial('partials.empty', ['icon' => 'cart-plus', 'title' => 'Gelir kaydı yok', 'text' => $hasFilter ? 'Filtreyi değiştirin.' : 'Kira, reklam panosu, baz istasyonu veya faiz gelirlerini buradan kaydedin; aidat tahsilatları Tahsilat modülünde tutulur.', 'action' => can('incomes.manage') && !$hasFilter ? '<a class="btn btn-primary" href="' . e(route('incomes.create')) . '"><i class="bi bi-plus-lg"></i>İlk geliri ekle</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?><tfoot><tr><td colspan="5">Geçerli toplam (filtre)<?php if ((int) $totals['cancelled'] > 0): ?> <span class="small text-muted" style="font-weight:400">· iptal <?= e(money($totals['cancelled'])) ?></span><?php endif; ?></td><td class="num"><?= e(money($totals['amount'])) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
<?= $this->partial('partials.reason-modal') ?>
</div>
