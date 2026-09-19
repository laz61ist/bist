<?php $f = $filters; $hasFilter = $f['q'] !== '' || $f['durum'] !== '' || $f['kategori'] !== '' || $f['oncelik'] !== '' || $f['atanan']; ?>
<?= $this->partial('partials.page-head', ['title' => 'Talepler ve iş emirleri', 'desc' => 'Arıza, şikayet ve öneriler; atama, hedef tarih ve masraf takibi.', 'actions' => can('requests.manage') ? '<a class="btn btn-primary" href="' . e(route('requests.create')) . '"><i class="bi bi-plus-lg"></i>Yeni talep</a>' : '']) ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k">Açık talep</div><div class="v"><?= $summary['open'] ?></div><div class="d"><?php foreach (['yeni', 'inceleniyor', 'atandi', 'devam', 'beklemede'] as $s): if (!empty($statusCounts[$s])): ?><a class="pill <?= status_tone($s) ?>" href="<?= e(route('requests.index', ['durum' => $s])) ?>"><?= e(list_label('request_statuses', $s)) ?> <?= (int) $statusCounts[$s] ?></a><?php endif; endforeach; ?></div></div>
  <div class="stat <?= $summary['overdue'] > 0 ? 'tone-bad' : '' ?>"><div class="k">Hedef tarihi geçmiş</div><div class="v"><?= $summary['overdue'] ?></div><div class="d">Açık taleplerden</div></div>
  <div class="stat <?= $summary['urgent'] > 0 ? 'tone-warn' : '' ?>"><div class="k">Acil</div><div class="v"><?= $summary['urgent'] ?></div><div class="d">Öncelik "acil" olan açık talepler</div></div>
  <div class="stat tone-ok"><div class="k">Bu ay tamamlanan</div><div class="v"><?= $summary['done_month'] ?></div><div class="d">Toplam tamamlanan <?= (int) ($statusCounts['tamamlandi'] ?? 0) ?></div></div>
</div>

<div class="card">
  <form class="table-toolbar" method="get" data-autosubmit>
    <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Başlık, açıklama, konum, kapı no…">
    <select class="select" name="durum"><option value="">Tüm durumlar</option><option value="acik" <?= $f['durum'] === 'acik' ? 'selected' : '' ?>>Açık olanlar</option><?php foreach ($request_statuses as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="kategori"><option value="">Tüm kategoriler</option><?php foreach ($request_categories as $k => $v): ?><option value="<?= $k ?>" <?= $f['kategori'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="oncelik"><option value="">Tüm öncelikler</option><?php foreach ($request_priorities as $k => $v): ?><option value="<?= $k ?>" <?= $f['oncelik'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="atanan"><option value="">Tüm görevliler</option><?php foreach ($staff as $id => $n): ?><option value="<?= $id ?>" <?= $f['atanan'] === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <span class="spacer"></span><button class="btn btn-sm"><i class="bi bi-funnel"></i>Filtrele</button>
    <?php if ($hasFilter): ?><a class="btn btn-sm btn-ghost" href="<?= e(route('requests.index')) ?>">Temizle</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Talep</th><th>Bölüm / kişi</th><th class="hide-sm">Atanan</th><th>Öncelik</th><th>Durum</th><th>Hedef</th><th class="num hide-sm">Masraf</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $open = !in_array($r['status'], ['tamamlandi', 'iptal'], true); $late = $open && $r['target_date'] !== null && $r['target_date'] < $today; $who = $r['company_name'] ?: trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?>
      <tr data-href="<?= e(route('requests.show', ['id' => $r['id']])) ?>" class="<?= $r['status'] === 'iptal' ? 'is-cancelled' : '' ?>">
        <td><div class="primary-cell">#<?= $r['id'] ?> <?= e($r['title']) ?></div><div class="sub-cell"><?= e(list_label('request_categories', $r['category'])) ?><?= $r['location'] ? ' · ' . e($r['location']) : '' ?> · <?= e(tr_date(substr((string) $r['created_at'], 0, 10))) ?><?= $r['comment_count'] > 0 ? ' · <i class="bi bi-chat-left-text"></i> ' . (int) $r['comment_count'] : '' ?><?= $r['visibility'] === 'herkes' ? ' · <i class="bi bi-eye" title="Tüm sakinler görebilir"></i>' : '' ?></div></td>
        <td><?= $r['unit_id'] ? '<div>' . e(($r['block_name'] ? $r['block_name'] . ' · ' : '') . 'No ' . $r['door_no']) . '</div>' : '<div class="text-muted">Ortak alan</div>' ?><?= $who !== '' ? '<div class="sub-cell">' . e($who) . '</div>' : '' ?></td>
        <td class="hide-sm"><?= $r['assigned_name'] ? e($r['assigned_name']) : '<span class="text-muted">—</span>' ?></td>
        <td><span class="pill <?= $r['priority'] === 'acil' ? 'bad' : ($r['priority'] === 'yuksek' ? 'warn' : 'neutral') ?> no-dot"><?= e(list_label('request_priorities', $r['priority'])) ?></span></td>
        <td><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('request_statuses', $r['status'])) ?></span></td>
        <td class="small"><?= $r['target_date'] ? ($late ? '<span class="pill bad"><i class="bi bi-exclamation-circle"></i>' . e(tr_date($r['target_date'])) . '</span>' : e(tr_date($r['target_date']))) : '<span class="text-muted">—</span>' ?></td>
        <td class="num hide-sm <?= (int) $r['cost_amount'] > 0 ? '' : 'text-muted' ?>"><?= (int) $r['cost_amount'] > 0 ? e(money($r['cost_amount'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="7"><?= $this->partial('partials.empty', ['icon' => 'tools', 'title' => 'Talep bulunamadı', 'text' => $hasFilter ? 'Filtreyi değiştirin.' : 'Sakinlerden gelen arıza ve şikayetleri buradan takip edin; sakin alanından açılan talepler de burada listelenir.', 'action' => can('requests.manage') && !$hasFilter ? '<a class="btn btn-primary" href="' . e(route('requests.create')) . '"><i class="bi bi-plus-lg"></i>Yeni talep</a>' : '']) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  <?= $this->partial('partials.pagination', ['p' => $p]) ?>
</div>
