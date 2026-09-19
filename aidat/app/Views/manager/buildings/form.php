<?php use Aidat\Core\Form; $r = $row ?? []; ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Yapılar', route('buildings.index')], [$row ? 'Düzenle' : 'Yeni']], 'desc' => 'Yapı kimliği, adres ve tahsilat için banka bilgileri. IBAN makbuz ve sakin ekranında gösterilir.']) ?>
<form method="post" action="<?= e($row ? route('buildings.update', ['id' => $row['id']]) : route('buildings.store')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-building"></i>Kimlik</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::text('name', 'Yapı adı', ['required' => true, 'value' => $r['name'] ?? '', 'col' => 'c8', 'placeholder' => 'Örn. Çınar Apartmanı']) ?>
        <?= Form::select('type', 'Tür', $building_types, ['required' => true, 'value' => $r['type'] ?? 'apartman', 'col' => 'c4']) ?>
        <?= Form::text('tax_no', 'Vergi no', ['value' => $r['tax_no'] ?? '', 'col' => 'c4', 'help' => 'Yönetim adına vergi numarası (varsa)']) ?>
        <?= Form::text('tax_office', 'Vergi dairesi', ['value' => $r['tax_office'] ?? '', 'col' => 'c4']) ?>
        <?= Form::date('management_start', 'Yönetim başlangıcı', ['value' => $r['management_start'] ?? '', 'col' => 'c4']) ?>
      </div></div></div>
      <div class="card"><div class="card-head"><h3><i class="bi bi-geo-alt"></i>Adres ve iletişim</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::textarea('address', 'Adres', ['value' => $r['address'] ?? '', 'rows' => 2]) ?>
        <?= Form::text('district', 'İlçe', ['value' => $r['district'] ?? '', 'col' => 'c4']) ?>
        <?= Form::text('city', 'İl', ['value' => $r['city'] ?? '', 'col' => 'c4']) ?>
        <?= Form::text('phone', 'Yönetim telefonu', ['value' => $r['phone'] ?? '', 'col' => 'c4']) ?>
        <?= Form::email('email', 'Yönetim e-postası', ['value' => $r['email'] ?? '', 'col' => 'c6']) ?>
      </div></div></div>
    </div>
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-bank"></i>Banka bilgileri</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::text('bank_name', 'Banka', ['value' => $r['bank_name'] ?? '']) ?>
        <?= Form::text('iban', 'IBAN', ['value' => $r['iban'] ?? '', 'class' => 'mono', 'placeholder' => 'TR00 0000 0000 0000 0000 0000 00', 'help' => 'Sakinler havale/EFT için bu IBAN\'ı görür.']) ?>
        <?= Form::text('account_holder', 'Hesap sahibi', ['value' => $r['account_holder'] ?? '', 'placeholder' => 'Örn. Çınar Apt. Yönetimi']) ?>
      </div><?php if (!$row): ?><div class="alert info mt-4"><i class="bi bi-info-circle"></i><div>Kaydettiğinizde <strong>Nakit kasa</strong> hesabı ve IBAN girdiyseniz banka hesabı otomatik açılır; varsayılan gider kategorileri yüklenir.</div></div><?php endif; ?></div></div>
      <div class="card"><div class="card-head"><h3><i class="bi bi-sticky"></i>Notlar</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::textarea('notes', '', ['value' => $r['notes'] ?? '', 'rows' => 3, 'placeholder' => 'Yönetim planı özeti, özel kurallar…']) ?>
        <?php if ($row): ?><?= Form::checkbox('is_active', 'Yapı aktif', ['checked' => (bool) $row['is_active'], 'help' => 'Pasif yapı listelerde görünmez; veriler korunur.']) ?><?php endif; ?>
      </div></div></div>
    </div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= e(route('buildings.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i><?= $row ? 'Güncelle' : 'Yapıyı oluştur' ?></button></div>
</form>
