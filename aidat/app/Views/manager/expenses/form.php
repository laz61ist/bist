<?php use Aidat\Core\Form; $r = $row ?? []; $d = $defaults ?? [];
$val = static fn (string $k, mixed $fallback = '') => $r[$k] ?? $d[$k] ?? $fallback;
$state = [
    'status' => (string) old('status', $r ? '' : 'odendi'),
    'scope' => (string) old('scope', $val('scope', 'tumu')),
    'vendor' => (string) old('vendor_id', $val('vendor_id', '') ?? ''),
    'contract' => (string) old('contract_id', $val('contract_id', '') ?? ''),
    'amount' => (string) old('amount', $formAmount !== null ? money_input($formAmount) : ''),
    'mode' => (string) old('vat_mode', $val('vat_mode', 'dahil')),
    'rate' => (string) old('vat_rate', (string) (int) $val('vat_rate', 20)),
    'contracts' => array_map(static fn (array $c) => ['id' => (int) $c['id'], 'vendor' => (int) $c['vendor_id'], 'title' => $c['title'] . ($c['status'] !== 'aktif' ? ' (' . list_label('contract_statuses', $c['status']) . ')' : '')], $contracts),
];
?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Giderler', route('expenses.index')], [$row ? 'Düzenle' : 'Yeni']], 'desc' => $row ? 'Ödenmiş tutarın altına inilemez; iptal için gider sayfasındaki ters kayıt kullanılır.' : 'Tutar kuruş hassasiyetinde saklanır; KDV modu seçimine göre toplam otomatik hesaplanır.']) ?>
<form method="post" action="<?= e($row ? route('expenses.update', ['id' => $row['id']]) : route('expenses.store')) ?>" enctype="multipart/form-data" novalidate
      x-data="expenseForm(<?= e(json_encode($state, JSON_UNESCAPED_UNICODE)) ?>)">
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-receipt"></i>Gider bilgileri</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::date('expense_date', 'Gider tarihi', ['required' => true, 'value' => $val('expense_date'), 'col' => 'c4']) ?>
        <?= Form::date('due_date', 'Vade', ['value' => $val('due_date'), 'col' => 'c4', 'help' => 'Planlanan giderde ödeme günü']) ?>
        <?= Form::month('period', 'Dönem', ['value' => $val('period', substr((string) $val('expense_date', date('Y-m-d')), 0, 7)), 'col' => 'c4', 'help' => 'Boş bırakılırsa gider tarihinden alınır']) ?>
        <?= Form::select('category_id', 'Kategori', $categories, ['value' => $val('category_id'), 'col' => 'c6', 'placeholder' => 'Kategori seçin…', 'help' => 'Listeyi Gider kategorileri sayfasından düzenleyebilirsiniz']) ?>
        <?= Form::select('vendor_id', 'Tedarikçi', $vendors, ['value' => $val('vendor_id'), 'col' => 'c6', 'placeholder' => 'Tedarikçi yok', 'attrs' => 'x-model="vendor"']) ?>
        <div class="field c6" x-show="contracts.some(c => String(c.vendor) === String(vendor))" x-cloak>
          <label for="f_contract_id">Sözleşme</label>
          <select class="select <?= error_for('contract_id') ? 'is-invalid' : '' ?>" name="contract_id" id="f_contract_id" x-model="contract">
            <option value="">Sözleşmesiz</option>
            <template x-for="c in contracts.filter(c => String(c.vendor) === String(vendor))" :key="c.id"><option :value="c.id" x-text="c.title" :selected="String(c.id) === String(contract)"></option></template>
          </select>
          <?php if (error_for('contract_id')): ?><div class="err"><i class="bi bi-exclamation-circle"></i><?= e(error_for('contract_id')) ?></div><?php endif; ?>
        </div>
        <?= Form::select('document_kind', 'Belge türü', $document_kinds, ['required' => true, 'value' => $val('document_kind', 'fatura'), 'col' => 'c3']) ?>
        <?= Form::text('document_no', 'Belge no', ['value' => $val('document_no'), 'col' => 'c3', 'class' => 'mono', 'placeholder' => 'FTR2026-0001']) ?>
        <?= Form::textarea('description', 'Açıklama', ['value' => $val('description'), 'rows' => 2, 'placeholder' => 'Örn. Asansör aylık bakım · Eylül']) ?>
      </div></div></div>

      <div class="card"><div class="card-head"><h3><i class="bi bi-calculator"></i>Tutar ve KDV</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::money('amount', 'Tutar', ['required' => true, 'value' => $formAmount, 'col' => 'c4', 'attrs' => 'x-model="amount"', 'help' => 'KDV hariç modda net tutarı girin']) ?>
        <?= Form::select('vat_rate', 'KDV oranı', ['0' => '%0', '1' => '%1', '10' => '%10', '20' => '%20'], ['required' => true, 'value' => (string) (int) $val('vat_rate', 20), 'col' => 'c4', 'attrs' => 'x-model="rate"']) ?>
        <div class="field c4"><label>Hesaplanan</label><div class="small" style="padding-top:8px;line-height:1.7"><span class="text-muted">Net</span> <span class="num" x-text="money(calc().net)"></span><br><span class="text-muted">KDV</span> <span class="num" x-text="money(calc().vat)"></span><br><span class="text-muted">Toplam</span> <strong class="num" x-text="money(calc().total)"></strong></div></div>
        <?= Form::radioCards('vat_mode', 'KDV durumu', ['dahil' => ['KDV dahil', 'Girilen tutar KDV içerir'], 'haric' => ['KDV hariç', 'KDV eklenir, toplam büyür'], 'yok' => ['KDV yok', 'Maaş, SGK, harç vb.']], ['value' => $val('vat_mode', 'dahil'), 'attrs' => 'x-model="mode"']) ?>
      </div></div></div>

      <div class="card"><div class="card-head"><h3><i class="bi bi-diagram-3"></i>Kapsam ve bütçe</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::radioCards('scope', 'Kapsam', ['tumu' => ['Tüm yapı', 'Ortak gider'], 'blok' => ['Belirli blok', 'Yalnızca bir bloğa ait']], ['value' => $val('scope', 'tumu'), 'attrs' => 'x-model="scope"']) ?>
        <div class="field c6" x-show="scope === 'blok'" x-cloak><?= Form::select('block_id', 'Blok', $blocks, ['value' => $val('block_id'), 'placeholder' => 'Blok seçin…']) ?></div>
        <?= Form::select('budget_line_id', 'Bütçe kalemi (' . $budgetYear . ')', $budgetLines, ['value' => $val('budget_line_id'), 'col' => 'c6', 'placeholder' => $budgetLines === [] ? 'Onaylı bütçe yok' : 'Bütçe kalemi seçin (isteğe bağlı)', 'help' => 'Planlanan-gerçekleşen karşılaştırmasında kullanılır', 'disabled' => $budgetLines === []]) ?>
      </div></div></div>
    </div>

    <div class="stack">
      <?php if (!$row): ?>
      <div class="card"><div class="card-head"><h3><i class="bi bi-cash-coin"></i>Ödeme</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::radioCards('status', 'Durum', ['odendi' => ['Ödendi', 'Kasa/bankadan çıkış yazılır'], 'planlandi' => ['Planlandı', 'Vadesinde ödenecek']], ['value' => 'odendi', 'attrs' => 'x-model="status"']) ?>
        <?= Form::select('account_id', 'Kasa / banka', $accounts, ['value' => $val('account_id'), 'placeholder' => 'Hesap seçin…', 'help' => 'Ödendi durumunda zorunlu']) ?>
      </div></div></div>
      <?php else: ?>
      <div class="card"><div class="card-head"><h3><i class="bi bi-cash-coin"></i>Ödeme durumu</h3></div><div class="card-body">
        <dl class="dl"><dt>Durum</dt><dd><span class="pill <?= status_tone($row['status']) ?>"><?= e(list_label('expense_statuses', $row['status'])) ?></span></dd><dt>Ödenen</dt><dd class="num"><?= e(money($row['paid_amount'])) ?></dd></dl>
        <p class="small text-muted mt-3">Ödeme eklemek için gider sayfasındaki <strong>Ödeme yap</strong> formunu kullanın.</p>
        <?= Form::hidden('account_id', $row['account_id'] ?? '') ?>
      </div></div>
      <?php endif; ?>
      <div class="card"><div class="card-head"><h3><i class="bi bi-paperclip"></i>Belge</h3><span class="small text-muted">isteğe bağlı</span></div><div class="card-body"><div class="form-grid">
        <?= Form::file('file', 'Fatura / fiş görüntüsü', ['help' => 'Yalnızca yönetim görebilir; sakinlere açmak için Belgeler sayfasından görünürlüğü değiştirin.']) ?>
      </div></div></div>
      <div class="card"><div class="card-foot" style="border-top:0;border-radius:var(--r-lg)"><a class="btn" href="<?= e($row ? route('expenses.show', ['id' => $row['id']]) : route('expenses.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i><?= $row ? 'Güncelle' : 'Kaydet' ?></button></div></div>
    </div>
  </div>
</form>
<script>
document.addEventListener('alpine:init', function () {
  Alpine.data('expenseForm', function (state) {
    return Object.assign({}, state, {
      init: function () { const self = this; this.$watch('vendor', function (v) { if (!self.contracts.some(function (c) { return String(c.vendor) === String(v) && String(c.id) === String(self.contract); })) self.contract = ''; }); },
      calc: function () {
        const a = window.parseMoney(this.amount); const r = parseFloat(this.rate) || 0;
        if (this.mode === 'yok' || r <= 0) return { net: a, vat: 0, total: a };
        if (this.mode === 'haric') { const v = Math.round(a * r / 100); return { net: a, vat: v, total: a + v }; }
        const v = Math.round(a - a / (1 + r / 100)); return { net: a - v, vat: v, total: a };
      }
    });
  });
});
</script>
