<?php $canManage = can('settings.manage'); ?>
<?= $this->partial('partials.page-head', ['title' => 'Gider ve gelir kategorileri', 'desc' => $summary['expense'] . ' gider kategorisi (' . $summary['global'] . ' genel) · ' . $summary['income'] . ' gelir kategorisi. Genel kategoriler salt okunurdur; kullanımda olan kategori silinmez, pasife alınır.', 'crumbs' => [['Giderler', route('expenses.index')], ['Kategoriler']]]) ?>
<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><div><h3><i class="bi bi-diagram-3"></i>Gider kategorileri</h3><div class="sub">Ana kategori › alt kategori · kullanım: gider + periyodik + bütçe kalemi</div></div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Kategori</th><th>Durum</th><th class="num">Kullanım</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tree as $parent): $pUsage = $parent['usage'] + array_sum(array_column($parent['children'], 'usage')); ?>
        <tr x-data="{edit:false, add:false}" style="background:var(--surface-2)">
          <td>
            <div x-show="!edit" class="primary-cell"><i class="bi bi-folder2<?= $parent['children'] !== [] ? '-open' : '' ?> text-muted"></i> <?= e($parent['name']) ?><?php if (!$parent['own']): ?> <span class="badge" title="Genel kategori · salt okunur">genel</span><?php endif; ?></div>
            <?php if ($canManage && $parent['own']): ?>
            <form x-show="edit" x-cloak method="post" action="<?= e(route('categories.update', ['id' => $parent['id']])) ?>" class="flex gap-2 center"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><input class="input" name="name" value="<?= e($parent['name']) ?>" required style="width:220px;height:32px"><button class="btn btn-sm btn-primary">Kaydet</button><button type="button" class="btn btn-sm btn-ghost" @click="edit=false">Vazgeç</button></form>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <form x-show="add" x-cloak method="post" action="<?= e(route('categories.store')) ?>" class="flex gap-2 center mt-2"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><input type="hidden" name="parent_id" value="<?= $parent['id'] ?>"><input class="input" name="name" placeholder="Alt kategori adı" required style="width:220px;height:32px"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i>Ekle</button><button type="button" class="btn btn-sm btn-ghost" @click="add=false">Vazgeç</button></form>
            <?php endif; ?>
          </td>
          <td><span class="pill <?= $parent['is_active'] ? 'ok' : 'neutral' ?> no-dot"><?= $parent['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
          <td class="num"><?= $pUsage > 0 ? '<a href="' . e(route('expenses.index', ['kategori' => $parent['id']])) . '">' . $pUsage . '</a>' : '<span class="text-muted">0</span>' ?></td>
          <td><?php if ($canManage): ?><div class="row-actions">
            <button type="button" class="btn btn-sm btn-ghost" @click="add=!add" title="Alt kategori ekle"><i class="bi bi-plus-square"></i></button>
            <?php if ($parent['own']): ?>
            <button type="button" class="btn btn-sm btn-ghost" @click="edit=!edit" title="Yeniden adlandır"><i class="bi bi-pencil"></i></button>
            <form method="post" action="<?= e(route('categories.update', ['id' => $parent['id']])) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><input type="hidden" name="is_active" value="<?= $parent['is_active'] ? 0 : 1 ?>"><button class="btn btn-sm btn-ghost" title="<?= $parent['is_active'] ? 'Pasife al' : 'Etkinleştir' ?>"><i class="bi bi-<?= $parent['is_active'] ? 'eye-slash' : 'eye' ?>"></i></button></form>
            <form method="post" action="<?= e(route('categories.destroy', ['id' => $parent['id']])) ?>" x-data="confirmForm('Kategori silinsin mi?')" @submit="submit($event)"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><button class="btn btn-sm btn-ghost text-bad" title="Sil" <?= $pUsage > 0 || $parent['children'] !== [] ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
          </div><?php endif; ?></td>
        </tr>
        <?php foreach ($parent['children'] as $c): ?>
        <tr x-data="{edit:false}" class="<?= $c['is_active'] ? '' : 'is-muted' ?>">
          <td style="padding-left:36px">
            <span x-show="!edit"><i class="bi bi-arrow-return-right text-muted small"></i> <?= e($c['name']) ?><?php if (!$c['own']): ?> <span class="badge" title="Genel kategori · salt okunur">genel</span><?php endif; ?></span>
            <?php if ($canManage && $c['own']): ?>
            <form x-show="edit" x-cloak method="post" action="<?= e(route('categories.update', ['id' => $c['id']])) ?>" class="flex gap-2 center"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><input class="input" name="name" value="<?= e($c['name']) ?>" required style="width:220px;height:32px"><button class="btn btn-sm btn-primary">Kaydet</button><button type="button" class="btn btn-sm btn-ghost" @click="edit=false">Vazgeç</button></form>
            <?php endif; ?>
          </td>
          <td><span class="pill <?= $c['is_active'] ? 'ok' : 'neutral' ?> no-dot"><?= $c['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
          <td class="num"><?= $c['usage'] > 0 ? '<a href="' . e(route('expenses.index', ['kategori' => $c['id']])) . '">' . $c['usage'] . '</a>' : '<span class="text-muted">0</span>' ?></td>
          <td><?php if ($canManage && $c['own']): ?><div class="row-actions">
            <button type="button" class="btn btn-sm btn-ghost" @click="edit=!edit" title="Yeniden adlandır"><i class="bi bi-pencil"></i></button>
            <form method="post" action="<?= e(route('categories.update', ['id' => $c['id']])) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><input type="hidden" name="is_active" value="<?= $c['is_active'] ? 0 : 1 ?>"><button class="btn btn-sm btn-ghost" title="<?= $c['is_active'] ? 'Pasife al' : 'Etkinleştir' ?>"><i class="bi bi-<?= $c['is_active'] ? 'eye-slash' : 'eye' ?>"></i></button></form>
            <form method="post" action="<?= e(route('categories.destroy', ['id' => $c['id']])) ?>" x-data="confirmForm('Kategori silinsin mi?')" @submit="submit($event)"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><button class="btn btn-sm btn-ghost text-bad" title="Sil" <?= $c['usage'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form>
          </div><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if ($tree === []): ?><tr><td colspan="4"><?= $this->partial('partials.empty', ['icon' => 'diagram-3', 'title' => 'Gider kategorisi yok', 'text' => 'Aşağıdaki formla ana kategori ekleyin; ilk gider kaydında varsayılan set de otomatik oluşturulur.']) ?></td></tr><?php endif; ?>
      </tbody></table></div>
    <?php if ($canManage): ?>
    <form class="card-foot" method="post" action="<?= e(route('categories.store')) ?>" style="justify-content:flex-start"><?= csrf_field() ?><input type="hidden" name="kind" value="gider"><input type="hidden" name="parent_id" value="">
      <input class="input" name="name" placeholder="Yeni ana kategori (örn. Otopark)" required style="width:260px"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i>Ana kategori ekle</button>
      <span class="small text-muted">Alt kategori için satırdaki <i class="bi bi-plus-square"></i> düğmesini kullanın.</span>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h3><i class="bi bi-cart-plus"></i>Gelir kategorileri</h3><div class="sub">Aidat dışı gelirler için</div></div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Kategori</th><th>Durum</th><th class="num">Kullanım</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($incomeCats as $c): ?>
        <tr x-data="{edit:false}" class="<?= $c['is_active'] ? '' : 'is-muted' ?>">
          <td>
            <span x-show="!edit" class="primary-cell"><?= e($c['name']) ?><?php if (!$c['own']): ?> <span class="badge">genel</span><?php endif; ?></span>
            <?php if ($canManage && $c['own']): ?>
            <form x-show="edit" x-cloak method="post" action="<?= e(route('categories.update', ['id' => $c['id']])) ?>" class="flex gap-2 center"><?= csrf_field() ?><input type="hidden" name="kind" value="gelir"><input class="input" name="name" value="<?= e($c['name']) ?>" required style="width:200px;height:32px"><button class="btn btn-sm btn-primary">Kaydet</button><button type="button" class="btn btn-sm btn-ghost" @click="edit=false">Vazgeç</button></form>
            <?php endif; ?>
          </td>
          <td><span class="pill <?= $c['is_active'] ? 'ok' : 'neutral' ?> no-dot"><?= $c['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
          <td class="num"><?= $c['usage'] > 0 ? '<a href="' . e(route('incomes.index', ['kategori' => $c['id']])) . '">' . $c['usage'] . '</a>' : '<span class="text-muted">0</span>' ?></td>
          <td><?php if ($canManage && $c['own']): ?><div class="row-actions">
            <button type="button" class="btn btn-sm btn-ghost" @click="edit=!edit" title="Yeniden adlandır"><i class="bi bi-pencil"></i></button>
            <form method="post" action="<?= e(route('categories.update', ['id' => $c['id']])) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="gelir"><input type="hidden" name="is_active" value="<?= $c['is_active'] ? 0 : 1 ?>"><button class="btn btn-sm btn-ghost" title="<?= $c['is_active'] ? 'Pasife al' : 'Etkinleştir' ?>"><i class="bi bi-<?= $c['is_active'] ? 'eye-slash' : 'eye' ?>"></i></button></form>
            <form method="post" action="<?= e(route('categories.destroy', ['id' => $c['id']])) ?>" x-data="confirmForm('Kategori silinsin mi?')" @submit="submit($event)"><?= csrf_field() ?><input type="hidden" name="kind" value="gelir"><button class="btn btn-sm btn-ghost text-bad" title="Sil" <?= $c['usage'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form>
          </div><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($incomeCats === []): ?><tr><td colspan="4"><?= $this->partial('partials.empty', ['icon' => 'cart-plus', 'title' => 'Gelir kategorisi yok', 'text' => 'Kira, reklam, faiz gibi gelir türlerini ekleyin.']) ?></td></tr><?php endif; ?>
      </tbody></table></div>
    <?php if ($canManage): ?>
    <form class="card-foot" method="post" action="<?= e(route('categories.store')) ?>" style="justify-content:flex-start"><?= csrf_field() ?><input type="hidden" name="kind" value="gelir">
      <input class="input" name="name" placeholder="Yeni gelir kategorisi" required style="width:220px"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i>Ekle</button>
    </form>
    <?php endif; ?>
  </div>
</div>
