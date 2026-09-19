<?php $f = $filters; ?>
<?= $this->partial('partials.page-head', ['title' => 'Kişiler', 'desc' => 'Malik, kiracı ve oturanlar. TCKN/VKN yalnızca yetkili kullanıcılara maskesiz gösterilir.', 'actions' => can('people.manage') ? '<a class="btn btn-primary" href="' . e(route('people.create')) . '"><i class="bi bi-person-plus"></i>Yeni kişi</a>' : '']) ?>
<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Ad, soyad, telefon, e-posta…">
    <select class="select" name="rol"><option value="">Tüm sıfatlar</option><?php foreach ($occupancy_roles as $k => $v): ?><option value="<?= $k ?>" <?= $f['rol'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Kişi</th><th>İletişim</th><th>Bağımsız bölümler</th><th class="num">Açık borç</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $name = $r['company_name'] ?: trim($r['first_name'] . ' ' . $r['last_name']); ?>
      <tr data-href="<?= e(route('people.show', ['id' => $r['id']])) ?>">
        <td><div class="flex center gap-3"><span class="avatar"><?= e(\Aidat\Core\Str::initials($name)) ?></span><div><div class="primary-cell"><?= e($name) ?></div><div class="sub-cell"><?= e(list_label('person_types', $r['type'])) ?><?= $r['user_id'] ? ' · <i class="bi bi-person-check text-ok"></i> hesap var' : '' ?></div></div></div></td>
        <td class="small"><?= $r['phone'] ? '<div class="mono">' . e(\Aidat\Core\Str::formatPhone($r['phone'])) . '</div>' : '' ?><?= $r['email'] ? '<div class="text-muted">' . e($r['email']) . '</div>' : '' ?></td>
        <td class="small"><?= $r['units_text'] ? e(str_replace(['(malik)', '(kiraci)', '(oturan)', '(vekil)'], ['· malik', '· kiracı', '· oturan', '· vekil'], $r['units_text'])) : '<span class="text-muted">Bağlı bölüm yok</span>' ?></td>
        <td class="num <?= $r['open_debt'] > 0 ? 'text-bad' : 'text-muted' ?>"><?= e(money($r['open_debt'])) ?></td>
        <td><div class="row-actions"><?php if (can('people.manage')): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('people.edit', ['id' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="5"><?= $this->partial('partials.empty', ['icon' => 'people', 'title' => 'Kişi bulunamadı', 'text' => 'Yeni kişi ekleyip bir bağımsız bölüme malik veya kiracı olarak bağlayın.', 'action' => can('people.manage') ? '<a class="btn btn-primary" href="' . e(route('people.create')) . '"><i class="bi bi-person-plus"></i>Yeni kişi</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
