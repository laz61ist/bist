<?php $u = $unit; ?>
<?= $this->partial('partials.page-head', ['title' => 'Hesap ekstresi', 'crumbs' => [['Bağımsız bölümler', route('units.index')], ['No ' . $u['door_no'], route('units.show', ['id' => $u['id']])], ['Ekstre']], 'desc' => $owner . ' · ' . tr_date($from) . ' – ' . tr_date($to), 'actions' => '<a class="btn" target="_blank" href="' . e(route('units.statement', ['id' => $u['id'], 'bas' => $from, 'bit' => $to, 'yazdir' => 1])) . '"><i class="bi bi-printer"></i>Yazdır / PDF</a>']) ?>
<div class="card">
  <form class="table-toolbar" method="get"><label class="small text-muted">Başlangıç</label><input type="date" class="input" name="bas" value="<?= e($from) ?>"><label class="small text-muted">Bitiş</label><input type="date" class="input" name="bit" value="<?= e($to) ?>"><button class="btn btn-sm"><i class="bi bi-funnel"></i>Uygula</button><span class="spacer"></span><span class="count">Devir: <?= e(money($st['opening'])) ?></span></form>
  <?= $this->partial('manager.units.statement-table', ['st' => $st]) ?>
</div>
