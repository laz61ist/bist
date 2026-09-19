<?php use Aidat\Core\Form; ?>
<?= $this->partial('partials.page-head', ['title' => 'Tekil borçlandırma', 'crumbs' => [['Borç kayıtları', route('charges.index')], ['Tekil borçlandırma']], 'desc' => 'Tek bir bağımsız bölüme borç yazar. Toplu tahakkuk için plan kullanın.']) ?>
<form method="post" action="<?= e(route('charges.store')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="card">
      <div class="card-body"><div class="form-grid">
        <?= Form::select('unit_id', 'Bağımsız bölüm', $units, ['required' => true, 'value' => $preselect ?: '', 'col' => 'c6', 'placeholder' => 'Bölüm seçin…']) ?>
        <?= Form::select('charge_type', 'Borç türü', $charge_types, ['required' => true, 'value' => 'diger', 'col' => 'c6']) ?>
        <?= Form::text('title', 'Başlık', ['required' => true, 'col' => 'c12', 'placeholder' => 'Örn. Kapı kilidi değişimi katkı payı', 'maxlength' => 150]) ?>
        <?= Form::month('period', 'Dönem', ['required' => true, 'value' => $defaults['period'], 'col' => 'c4']) ?>
        <?= Form::date('due_date', 'Vade tarihi', ['required' => true, 'value' => $defaults['due_date'], 'col' => 'c4']) ?>
        <?= Form::money('amount', 'Tutar', ['required' => true, 'col' => 'c4']) ?>
        <?= Form::radioCards('liability', 'Borç sorumluluğu', ['' => ['Bölümün ayarı', 'Bölüm kartındaki varsayılan'], 'malik' => ['Malik öder', ''], 'kiraci' => ['Kiracı öder', 'Kiracı yoksa malik'], 'paylasimli' => ['Paylaşımlı', 'Kayıt malik üzerine']], ['value' => '']) ?>
        <?= Form::textarea('description', 'Açıklama', ['rows' => 2, 'col' => 'c12']) ?>
        <?= Form::checkbox('late_fee_exempt', 'Bu borca gecikme tazminatı uygulanmasın', ['help' => 'Örn. taksitlendirilmiş ya da uzlaşılmış borçlar']) ?>
      </div></div>
      <div class="card-foot"><a class="btn" href="<?= e($preselect ? route('units.show', ['id' => $preselect]) : route('charges.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i>Borçlandır</button></div>
    </div>
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-info-circle"></i>Nasıl çalışır?</h3></div><div class="card-body small text-2">
        <p>• Borç, dönem ve vadeyle birlikte bölümün cari hesabına yazılır; sorumlu kişi (malik/kiracı) o tarihteki oturuma göre belirlenir.</p>
        <p class="mt-2">• Bölümde mahsup bekleyen avans varsa yeni borca <strong>otomatik</strong> mahsup edilir.</p>
        <p class="mt-2">• Kapalı döneme borç yazılamaz. Yanlış kayıt silinmez; <strong>iptal</strong> ile ters kayıt oluşturulur.</p>
        <p class="mt-2">• Gecikme tazminatı ve devir bakiyesi elle oluşturulmaz; ilgili ekranlardan üretilir.</p>
      </div></div>
    </div>
  </div>
</form>
