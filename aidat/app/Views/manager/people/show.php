<?php $r = $row; $name = $title; $today = date('Y-m-d'); ?>
<?= $this->partial('partials.page-head', ['title' => $name, 'crumbs' => [['Kişiler', route('people.index')], [$name]], 'desc' => list_label('person_types', $r['type']) . ($r['phone'] ? ' · ' . \Aidat\Core\Str::formatPhone($r['phone']) : '') . ($r['email'] ? ' · ' . $r['email'] : ''), 'actions' => can('people.manage') ? '<a class="btn" href="' . e(route('people.edit', ['id' => $r['id']])) . '"><i class="bi bi-pencil"></i>Düzenle</a>' : '']) ?>
<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-door-open"></i>Bağımsız bölümler</h3></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Bölüm</th><th>Sıfat</th><th>Sorumluluk</th><th>Başlangıç</th><th>Bitiş</th></tr></thead>
        <tbody><?php foreach ($occupancies as $o): $active = $o['end_date'] === null || $o['end_date'] >= $today; ?>
          <tr data-href="<?= e(route('units.show', ['id' => $o['unit_id']])) ?>" class="<?= $active ? '' : 'is-muted' ?>"><td class="primary-cell"><?= e(($o['block_name'] ? $o['block_name'] . ' · ' : '') . 'No ' . $o['door_no']) ?></td><td><span class="pill <?= $active ? ($o['role'] === 'malik' ? 'info' : 'neutral') : 'neutral' ?> no-dot"><?= e(list_label('occupancy_roles', $o['role'])) ?></span></td><td class="small"><?= e(list_label('liability_modes', $o['liability'])) ?></td><td class="small"><?= e(tr_date($o['start_date'])) ?></td><td class="small"><?= $o['end_date'] ? e(tr_date($o['end_date'])) : '<span class="text-ok">Devam ediyor</span>' ?></td></tr>
        <?php endforeach; ?><?php if ($occupancies === []): ?><tr><td colspan="5" class="text-muted centered" style="padding:20px">Bağlı bölüm yok.</td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-receipt"></i>Açık borçlar (aktif bölümler)</h3></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Bölüm</th><th>Borç</th><th>Vade</th><th class="num">Kalan</th></tr></thead><tbody>
        <?php $sum = 0; foreach ($charges as $c): $sum += (int) $c['amount'] - (int) $c['paid_amount']; ?><tr data-href="<?= e(route('charges.show', ['id' => $c['id']])) ?>"><td>No <?= e($c['door_no']) ?></td><td><?= e($c['title']) ?></td><td class="small <?= $c['due_date'] < $today ? 'text-bad' : '' ?>"><?= e(tr_date($c['due_date'])) ?></td><td class="num"><?= e(money((int) $c['amount'] - (int) $c['paid_amount'])) ?></td></tr><?php endforeach; ?>
        <?php if ($charges === []): ?><tr><td colspan="4" class="centered" style="padding:20px"><span class="pill ok">Açık borç yok</span></td></tr><?php endif; ?>
      </tbody><?php if ($charges !== []): ?><tfoot><tr><td colspan="3">Toplam</td><td class="num"><?= e(money($sum)) ?></td></tr></tfoot><?php endif; ?></table></div>
    </div>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-cash-coin"></i>Son tahsilatlar</h3></div>
      <div class="table-wrap"><table class="table compact"><tbody><?php foreach ($payments as $pm): ?><tr data-href="<?= e(route('payments.show', ['id' => $pm['id']])) ?>" class="<?= $pm['status'] !== 'gecerli' ? 'is-cancelled' : '' ?>"><td class="mono"><?= e($pm['receipt_no']) ?></td><td>No <?= e($pm['door_no']) ?></td><td class="small"><?= e(tr_date($pm['payment_date'])) ?></td><td class="num"><?= e(money($pm['amount'])) ?></td></tr><?php endforeach; ?><?php if ($payments === []): ?><tr><td class="text-muted centered" style="padding:20px">Tahsilat yok.</td></tr><?php endif; ?></tbody></table></div>
    </div>
  </div>
  <div class="stack">
    <div class="card"><div class="card-head"><h3><i class="bi bi-person-vcard"></i>Bilgiler</h3></div><div class="card-body"><dl class="dl">
      <dt>TCKN / VKN</dt><dd class="mono"><?= $r['identity_no'] ? e($canIdentity ? $r['identity_no'] : \Aidat\Core\Str::maskIdentity($r['identity_no'])) : '—' ?></dd>
      <dt>Telefon</dt><dd class="mono"><?= e(\Aidat\Core\Str::formatPhone($r['phone']) ?: '—') ?><?= $r['phone2'] ? '<br>' . e(\Aidat\Core\Str::formatPhone($r['phone2'])) : '' ?></dd>
      <dt>E-posta</dt><dd><?= e($r['email'] ?: '—') ?></dd>
      <dt>Bildirim izni</dt><dd><?= $r['contact_consent'] ? '<span class="pill ok">Var</span>' : '<span class="pill neutral">Yok</span>' ?></dd>
      <dt>Acil durum</dt><dd><?= e($r['emergency_name'] ?: '—') ?><?= $r['emergency_phone'] ? ' · ' . e(\Aidat\Core\Str::formatPhone($r['emergency_phone'])) : '' ?></dd>
      <?php if ($r['notes']): ?><dt>Not</dt><dd><?= nl2br(e($r['notes'])) ?></dd><?php endif; ?>
    </dl></div></div>
    <div class="card"><div class="card-head"><h3><i class="bi bi-house-heart"></i>Sakin alanı hesabı</h3></div><div class="card-body">
      <?php if ($portalUser): ?>
        <dl class="dl"><dt>E-posta</dt><dd><?= e($portalUser['email']) ?></dd><dt>Durum</dt><dd><?= $portalUser['is_active'] ? '<span class="pill ok">Aktif</span>' : '<span class="pill bad">Pasif</span>' ?></dd><dt>Son giriş</dt><dd><?= e(tr_datetime($portalUser['last_login_at'])) ?></dd></dl>
        <?php if (can('users.manage')): ?><a class="btn btn-sm mt-3" href="<?= e(route('users.edit', ['id' => $portalUser['id']])) ?>"><i class="bi bi-person-gear"></i>Kullanıcıyı yönet</a><?php endif; ?>
      <?php else: ?>
        <p class="small text-muted">Bu kişinin sakin alanı hesabı yok. Hesap açıldığında kendi borçlarını, makbuzlarını ve yapının gelir-giderini görebilir.</p>
        <?php if (can('users.manage')): ?><form method="post" action="<?= e(route('people.account', ['id' => $r['id']])) ?>" class="flex gap-2 mt-2"><?= csrf_field() ?><?php if (!$r['email']): ?><input class="input" type="email" name="email" placeholder="e-posta" required><?php endif; ?><button class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i>Hesap oluştur</button></form><?php endif; ?>
      <?php endif; ?>
    </div></div>
    <?php if ($notifications !== []): ?><div class="card"><div class="card-head"><h3><i class="bi bi-send"></i>Gönderilen bildirimler</h3></div><div class="notif-list"><?php foreach ($notifications as $n): ?><div class="notif"><i class="bi bi-<?= $n['channel'] === 'eposta' ? 'envelope' : ($n['channel'] === 'sms' ? 'chat-dots' : 'bell') ?> text-muted"></i><div><div class="t"><?= e($n['subject']) ?></div><div class="m"><?= e(tr_datetime($n['created_at'])) ?> · <?= e($n['status']) ?></div></div></div><?php endforeach; ?></div></div><?php endif; ?>
  </div>
</div>
