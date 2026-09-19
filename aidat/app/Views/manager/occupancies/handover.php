<?php use Aidat\Core\Form; ?>
<?= $this->partial('partials.page-head', ['title' => 'Devir işlemi', 'crumbs' => [['Devir ve oturum geçmişi', route('occupancies.index')], ['Devir']], 'desc' => 'Satış veya kiracı değişiminde eski oturumu kapatır, yeni oturumu başlatır ve açık bakiyenin kimde kalacağını kaydeder.']) ?>
<?php if (!$unit): ?>
  <div class="card"><div class="card-body"><form method="get" class="flex gap-2 center wrap"><label class="field-label">Bölüm seçin</label><select class="select" name="bolum" style="width:auto" onchange="this.form.submit()"><option value="">Seçin…</option><?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>"><?= e(($u['block_name'] ? $u['block_name'] . ' · ' : '') . 'No ' . $u['door_no']) ?></option><?php endforeach; ?></select></form></div></div>
<?php else: ?>
<form method="post" action="<?= e(route('handovers.store')) ?>" novalidate>
  <?= csrf_field() ?><input type="hidden" name="unit_id" value="<?= $unit['id'] ?>">
  <div class="grid grid-main">
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-box-arrow-right"></i>Eski taraf</h3></div><div class="card-body">
        <?php if ($current === []): ?><div class="alert info"><i class="bi bi-info-circle"></i><div>Aktif oturum yok; yalnızca yeni taraf başlatılacak.</div></div><?php else: ?>
        <div class="radio-cards">
          <label class="radio-card"><input type="radio" name="from_occupancy_id" value="" checked><span><span class="t">Kapatma yok</span><span class="h">Mevcut oturumlar açık kalsın</span></span></label>
          <?php foreach ($current as $o): ?><label class="radio-card"><input type="radio" name="from_occupancy_id" value="<?= $o['id'] ?>"><span><span class="t"><?= e($o['company_name'] ?: trim($o['first_name'] . ' ' . $o['last_name'])) ?></span><span class="h"><?= e(list_label('occupancy_roles', $o['role'])) ?> · <?= e(tr_date($o['start_date'])) ?>'den beri</span></span></label><?php endforeach; ?>
        </div><?php endif; ?>
      </div></div>
      <div class="card"><div class="card-head"><h3><i class="bi bi-box-arrow-in-left"></i>Yeni taraf</h3></div><div class="card-body"><div class="form-grid">
        <div class="field c12"><label>Kişi <span class="req">*</span></label><select class="select" name="to_person_id" required><option value="">Seçin…</option><?php foreach ($people as $pp): ?><option value="<?= $pp['id'] ?>" <?= (int) old('to_person_id') === (int) $pp['id'] ? 'selected' : '' ?>><?= e($pp['company_name'] ?: trim($pp['first_name'] . ' ' . $pp['last_name'])) ?></option><?php endforeach; ?></select><div class="help">Listede yoksa önce <a href="<?= e(route('people.create')) ?>">kişi oluşturun</a>.</div></div>
        <?= Form::select('to_role', 'Sıfat', $occupancy_roles, ['value' => 'malik', 'col' => 'c4', 'required' => true]) ?>
        <?= Form::select('to_liability', 'Borç sorumluluğu', $liability_modes, ['value' => 'malik', 'col' => 'c4', 'required' => true]) ?>
        <?= Form::date('handover_date', 'Devir tarihi', ['value' => date('Y-m-d'), 'col' => 'c4', 'required' => true]) ?>
      </div></div></div>
    </div>
    <div class="stack">
      <div class="card"><div class="card-head"><h3><i class="bi bi-cash-stack"></i>Bakiye ve depozito</h3></div><div class="card-body">
        <div class="balance-box <?= $balance['debt'] > 0 ? 'bad' : 'ok' ?> mb-4"><div><div class="l">Açık borç</div><div class="v"><?= e(money($balance['debt'])) ?></div></div><div class="small text-muted">avans <?= e(money($balance['advance'])) ?></div></div>
        <div class="form-grid">
          <?= Form::radioCards('balance_mode', 'Açık bakiye', ['kalir' => ['Eski tarafta kalır', 'Borç sorumlusu değişmez; eski malikten tahsil edilir'], 'devreder' => ['Yeni tarafa devreder', 'Açık borçların sorumlusu yeni taraf olur (KMK md. 20 uyarınca malik sorumluluğu)']], ['value' => 'kalir']) ?>
          <?= Form::money('deposit_transferred', 'Devredilen depozito / avans', ['value' => 0, 'help' => 'Bilgi amaçlı; defter hareketi oluşturmaz']) ?>
          <?= Form::textarea('notes', 'Tutanak notu', ['rows' => 3, 'placeholder' => 'Sayaç değerleri, anahtar teslimi, taraflar…']) ?>
        </div>
      </div></div>
    </div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= e(route('units.show', ['id' => $unit['id']])) ?>">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i>Devri tamamla</button></div>
</form>
<?php endif; ?>
