<?php $f = $filters; ?>
<?= $this->partial('partials.page-head', ['title' => 'Personel', 'desc' => $summary['active'] . ' aktif personel · aylık brüt bordro ' . money($summary['payroll']), 'actions' => '<a class="btn btn-primary" href="' . e(route('staff.create')) . '"><i class="bi bi-person-plus"></i>Yeni personel</a>']) ?>
<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <select class="select" name="durum"><option value="aktif" <?= $f['durum'] === 'aktif' ? 'selected' : '' ?>>Aktif personel</option><option value="pasif" <?= $f['durum'] === 'pasif' ? 'selected' : '' ?>>Ayrılanlar</option><option value="tumu" <?= $f['durum'] === 'tumu' ? 'selected' : '' ?>>Tümü</option></select>
    <span class="spacer"></span><span class="count"><?= count($rows) ?> kayıt</span>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Personel</th><th>Görev</th><th class="hide-sm">İletişim</th><th class="hide-sm">TCKN / SGK</th><th>Başlangıç</th><th class="num">Maaş</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr data-href="<?= e(route('staff.edit', ['id' => $s['id']])) ?>" class="<?= (int) $s['is_active'] === 1 ? '' : 'is-muted' ?>">
        <td><div class="flex center gap-3"><span class="avatar"><?= e(\Aidat\Core\Str::initials($s['full_name'])) ?></span><div><div class="primary-cell"><?= e($s['full_name']) ?></div><?php if ($s['notes']): ?><div class="sub-cell"><?= e(\Aidat\Core\Str::limit((string) $s['notes'], 50)) ?></div><?php endif; ?></div></div></td>
        <td><?= e(list_label('staff_positions', $s['position'])) ?></td>
        <td class="small hide-sm"><?= $s['phone'] ? '<div class="mono">' . e(\Aidat\Core\Str::formatPhone($s['phone'])) . '</div>' : '' ?><?= $s['email'] ? '<div class="text-muted">' . e($s['email']) . '</div>' : '' ?><?= !$s['phone'] && !$s['email'] ? '<span class="text-muted">—</span>' : '' ?></td>
        <td class="small mono hide-sm"><?= $s['identity_no'] ? e(\Aidat\Core\Str::maskIdentity($s['identity_no'])) : '—' ?><?= $s['sgk_no'] ? '<div class="text-muted">SGK ' . e($s['sgk_no']) . '</div>' : '' ?></td>
        <td class="small"><?= $s['start_date'] ? e(tr_date($s['start_date'])) : '—' ?><?= $s['end_date'] ? '<div class="text-muted">→ ' . e(tr_date($s['end_date'])) . '</div>' : '' ?></td>
        <td class="num"><?= (int) $s['salary'] > 0 ? e(money($s['salary'])) : '<span class="text-muted">—</span>' ?></td>
        <td><span class="pill <?= (int) $s['is_active'] === 1 ? 'ok' : 'neutral' ?>"><?= (int) $s['is_active'] === 1 ? 'Aktif' : 'Ayrıldı' ?></span></td>
        <td><div class="row-actions"><a class="btn btn-sm btn-ghost" href="<?= e(route('staff.edit', ['id' => $s['id']])) ?>" title="Düzenle"><i class="bi bi-pencil"></i></a></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="8"><?= $this->partial('partials.empty', ['icon' => 'person-badge', 'title' => 'Personel kaydı yok', 'text' => $f['durum'] === 'pasif' ? 'Ayrılan personel bulunmuyor.' : 'Kapıcı, güvenlik ve temizlik görevlilerini kaydedin; maaş giderlerini periyodik gider olarak tanımlayabilirsiniz.', 'action' => '<a class="btn btn-primary" href="' . e(route('staff.create')) . '"><i class="bi bi-person-plus"></i>Yeni personel</a>']) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows !== [] && $f['durum'] === 'aktif'): ?><tfoot><tr><td colspan="5">Aylık toplam</td><td class="num"><?= e(money($summary['payroll'])) ?></td><td colspan="2"></td></tr></tfoot><?php endif; ?>
  </table></div>
</div>
