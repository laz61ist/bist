<?php /** @var string $area */ ?>
<nav class="mobile-bar" aria-label="Hızlı erişim">
<?php if ($area === 'portal'): ?>
  <a href="<?= e(route('portal.home')) ?>" class="<?= app()->request()->path === route('portal.home') ? 'is-active' : '' ?>"><i class="bi bi-house-heart"></i>Özet</a>
  <a href="<?= e(route('portal.charges')) ?>" class="<?= is_active('/sakin/borclarim') ? 'is-active' : '' ?>"><i class="bi bi-receipt"></i>Borçlar</a>
  <a href="<?= e(route('portal.requests_create')) ?>" class="fab"><i class="bi bi-plus-lg"></i>Talep</a>
  <a href="<?= e(route('portal.announcements')) ?>" class="<?= is_active('/sakin/duyurular') ? 'is-active' : '' ?>"><i class="bi bi-megaphone"></i>Duyuru</a>
  <button type="button" @click="menuOpen = true"><i class="bi bi-list"></i>Menü</button>
<?php else: ?>
  <a href="<?= e(route('dashboard')) ?>" class="<?= app()->request()->path === route('dashboard') ? 'is-active' : '' ?>"><i class="bi bi-grid-1x2"></i>Panel</a>
  <a href="<?= e(route('units.index')) ?>" class="<?= is_active('/yonetim/bolumler') ? 'is-active' : '' ?>"><i class="bi bi-door-open"></i>Bölümler</a>
  <?php if (can('payments.create')): ?><a href="<?= e(route('payments.create')) ?>" class="fab"><i class="bi bi-plus-lg"></i>Tahsilat</a><?php else: ?><a href="<?= e(route('payments.index')) ?>" class="fab"><i class="bi bi-cash-coin"></i>Tahsilat</a><?php endif; ?>
  <a href="<?= e(route('debtors.index')) ?>" class="<?= is_active('/yonetim/borclular') ? 'is-active' : '' ?>"><i class="bi bi-exclamation-diamond"></i>Borçlu</a>
  <button type="button" @click="menuOpen = true"><i class="bi bi-list"></i>Menü</button>
<?php endif; ?>
</nav>
