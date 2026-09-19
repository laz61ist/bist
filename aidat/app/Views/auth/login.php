<?php use Aidat\Core\Form; ?>
<h1>Giriş yap</h1>
<div class="sub">Yönetim defterinize devam etmek için hesabınızla oturum açın.</div>
<div class="card">
  <form class="card-body" method="post" action="<?= e(route('login.post')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= Form::email('email', 'E-posta', ['required' => true, 'autocomplete' => 'username', 'autofocus' => true, 'placeholder' => 'ornek@site.com']) ?>
      <?= Form::password('password', 'Şifre', ['required' => true, 'autocomplete' => 'current-password']) ?>
      <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"><span>Bu cihazda beni hatırla <span class="h">30 gün boyunca oturum açık kalır.</span></span></label></div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg btn-block mt-4"><i class="bi bi-box-arrow-in-right"></i>Giriş yap</button>
    <div class="foot-links"><a href="<?= e(route('password.forgot')) ?>">Şifremi unuttum</a><span>Sorun mu var? Yöneticinizle iletişime geçin.</span></div>
  </form>
</div>
<?php if ($demo): ?>
<div class="demo-box">
  <strong>Demo hesaplar</strong> (şifre: <code>Demo1234!</code>)<br>
  Süper yönetici <code>demo.admin@aidat.local</code> · Yönetici <code>demo.yonetici@aidat.local</code> · Muhasebe <code>demo.muhasebe@aidat.local</code><br>
  Denetçi <code>demo.denetci@aidat.local</code> · Malik <code>demo.malik@aidat.local</code> · Kiracı <code>demo.kiraci@aidat.local</code>
</div>
<?php endif; ?>
