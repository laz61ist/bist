<?php use Aidat\Core\Form; ?>
<h1>Şifremi unuttum</h1>
<div class="sub">E-posta adresinizi yazın; kayıtlıysa sıfırlama bağlantısı gönderelim.</div>
<div class="card">
  <form class="card-body" method="post" action="<?= e(route('password.email')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid"><?= Form::email('email', 'E-posta', ['required' => true, 'autofocus' => true]) ?></div>
    <button type="submit" class="btn btn-primary btn-lg btn-block mt-4"><i class="bi bi-envelope"></i>Bağlantı gönder</button>
    <div class="foot-links"><a href="<?= e(route('login')) ?>"><i class="bi bi-arrow-left"></i> Girişe dön</a></div>
  </form>
</div>
