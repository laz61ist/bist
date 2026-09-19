<?php use Aidat\Core\Form; $r = $row ?? []; $canIdentity = can('people.identity'); ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Kişiler', route('people.index')], [$row ? 'Düzenle' : 'Yeni']]]) ?>
<form method="post" action="<?= e($row ? route('people.update', ['id' => $row['id']]) : route('people.store')) ?>" novalidate x-data="{type:'<?= e(old('type', $r['type'] ?? 'gercek')) ?>'}">
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-person"></i>Kimlik</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::radioCards('type', 'Kişi türü', ['gercek' => ['Gerçek kişi', 'Ad, soyad, TCKN'], 'tuzel' => ['Tüzel kişi', 'Şirket unvanı, VKN']], ['value' => $r['type'] ?? 'gercek', 'attrs' => 'x-model="type"']) ?>
        <div class="field c12" x-show="type==='tuzel'" x-cloak><?= Form::text('company_name', 'Şirket unvanı', ['value' => $r['company_name'] ?? '']) ?></div>
        <?= Form::text('first_name', 'Ad (tüzel kişide yetkili adı)', ['required' => true, 'value' => $r['first_name'] ?? '', 'col' => 'c6']) ?>
        <?= Form::text('last_name', 'Soyad', ['value' => $r['last_name'] ?? '', 'col' => 'c6']) ?>
        <?php if ($canIdentity || !$row): ?><?= Form::text('identity_no', 'TCKN / VKN', ['value' => $r['identity_no'] ?? '', 'col' => 'c6', 'class' => 'mono', 'maxlength' => 11, 'help' => 'İcra takibi ve tebligat için; maskeli saklanır, yalnızca yetkili görür.']) ?><?php else: ?><div class="field c6"><label>TCKN / VKN</label><input class="input mono" value="<?= e(\Aidat\Core\Str::maskIdentity($r['identity_no'] ?? '')) ?>" readonly><div class="help">Görüntüleme yetkiniz yok.</div></div><?php endif; ?>
      </div></div></div>
      <div class="card"><div class="card-head"><h3><i class="bi bi-telephone"></i>İletişim</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::text('phone', 'Telefon', ['value' => $r['phone'] ?? '', 'col' => 'c4', 'placeholder' => '05xx xxx xx xx']) ?>
        <?= Form::text('phone2', 'İkinci telefon', ['value' => $r['phone2'] ?? '', 'col' => 'c4']) ?>
        <?= Form::email('email', 'E-posta', ['value' => $r['email'] ?? '', 'col' => 'c4', 'help' => 'Sakin alanı hesabı bu adrese açılır']) ?>
        <?= Form::text('emergency_name', 'Acil durum kişisi', ['value' => $r['emergency_name'] ?? '', 'col' => 'c6']) ?>
        <?= Form::text('emergency_phone', 'Acil durum telefonu', ['value' => $r['emergency_phone'] ?? '', 'col' => 'c6']) ?>
        <?= Form::checkbox('contact_consent', 'SMS / e-posta ile bilgilendirme izni verdi (KVKK)', ['checked' => (bool) ($r['contact_consent'] ?? false)]) ?>
        <?= Form::textarea('notes', 'Notlar', ['value' => $r['notes'] ?? '', 'rows' => 2]) ?>
      </div></div></div>
    </div>
    <div class="stack">
      <?php if (!$row): ?>
      <div class="card"><div class="card-head"><h3><i class="bi bi-door-open"></i>Bağımsız bölüme bağla</h3><span class="small text-muted">isteğe bağlı</span></div><div class="card-body"><div class="form-grid">
        <?= Form::select('unit_id', 'Bağımsız bölüm', $units, ['value' => $preUnit ?: '', 'placeholder' => 'Şimdilik bağlama']) ?>
        <?= Form::select('occ_role', 'Sıfat', $occupancy_roles, ['value' => 'malik', 'col' => 'c6']) ?>
        <?= Form::select('occ_liability', 'Borç sorumluluğu', $liability_modes, ['value' => 'malik', 'col' => 'c6']) ?>
        <?= Form::date('occ_start', 'Başlangıç tarihi', ['value' => date('Y-m-d'), 'col' => 'c6']) ?>
      </div></div></div>
      <?php endif; ?>
      <div class="alert info"><i class="bi bi-shield-check"></i><div><strong>Kişisel veri notu.</strong> TCKN/VKN ve telefon yalnızca yönetim amacıyla saklanır; sakin ekranında başka kişilerin bilgileri gösterilmez.</div></div>
    </div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= e($row ? route('people.show', ['id' => $row['id']]) : route('people.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i><?= $row ? 'Güncelle' : 'Kaydet' ?></button></div>
</form>
