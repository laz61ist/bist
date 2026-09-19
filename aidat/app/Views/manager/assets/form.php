<?php use Aidat\Core\Form; $r = $row ?? []; ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Demirbaş', route('assets.index')], [$row ? 'Düzenle' : 'Yeni']]]) ?>
<form method="post" action="<?= e($row ? route('assets.update', ['id' => $row['id']]) : route('assets.store')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-box-seam"></i>Demirbaş</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::text('name', 'Ad', ['required' => true, 'value' => $r['name'] ?? '', 'col' => 'c8', 'placeholder' => 'Hidrofor pompası, jeneratör, kamera sistemi…']) ?>
        <?= Form::select('status', 'Durum', $asset_statuses, ['required' => true, 'value' => $r['status'] ?? 'aktif', 'col' => 'c4']) ?>
        <?= Form::text('category', 'Kategori', ['value' => $r['category'] ?? '', 'col' => 'c6', 'placeholder' => 'Tesisat, Güvenlik, Elektrik…', 'attrs' => 'list="asset_categories"']) ?>
        <datalist id="asset_categories"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"></option><?php endforeach; ?></datalist>
        <?= Form::text('serial_no', 'Seri / model no', ['value' => $r['serial_no'] ?? '', 'col' => 'c6', 'class' => 'mono']) ?>
        <?= Form::text('location', 'Konum', ['value' => $r['location'] ?? '', 'col' => 'c6', 'placeholder' => 'Kazan dairesi, çatı, giriş…']) ?>
        <?= Form::select('vendor_id', 'Tedarikçi', $vendors, ['value' => $r['vendor_id'] ?? '', 'col' => 'c6', 'placeholder' => 'Tedarikçi yok']) ?>
        <?= Form::textarea('notes', 'Notlar', ['value' => $r['notes'] ?? '', 'rows' => 3, 'placeholder' => 'Bakım periyodu, servis telefonu, anahtar teslim bilgisi…']) ?>
      </div></div></div>
    </div>
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-receipt"></i>Alım ve garanti</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::date('purchase_date', 'Alım tarihi', ['value' => $r['purchase_date'] ?? '', 'col' => 'c6']) ?>
        <?= Form::money('cost', 'Maliyet', ['value' => (int) ($r['cost'] ?? 0), 'col' => 'c6']) ?>
        <?= Form::date('warranty_until', 'Garanti bitişi', ['value' => $r['warranty_until'] ?? '', 'col' => 'c6', 'help' => 'Bitişe 60 gün kala listede uyarı görünür']) ?>
        <?= Form::number('expense_id', 'Gider kaydı no', ['value' => $r['expense_id'] ?? '', 'col' => 'c6', 'min' => 1, 'help' => 'Alım gideri kaydedildiyse numarasını yazın']) ?>
      </div></div></div>
      <div class="alert info"><i class="bi bi-info-circle"></i><div>Demirbaş katkı payı toplamak için tahakkuk planında "Demirbaş" türünü kullanın; hurdaya ayrılan kayıtlar toplamlara dahil edilmez.</div></div>
    </div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= e(route('assets.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i><?= $row ? 'Güncelle' : 'Kaydet' ?></button></div>
</form>
