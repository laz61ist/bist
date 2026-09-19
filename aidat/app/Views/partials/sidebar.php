<?php
/** @var array $menu @var string $area */
$badges = $badges ?? ($this->shared()['navBadges'] ?? []);
?>
<aside class="sidebar" aria-label="Ana menü">
  <div class="sidebar-brand">
    <div class="brand-mark" aria-hidden="true">A</div>
    <div class="brand-text">
      <div class="name"><?= e(config('app.name')) ?></div>
      <div class="sub"><?= $area === 'portal' ? 'Sakin alanı' : 'Yönetim defteri' ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($menu as $group): ?>
      <?php
      $items = array_filter($group['items'], static function (array $item): bool {
          if (!app()->router()->hasRoute($item['route'])) {
              return false;
          }
          return !isset($item['can']) || $item['can'] === null || can($item['can']);
      });
      if ($items === []) {
          continue;
      }
      ?>
      <?php if (!empty($group['section'])): ?><div class="nav-section"><?= e($group['section']) ?></div><?php endif; ?>
      <?php foreach ($items as $item): ?>
        <?php
        $active = !empty($item['exact'])
            ? app()->request()->path === route($item['route'])
            : is_active(...$item['match']);
        $count = isset($item['badge']) ? (int) ($badges[$item['badge']] ?? 0) : 0;
        ?>
        <a class="nav-item<?= $active ? ' is-active' : '' ?>" href="<?= e(route($item['route'])) ?>" title="<?= e($item['label']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
          <?php if ($count > 0): ?><em class="count" title="<?= $count ?> kayıt"><?= $count ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($area === 'manager' && app()->router()->hasRoute('portal.home') && app()->gate()->isAdmin() === false): ?>
    <?php endif; ?>
  </nav>
  <div class="sidebar-foot">
    <button type="button" class="nav-item sidebar-toggle-d" @click="toggleCollapse()" title="Menüyü daralt/genişlet" style="width:100%">
      <i class="bi" :class="collapsed ? 'bi-chevron-double-right' : 'bi-chevron-double-left'"></i><span class="txt" x-text="collapsed ? '' : 'Menüyü daralt'"></span>
    </button>
    <div class="txt small text-muted" style="padding:6px 10px 0">v<?= e(config('app.version')) ?> · <kbd>?</kbd> kısayollar</div>
  </div>
</aside>
