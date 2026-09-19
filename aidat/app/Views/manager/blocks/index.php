<?php use Aidat\Core\Form; $canManage = can('buildings.manage'); ?>
<?= $this->partial('partials.page-head', ['title' => 'Bloklar ve aidat grupları', 'desc' => 'Bloklar bağımsız bölümleri gruplar; aidat grupları farklı tarifeyle borçlandırma sağlar (ör. dükkanlar, çatı katları).']) ?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h3><i class="bi bi-building"></i>Bloklar</h3><span class="small text-muted"><?= $noBlock ?> bölüm bloksuz</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Blok</th><th>Kod</th><th class="num">Kat</th><th class="num">Bölüm</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($blocks as $b): ?>
        <tr x-data="{edit:false}">
          <td colspan="5" x-show="edit" x-cloak>
            <form method="post" action="<?= e(route('blocks.update', ['id' => $b['id']])) ?>" class="flex gap-2 wrap center"><?= csrf_field() ?>
              <input class="input" name="name" value="<?= e($b['name']) ?>" style="width:160px" required><input class="input" name="code" value="<?= e($b['code']) ?>" placeholder="Kod" style="width:90px"><input class="input num" name="floors" type="number" value="<?= e($b['floors']) ?>" placeholder="Kat" style="width:80px"><input class="input" name="description" value="<?= e($b['description']) ?>" placeholder="Açıklama" style="width:180px">
              <button class="btn btn-sm btn-primary">Kaydet</button><button type="button" class="btn btn-sm btn-ghost" @click="edit=false">Vazgeç</button>
            </form>
          </td>
          <td x-show="!edit" class="primary-cell"><?= e($b['name']) ?><?php if ($b['description']): ?><div class="sub-cell"><?= e($b['description']) ?></div><?php endif; ?></td>
          <td x-show="!edit"><span class="badge"><?= e($b['code'] ?: '—') ?></span></td>
          <td x-show="!edit" class="num"><?= e($b['floors'] ?? '—') ?></td>
          <td x-show="!edit" class="num"><a href="<?= e(route('units.index', ['blok' => $b['id']])) ?>"><?= $b['unit_count'] ?></a></td>
          <td x-show="!edit"><?php if ($canManage): ?><div class="row-actions"><button class="btn btn-sm btn-ghost" @click="edit=true" title="Düzenle"><i class="bi bi-pencil"></i></button><form method="post" action="<?= e(route('blocks.destroy', ['id' => $b['id']])) ?>" x-data="confirmForm('Blok silinsin mi?')" @submit="submit($event)"><?= csrf_field() ?><button class="btn btn-sm btn-ghost text-bad" title="Sil" <?= $b['unit_count'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form></div><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($blocks === []): ?><tr><td colspan="5" class="text-muted centered" style="padding:24px">Blok yok. Tek bloklu apartmanlarda blok tanımlamak zorunlu değildir.</td></tr><?php endif; ?>
      </tbody></table></div>
    <?php if ($canManage): ?>
    <form class="card-foot" method="post" action="<?= e(route('blocks.store')) ?>" style="justify-content:flex-start"><?= csrf_field() ?>
      <input class="input" name="name" placeholder="Blok adı (A Blok)" required style="width:160px"><input class="input" name="code" placeholder="Kod" style="width:80px"><input class="input num" name="floors" type="number" min="0" placeholder="Kat" style="width:80px"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i>Blok ekle</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3><i class="bi bi-tags"></i>Aidat grupları</h3></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Grup</th><th>Açıklama</th><th class="num">Bölüm</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($groups as $g): ?>
        <tr><td class="primary-cell"><?= e($g['name']) ?></td><td class="text-muted"><?= e($g['description']) ?></td><td class="num"><?= $g['unit_count'] ?></td><td><?php if ($canManage): ?><form method="post" action="<?= e(route('fee_groups.destroy', ['id' => $g['id']])) ?>" x-data="confirmForm('Grup silinsin mi? Bölümlerin grup bağı kaldırılır.')" @submit="submit($event)" class="row-actions"><?= csrf_field() ?><button class="btn btn-sm btn-ghost text-bad"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if ($groups === []): ?><tr><td colspan="4" class="text-muted centered" style="padding:24px">Grup yok. "Aidat grubuna göre" dağıtım için grup tanımlayın (örn. Daire, Dükkan).</td></tr><?php endif; ?>
      </tbody></table></div>
    <?php if ($canManage): ?>
    <form class="card-foot" method="post" action="<?= e(route('fee_groups.store')) ?>" style="justify-content:flex-start"><?= csrf_field() ?>
      <input class="input" name="name" placeholder="Grup adı (Dükkan)" required style="width:160px"><input class="input" name="description" placeholder="Açıklama" style="width:200px"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i>Grup ekle</button>
    </form>
    <?php endif; ?>
  </div>
</div>
