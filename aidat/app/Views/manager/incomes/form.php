<?php use Aidat\Core\Form; $d = $defaults; ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Diğer gelirler', route('incomes.index')], ['Yeni']], 'desc' => 'Tutar seçilen kasa/banka hesabına giriş olarak işlenir. Yanlış kayıt silinmez; listeden gerekçeyle iptal edilir.']) ?>
<form method="post" action="<?= e(route('incomes.store')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="card"><div class="card-body"><div class="form-grid">
    <?= Form::date('income_date', 'Gelir tarihi', ['required' => true, 'value' => $d['income_date'], 'col' => 'c4']) ?>
    <?= Form::month('period', 'Dönem', ['value' => $d['period'], 'col' => 'c4', 'help' => 'Boş bırakılırsa tarihten alınır']) ?>
    <?= Form::money('amount', 'Tutar', ['required' => true, 'col' => 'c4']) ?>
    <?= Form::select('category_id', 'Kategori', $categories, ['value' => '', 'col' => 'c6', 'placeholder' => 'Kategori seçin…', 'help' => 'Kategorileri Gider kategorileri sayfasından yönetebilirsiniz']) ?>
    <?= Form::select('account_id', 'Kasa / banka', $accounts, ['required' => true, 'value' => $d['account_id'], 'col' => 'c6', 'placeholder' => 'Hesap seçin…']) ?>
    <?= Form::text('document_no', 'Belge no', ['col' => 'c4', 'class' => 'mono', 'placeholder' => 'Dekont / makbuz no']) ?>
    <?= Form::textarea('description', 'Açıklama', ['col' => 'c8', 'rows' => 2, 'placeholder' => 'Örn. Çatı baz istasyonu kira geliri · 3. çeyrek']) ?>
  </div></div>
  <div class="card-foot"><a class="btn" href="<?= e(route('incomes.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i>Kaydet</button></div></div>
</form>
