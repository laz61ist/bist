<?php use Aidat\Core\Form; ?>
<?= $this->partial('partials.page-head', ['title' => 'Profilim', 'desc' => 'Hesap bilgileriniz, şifreniz ve erişim kapsamınız.']) ?>
<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-person"></i>Hesap bilgileri</h3></div>
      <form class="card-body" method="post" action="<?= e(route('profile.update')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <?= Form::text('name', 'Ad soyad', ['required' => true, 'value' => $user['name'], 'col' => 'c6']) ?>
          <?= Form::email('email', 'E-posta', ['required' => true, 'value' => $user['email'], 'col' => 'c6']) ?>
          <?= Form::text('phone', 'Telefon', ['value' => $user['phone'], 'col' => 'c6', 'placeholder' => '05xx xxx xx xx']) ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary"><i class="bi bi-check2"></i>Kaydet</button></div>
      </form>
    </div>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-shield-lock"></i>Şifre değiştir</h3></div>
      <form class="card-body" method="post" action="<?= e(route('profile.password')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <?= Form::password('current_password', 'Mevcut şifre', ['required' => true, 'col' => 'c4', 'autocomplete' => 'current-password']) ?>
          <?= Form::password('password', 'Yeni şifre', ['required' => true, 'col' => 'c4', 'autocomplete' => 'new-password', 'help' => 'En az 8 karakter']) ?>
          <?= Form::password('password_confirmation', 'Yeni şifre (tekrar)', ['required' => true, 'col' => 'c4', 'autocomplete' => 'new-password']) ?>
        </div>
        <div class="form-actions"><button class="btn"><i class="bi bi-key"></i>Şifreyi değiştir</button></div>
      </form>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-diagram-3"></i>Erişim kapsamım</h3></div>
      <div class="card-body">
        <?php if (is_admin()): ?><div class="alert info"><i class="bi bi-star"></i><div><strong>Süper yönetici.</strong> Tüm yapılar ve ayarlar.</div></div><?php endif; ?>
        <?php if ($memberships === [] && $units === [] && !is_admin()): ?><p class="text-muted">Henüz bir yapıya atanmadınız.</p><?php endif; ?>
        <?php if ($memberships !== []): ?>
          <div class="eyebrow mb-2">Yönetim rolleri</div>
          <dl class="dl">
            <?php foreach ($memberships as $m): ?><dt><?= e($m['building_name']) ?></dt><dd><span class="pill info no-dot"><?= e(config('permissions.roles.' . $m['role'] . '.label', $m['role'])) ?></span></dd><?php endforeach; ?>
          </dl>
        <?php endif; ?>
        <?php if ($units !== []): ?>
          <div class="eyebrow mb-2 mt-4">Bağımsız bölümlerim</div>
          <dl class="dl">
            <?php foreach ($units as $u): ?><dt><?= e($u['building_name']) ?></dt><dd><?= e(($u['block_name'] ? $u['block_name'] . ' · ' : '') . 'No ' . $u['door_no']) ?> · <?= e(list_label('occupancy_roles', $u['role'])) ?></dd><?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>Oturum</h3></div>
      <div class="card-body"><dl class="dl"><dt>Son giriş</dt><dd><?= e(tr_datetime($user['last_login_at'])) ?></dd><dt>Hesap oluşturma</dt><dd><?= e(tr_date($user['created_at'])) ?></dd></dl></div>
    </div>
  </div>
</div>
