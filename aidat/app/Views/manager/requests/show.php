<?php use Aidat\Core\Form; $r = $row; $canManage = can('requests.manage'); $open = !in_array($r['status'], ['tamamlandi', 'iptal'], true); $late = $open && $r['target_date'] !== null && $r['target_date'] < $today;
$who = $r['company_name'] ?: trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
$statusChanges = array_values(array_filter($comments, static fn ($c) => $c['status_change'] !== null));
$actions = '<a class="btn btn-ghost" href="' . e(route('requests.index')) . '"><i class="bi bi-arrow-left"></i>Listeye dön</a>';
if ($r['unit_id']) { $actions .= '<a class="btn" href="' . e(route('units.show', ['id' => $r['unit_id']])) . '"><i class="bi bi-door-open"></i>Bölüm</a>'; }
?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Talepler', route('requests.index')], ['#' . $r['id']]], 'desc' => list_label('request_categories', $r['category']) . ($r['location'] ? ' · ' . $r['location'] : '') . ' · ' . tr_datetime($r['created_at']) . ($r['creator_name'] ? ' · ' . $r['creator_name'] : ''), 'actions' => $actions]) ?>

<div class="stat-row mb-4">
  <div class="stat"><div class="k">Durum</div><div class="v" style="font-family:var(--font-sans);font-size:18px"><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('request_statuses', $r['status'])) ?></span></div><div class="d"><?= $r['resolved_at'] ? 'Çözüldü: ' . e(tr_datetime($r['resolved_at'])) : ($r['closed_at'] ? 'Kapatıldı: ' . e(tr_datetime($r['closed_at'])) : 'Açık') ?></div></div>
  <div class="stat"><div class="k">Öncelik</div><div class="v" style="font-family:var(--font-sans);font-size:18px"><span class="pill <?= $r['priority'] === 'acil' ? 'bad' : ($r['priority'] === 'yuksek' ? 'warn' : 'neutral') ?> no-dot"><?= e(list_label('request_priorities', $r['priority'])) ?></span></div><div class="d"><?= e(list_label('request_visibilities', $r['visibility'])) ?></div></div>
  <div class="stat <?= $late ? 'tone-bad' : '' ?>"><div class="k">Hedef tarih</div><div class="v" style="font-size:18px"><?= $r['target_date'] ? e(tr_date($r['target_date'])) : '—' ?></div><div class="d"><?= $late ? '<span class="pill bad">Gecikti</span>' : ($r['assigned_name'] ? 'Atanan: ' . e($r['assigned_name']) : 'Atanmadı') ?></div></div>
  <div class="stat"><div class="k">Masraf</div><div class="v"><?= e(money($r['cost_amount'], false)) ?> <small>₺</small></div><div class="d"><?= $expense ? '<a href="' . e(route('expenses.show', ['id' => $expense['id']])) . '">Gider #' . $expense['id'] . '</a> · <span class="pill ' . status_tone($expense['status']) . '">' . e(list_label('expense_statuses', $expense['status'])) . '</span>' : 'Gidere aktarılmadı' ?></div></div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-card-text"></i>Açıklama</h3></div>
      <div class="card-body">
        <?php if ($r['description']): ?><p style="white-space:pre-line;margin:0 0 14px"><?= e($r['description']) ?></p><?php else: ?><p class="text-muted small">Açıklama girilmemiş.</p><?php endif; ?>
        <dl class="dl">
          <dt>Bölüm</dt><dd><?= $r['unit_id'] ? '<a href="' . e(route('units.show', ['id' => $r['unit_id']])) . '">' . e(($r['block_name'] ? $r['block_name'] . ' · ' : '') . 'No ' . $r['door_no']) . '</a>' : '<span class="text-muted">Ortak alan</span>' ?></dd>
          <dt>Talep sahibi</dt><dd><?= $who !== '' ? '<a href="' . e(route('people.show', ['id' => $r['person_id']])) . '">' . e($who) . '</a>' . ($r['person_phone'] ? ' · <span class="mono">' . e(\Aidat\Core\Str::formatPhone($r['person_phone'])) . '</span>' : '') . ($r['person_user_id'] ? ' · <i class="bi bi-person-check text-ok" title="Sakin alanı hesabı var"></i>' : '') : '<span class="text-muted">—</span>' ?></dd>
          <?php if ($documents !== []): ?><dt>Ekler</dt><dd><?php foreach ($documents as $doc): ?><a class="btn btn-sm" href="<?= e(route('documents.download', ['id' => $doc['id']])) ?>" target="_blank"><i class="bi bi-paperclip"></i><?= e($doc['original_name']) ?></a> <?php endforeach; ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-chat-left-text"></i>Yorumlar ve geçmiş</h3><span class="small text-muted"><?= count($comments) ?> kayıt</span></div>
      <div class="card-body">
        <ul class="timeline">
          <li><span class="pt ok"></span><div class="tt">Talep açıldı</div><div class="tm"><?= e(tr_datetime($r['created_at'])) ?> · <?= e($r['creator_name'] ?: 'sakin alanı') ?></div></li>
          <?php foreach ($comments as $c): ?>
            <li><span class="pt <?= $c['status_change'] === 'tamamlandi' ? 'ok' : ($c['status_change'] === 'iptal' ? 'bad' : ($c['status_change'] ? 'warn' : '')) ?>"></span>
              <div class="tt"><?php if ($c['status_change']): ?><span class="pill <?= status_tone($c['status_change']) ?>"><?= e(list_label('request_statuses', $c['status_change'])) ?></span> <?php endif; ?><?php if ((int) $c['is_internal'] === 1): ?><span class="pill neutral no-dot"><i class="bi bi-lock"></i> İç not</span> <?php endif; ?></div>
              <div class="tb" style="white-space:pre-line"><?= e($c['body']) ?></div>
              <div class="tm"><?= e(tr_datetime($c['created_at'])) ?> · <?= e($c['user_name'] ?: 'sakin') ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= e(route('requests.comment', ['id' => $r['id']])) ?>" class="mt-4" novalidate><?= csrf_field() ?>
          <div class="form-grid">
            <?= Form::textarea('body', 'Yorum ekle', ['rows' => 3, 'required' => true, 'placeholder' => 'Talep sahibine iletilecek yanıt veya iç not…']) ?>
            <?php if ($canManage): ?><?= Form::checkbox('is_internal', 'İç not (yalnızca yönetim görür, talep sahibine bildirim gitmez)') ?><?php endif; ?>
          </div>
          <div class="form-actions"><button class="btn btn-primary"><i class="bi bi-send"></i>Gönder</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="stack">
    <?php if ($canManage): ?>
    <div class="card" x-data="{toExpense:false}">
      <div class="card-head"><h3><i class="bi bi-pencil-square"></i>Güncelle</h3></div>
      <form method="post" action="<?= e(route('requests.update', ['id' => $r['id']])) ?>" novalidate><?= csrf_field() ?>
        <div class="card-body"><div class="form-grid">
          <?= Form::select('status', 'Durum', $request_statuses, ['value' => $r['status'], 'required' => true, 'col' => 'c6']) ?>
          <?= Form::select('priority', 'Öncelik', $request_priorities, ['value' => $r['priority'], 'required' => true, 'col' => 'c6']) ?>
          <?= Form::select('assigned_to', 'Atanan', $staff, ['value' => $r['assigned_to'] ?? '', 'placeholder' => 'Atanmadı', 'col' => 'c6']) ?>
          <?= Form::date('target_date', 'Hedef tarih', ['value' => $r['target_date'] ?? '', 'col' => 'c6']) ?>
          <?= Form::textarea('note', 'Not (isteğe bağlı)', ['rows' => 2, 'placeholder' => 'Durum değişikliğiyle birlikte talep sahibine iletilir']) ?>
          <div class="field"><span class="field-label"><i class="bi bi-cash-stack"></i> Masraf</span></div>
          <?= Form::money('cost_amount', 'Masraf tutarı', ['value' => (int) $r['cost_amount'], 'col' => 'c6', 'readonly' => $expense !== null, 'help' => $expense ? 'Gidere aktarıldı; değişiklik gider kaydından yapılır.' : 'Malzeme + işçilik (KDV dahil)']) ?>
          <?php if ($expense === null && can('expenses.manage')): ?>
            <div class="field c6"><span class="field-label">&nbsp;</span><label class="check"><input type="hidden" name="to_expense" value="0"><input type="checkbox" name="to_expense" value="1" x-model="toExpense"><span>Gidere aktar<span class="h">Ödenmiş gider kaydı oluşturur ve defterden düşer</span></span></label></div>
            <div class="field c6" x-show="toExpense" x-cloak><?= Form::select('expense_account_id', 'Ödeme hesabı', $accounts, ['placeholder' => 'Kasa / banka seçin']) ?></div>
            <div class="field c6" x-show="toExpense" x-cloak><?= Form::select('expense_category_id', 'Gider kategorisi', $expenseCategories, ['placeholder' => 'Kategorisiz']) ?></div>
          <?php endif; ?>
        </div></div>
        <div class="card-foot"><span class="small text-muted"><?= $r['person_id'] ? 'Durum değişince talep sahibine bildirim gider.' : 'Talep sahibi yok; bildirim gönderilmez.' ?></span><button class="btn btn-primary"><i class="bi bi-check2"></i>Kaydet</button></div>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-signpost-split"></i>Durum akışı</h3></div>
      <div class="card-body">
        <?php if ($statusChanges === []): ?><p class="small text-muted">Henüz durum değişikliği yok.</p><?php else: ?>
        <ul class="timeline">
          <?php foreach ($statusChanges as $c): ?><li><span class="pt <?= $c['status_change'] === 'tamamlandi' ? 'ok' : ($c['status_change'] === 'iptal' ? 'bad' : 'warn') ?>"></span><div class="tt"><?= e(list_label('request_statuses', $c['status_change'])) ?></div><div class="tm"><?= e(tr_datetime($c['created_at'])) ?> · <?= e($c['user_name'] ?: '—') ?></div></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
