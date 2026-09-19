<?php $f = $filters; $hasFilter = $f['q'] !== '' || $f['bolum'] || $f['hesap'] || $f['yontem'] !== '' || $f['durum'] !== '' || $f['bas'] !== '' || $f['bit'] !== '';
$actions = '';
if (can('payments.create')) { $actions .= '<a class="btn btn-primary" href="' . e(route('payments.create')) . '"><i class="bi bi-plus-lg"></i>Tahsilat gir</a>'; }
$actions .= '<a class="btn" href="' . e(route('receipts.index')) . '"><i class="bi bi-file-earmark-check"></i>Makbuz defteri</a>';
$actions .= '<div class="dropdown" x-data="dropdown" @click.outside="close()"><button type="button" class="btn" @click="toggle()"><i class="bi bi-download"></i>Dışa aktar</button><div class="menu" x-show="open" x-cloak><a href="' . e(query_with(['format' => 'csv', 'sayfa' => null])) . '"><i class="bi bi-filetype-csv"></i>CSV</a>' . ($xlsx ? '<a href="' . e(query_with(['format' => 'xlsx', 'sayfa' => null])) . '"><i class="bi bi-file-earmark-spreadsheet"></i>Excel (XLSX)</a>' : '') . '</div></div>';
?>
<?= $this->partial('partials.page-head', ['title' => 'Tahsilatlar', 'desc' => number_tr($summary['valid_count']) . ' geçerli · ' . number_tr($summary['cancelled_count']) . ' iptal/iade · toplam ' . money($summary['valid_total']), 'actions' => $actions]) ?>

<div class="stat-row mb-4">
  <div class="stat tone-ok"><div class="k"><i class="bi bi-cash-coin"></i>Geçerli tahsilat</div><div class="v"><?= e(money($summary['valid_total'], false)) ?> <small>₺</small></div><div class="d"><?= number_tr($summary['valid_count']) ?> makbuz<?= $hasFilter ? ' · filtreli' : '' ?></div></div>
  <div class="stat"><div class="k"><i class="bi bi-piggy-bank"></i>Avansa alınan</div><div class="v"><?= e(money($summary['advance_total'], false)) ?> <small>₺</small></div><div class="d">Dağıtılmamış tutar</div></div>
  <div class="stat tone-bad"><div class="k"><i class="bi bi-x-circle"></i>İptal / iade</div><div class="v"><?= number_tr($summary['cancelled_count']) ?></div><div class="d">Makbuz numaraları korunur</div></div>
</div>

<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Makbuz no, referans veya kapı no…">
    <select class="select" name="bolum"><option value="">Tüm bölümler</option><?php foreach ($units as $grp => $opts): ?><optgroup label="<?= e($grp) ?>"><?php foreach ($opts as $id => $n): ?><option value="<?= $id ?>" <?= $f['bolum'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
    <select class="select" name="hesap"><option value="">Tüm hesaplar</option><?php foreach ($accounts as $id => $n): ?><option value="<?= $id ?>" <?= $f['hesap'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="select" name="yontem"><option value="">Tüm yöntemler</option><?php foreach ($payment_methods as $k => $v): ?><option value="<?= $k ?>" <?= $f['yontem'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($payment_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <input type="date" class="input" name="bas" value="<?= e($f['bas']) ?>" title="Başlangıç">
    <input type="date" class="input" name="bit" value="<?= e($f['bit']) ?>" title="Bitiş">
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('payments.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Makbuz</th><th>Tarih</th><th>Bölüm / ödeyen</th><th class="hide-sm">Yöntem · hesap</th><th class="hide-sm">Referans</th><th>Durum</th><th class="num">Tutar</th><th class="num hide-sm">Avans</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr data-href="<?= e(route('payments.show', ['id' => $r['id']])) ?>" class="<?= $r['status'] !== 'gecerli' ? 'is-cancelled' : '' ?>">
        <td><div class="mono primary-cell"><?= e($r['receipt_no']) ?></div><div class="sub-cell"><?= e(list_label('allocation_modes', $r['allocation_mode'])) ?></div></td>
        <td class="small"><?= e(tr_date($r['payment_date'])) ?><?= $r['payment_time'] ? '<span class="text-muted"> ' . e($r['payment_time']) . '</span>' : '' ?></td>
        <td><div class="primary-cell"><?= $r['door_no'] ? e(($r['block_name'] ? $r['block_name'] . ' · ' : '') . 'No ' . $r['door_no']) : '<span class="text-muted">Bölümsüz (avans)</span>' ?></div><div class="sub-cell"><?= e(trim((string) $r['payer_name']) ?: '—') ?></div></td>
        <td class="hide-sm small"><?= e(list_label('payment_methods', $r['method'])) ?><div class="sub-cell"><?= e($r['account_name']) ?></div></td>
        <td class="hide-sm small mono"><?= e($r['reference_no'] ?: '—') ?></td>
        <td><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('payment_statuses', $r['status'])) ?></span></td>
        <td class="num" style="font-weight:600"><?= e(money($r['amount'])) ?></td>
        <td class="num hide-sm <?= (int) $r['unallocated_amount'] > 0 ? '' : 'text-muted' ?>"><?= e(money($r['unallocated_amount'])) ?></td>
        <td><div class="row-actions"><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="<?= e(route('payments.receipt', ['id' => $r['id']])) ?>" title="Makbuz"><i class="bi bi-printer"></i></a><?php if ($r['unit_id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('units.show', ['id' => $r['unit_id']])) ?>" title="Bölüm"><i class="bi bi-door-open"></i></a><?php endif; ?></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="9"><?= $this->partial('partials.empty', ['icon' => 'cash-coin', 'title' => 'Tahsilat bulunamadı', 'text' => $hasFilter ? 'Filtreyi değiştirin veya temizleyin.' : 'İlk tahsilatı girdiğinizde makbuz otomatik numaralanır.', 'action' => can('payments.create') && !$hasFilter ? '<a class="btn btn-primary" href="' . e(route('payments.create')) . '"><i class="bi bi-plus-lg"></i>Tahsilat gir</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?><tfoot><tr><td colspan="6">Sayfa toplamı (geçerli)<span class="text-muted small"> · tüm sonuçlar: <?= e(money($summary['valid_total'])) ?></span></td><td class="num"><?= e(money($summary['page_valid'])) ?></td><td class="hide-sm"></td><td></td></tr></tfoot><?php endif; ?>
  </table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
