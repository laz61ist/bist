<?php use Aidat\Core\Form; ?>
<?= $this->partial('partials.page-head', ['title' => 'Toplu bölüm oluştur', 'crumbs' => [['Bağımsız bölümler', route('units.index')], ['Toplu oluştur']], 'desc' => 'Bir numara aralığını tek adımda oluşturur. Var olan kapı numaraları atlanır; kat bilgisi "kat başına bölüm" ile hesaplanır.']) ?>
<form method="post" action="<?= e(route('units.bulk_store')) ?>" novalidate x-data="{s:<?= (int) old('start_no', 1) ?>, e:<?= (int) old('end_no', 20) ?>, pf:<?= (int) old('per_floor', 4) ?>, ff:<?= (int) old('first_floor', 1) ?>}">
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="card"><div class="card-body"><div class="form-grid">
      <?= Form::select('block_id', 'Blok', $blocks, ['value' => old('block_id', ''), 'col' => 'c6', 'placeholder' => 'Blok yok / tek blok']) ?>
      <?= Form::text('prefix', 'Kapı no ön eki', ['value' => old('prefix', ''), 'col' => 'c6', 'placeholder' => 'Boş bırakın veya A-', 'help' => 'Örn. "A-" → A-1, A-2…']) ?>
      <?= Form::number('start_no', 'Başlangıç no', ['required' => true, 'value' => old('start_no', 1), 'col' => 'c3', 'min' => 1, 'attrs' => 'x-model.number="s"']) ?>
      <?= Form::number('end_no', 'Bitiş no', ['required' => true, 'value' => old('end_no', 20), 'col' => 'c3', 'min' => 1, 'attrs' => 'x-model.number="e"']) ?>
      <?= Form::number('per_floor', 'Kat başına bölüm', ['value' => old('per_floor', 4), 'col' => 'c3', 'min' => 1, 'attrs' => 'x-model.number="pf"', 'help' => 'Boşsa kat yazılmaz']) ?>
      <?= Form::number('first_floor', 'İlk kat', ['value' => old('first_floor', 1), 'col' => 'c3', 'attrs' => 'x-model.number="ff"', 'help' => 'Zemin için 0']) ?>
      <?= Form::select('type', 'Tür', $unit_types, ['required' => true, 'value' => old('type', 'daire'), 'col' => 'c4']) ?>
      <?= Form::number('gross_m2', 'Brüt m² (ortak)', ['value' => old('gross_m2', ''), 'col' => 'c4', 'step' => '0.01', 'help' => 'Sonradan bölüm bazında düzenlenebilir']) ?>
      <?= Form::number('land_share', 'Arsa payı (ortak)', ['value' => old('land_share', ''), 'col' => 'c4', 'step' => '0.0001']) ?>
    </div></div>
    <div class="card-foot"><a class="btn" href="<?= e(route('units.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-magic"></i><span x-text="Math.max(0, e - s + 1) + ' bölüm oluştur'"></span></button></div></div>
    <div class="card"><div class="card-head"><h3>Önizleme</h3></div><div class="card-body">
      <p class="small text-muted">İlk 12 kayıt:</p>
      <div class="flex wrap gap-2">
        <template x-for="i in Math.min(12, Math.max(0, e - s + 1))" :key="i"><span class="tag"><i class="bi bi-door-open"></i><span x-text="'No ' + (s + i - 1) + (pf > 0 ? ' · kat ' + (ff + Math.floor((i - 1) / pf)) : '')"></span></span></template>
      </div>
      <p class="small text-muted mt-3" x-show="e - s + 1 > 12" x-text="'… ve ' + (e - s + 1 - 12) + ' bölüm daha'"></p>
      <div class="alert warn mt-4"><i class="bi bi-exclamation-triangle"></i><div>Tek seferde en fazla 500 bölüm. Aynı blokta var olan kapı numaraları atlanır, üzerine yazılmaz.</div></div>
    </div></div>
  </div>
</form>
