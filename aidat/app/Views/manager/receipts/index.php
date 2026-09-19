<?php $f = $filters; $hasFilter = $f['durum'] !== '' || $f['q'] !== '';
$actions = '<div class="dropdown" x-data="dropdown" @click.outside="close()"><button type="button" class="btn" @click="toggle()"><i class="bi bi-download"></i>Dışa aktar</button><div class="menu" x-show="open" x-cloak><a href="' . e(query_with(['format' => 'csv', 'sayfa' => null])) . '"><i class="bi bi-filetype-csv"></i>CSV</a>' . ($xlsx ? '<a href="' . e(query_with(['format' => 'xlsx', 'sayfa' => null])) . '"><i class="bi bi-file-earmark-spreadsheet"></i>Excel (XLSX)</a>' : '') . '</div></div>';
$actions .= '<a class="btn" href="' . e(route('payments.index')) . '"><i class="bi bi-cash-coin"></i>Tahsilatlar</a>';
$seriesLabel = $prefix . ($resetYearly && $f['yil'] > 0 ? '-' . $f['yil'] : '') . ' serisi';
?>
<?= $this->partial('partials.page-head', ['title' => 'Makbuz defteri', 'crumbs' => [['Tahsilatlar', route('payments.index')], ['Makbuz defteri']], 'desc' => $seriesLabel . ' · sıra numarasına göre kesintisiz döküm. İptal/iade edilen makbuzlar numarasıyla birlikte defterde kalır; atlanan numaralar işaretlenir.', 'actions' => $actions]) ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k"><i class="bi bi-file-earmark-check"></i>Makbuz sayısı</div><div class="v"><?= number_tr($summary['count']) ?></div><div class="d"><?= $summary['count'] > 0 ? 'Sıra ' . number_tr($summary['first_seq']) . ' – ' . number_tr($summary['last_seq']) : 'Kayıt yok' ?></div></div>
  <div class="stat tone-ok"><div class="k"><i class="bi bi-cash-coin"></i>Geçerli toplam</div><div class="v"><?= e(money($summary['valid_total'], false)) ?> <small>₺</small></div><div class="d"><?= number_tr($summary['valid_count']) ?> makbuz</div></div>
  <div class="stat tone-bad"><div class="k"><i class="bi bi-x-circle"></i>İptal / iade</div><div class="v"><?= e(money($summary['cancelled_total'], false)) ?> <small>₺</small></div><div class="d"><?= number_tr($summary['cancelled_count']) ?> makbuz · numaralar korunur</div></div>
  <?php if ($summary['gaps'] !== null): ?><div class="stat <?= $summary['gaps'] > 0 ? 'tone-warn' : 'tone-ok' ?>"><div class="k"><i class="bi bi-skip-forward"></i>Atlanan numara</div><div class="v"><?= number_tr($summary['gaps']) ?></div><div class="d"><?= $summary['gaps'] > 0 ? 'Seride boşluk var; satırlarda işaretli' : 'Seri kesintisiz' ?></div></div><?php endif; ?>
</div>

<?php if (($summary['gaps'] ?? 0) > 0): ?>
<div class="alert warn mb-4"><i class="bi bi-exclamation-triangle"></i><div><span class="t">Seride <?= number_tr($summary['gaps']) ?> numara eksik.</span> Makbuz kayıtları silinmediğinden boşluk normalde oluşmaz; veri aktarımı veya elle müdahale kaynaklı olabilir. Eksik numaralar aşağıda sarı bantla gösterilir; denetim için işlem izini inceleyin.</div></div>
<?php endif; ?>

<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Makbuz no veya kapı no…">
    <select class="select" name="yil"><?php if ($years === []): ?><option value="">Yıl</option><?php endif; ?><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $f['yil'] === $y ? 'selected' : '' ?>><?= $y > 0 ? $y : 'Tek seri (yıllık sıfırlama yok)' ?></option><?php endforeach; ?><?php if (count($years) > 1): ?><option value="-1" <?= $f['yil'] === -1 ? 'selected' : '' ?>>Tüm yıllar</option><?php endif; ?></select>
    <select class="select" name="durum"><option value="">Tüm durumlar</option><?php foreach ($payment_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <span class="spacer"></span><span class="count"><?= number_tr($summary['count']) ?> kayıt</span>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('receipts.index', ['yil' => $f['yil']])) ?>">Temizle</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th class="num">Sıra</th><th>Makbuz no</th><th>Tarih</th><th>Bölüm / ödeyen</th><th class="hide-sm">Yöntem · hesap</th><th class="hide-sm">Tahsil eden</th><th>Durum</th><th class="num">Tutar</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php if ($r['gap'] > 0): ?>
        <tr class="is-muted" style="background:var(--amber-bg)"><td class="num text-warn"><i class="bi bi-skip-forward"></i></td><td colspan="8" class="small text-warn"><strong><?= $r['gap'] === 1 ? 'Sıra ' . number_tr($r['gap_from']) : 'Sıra ' . number_tr($r['gap_from']) . ' – ' . number_tr($r['gap_to']) ?></strong> atlanmış (<?= number_tr($r['gap']) ?> numara eksik)</td></tr>
      <?php endif; ?>
      <tr data-href="<?= e(route('payments.show', ['id' => $r['id']])) ?>" class="<?= $r['status'] !== 'gecerli' ? 'is-cancelled' : '' ?>">
        <td class="num text-muted"><?= number_tr($r['receipt_seq']) ?></td>
        <td class="mono" style="font-weight:600"><?= e($r['receipt_no']) ?></td>
        <td class="small"><?= e(tr_date($r['payment_date'])) ?><?= $r['payment_time'] ? '<span class="text-muted"> ' . e($r['payment_time']) . '</span>' : '' ?></td>
        <td><div class="primary-cell"><?= $r['door_no'] ? e(($r['block_name'] ? $r['block_name'] . ' · ' : '') . 'No ' . $r['door_no']) : '<span class="text-muted">Bölümsüz</span>' ?></div><div class="sub-cell"><?= e(trim((string) $r['payer_name']) ?: '—') ?></div></td>
        <td class="hide-sm small"><?= e(list_label('payment_methods', $r['method'])) ?><div class="sub-cell"><?= e($r['account_name']) ?></div></td>
        <td class="hide-sm small"><?= e($r['collector_name'] ?: 'sistem') ?></td>
        <td><span class="pill <?= status_tone($r['status']) ?>" title="<?= e($r['cancel_reason'] ?? '') ?>"><?= e(list_label('payment_statuses', $r['status'])) ?></span></td>
        <td class="num" style="font-weight:600"><?= e(money($r['amount'])) ?></td>
        <td><div class="row-actions"><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="<?= e(route('payments.receipt', ['id' => $r['id']])) ?>" title="Makbuz"><i class="bi bi-printer"></i></a></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="9"><?= $this->partial('partials.empty', ['icon' => 'file-earmark-check', 'title' => 'Makbuz yok', 'text' => $hasFilter ? 'Filtreyi değiştirin.' : 'Tahsilat girildiğinde makbuz numarası burada sıralı görünür.']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?><tfoot><tr><td colspan="7">Geçerli toplam (tüm sonuçlar)</td><td class="num"><?= e(money($summary['valid_total'])) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
