<?php
/** @var string $area */
$buildings = [];
if ($area === 'manager') {
    $ids = app()->gate()->managedBuildingIds();
    if ($ids !== []) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $buildings = app()->db()->fetchAll("SELECT id, name, type FROM buildings WHERE id IN ({$in}) ORDER BY name", $ids);
    }
}
$unread = 0;
$notifs = [];
if ($user) {
    $unread = app()->db()->fetchInt('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND channel = ? AND read_at IS NULL', [(int) $user['id'], 'uygulama']);
    $notifs = app()->db()->fetchAll('SELECT id, subject, body, created_at, read_at FROM notifications WHERE user_id = ? AND channel = ? ORDER BY id DESC LIMIT 8', [(int) $user['id'], 'uygulama']);
}
$hasPortal = app()->router()->hasRoute('portal.home');
$hasManage = app()->gate()->hasManagementAccess();
?>
<header class="topbar">
  <button type="button" class="icon-btn sidebar-toggle-m" @click="menuOpen = !menuOpen" aria-label="Menü"><i class="bi bi-list"></i></button>
  <?php if ($area === 'manager' && $building): ?>
    <div class="dropdown" x-data="dropdown" @click.outside="close()">
      <button type="button" class="switcher" @click="toggle()" aria-haspopup="menu">
        <i class="bi bi-buildings"></i>
        <span class="t"><span class="n"><?= e($building['name']) ?></span><span class="s"><?= e(list_label('building_types', $building['type'])) ?> · <?= e($building['district'] ?: $building['city'] ?: 'Adres girilmedi') ?></span></span>
        <i class="bi bi-chevron-expand text-muted"></i>
      </button>
      <div class="menu left" x-show="open" x-transition.origin.top.left x-cloak role="menu">
        <div class="hd">Yapı seç</div>
        <?php foreach ($buildings as $b): ?>
          <form method="post" action="<?= e(route('buildings.switch', ['id' => $b['id']])) ?>"><?= csrf_field() ?>
            <button type="submit" class="<?= (int) $b['id'] === (int) $building['id'] ? 'is-on' : '' ?>"><i class="bi bi-building"></i><?= e($b['name']) ?></button>
          </form>
        <?php endforeach; ?>
        <?php if (can('buildings.manage') || is_admin()): ?>
          <div class="sep"></div>
          <a href="<?= e(route('buildings.create')) ?>"><i class="bi bi-plus-lg"></i>Yeni yapı ekle</a>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($area === 'portal'): ?>
    <div class="switcher" style="cursor:default"><i class="bi bi-house-heart"></i><span class="t"><span class="n">Sakin alanı</span><span class="s"><?= e($user['name'] ?? '') ?></span></span></div>
  <?php endif; ?>
  <div class="grow"></div>
  <?php if ($area === 'manager' && app()->router()->hasRoute('search')): ?>
    <button type="button" class="search-btn" @click="cmdkOpen = true"><i class="bi bi-search"></i><span class="lbl">Ara veya komut…</span><kbd>⌘K</kbd></button>
  <?php endif; ?>
  <div class="dropdown" x-data="dropdown" @click.outside="close()">
    <button type="button" class="icon-btn" @click="toggle()" aria-label="Bildirimler" title="Bildirimler"><i class="bi bi-bell"></i><?php if ($unread > 0): ?><span class="dot"></span><?php endif; ?></button>
    <div class="menu" x-show="open" x-transition.origin.top.right x-cloak style="min-width:340px;padding:0">
      <div class="card-head" style="padding:10px 14px"><h3 style="font-size:13.5px">Bildirimler</h3><?php if ($unread > 0 && app()->router()->hasRoute('notifications.read_all')): ?><form method="post" action="<?= e(route('notifications.read_all')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Tümünü okundu say</button></form><?php endif; ?></div>
      <div class="notif-list">
        <?php if ($notifs === []): ?><div class="empty" style="padding:24px"><div class="ic"><i class="bi bi-bell-slash"></i></div><p>Bildirim yok.</p></div><?php endif; ?>
        <?php foreach ($notifs as $n): ?>
          <div class="notif<?= $n['read_at'] === null ? ' unread' : '' ?>"><i class="bi bi-dot text-muted"></i><div><div class="t"><?= e($n['subject']) ?></div><div class="m"><?= e(\Aidat\Core\Str::limit((string) $n['body'], 90)) ?> · <?= e(\Aidat\Core\Dates::ago($n['created_at'])) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <button type="button" class="icon-btn" @click="cycleTheme()" :title="themeLabel()" aria-label="Tema"><i class="bi" :class="themeIcon()"></i></button>
  <div class="dropdown" x-data="dropdown" @click.outside="close()">
    <button type="button" class="icon-btn" style="width:auto;padding:0 6px 0 2px;gap:8px" @click="toggle()" aria-haspopup="menu" aria-label="Hesap">
      <span class="avatar"><?= e(\Aidat\Core\Str::initials((string) ($user['name'] ?? '?'))) ?></span>
      <i class="bi bi-chevron-down hide-sm" style="font-size:11px"></i>
    </button>
    <div class="menu" x-show="open" x-transition.origin.top.right x-cloak role="menu">
      <div class="hd" style="text-transform:none;letter-spacing:0;font-size:13px;color:var(--ink)"><?= e($user['name'] ?? '') ?><div class="small text-muted" style="font-weight:400"><?= e($user['email'] ?? '') ?></div></div>
      <div class="sep"></div>
      <a href="<?= e(route('profile.edit')) ?>"><i class="bi bi-person"></i>Profilim</a>
      <?php if ($area === 'manager' && $hasPortal): ?><a href="<?= e(route('portal.home')) ?>"><i class="bi bi-house-heart"></i>Sakin alanına geç</a><?php endif; ?>
      <?php if ($area === 'portal' && $hasManage): ?><a href="<?= e(route('dashboard')) ?>"><i class="bi bi-grid-1x2"></i>Yönetim paneline geç</a><?php endif; ?>
      <button type="button" @click="$dispatch('open-shortcuts')"><i class="bi bi-keyboard"></i>Klavye kısayolları</button>
      <div class="sep"></div>
      <form method="post" action="<?= e(route('logout')) ?>"><?= csrf_field() ?><button type="submit" class="danger"><i class="bi bi-box-arrow-right"></i>Çıkış yap</button></form>
    </div>
  </div>
</header>
