<?php use Aidat\Core\Form; ?>
<h1>Yeni şifre belirle</h1>
<div class="sub">En az 8 karakter. Tahmin edilmesi zor bir şifre seçin.</div>
<div class="card">
  <form class="card-body" method="post" action="<?= e(route('password.update', ['token' => $token])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= Form::email('email', 'E-posta', ['required' => true]) ?>
      <?= Form::password('password', 'Yeni şifre', ['required' => true, 'autocomplete' => 'new-password']) ?>
      <?= Form::password('password_confirmation', 'Yeni şifre (tekrar)', ['required' => true, 'autocomplete' => 'new-password']) ?>
    </div>
    <button type="submit" class="btn btn-primary btn-lg btn-block mt-4"><i class="bi bi-shield-lock"></i>Şifreyi güncelle</button>
  </form>
</div>
