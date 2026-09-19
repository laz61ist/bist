<?php
$u = $ctx['unit'];
$liabilityText = match ((string) $u['liability_mode']) {
    'kiraci' => 'Bu bölümde aidat ve giderler kiracı tarafından ödenir; demirbaş ve yenileme gibi kalıcı yatırımlar malike aittir.',
    'paylasimli' => 'Bu bölümde borçlar malik ve kiracı arasında paylaşımlıdır; her kalemde kimin ödeyeceği ayrıca belirtilir.',
    default => 'Bu bölümde tüm aidat ve giderler malik tarafından ödenir.',
};
$roleNote = $ctx['role'] === 'kiraci' ? 'Siz kiracı olarak görünüyorsunuz; "Malik öder" işaretli kalemler malikinizin sorumluluğundadır.' : 'Siz malik olarak görünüyorsunuz.';
?>
<?= $this->partial('partials.page-head', ['title' => 'Borçlarım', 'desc' => $u['label'] . ' · her borç kalemi, vadesi ve ödeme durumu', 'actions' => '<a class="btn" href="' . e(route('portal.statement')) . '"><i class="bi bi-journal-text"></i>Ekstre</a>']) ?>

<div class="stat-row mb-4">
  <div class="balance-box <?= $balance['debt'] > 0 ? 'bad' : 'ok' ?>"><div><div class="l">Toplam açık borç</div><div class="v"><?= e(money($balance['debt'])) ?></div></div></div>
  <div class="stat tone-bad"><div class="k">Vadesi geçmiş</div><div class="v"><?= e(money($balance['overdue'], false)) ?> <small>₺</small></div><div class="d">Bugün itibarıyla</div></div>
  <div class="stat tone-ok"><div class="k">Avans</div><div class="v"><?= e(money($balance['advance'], false)) ?> <small>₺</small></div><div class="d">Sonraki borçtan düşülür</div></div>
</div>

<div class="alert info mb-4"><i class="bi bi-info-circle"></i><div><span class="t">Kim öder?</span> <?= e($liabilityText) ?> <?= e($roleNote) ?><?php if ($lateRule): ?> Vadesi geçen borçlara <?= $lateRule['type'] === 'gunluk' ? 'günlük' : 'aylık' ?> %<?= e((string) $lateRule['rate']) ?> gecikme tazminatı uygulanır<?= $lateRule['grace'] > 0 ? ' (' . (int) $lateRule['grace'] . ' gün hoşgörü süresi vardır)' : '' ?>; bu kalemler ilgili borcun altında girintili gösterilir.<?php endif; ?></div></div>

<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <select class="select" name="durum" aria-label="Durum">
      <option value="tumu" <?= $filters['durum'] === 'tumu' ? 'selected' : '' ?>>Tüm kalemler</option>
      <option value="acik" <?= $filters['durum'] === 'acik' ? 'selected' : '' ?>>Yalnızca açık borçlar</option>
      <option value="odenen" <?= $filters['durum'] === 'odenen' ? 'selected' : '' ?>>Yalnızca ödenenler</option>
    </select>
    <select class="select" name="donem" aria-label="Dönem">
      <option value="">Tüm dönemler</option>
      <?php foreach ($periods as $pp): ?><option value="<?= e($pp) ?>" <?= $filters['donem'] === $pp ? 'selected' : '' ?>><?= e(tr_period($pp)) ?></option><?php endforeach; ?>
    </select>
    <span class="spacer"></span>
    <span class="small text-muted"><?= count($rows) ?> kalem</span>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Borç</th><th class="hide-sm">Vade</th><th>Durum</th><th class="num">Tutar</th><th class="num">Kalan</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $remaining = (int) $r['amount'] - (int) $r['paid_amount']; $isOverdue = in_array($r['status'], ['odenmedi', 'kismi'], true) && $r['due_date'] < $today; ?>
      <tr class="<?= $r['is_child'] ? 'is-muted' : '' ?>">
        <td<?= $r['is_child'] ? ' style="padding-left:34px"' : '' ?>>
          <div class="primary-cell"><?= $r['is_child'] ? '<i class="bi bi-arrow-return-right text-muted"></i> ' : '' ?><?= e($r['title']) ?></div>
          <div class="sub-cell"><?= e(list_label('charge_types', $r['charge_type'])) ?> · <?= e(tr_period($r['period'])) ?> · <?= e(list_label('liability_modes', $r['liability'])) ?></div>
        </td>
        <td class="small hide-sm <?= $isOverdue ? 'text-bad' : '' ?>"><?= e(tr_date($r['due_date'])) ?><?= $isOverdue ? ' <i class="bi bi-exclamation-circle" title="Vadesi geçti"></i>' : '' ?></td>
        <td><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('charge_statuses', $r['status'])) ?></span><?php if ($isOverdue): ?><div class="sub-cell text-bad" style="white-space:nowrap">Vade <?= e(tr_date($r['due_date'])) ?></div><?php endif; ?></td>
        <td class="num"><?= e(money($r['amount'])) ?></td>
        <td class="num" style="font-weight:600"><?= $remaining > 0 ? e(money($remaining)) : '<span class="text-muted">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="5"><?= $this->partial('partials.empty', ['icon' => 'receipt', 'title' => 'Bu seçimde borç kalemi yok', 'text' => $filters['durum'] === 'acik' ? 'Açık borcunuz bulunmuyor.' : 'Filtreyi değiştirerek diğer dönemlere bakabilirsiniz.']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?>
    <tfoot>
      <tr><td colspan="3">Toplam tahakkuk<span class="hide-sm"> (seçili kalemler)</span></td><td class="num"><?= e(money($totals['amount'])) ?></td><td class="num <?= $totals['open'] > 0 ? 'text-bad' : 'text-ok' ?>"><?= e(money($totals['open'])) ?></td></tr>
    </tfoot>
    <?php endif; ?>
  </table></div>
  <?php if ($rows !== []): ?><div class="card-foot small text-muted">Ödenen <?= e(money($totals['paid'])) ?><?= $totals['overdue'] > 0 ? ' · vadesi geçmiş ' . e(money($totals['overdue'])) : '' ?> · Ödemeler en eski vadeli borçtan başlayarak kapatılır.</div><?php endif; ?>
</div>
