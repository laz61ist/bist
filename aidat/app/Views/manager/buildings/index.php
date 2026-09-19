<?= $this->partial('partials.page-head', ['title' => 'Yapılar', 'desc' => 'Yönettiğiniz apartman ve siteler. Üst çubuktan aktif yapıyı değiştirebilirsiniz.', 'actions' => $canCreate ? '<a class="btn btn-primary" href="' . e(route('buildings.create')) . '"><i class="bi bi-plus-lg"></i>Yeni yapı</a>' : '']) ?>
<div class="grid grid-3">
<?php foreach ($rows as $r): $active = (int) $r['id'] === current_building_id(); ?>
  <div class="card <?= $active ? 'accent-amber' : '' ?>">
    <div class="card-body">
      <div class="flex between center mb-3">
        <div><h3 style="font-size:17px"><?= e($r['name']) ?></h3><div class="small text-muted"><?= e(list_label('building_types', $r['type'])) ?><?= $r['district'] || $r['city'] ? ' · ' . e(trim(($r['district'] ?? '') . ' / ' . ($r['city'] ?? ''), ' /')) : '' ?></div></div>
        <?php if ($active): ?><span class="pill warn no-dot">Aktif</span><?php elseif (!$r['is_active']): ?><span class="pill neutral no-dot">Pasif</span><?php endif; ?>
      </div>
      <dl class="dl">
        <dt>Bölüm</dt><dd class="num" style="text-align:left"><?= $r['unit_count'] ?></dd>
        <dt>Kullanıcı</dt><dd class="num" style="text-align:left"><?= $r['user_count'] ?></dd>
        <dt>Kasa + banka</dt><dd class="num <?= $r['balance'] < 0 ? 'text-bad' : '' ?>" style="text-align:left"><?= e(money($r['balance'])) ?></dd>
        <dt>Açık borç</dt><dd class="num <?= $r['open_debt'] > 0 ? 'text-bad' : 'text-ok' ?>" style="text-align:left"><?= e(money($r['open_debt'])) ?></dd>
      </dl>
    </div>
    <div class="card-foot">
      <?php if (!$active): ?><form method="post" action="<?= e(route('buildings.switch', ['id' => $r['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm"><i class="bi bi-box-arrow-in-right"></i>Bu yapıya geç</button></form><?php else: ?><a class="btn btn-sm" href="<?= e(route('dashboard')) ?>"><i class="bi bi-grid-1x2"></i>Panel</a><?php endif; ?>
      <?php if (app()->gate()->allows('buildings.manage', (int) $r['id'])): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('buildings.edit', ['id' => $r['id']])) ?>"><i class="bi bi-pencil"></i>Düzenle</a><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if ($rows === []): ?><div class="card span-3"><?= $this->partial('partials.empty', ['icon' => 'buildings', 'title' => 'Henüz yapı yok', 'text' => 'İlk apartman veya sitenizi ekleyin; kasa hesabı ve gider kategorileri otomatik oluşturulur.', 'action' => '<a class="btn btn-primary" href="' . e(route('buildings.create')) . '"><i class="bi bi-plus-lg"></i>Yapı oluştur</a>']) ?></div><?php endif; ?>
</div>
