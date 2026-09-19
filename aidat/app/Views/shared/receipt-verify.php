<div class="print-sheet receipt">
  <?php if (!$valid): ?>
    <div class="empty"><div class="ic"><i class="bi bi-shield-x"></i></div><h3>Makbuz doğrulanamadı</h3><p>Kod geçersiz. Makbuz üzerindeki QR kodu yeniden okutun veya yönetiminizle iletişime geçin.</p></div>
  <?php else: ?>
    <div class="receipt-head">
      <div><div class="org"><?= e($data['building_name']) ?></div><div class="small text-muted">Makbuz doğrulama</div></div>
      <div class="meta"><div class="no"><?= e($data['receipt_no']) ?></div><div><?= e(tr_date($data['payment_date'])) ?></div></div>
    </div>
    <div class="flex center gap-4 mb-4">
      <span class="seal <?= $data['status'] === 'gecerli' ? 'ok' : '' ?>"><?= $data['status'] === 'gecerli' ? 'Geçerli' : e(list_label('payment_statuses', $data['status'])) ?></span>
      <div>
        <div class="eyebrow">Tutar</div>
        <div class="num" style="font-size:24px;font-weight:600"><?= e(money($data['amount'])) ?></div>
        <div class="small text-muted"><?= e(list_label('payment_methods', $data['method'])) ?> · <?= $data['door_no'] ? 'Bölüm ' . e($data['door_no']) : 'Bölüm belirtilmemiş' ?></div>
      </div>
    </div>
    <p class="small text-muted">Bu sayfa kişisel veri göstermez. Makbuzun tam içeriği yalnızca yönetim ve ilgili sakinin hesabında görüntülenir.</p>
  <?php endif; ?>
</div>
