<?php use Aidat\Core\Form; $r = $row ?? []; $canIdentity = $canIdentity ?? can('people.identity'); ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Personel', route('staff.index')], [$row ? 'Düzenle' : 'Yeni']]]) ?>
<form method="post" action="<?= e($row ? route('staff.update', ['id' => $row['id']]) : route('staff.store')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-person-badge"></i>Kimlik ve görev</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::text('full_name', 'Ad soyad', ['required' => true, 'value' => $r['full_name'] ?? '', 'col' => 'c6']) ?>
        <?= Form::select('position', 'Görev', $staff_positions, ['required' => true, 'value' => $r['position'] ?? 'kapici', 'col' => 'c6']) ?>
        <?php if ($canIdentity || !$row): ?><?= Form::text('identity_no', 'TCKN', ['value' => $r['identity_no'] ?? '', 'col' => 'c6', 'class' => 'mono', 'maxlength' => 11, 'help' => 'SGK bildirimi için; listede maskeli gösterilir.']) ?><?php else: ?><div class="field c6"><label>TCKN</label><input class="input mono" value="<?= e(\Aidat\Core\Str::maskIdentity($r['identity_no'] ?? '')) ?>" readonly><div class="help">Görüntüleme yetkiniz yok.</div></div><?php endif; ?>
        <?= Form::text('sgk_no', 'SGK sicil no', ['value' => $r['sgk_no'] ?? '', 'col' => 'c6', 'class' => 'mono']) ?>
        <?= Form::text('phone', 'Telefon', ['value' => $r['phone'] ?? '', 'col' => 'c6', 'placeholder' => '05xx xxx xx xx']) ?>
        <?= Form::email('email', 'E-posta', ['value' => $r['email'] ?? '', 'col' => 'c6']) ?>
      </div></div></div>
      <div class="card"><div class="card-head"><h3><i class="bi bi-sticky"></i>Notlar</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::textarea('notes', 'Notlar', ['value' => $r['notes'] ?? '', 'rows' => 3, 'placeholder' => 'İzin günleri, sözleşme koşulları, acil durum kişisi…']) ?>
      </div></div></div>
    </div>
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-calendar-check"></i>Çalışma ve ücret</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::date('start_date', 'İşe başlama', ['value' => $r['start_date'] ?? date('Y-m-d'), 'col' => 'c6']) ?>
        <?= Form::date('end_date', 'Ayrılış', ['value' => $r['end_date'] ?? '', 'col' => 'c6', 'help' => 'Geçmiş bir tarih girilirse kayıt pasife alınır']) ?>
        <?= Form::money('salary', 'Aylık brüt maaş', ['value' => (int) ($r['salary'] ?? 0), 'col' => 'c6']) ?>
        <?= Form::checkbox('is_active', 'Aktif personel', ['checked' => (bool) ($r['is_active'] ?? true), 'col' => 'c6']) ?>
      </div></div></div>
      <div class="alert info"><i class="bi bi-info-circle"></i><div>Maaş ve SGK ödemeleri gider modülünden "Personel" kategorisiyle kaydedilir; buradaki tutar yalnızca bilgi amaçlıdır.</div></div>
    </div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= e(route('staff.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i><?= $row ? 'Güncelle' : 'Kaydet' ?></button></div>
</form>
