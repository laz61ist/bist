<?php use Aidat\Core\Form;
$net = (int) $e['amount'] - (int) $e['vat_amount'];
$isCancelled = $e['status'] === 'iptal';
$payable = !$isCancelled && $remaining > 0;
$actions = '';
if (can('expenses.manage') && $payable) { $actions .= '<a class="btn btn-primary" href="#odeme"><i class="bi bi-cash-coin"></i>Ödeme yap</a>'; }
if (can('expenses.manage') && !$isCancelled) { $actions .= '<a class="btn" href="' . e(route('expenses.edit', ['id' => $e['id']])) . '"><i class="bi bi-pencil"></i>Düzenle</a>'; }
if (can('expenses.cancel') && !$isCancelled) { $actions .= '<button type="button" class="btn btn-ghost text-bad" @click="ask(\'' . e(route('expenses.cancel', ['id' => $e['id']])) . '\', \'Gideri iptal et\', \'İptal gerekçesi\')"><i class="bi bi-x-circle"></i>İptal (ters kayıt)</button>'; }
$title = (string) ($e['description'] ?: $e['category_name'] ?: 'Gider #' . $e['id']);
?>
<div x-data="reasonModal">
<?= $this->partial('partials.page-head', ['title' => \Aidat\Core\Str::limit($title, 70), 'crumbs' => [['Giderler', route('expenses.index')], ['#' . $e['id']]], 'desc' => tr_date($e['expense_date']) . ' · ' . tr_period($e['period']) . ' · ' . ($e['parent_name'] && $e['parent_name'] !== $e['category_name'] ? $e['parent_name'] . ' › ' : '') . ($e['category_name'] ?: 'Kategorisiz') . ($e['block_name'] ? ' · ' . $e['block_name'] : ' · tüm yapı'), 'actions' => $actions]) ?>

<?php if ($isCancelled): ?><div class="alert bad mb-4"><i class="bi bi-x-octagon"></i><div><span class="t">Bu gider iptal edildi.</span> <?= e(tr_datetime($e['cancelled_at'])) ?> · Gerekçe: <?= e($e['cancel_reason']) ?>. Ödemeler için ters kayıt oluşturuldu.</div></div><?php endif; ?>
<?php if (!$isCancelled && $e['status'] === 'gecikti'): ?><div class="alert warn mb-4"><i class="bi bi-exclamation-triangle"></i><div>Vadesi (<?= e(tr_date($e['due_date'])) ?>) geçti; kalan <?= e(money($remaining)) ?>.</div></div><?php endif; ?>

<div class="stat-row mb-4">
  <div class="balance-box <?= $remaining > 0 && !$isCancelled ? 'bad' : 'ok' ?>"><div><div class="l">Kalan</div><div class="v"><?= e(money($isCancelled ? 0 : $remaining)) ?></div></div></div>
  <div class="stat"><div class="k">Toplam tutar</div><div class="v"><?= e(money($e['amount'], false)) ?> <small>₺</small></div><div class="d"><?= e(list_label('vat_modes', $e['vat_mode'])) ?><?= (float) $e['vat_rate'] > 0 ? ' · %' . e(number_tr((float) $e['vat_rate'])) : '' ?></div></div>
  <div class="stat tone-ok"><div class="k">Ödenen</div><div class="v"><?= e(money($e['paid_amount'], false)) ?> <small>₺</small></div><div class="d"><?= count($payments) ?> ödeme</div></div>
  <div class="stat"><div class="k">Durum</div><div class="v" style="font-family:var(--font-sans);font-size:18px"><span class="pill <?= status_tone($e['status']) ?>"><?= e(list_label('expense_statuses', $e['status'])) ?></span></div><div class="d"><?= $e['due_date'] ? 'Vade ' . e(tr_date($e['due_date'])) : 'Vade belirtilmedi' ?></div></div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-receipt"></i>Gider bilgileri</h3><span class="small text-muted">#<?= $e['id'] ?></span></div>
      <div class="card-body"><dl class="dl">
        <dt>Açıklama</dt><dd><?= $e['description'] ? nl2br(e($e['description'])) : '—' ?></dd>
        <dt>Kategori</dt><dd><?= e($e['parent_name'] && $e['parent_name'] !== $e['category_name'] ? $e['parent_name'] . ' › ' : '') ?><?= e($e['category_name'] ?: 'Kategorisiz') ?></dd>
        <dt>Tedarikçi</dt><dd><?= $e['vendor_name'] ? '<a href="' . e(route('vendors.show', ['id' => $e['vendor_id']])) . '">' . e($e['vendor_name']) . '</a>' : '—' ?></dd>
        <?php if ($contract): ?><dt>Sözleşme</dt><dd><a href="<?= e(can('vendors.manage') ? route('contracts.edit', ['id' => $contract['id']]) : route('contracts.index')) ?>"><?= e($contract['title']) ?></a> <span class="small text-muted">· <?= e(tr_date($contract['start_date'])) ?> – <?= e(tr_date($contract['end_date'])) ?></span></dd><?php endif; ?>
        <dt>Belge</dt><dd><?= e(list_label('document_kinds', $e['document_kind'])) ?><?= $e['document_no'] ? ' · <span class="mono">' . e($e['document_no']) . '</span>' : '' ?></dd>
        <dt>Tarih / dönem</dt><dd><?= e(tr_date($e['expense_date'])) ?> · <?= e(tr_period($e['period'])) ?><?= $e['due_date'] ? ' · vade ' . e(tr_date($e['due_date'])) : '' ?></dd>
        <dt>Kapsam</dt><dd><?= $e['scope'] === 'blok' ? e($e['block_name'] ?: 'Blok') : 'Tüm yapı (ortak gider)' ?></dd>
        <?php if ($budgetLine): ?><dt>Bütçe kalemi</dt><dd><a href="<?= e(route('budgets.show', ['id' => $budgetLine['budget_id']])) ?>"><?= e($budgetLine['name']) ?></a> <span class="small text-muted">· <?= $budgetLine['fiscal_year'] ?> işletme projesi</span></dd><?php endif; ?>
        <?php if ($recurring): ?><dt>Periyodik gider</dt><dd><a href="<?= e(route('recurring.index')) ?>"><?= e($recurring['title']) ?></a></dd><?php endif; ?>
        <dt>Kasa / banka</dt><dd><?= e($e['account_name'] ?: '—') ?></dd>
      </dl></div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-calculator"></i>KDV dökümü</h3><div class="sub"><?= e(list_label('vat_modes', $e['vat_mode'])) ?></div></div>
      <div class="table-wrap"><table class="table compact">
        <tbody>
          <tr><td>Matrah (KDV hariç)</td><td class="num"><?= e(money($net)) ?></td></tr>
          <tr><td>KDV<?= (float) $e['vat_rate'] > 0 ? ' (%' . e(number_tr((float) $e['vat_rate'])) . ')' : '' ?></td><td class="num"><?= e(money($e['vat_amount'])) ?></td></tr>
        </tbody>
        <tfoot><tr><td>Toplam</td><td class="num"><?= e(money($e['amount'])) ?></td></tr></tfoot>
      </table></div>
    </div>

    <div class="card" id="odeme">
      <div class="card-head"><h3><i class="bi bi-cash-coin"></i>Ödeme geçmişi</h3><div class="sub">Her ödeme kasa/banka defterine çıkış olarak işlenir</div></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Tarih</th><th>Hesap</th><th class="hide-sm">Referans</th><th class="num">Tutar</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $pm): ?>
          <tr><td class="small"><?= e(tr_date($pm['paid_date'])) ?></td><td><i class="bi bi-<?= $pm['account_type'] === 'banka' ? 'bank' : 'cash-stack' ?> text-muted"></i> <?= e($pm['account_name']) ?></td><td class="hide-sm mono small"><?= e($pm['reference_no'] ?: '—') ?></td><td class="num"><?= e(money($pm['amount'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($payments === []): ?><tr><td colspan="4" class="text-muted centered" style="padding:20px">Henüz ödeme yapılmadı.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($payments !== []): ?><tfoot><tr><td colspan="3">Toplam ödenen</td><td class="num"><?= e(money($e['paid_amount'])) ?></td></tr></tfoot><?php endif; ?>
      </table></div>
      <?php if (can('expenses.manage') && $payable): ?>
      <form method="post" action="<?= e(route('expenses.pay', ['id' => $e['id']])) ?>" class="card-body" style="border-top:1px solid var(--rule-soft);background:var(--surface-2)" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field c4"><label for="f_account_id">Kasa / banka <span class="req" aria-hidden="true">*</span></label><select class="select <?= error_for('account_id') ? 'is-invalid' : '' ?>" name="account_id" id="f_account_id" required><option value="">Hesap seçin…</option><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= (string) old('account_id', $e['account_id']) === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?> · bakiye <?= e(money($a['balance'])) ?></option><?php endforeach; ?></select><?php if (error_for('account_id')): ?><div class="err"><i class="bi bi-exclamation-circle"></i><?= e(error_for('account_id')) ?></div><?php endif; ?></div>
          <?= Form::money('amount', 'Ödeme tutarı', ['required' => true, 'value' => $remaining, 'col' => 'c3', 'help' => 'Kalan: ' . money($remaining)]) ?>
          <?= Form::date('paid_date', 'Ödeme tarihi', ['required' => true, 'value' => $today, 'col' => 'c3']) ?>
          <?= Form::text('reference_no', 'Referans', ['value' => $e['document_no'] ?? '', 'col' => 'c2', 'class' => 'mono', 'placeholder' => 'Dekont no']) ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary"><i class="bi bi-check2"></i>Ödemeyi kaydet</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-paperclip"></i>Belgeler</h3><?php if (can('expenses.manage') && !$isCancelled): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('expenses.edit', ['id' => $e['id']])) ?>" title="Düzenle sayfasından belge ekleyin"><i class="bi bi-upload"></i>Ekle</a><?php endif; ?></div>
      <div class="card-body">
        <?php foreach ($documents as $doc): ?>
          <div class="flex between center gap-2" style="padding:8px 0;border-bottom:1px solid var(--rule-soft)">
            <div class="grow"><div class="primary-cell"><i class="bi bi-<?= $doc['mime'] === 'application/pdf' ? 'file-earmark-pdf' : 'file-earmark-image' ?> text-muted"></i> <?= e($doc['title']) ?></div><div class="sub-cell"><?= e($doc['original_name']) ?> · <?= e(number_tr((int) $doc['size'] / 1024)) ?> KB · <?= e(tr_date($doc['created_at'])) ?> · <?= e(list_label('document_visibilities', $doc['visibility'])) ?></div></div>
            <a class="btn btn-sm" href="<?= e(route('documents.download', ['id' => $doc['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-download"></i>Aç</a>
          </div>
        <?php endforeach; ?>
        <?php if ($documents === []): ?><p class="small text-muted">Belge eklenmemiş. Fatura/fiş görüntüsü eklemek şeffaflık raporlarında sakinlere gösterilebilir.</p><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>İşlem geçmişi</h3></div>
      <div class="card-body">
        <ul class="timeline">
          <?php foreach ($timeline as $t): ?><li><span class="pt <?= str_contains($t['action'], 'cancel') ? 'bad' : (str_contains($t['action'], 'pay') ? 'ok' : '') ?>"></span><div class="tt"><?= e($t['summary'] ?: $t['action']) ?></div><div class="tm"><?= e(tr_datetime($t['created_at'])) ?> · <?= e($t['user_name'] ?: 'sistem') ?></div></li><?php endforeach; ?>
          <?php if ($timeline === []): ?><li><span class="pt"></span><div class="tb text-muted">Henüz işlem yok.</div></li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?= $this->partial('partials.reason-modal') ?>
</div>
