<?php use Aidat\Core\Form; $r = $row ?? []; $isEdit = !empty($planId);
$oldGroups = old('group_amounts');
$oldGroups = is_array($oldGroups) ? $oldGroups : null;
$init = [
    'distribution' => (string) old('distribution', $r['distribution'] ?? 'esit'),
    'recurrence' => (string) old('recurrence', $r['recurrence'] ?? 'tek'),
    'vat' => (string) old('vat_mode', $r['vat_mode'] ?? 'yok'),
];
?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Tahakkuk planları', route('plans.index')], [$isEdit ? 'Düzenle' : 'Yeni']], 'desc' => 'Plan önce önizlenir, taslak olarak kaydedilir; onaylanıp işlendiğinde borç kayıtları oluşur.']) ?>
<form method="post" action="<?= e(route('plans.preview')) ?>" novalidate x-data='<?= e(json_encode($init, JSON_UNESCAPED_UNICODE)) ?>'>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><?= Form::hidden('plan_id', $planId) ?><?php endif; ?>
  <div class="grid grid-main">
    <div class="stack">
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-journal-text"></i>Plan bilgileri</h3></div>
        <div class="card-body"><div class="form-grid">
          <?= Form::text('name', 'Plan adı', ['required' => true, 'value' => $r['name'] ?? '', 'col' => 'c8', 'placeholder' => 'Örn. 2026 Ekim aidatı', 'help' => 'Borç başlığında "Plan adı · Dönem" olarak görünür']) ?>
          <?= Form::select('charge_type', 'Borç türü', $charge_types, ['required' => true, 'value' => $r['charge_type'] ?? 'aidat', 'col' => 'c4']) ?>
          <?= Form::select('block_id', 'Blok kapsamı', $blocks, ['value' => $r['block_id'] ?? '', 'col' => 'c6', 'placeholder' => 'Tüm bloklar']) ?>
          <?= Form::select('fee_group_id', 'Aidat grubu kapsamı', $groups, ['value' => $r['fee_group_id'] ?? '', 'col' => 'c6', 'placeholder' => 'Tüm gruplar']) ?>
          <?= Form::month('period', 'Dönem', ['required' => true, 'value' => $r['period'] ?? $defaults['period'], 'col' => 'c4']) ?>
          <?= Form::date('due_date', 'Vade tarihi', ['required' => true, 'value' => $r['due_date'] ?? $defaults['due_date'], 'col' => 'c4', 'help' => 'Tekrarlı planlarda sonraki dönemler aynı güne düşer']) ?>
          <?= Form::select('recurrence', 'Tekrar', $recurrences, ['required' => true, 'value' => $r['recurrence'] ?? 'tek', 'col' => 'c4', 'attrs' => 'x-model="recurrence"']) ?>
          <div class="field c4" x-show="recurrence !== 'tek'" x-cloak><?= Form::month('repeat_until', 'Bitiş dönemi', ['value' => $r['repeat_until'] ?? '', 'help' => 'Boşsa iptal edilene kadar sürer']) ?></div>
        </div></div>
      </div>

      <div class="card">
        <div class="card-head"><div><h3><i class="bi bi-diagram-3"></i>Dağıtım</h3><div class="sub">Tutarların bölümlere nasıl paylaştırılacağı</div></div></div>
        <div class="card-body"><div class="form-grid">
          <?= Form::radioCards('distribution', 'Dağıtım yöntemi', [
              'esit' => ['Eşit', 'Toplam, dahil bölümlere eşit bölünür'],
              'm2' => ['m² oranında', 'Brüt m² (yoksa net m²) ağırlıklı'],
              'arsa_payi' => ['Arsa payı oranında', 'Tapudaki arsa payı ağırlıklı'],
              'sabit' => ['Sabit tutar', 'Her bölüme aynı tutar'],
              'grup' => ['Aidat grubuna göre', 'Her grup için ayrı tutar'],
              'manuel' => ['Manuel', 'Önizlemede bölüm bazında girilir'],
          ], ['value' => $r['distribution'] ?? 'esit', 'attrs' => 'x-model="distribution"']) ?>
          <div class="field c6" x-show="['esit','m2','arsa_payi'].includes(distribution)" x-cloak><?= Form::money('total_amount', 'Toplam tutar', ['value' => $r['total_amount'] ?? '', 'help' => 'Yuvarlama farkı son satıra eklenir; toplam korunur']) ?></div>
          <div class="field c6" x-show="distribution === 'sabit'" x-cloak><?= Form::money('unit_amount', 'Birim tutar (bölüm başına)', ['value' => $r['unit_amount'] ?? '']) ?></div>
          <div class="field" x-show="distribution === 'grup'" x-cloak>
            <?php if ($groups === []): ?>
              <div class="alert warn"><i class="bi bi-exclamation-triangle"></i><div>Tanımlı aidat grubu yok. Önce <a href="<?= e(route('blocks.index')) ?>">Bloklar ve gruplar</a> sayfasından grup oluşturun.</div></div>
            <?php else: ?>
              <span class="field-label">Grup tutarları</span>
              <div class="form-grid">
                <?php foreach ($groups as $gid => $gname): $gv = $oldGroups[$gid] ?? ($r['group_amounts'][$gid] ?? ($r['group_amounts'][(string) $gid] ?? '')); ?>
                  <?= Form::money('group_amounts[' . $gid . ']', $gname, ['value' => $gv, 'raw' => $oldGroups !== null, 'col' => 'c4', 'no_old' => true]) ?>
                <?php endforeach; ?>
              </div>
              <?php if (error_for('group_amounts')): ?><div class="err"><i class="bi bi-exclamation-circle"></i><?= e(error_for('group_amounts')) ?></div><?php else: ?><div class="help">Grubu olmayan bölümlere 0 ₺ yazılır; önizlemede hariç tutabilirsiniz.</div><?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="field" x-show="distribution === 'manuel'" x-cloak><div class="alert info"><i class="bi bi-info-circle"></i><div>Manuel dağıtımda tutarlar bir sonraki adımda (önizleme) bölüm bazında girilir.</div></div></div>
        </div></div>
      </div>

      <div class="card">
        <div class="card-head"><h3><i class="bi bi-person-check"></i>Sorumluluk ve KDV</h3></div>
        <div class="card-body"><div class="form-grid">
          <?= Form::radioCards('liability', 'Borç kime yazılsın?', [
              'bolum' => ['Bölümün kendi ayarı', 'Her bölümün borç sorumluluğu ayarına göre'],
              'malik' => ['Malik öder', 'Tüm satırlar malike'],
              'kiraci' => ['Kiracı öder', 'Kiracı yoksa malik'],
              'paylasimli' => ['Paylaşımlı', 'Kayıt malik üzerine açılır'],
          ], ['value' => $r['liability'] ?? 'bolum']) ?>
          <?= Form::select('vat_mode', 'KDV', $vat_modes, ['required' => true, 'value' => $r['vat_mode'] ?? 'yok', 'col' => 'c4', 'attrs' => 'x-model="vat"']) ?>
          <div class="field c4" x-show="vat !== 'yok'" x-cloak><?= Form::number('vat_rate', 'KDV oranı (%)', ['value' => $r['vat_rate'] ?? '', 'step' => '0.1', 'min' => 0, 'max' => 100, 'help' => 'KDV hariç girildiyse tutarlara eklenir']) ?></div>
          <?= Form::textarea('description', 'Açıklama', ['value' => $r['description'] ?? '', 'rows' => 2, 'help' => 'Borç kaydının açıklamasına kopyalanır']) ?>
        </div></div>
        <div class="card-foot"><a class="btn" href="<?= e($isEdit ? route('plans.show', ['id' => $planId]) : route('plans.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-eye"></i>Önizle</button></div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-signpost-split"></i>Akış</h3></div>
        <div class="card-body">
          <ul class="timeline">
            <li><span class="pt ok"></span><div class="tt">1. Önizle</div><div class="tm">Bölüm listesi ve tutarlar sunucuda hesaplanır; bölümleri hariç tutabilirsiniz.</div></li>
            <li><span class="pt"></span><div class="tt">2. Taslak kaydet</div><div class="tm">Taslak plan düzenlenebilir, borç oluşturmaz.</div></li>
            <li><span class="pt"></span><div class="tt">3. Onayla ve işle</div><div class="tm">İşlendiğinde her bölüme borç kaydı açılır; avanslar otomatik mahsup edilir.</div></li>
          </ul>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-lightbulb"></i>İpuçları</h3></div>
        <div class="card-body small text-2">
          <p>• Aylık aidat için <strong>Tekrar: Aylık</strong> seçin; her ay "Sonraki dönemi işle" ile tek tıkla tahakkuk oluşur (mükerrer dönem engellenir).</p>
          <p class="mt-2">• m² ve arsa payı dağıtımında bilgisi eksik bölümler önizlemede uyarı verir; <a href="<?= e(route('units.index')) ?>">bölüm kartından</a> tamamlayın.</p>
          <p class="mt-2">• Kapalı dönemlere tahakkuk işlenemez.</p>
        </div>
      </div>
    </div>
  </div>
</form>
