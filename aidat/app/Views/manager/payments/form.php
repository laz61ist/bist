<?php use Aidat\Core\Form;
$mode = (string) old('allocation_mode', 'eski');
$opts = ['unitId' => $preUnit > 0 ? (string) $preUnit : '', 'url' => route('payments.unit_charges', ['id' => 0]), 'amount' => (string) old('amount', '')];
$modeCards = ['eski' => ['En eski borçtan başla', 'Vadesi en eski açık borçtan itibaren kapatır; artan tutar avansa alınır'], 'manuel' => ['Manuel seç', 'Hangi borca ne kadar düşeceğini siz yazın'], 'avans' => ['Avansa al', 'Borca dağıtılmaz; yeni borçta otomatik mahsup edilir']];
?>
<?= $this->partial('partials.page-head', ['title' => 'Tahsilat girişi', 'crumbs' => [['Tahsilatlar', route('payments.index')], ['Yeni']], 'desc' => 'Makbuz numarası kayıt anında otomatik verilir. Dağıtım toplamı tahsilat tutarını aşamaz; artan tutar avans olarak bekler.']) ?>
<form method="post" action="<?= e(route('payments.store')) ?>" novalidate x-data="Object.assign(allocator([], '<?= e($mode) ?>'), paymentFormExt(<?= e(json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>))" @submit="if (!canSubmit()) { $event.preventDefault(); window.toast(submitBlockReason(), 'warn'); }">
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-cash-coin"></i>Tahsilat bilgileri</h3></div>
        <div class="card-body"><div class="form-grid">
          <?= Form::select('unit_id', 'Bağımsız bölüm', $units, ['value' => $preUnit ?: '', 'col' => 'c6', 'placeholder' => 'Bölüm seçin… (yalnızca avans için boş)', 'attrs' => 'x-model="unitId"', 'help' => 'Seçince açık borçlar sağda listelenir']) ?>
          <?= Form::select('account_id', 'Kasa / banka hesabı', $accounts, ['required' => true, 'value' => $defaultAccount ?? '', 'col' => 'c6', 'placeholder' => 'Hesap seçin…']) ?>
          <?= Form::date('payment_date', 'Tahsilat tarihi', ['required' => true, 'value' => $today, 'col' => 'c4']) ?>
          <?= Form::time('payment_time', 'Saat', ['value' => $now, 'col' => 'c2']) ?>
          <?= Form::select('method', 'Ödeme yöntemi', $payment_methods, ['required' => true, 'value' => 'nakit', 'col' => 'c6']) ?>
          <?= Form::money('amount', 'Tutar', ['required' => true, 'value' => old('amount', ''), 'col' => 'c6', 'attrs' => 'x-model="amount" autofocus', 'raw' => true]) ?>
          <?= Form::text('reference_no', 'Referans / dekont no', ['value' => old('reference_no', ''), 'col' => 'c6', 'placeholder' => 'EFT123456, çek no…']) ?>
          <?= Form::textarea('description', 'Açıklama', ['value' => old('description', ''), 'rows' => 2, 'placeholder' => 'Örn. Eylül aidatı + Ağustos kalan']) ?>
        </div></div>
      </div>

      <div class="card">
        <div class="card-head"><div><h3><i class="bi bi-diagram-3"></i>Borca dağıtım</h3><div class="sub">Tahsilatın hangi borçları kapatacağı</div></div><div class="tools"><span class="pill neutral no-dot" x-show="loading"><i class="bi bi-arrow-repeat"></i>Borçlar yükleniyor…</span></div></div>
        <div class="card-body">
          <?= Form::radioCards('allocation_mode', 'Dağıtım şekli', $modeCards, ['value' => $mode, 'attrs' => 'x-model="mode"']) ?>
          <div class="alert warn mt-4" x-show="error" x-cloak><i class="bi bi-wifi-off"></i><div x-text="error"></div></div>
          <div class="alert info mt-4" x-show="!unitId && mode !== 'avans'" x-cloak><i class="bi bi-info-circle"></i><div>Bölüm seçilmeden tahsilat yalnızca <strong>avans</strong> olarak kaydedilebilir.</div></div>
          <div class="alert info mt-4" x-show="unitId && loaded && charges.length === 0 && mode !== 'avans'" x-cloak><i class="bi bi-check2-circle"></i><div>Bu bölümün açık borcu yok; tutarın tamamı avansa alınır ve bir sonraki tahakkukta otomatik mahsup edilir.</div></div>
        </div>
        <div class="table-wrap" x-show="unitId && charges.length > 0 && mode !== 'avans'" x-cloak>
          <table class="table">
            <thead><tr><th>Borç</th><th>Vade</th><th class="num">Kalan</th><th class="num" style="width:160px" x-text="mode === 'manuel' ? 'Düşülecek tutar' : 'Düşülecek'"></th></tr></thead>
            <tbody>
              <template x-for="c in charges" :key="c.id">
                <tr :class="{'is-muted': allocated(c.id) === 0}">
                  <td><div class="primary-cell" x-text="c.title"></div><div class="sub-cell"><span x-text="c.type"></span> · <span x-text="c.period_label"></span></div></td>
                  <td class="small" :class="c.overdue ? 'text-bad' : ''"><span x-text="c.due_label"></span><i class="bi bi-exclamation-circle" x-show="c.overdue" style="margin-left:4px"></i></td>
                  <td class="num" x-text="fmt(c.open)"></td>
                  <td class="num">
                    <template x-if="mode === 'manuel'">
                      <div class="input-group"><input type="text" class="input num" :name="'manual[' + c.id + ']'" x-model="manual[c.id]" data-money inputmode="decimal" autocomplete="off" placeholder="0,00" :max="c.open" @dblclick="manual[c.id] = window.formatMoneyInput(c.open / 100)" title="Çift tıkla: kalanın tamamı"><span class="addon">₺</span></div>
                    </template>
                    <template x-if="mode !== 'manuel'">
                      <span style="font-weight:600" :class="allocated(c.id) > 0 ? 'text-ok' : 'text-muted'" x-text="fmt(allocated(c.id))"></span>
                    </template>
                  </td>
                </tr>
              </template>
            </tbody>
            <tfoot>
              <tr><td colspan="2">Dağıtılan</td><td class="num text-muted" x-text="fmt(charges.reduce((a, c) => a + c.open, 0))"></td><td class="num" x-text="fmt(manualTotal())"></td></tr>
              <tr :class="over() ? 'text-bad' : ''"><td colspan="3"><span x-show="!over()">Kalan → avans</span><span x-show="over()"><i class="bi bi-exclamation-triangle"></i> Dağıtım toplamı tahsilat tutarını aşıyor</span></td><td class="num" style="font-weight:600" x-text="fmt(remaining())"></td></tr>
            </tfoot>
          </table>
        </div>
        <div class="card-body" x-show="mode === 'avans'" x-cloak><div class="alert info"><i class="bi bi-piggy-bank"></i><div>Tutarın tamamı (<strong x-text="fmt(amountKurus())"></strong>) avans olarak kaydedilir.</div></div></div>
      </div>
    </div>

    <div class="stack">
      <div class="card" x-show="unitId" x-cloak>
        <div class="card-head"><h3><i class="bi bi-door-open"></i>Bölüm bakiyesi</h3><a class="btn btn-sm btn-ghost" :href="'<?= e(route('units.index')) ?>/' + unitId" x-show="unitId">Bölüm <i class="bi bi-arrow-right"></i></a></div>
        <div class="card-body">
          <template x-if="balance">
            <div>
              <div class="small text-muted mb-3" x-text="unitInfo ? ('No ' + unitInfo.door_no + ' · ' + unitInfo.responsible) : ''"></div>
              <dl class="dl">
                <dt>Açık borç</dt><dd class="num" :class="balance.debt > 0 ? 'text-bad' : 'text-ok'" x-text="fmt(balance.debt)"></dd>
                <dt>Vadesi geçmiş</dt><dd class="num" :class="balance.overdue > 0 ? 'text-bad' : ''" x-text="fmt(balance.overdue)"></dd>
                <dt>Bekleyen avans</dt><dd class="num" x-text="fmt(balance.advance)"></dd>
                <dt>Tahsilat sonrası borç</dt><dd class="num" style="font-weight:600" x-text="fmt(Math.max(0, balance.debt - manualTotal()))"></dd>
              </dl>
              <button type="button" class="btn btn-sm mt-3" x-show="balance.debt > 0 && mode !== 'avans'" @click="amount = window.formatMoneyInput(balance.debt / 100)"><i class="bi bi-magic"></i>Tüm borcu kapat: <span x-text="fmt(balance.debt)"></span></button>
            </div>
          </template>
          <p class="small text-muted" x-show="!balance && loading">Yükleniyor…</p>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-receipt-cutoff"></i>Özet</h3></div>
        <div class="card-body">
          <dl class="dl">
            <dt>Tahsilat</dt><dd class="num" style="font-weight:600;font-size:18px" x-text="fmt(amountKurus())"></dd>
            <dt>Borca düşen</dt><dd class="num" x-text="fmt(manualTotal())"></dd>
            <dt>Avansa kalan</dt><dd class="num" :class="remaining() < 0 ? 'text-bad' : ''" x-text="fmt(remaining())"></dd>
          </dl>
          <div class="alert bad mt-3" x-show="over()" x-cloak><i class="bi bi-x-octagon"></i><div>Dağıtım toplamı tahsilat tutarını aşamaz. Tutarları azaltın veya tahsilat tutarını yükseltin.</div></div>
          <div class="alert warn mt-3" x-show="!over() && mode === 'eski' && amountKurus() > 0 && remaining() > 0 && loaded" x-cloak><i class="bi bi-piggy-bank"></i><div><strong x-text="fmt(remaining())"></strong> avansa alınacak.</div></div>
        </div>
        <div class="card-foot"><a class="btn" href="<?= e(route('payments.index')) ?>">Vazgeç</a><button class="btn btn-primary" :disabled="!canSubmit()"><i class="bi bi-check2"></i>Kaydet ve makbuz oluştur</button></div>
      </div>
    </div>
  </div>
</form>
<?php $this->section('scripts'); ?>
<script>
window.paymentFormExt = function (opts) {
  return {
    unitId: opts.unitId || '',
    amount: opts.amount || '',
    url: opts.url,
    loading: false, loaded: false, error: '', balance: null, unitInfo: null,
    init: function () {
      var self = this;
      this.$watch('unitId', function () { self.load(); });
      if (this.unitId) { this.load(); }
    },
    load: function () {
      var self = this;
      var id = parseInt(this.unitId, 10);
      this.charges = []; this.manual = {}; this.balance = null; this.unitInfo = null; this.error = ''; this.loaded = false;
      if (!id) { if (this.mode === 'manuel') { this.mode = 'eski'; } return; }
      this.loading = true;
      fetch(this.url.replace(/0$/, String(id)), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
        .then(function (d) {
          var m = {};
          (d.charges || []).forEach(function (c) { m[c.id] = ''; });
          self.charges = d.charges || []; self.manual = m; self.balance = d.balance || null; self.unitInfo = d.unit || null; self.loaded = true;
        })
        .catch(function () { self.error = 'Açık borçlar yüklenemedi. Sayfayı yenileyin.'; })
        .finally(function () { self.loading = false; });
    },
    over: function () { return this.mode === 'manuel' && this.remaining() < 0; },
    canSubmit: function () { return this.amountKurus() > 0 && !this.over() && (this.mode === 'avans' || !!this.unitId); },
    submitBlockReason: function () {
      if (this.amountKurus() <= 0) { return 'Tutar sıfırdan büyük olmalı.'; }
      if (this.over()) { return 'Dağıtım toplamı tahsilat tutarını aşamaz.'; }
      return 'Bölüm seçin veya dağıtım şeklini "Avansa al" yapın.';
    }
  };
};
</script>
<?php $this->endSection(); ?>
