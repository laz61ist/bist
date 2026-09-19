<?php use Aidat\Core\Form; $r = $row ?? []; $unitValue = old('unit_id', $r['unit_id'] ?? ($preUnit ?: '')); ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Talepler', route('requests.index')], ['Yeni']]]) ?>
<form method="post" action="<?= e(route('requests.store')) ?>" enctype="multipart/form-data" novalidate x-data="{unit:'<?= e((string) $unitValue) ?>', map:<?= e(json_encode((object) $unitPerson, JSON_UNESCAPED_UNICODE)) ?>, pick(){ const p = this.map[this.unit]; if (p) { $refs.person.value = String(p); } }}">
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-tools"></i>Talep</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::select('category', 'Kategori', $request_categories, ['required' => true, 'value' => $r['category'] ?? 'ariza', 'col' => 'c6']) ?>
        <?= Form::select('priority', 'Öncelik', $request_priorities, ['required' => true, 'value' => $r['priority'] ?? 'normal', 'col' => 'c6']) ?>
        <?= Form::text('title', 'Başlık', ['required' => true, 'value' => $r['title'] ?? '', 'placeholder' => 'Örn. A Blok asansör 3. katta takılıyor', 'maxlength' => 150]) ?>
        <?= Form::textarea('description', 'Açıklama', ['value' => $r['description'] ?? '', 'rows' => 4, 'placeholder' => 'Ne zaman, nerede, nasıl?']) ?>
        <?= Form::text('location', 'Konum', ['value' => $r['location'] ?? '', 'col' => 'c6', 'placeholder' => 'Otopark, çatı, 3. kat koridoru…']) ?>
        <?= Form::file('photo', 'Fotoğraf / belge', ['col' => 'c6', 'hint' => 'JPG, PNG veya PDF · en fazla 5 MB']) ?>
      </div></div></div>
      <div class="card"><div class="card-head"><h3><i class="bi bi-door-open"></i>Kaynak</h3><span class="small text-muted">isteğe bağlı</span></div><div class="card-body"><div class="form-grid">
        <?= Form::select('unit_id', 'Bağımsız bölüm', $units, ['value' => $unitValue, 'col' => 'c6', 'placeholder' => 'Ortak alan / bölüm yok', 'attrs' => 'x-model="unit" @change="pick()"']) ?>
        <?= Form::select('person_id', 'Talep sahibi', $people, ['value' => $r['person_id'] ?? '', 'col' => 'c6', 'placeholder' => 'Kişi seçilmedi', 'attrs' => 'x-ref="person"', 'help' => 'Bölüm seçilince aktif kiracı/malik otomatik gelir; durum değişikliği bu kişiye bildirilir.']) ?>
      </div></div></div>
    </div>
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-person-gear"></i>Atama ve takip</h3></div><div class="card-body"><div class="form-grid">
        <?= Form::select('assigned_to', 'Atanan görevli', $staff, ['value' => $r['assigned_to'] ?? '', 'placeholder' => 'Henüz atanmadı', 'help' => 'Atama yapılırsa durum "Atandı" olur ve görevliye bildirim gider.']) ?>
        <?= Form::date('target_date', 'Hedef tarih', ['value' => $r['target_date'] ?? '']) ?>
        <?= Form::radioCards('visibility', 'Görünürlük', ['ozel' => ['Özel', 'Talep sahibi ve yönetim'], 'herkes' => ['Herkese açık', 'Tüm sakinler görür']], ['value' => $r['visibility'] ?? 'ozel']) ?>
      </div></div></div>
      <div class="alert info"><i class="bi bi-info-circle"></i><div>Masraf ve gidere aktarma işlemi talep detayından yapılır. Tamamlanan taleplerde çözüm tarihi otomatik yazılır.</div></div>
    </div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= e(route('requests.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i>Kaydet</button></div>
</form>
