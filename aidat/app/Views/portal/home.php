<?php
$u = $ctx['unit'];
$firstName = trim(explode(' ', (string) ($currentUser['name'] ?? ''))[0] ?? '');
$debt = (int) $balance['debt'];
$overdue = (int) $balance['overdue'];
$advance = (int) $balance['advance'];
$tone = $overdue > 0 ? 'bad' : ($debt > 0 ? 'warn' : 'ok');
$actions = '';
if (count($ctx['units']) > 1) {
    $actions .= '<form method="post" action="' . e(route('portal.switch_unit')) . '" data-autosubmit class="flex gap-2 center">' . csrf_field() . '<label class="small text-muted" for="unit_switch">Bölüm</label><select class="select" name="unit_id" id="unit_switch" style="width:auto">';
    foreach ($ctx['units'] as $id => $row) {
        $actions .= '<option value="' . (int) $id . '"' . ((int) $id === (int) $ctx['unit_id'] ? ' selected' : '') . '>' . e($row['label']) . '</option>';
    }
    $actions .= '</select></form>';
}
?>
<?= $this->partial('partials.page-head', ['title' => 'Merhaba' . ($firstName !== '' ? ', ' . $firstName : ''), 'desc' => $building['name'] . ' · ' . $u['label'] . ' · ' . list_label('occupancy_roles', $ctx['role']), 'actions' => $actions]) ?>

<div class="grid grid-main">
  <div class="stack">
    <div class="card accent-<?= $tone === 'bad' ? 'clay' : ($tone === 'warn' ? 'amber' : 'moss') ?>">
      <div class="card-body">
        <div class="eyebrow">Güncel borcunuz</div>
        <div class="num" style="font-size:38px;font-weight:600;letter-spacing:-.02em;line-height:1.1;margin-top:6px;color:var(--<?= $tone === 'bad' ? 'clay' : ($tone === 'warn' ? 'amber-ink' : 'moss') ?>)"><?= e(money($debt)) ?></div>
        <div class="flex wrap gap-2 mt-3">
          <?php if ($debt === 0): ?>
            <span class="pill ok">Borcunuz yok</span>
          <?php else: ?>
            <?php if ($overdue > 0): ?><span class="pill bad">Vadesi geçmiş <?= e(money($overdue)) ?> · <?= $overdueCount ?> kalem</span><?php endif; ?>
            <?php if ($debt - $overdue > 0): ?><span class="pill warn">Vadesi gelmemiş <?= e(money($debt - $overdue)) ?></span><?php endif; ?>
          <?php endif; ?>
          <?php if ($advance > 0): ?><span class="pill info">Avansınız <?= e(money($advance)) ?></span><?php endif; ?>
        </div>
        <p class="small text-muted mt-3" style="margin-bottom:0">
          <?php if ($debt === 0 && $advance > 0): ?>Fazla ödemeniz bir sonraki aidatınızdan otomatik düşülür.
          <?php elseif ($debt === 0): ?>Tüm aidatlarınız ödenmiş görünüyor. Teşekkür ederiz.
          <?php elseif ($overdue > 0): ?>Vadesi geçen tutar için gecikme tazminatı işleyebilir; en kısa sürede ödemenizi öneririz.
          <?php else: ?>Vadesi henüz gelmemiş; dilediğiniz zaman ödeyebilirsiniz.<?php endif; ?>
          <a href="<?= e(route('portal.charges')) ?>">Kalem kalem görün <i class="bi bi-arrow-right"></i></a>
        </p>
      </div>
    </div>

    <div class="grid grid-2">
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-calendar-event"></i>Ne zaman?</h3></div>
        <div class="card-body">
          <?php if ($oldest !== null && $oldest['due_date'] < date('Y-m-d')): ?>
            <div class="eyebrow text-bad">Vadesi geçti</div>
            <div class="primary-cell mt-1" style="font-size:15px"><?= e($oldest['title']) ?></div>
            <div class="small text-muted">Vade <?= e(tr_date($oldest['due_date'])) ?> · <?= e(money((int) $oldest['amount'] - (int) $oldest['paid_amount'])) ?> kaldı</div>
            <?php if ($nextDue !== null): ?><div class="small text-muted mt-2">Sıradaki: <?= e($nextDue['title']) ?> · <?= e(tr_date($nextDue['due_date'])) ?></div><?php endif; ?>
          <?php elseif ($nextDue !== null): ?>
            <div class="eyebrow">Sıradaki ödeme</div>
            <div class="primary-cell mt-1" style="font-size:15px"><?= e($nextDue['title']) ?></div>
            <div class="small text-muted">Son ödeme günü <strong><?= e(tr_date($nextDue['due_date'])) ?></strong> · <?= e(money((int) $nextDue['amount'] - (int) $nextDue['paid_amount'])) ?></div>
          <?php else: ?>
            <div class="eyebrow">Sıradaki aidat</div>
            <div class="primary-cell mt-1" style="font-size:15px"><?= e(tr_period($nextPeriod)) ?></div>
            <div class="small text-muted">Her ayın <?= (int) $dueDay ?>. günü son ödeme günüdür.<?= $lastCharge ? ' Son aidatınız ' . e(money($lastCharge['amount'])) . ' idi.' : '' ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card" x-data="{copied:false, copy(t){ if(navigator.clipboard){ navigator.clipboard.writeText(t).then(()=>{ this.copied=true; setTimeout(()=>this.copied=false,1800); }); } }}">
        <div class="card-head"><h3><i class="bi bi-bank"></i>Nasıl öderim?</h3></div>
        <div class="card-body">
          <?php if (!empty($building['iban'])): ?>
            <div class="eyebrow">Havale / EFT</div>
            <div class="mono mt-1" style="font-size:14px;overflow-wrap:anywhere"><?= e(\Aidat\Core\Str::formatIban($building['iban'])) ?></div>
            <div class="small text-muted"><?= e($building['account_holder'] ?: $building['name']) ?><?= $building['bank_name'] ? ' · ' . e($building['bank_name']) : '' ?></div>
            <div class="mt-3 flex gap-2 center wrap">
              <div><div class="eyebrow">Açıklama önerisi</div><div class="mono" style="font-size:13.5px"><?= e($transferNote) ?></div></div>
              <button type="button" class="btn btn-sm" @click="copy(<?= e(json_encode($transferNote, JSON_UNESCAPED_UNICODE)) ?>)"><i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i><span x-text="copied ? 'Kopyalandı' : 'Kopyala'"></span></button>
            </div>
            <p class="small text-muted mt-3" style="margin-bottom:0">Açıklamaya bölümünüzü yazarsanız ödemeniz otomatik eşleşir. Kartla ödeme kabul edilmiyor; nakit ödemeyi yönetime elden yapabilirsiniz. Ödemeniz işlendiğinde makbuzunuz burada görünür.</p>
          <?php else: ?>
            <p class="small text-muted" style="margin-bottom:0">Yönetim henüz banka hesabı tanımlamamış. Ödemenizi yönetime elden (nakit) yapabilirsiniz; kartla ödeme kabul edilmiyor. Ödemeniz işlendiğinde makbuzunuz burada görünür.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="stat-row">
      <div class="stat"><div class="k">Son ödemeniz</div>
        <?php if ($lastPayment): ?><div class="v"><?= e(money($lastPayment['amount'], false)) ?> <small>₺</small></div><div class="d"><?= e(tr_date($lastPayment['payment_date'])) ?> · <a href="<?= e(route('portal.receipt', ['id' => $lastPayment['id']])) ?>">Makbuz</a></div>
        <?php else: ?><div class="v" style="font-family:var(--font-sans);font-size:16px">—</div><div class="d">Henüz ödeme kaydı yok</div><?php endif; ?>
      </div>
      <div class="stat"><div class="k">Aylık aidatınız</div>
        <?php if ($lastCharge): ?><div class="v"><?= e(money($lastCharge['amount'], false)) ?> <small>₺</small></div><div class="d"><?= e(tr_period($lastCharge['period'])) ?> dönemi</div>
        <?php else: ?><div class="v" style="font-family:var(--font-sans);font-size:16px">—</div><div class="d">Henüz tahakkuk yok</div><?php endif; ?>
      </div>
      <a class="stat" href="<?= e(route('portal.announcements')) ?>" style="text-decoration:none"><div class="k">Duyurular</div><div class="v <?= $unread > 0 ? 'text-warn' : '' ?>"><?= (int) $unread ?></div><div class="d">okunmamış</div></a>
      <a class="stat" href="<?= e(route('portal.requests')) ?>" style="text-decoration:none"><div class="k">Taleplerim</div><div class="v"><?= (int) $openRequests ?></div><div class="d">açık talep</div></a>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3>Hızlı erişim</h3></div>
      <div class="card-body tight">
        <?php
        $links = [
            ['portal.charges', 'receipt', 'Borçlarım', 'Kalem kalem borç ve ödeme durumu'],
            ['portal.payments', 'file-earmark-check', 'Ödemelerim ve makbuzlar', 'Makbuzlarınızı görüntüleyin, yazdırın'],
            ['portal.statement', 'journal-text', 'Hesap ekstrem', 'Borç ve ödemelerin tarih sırası'],
            ['portal.requests_create', 'plus-circle', 'Yeni talep', 'Arıza, öneri veya şikayet bildirin'],
        ];
        if ($openPolls > 0) { $links[] = ['portal.polls', 'ui-checks', 'Anketler', $openPolls . ' anket oyunuzu bekliyor']; }
        if ($showFinance) { $links[] = ['portal.finance', 'bar-chart-line', 'Yapının mali durumu', 'Gelir, gider ve kasa özeti']; }
        ?>
        <?php foreach ($links as [$rt, $ic, $t, $d]): ?>
          <a href="<?= e(route($rt)) ?>" class="flex center gap-3" style="padding:11px 18px;border-bottom:1px solid var(--rule-soft);color:inherit;text-decoration:none"><span class="avatar"><i class="bi bi-<?= $ic ?>"></i></span><span class="grow" style="min-width:0"><span class="primary-cell" style="display:block;font-weight:600"><?= e($t) ?></span><span class="sub-cell small text-muted" style="display:block"><?= e($d) ?></span></span><i class="bi bi-chevron-right text-muted"></i></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-megaphone"></i>Duyurular</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('portal.announcements')) ?>">Tümü <i class="bi bi-arrow-right"></i></a></div>
      <div class="card-body">
        <?php if ($announcements === []): ?><p class="small text-muted centered" style="margin:0">Henüz duyuru yok.</p><?php else: ?>
        <ul class="timeline">
          <?php foreach ($announcements as $an): ?>
            <li><span class="pt <?= $an['priority'] === 'acil' ? 'bad' : ($an['priority'] === 'onemli' ? 'warn' : 'ok') ?>"></span><div class="tt"><a href="<?= e(route('portal.announcement', ['id' => $an['id']])) ?>"><?= e($an['title']) ?></a><?= $an['read_at'] === null ? ' <span class="badge">Yeni</span>' : '' ?></div><div class="tm"><?= e(tr_date($an['published_at'])) ?><?= $an['is_pinned'] ? ' · sabit' : '' ?></div></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($nextMeeting): ?>
    <div class="card">
      <div class="card-head"><h3><i class="bi bi-people-fill"></i>Yaklaşan toplantı</h3></div>
      <div class="card-body">
        <div class="primary-cell" style="font-weight:600"><?= e($nextMeeting['title']) ?></div>
        <div class="small text-muted mt-1"><?= e(tr_datetime($nextMeeting['meeting_date'])) ?><?= $nextMeeting['location'] ? ' · ' . e($nextMeeting['location']) : '' ?></div>
        <a class="btn btn-sm mt-3" href="<?= e(route('portal.meetings')) ?>">Gündemi gör</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
