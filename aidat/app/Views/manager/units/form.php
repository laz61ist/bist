<?php use Aidat\Core\Form; $r = $row ?? []; ?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Bağımsız bölümler', route('units.index')], [$row ? 'Düzenle' : 'Yeni']]]) ?>
<form method="post" action="<?= e($row ? route('units.update', ['id' => $row['id']]) : route('units.store')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="card"><div class="card-body"><div class="form-grid">
    <?= Form::select('block_id', 'Blok', $blocks, ['value' => $r['block_id'] ?? '', 'col' => 'c4', 'placeholder' => 'Blok yok / tek blok']) ?>
    <?= Form::text('door_no', 'Kapı no', ['required' => true, 'value' => $r['door_no'] ?? '', 'col' => 'c4', 'placeholder' => '12 veya 12A']) ?>
    <?= Form::text('floor', 'Kat', ['value' => $r['floor'] ?? '', 'col' => 'c4', 'placeholder' => 'Zemin, 1, 2…']) ?>
    <?= Form::select('type', 'Tür', $unit_types, ['required' => true, 'value' => $r['type'] ?? 'daire', 'col' => 'c4']) ?>
    <?= Form::select('status', 'Durum', $unit_statuses, ['required' => true, 'value' => $r['status'] ?? 'dolu', 'col' => 'c4']) ?>
    <?= Form::select('fee_group_id', 'Aidat grubu', $groups, ['value' => $r['fee_group_id'] ?? '', 'col' => 'c4', 'placeholder' => 'Grup yok', 'help' => 'Gruba göre dağıtımda kullanılır']) ?>
    <?= Form::number('gross_m2', 'Brüt m²', ['value' => $r['gross_m2'] ?? '', 'col' => 'c4', 'step' => '0.01', 'min' => 0]) ?>
    <?= Form::number('net_m2', 'Net m²', ['value' => $r['net_m2'] ?? '', 'col' => 'c4', 'step' => '0.01', 'min' => 0]) ?>
    <?= Form::number('land_share', 'Arsa payı', ['value' => $r['land_share'] ?? '', 'col' => 'c4', 'step' => '0.0001', 'min' => 0, 'help' => 'Tapudaki pay (örn. 12 / 480 için 12)']) ?>
    <?= Form::radioCards('liability_mode', 'Borç sorumluluğu (varsayılan)', ['malik' => ['Malik öder', 'Aidat borcu malike yazılır'], 'kiraci' => ['Kiracı öder', 'Kiracı yoksa malik'], 'paylasimli' => ['Paylaşımlı', 'Plan bazında belirlenir']], ['value' => $r['liability_mode'] ?? 'malik']) ?>
    <?= Form::checkbox('late_fee_exempt', 'Gecikme tazminatından muaf', ['checked' => (bool) ($r['late_fee_exempt'] ?? false), 'help' => 'Yönetim kurulu kararıyla muaf tutulan bölümler için']) ?>
    <?= Form::textarea('notes', 'Notlar', ['value' => $r['notes'] ?? '', 'rows' => 2]) ?>
  </div></div>
  <div class="card-foot"><a class="btn" href="<?= e($row ? route('units.show', ['id' => $row['id']]) : route('units.index')) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i><?= $row ? 'Güncelle' : 'Kaydet' ?></button></div></div>
</form>
