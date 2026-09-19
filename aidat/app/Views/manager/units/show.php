<?php use Aidat\Core\Form; $u = $unit; $today = date('Y-m-d');
$activeOcc = array_values(array_filter($occupancies, static fn ($o) => $o['end_date'] === null || $o['end_date'] >= $today));
$pastOcc = array_values(array_filter($occupancies, static fn ($o) => !($o['end_date'] === null || $o['end_date'] >= $today)));
$actions = '';
if (can('payments.create')) { $actions .= '<a class="btn btn-primary" href="' . e(route('payments.create', ['bolum' => $u['id']])) . '"><i class="bi bi-cash-coin"></i>Tahsilat gir</a>'; }
if (can('charges.manage')) { $actions .= '<a class="btn" href="' . e(route('charges.create', ['bolum' => $u['id']])) . '"><i class="bi bi-plus-square"></i>Borçlandır</a>'; }
$actions .= '<a class="btn" href="' . e(route('units.statement', ['id' => $u['id']])) . '"><i class="bi bi-journal-text"></i>Ekstre</a>';
if (can('units.manage')) { $actions .= '<a class="btn btn-ghost" href="' . e(route('units.edit', ['id' => $u['id']])) . '"><i class="bi bi-pencil"></i>Düzenle</a>'; }
?>
<?= $this->partial('partials.page-head', ['title' => $title, 'crumbs' => [['Bağımsız bölümler', route('units.index')], ['No ' . $u['door_no']]], 'desc' => list_label('unit_types', $u['type']) . ($u['floor'] !== null && $u['floor'] !== '' ? ' · Kat ' . $u['floor'] : '') . ($u['gross_m2'] ? ' · ' . number_tr((float) $u['gross_m2']) . ' m²' : '') . ($u['land_share'] ? ' · arsa payı ' . number_tr((float) $u['land_share'], 2) : '') . ' · ' . list_label('liability_modes', $u['liability_mode']), 'actions' => $actions]) ?>

<div class="stat-row mb-4">
  <div class="balance-box <?= $balance['debt'] > 0 ? 'bad' : 'ok' ?>"><div><div class="l">Açık borç</div><div class="v"><?= e(money($balance['debt'])) ?></div></div></div>
  <div class="stat tone-bad"><div class="k">Vadesi geçmiş</div><div class="v"><?= e(money($balance['overdue'], false)) ?> <small>₺</small></div><div class="d"><?= count($openCharges) ?> açık kalem</div></div>
  <div class="stat tone-ok"><div class="k">Avans (mahsup bekleyen)</div><div class="v"><?= e(money($balance['advance'], false)) ?> <small>₺</small></div><div class="d">Yeni borçta otomatik mahsup edilir</div></div>
  <div class="stat"><div class="k">Durum</div><div class="v" style="font-family:var(--font-sans);font-size:18px"><span class="pill <?= status_tone($u['status']) ?>"><?= e(list_label('unit_statuses', $u['status'])) ?></span></div><div class="d"><?= $u['late_fee_exempt'] ? 'Gecikme tazminatından muaf' : 'Gecikme tazminatı uygulanır' ?></div></div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card" x-data="reasonModal">
      <div class="card-head"><div><h3><i class="bi bi-receipt"></i>Açık borçlar</h3><div class="sub">En eski vadeden başlayarak kapatılır</div></div><a class="btn btn-sm btn-ghost" href="<?= e(route('charges.index', ['bolum' => $u['id']])) ?>">Tüm borç kayıtları <i class="bi bi-arrow-right"></i></a></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Borç</th><th>Dönem</th><th>Vade</th><th>Durum</th><th class="num">Tutar</th><th class="num">Kalan</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($openCharges as $c): $overdue = $c['due_date'] < $today; ?>
          <tr data-href="<?= e(route('charges.show', ['id' => $c['id']])) ?>"><td><div class="primary-cell"><?= e($c['title']) ?></div><div class="sub-cell"><?= e(list_label('charge_types', $c['charge_type'])) ?> · <?= e(list_label('liability_modes', $c['liability'])) ?></div></td><td class="small"><?= e(tr_period($c['period'])) ?></td><td class="small <?= $overdue ? 'text-bad' : '' ?>"><?= e(tr_date($c['due_date'])) ?><?= $overdue ? ' <i class="bi bi-exclamation-circle"></i>' : '' ?></td><td><span class="pill <?= status_tone($c['status']) ?>"><?= e(list_label('charge_statuses', $c['status'])) ?></span></td><td class="num"><?= e(money($c['amount'])) ?></td><td class="num" style="font-weight:600"><?= e(money((int) $c['amount'] - (int) $c['paid_amount'])) ?></td>
          <td><?php if (can('charges.cancel') && (int) $c['paid_amount'] === 0): ?><div class="row-actions"><button class="btn btn-sm btn-ghost text-bad" title="İptal (ters kayıt)" @click.stop="ask('<?= e(route('charges.cancel', ['id' => $c['id']])) ?>', 'Borcu iptal et', 'İptal gerekçesi')"><i class="bi bi-x-circle"></i></button></div><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if ($openCharges === []): ?><tr><td colspan="7" class="centered" style="padding:24px"><span class="pill ok">Açık borç yok</span></td></tr><?php endif; ?>
        </tbody>
        <?php if ($openCharges !== []): ?><tfoot><tr><td colspan="5">Toplam kalan</td><td class="num"><?= e(money($balance['debt'])) ?></td><td></td></tr></tfoot><?php endif; ?>
      </table></div>
      <?= $this->partial('partials.reason-modal') ?>
    </div>

    <div class="grid grid-2">
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-cash-coin"></i>Son tahsilatlar</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('payments.index', ['bolum' => $u['id']])) ?>">Tümü</a></div>
        <div class="table-wrap"><table class="table compact"><tbody>
          <?php foreach ($payments as $pm): ?><tr data-href="<?= e(route('payments.show', ['id' => $pm['id']])) ?>" class="<?= $pm['status'] !== 'gecerli' ? 'is-cancelled' : '' ?>"><td><div class="mono primary-cell"><?= e($pm['receipt_no']) ?></div><div class="sub-cell"><?= e(tr_date($pm['payment_date'])) ?> · <?= e(list_label('payment_methods', $pm['method'])) ?></div></td><td class="num"><?= e(money($pm['amount'])) ?></td></tr><?php endforeach; ?>
          <?php if ($payments === []): ?><tr><td class="text-muted centered" style="padding:20px">Tahsilat yok.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
      <div class="card">
        <div class="card-head"><h3><i class="bi bi-journal-text"></i>Son borç kayıtları</h3></div>
        <div class="table-wrap"><table class="table compact"><tbody>
          <?php foreach ($recentCharges as $c): ?><tr data-href="<?= e(route('charges.show', ['id' => $c['id']])) ?>" class="<?= $c['status'] === 'iptal' ? 'is-cancelled' : '' ?>"><td><div class="primary-cell"><?= e(\Aidat\Core\Str::limit($c['title'], 32)) ?></div><div class="sub-cell"><?= e(tr_date($c['due_date'])) ?> · <span class="pill <?= status_tone($c['status']) ?>" style="padding:0 6px;font-size:11px"><?= e(list_label('charge_statuses', $c['status'])) ?></span></div></td><td class="num"><?= e(money($c['amount'])) ?></td></tr><?php endforeach; ?>
          <?php if ($recentCharges === []): ?><tr><td class="text-muted centered" style="padding:20px">Borç kaydı yok.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-clock-history"></i>İşlem geçmişi</h3></div>
      <div class="card-body">
        <ul class="timeline">
          <?php foreach ($timeline as $t): ?><li><span class="pt <?= str_contains($t['action'], 'cancel') ? 'bad' : (str_contains($t['action'], 'payment') ? 'ok' : '') ?>"></span><div class="tt"><?= e($t['summary'] ?: $t['action']) ?></div><div class="tm"><?= e(tr_datetime($t['created_at'])) ?> · <?= e($t['user_name'] ?: 'sistem') ?></div></li><?php endforeach; ?>
          <?php if ($timeline === []): ?><li><span class="pt"></span><div class="tb text-muted">Henüz işlem yok.</div></li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card" x-data="{add:false}">
      <div class="card-head"><h3><i class="bi bi-people"></i>Malik ve sakinler</h3><?php if (can('people.manage')): ?><div class="tools"><button class="btn btn-sm" @click="add=!add"><i class="bi bi-person-plus"></i>Kişi bağla</button><a class="btn btn-sm btn-ghost" href="<?= e(route('handovers.create', ['bolum' => $u['id']])) ?>" title="Devir"><i class="bi bi-arrow-left-right"></i>Devir</a></div><?php endif; ?></div>
      <div class="card-body">
        <?php foreach ($activeOcc as $o): ?>
          <div class="flex gap-3 center" style="padding:8px 0;border-bottom:1px solid var(--rule-soft)">
            <span class="avatar"><?= e(\Aidat\Core\Str::initials($o['company_name'] ?: $o['first_name'] . ' ' . $o['last_name'])) ?></span>
            <div class="grow"><div class="primary-cell"><a href="<?= e(route('people.show', ['id' => $o['person_id']])) ?>"><?= e($o['company_name'] ?: trim($o['first_name'] . ' ' . $o['last_name'])) ?></a></div><div class="sub-cell"><span class="pill <?= $o['role'] === 'malik' ? 'info' : 'neutral' ?> no-dot"><?= e(list_label('occupancy_roles', $o['role'])) ?></span> <?= e(tr_date($o['start_date'])) ?>'den beri<?= $o['phone'] ? ' · ' . e(\Aidat\Core\Str::formatPhone($o['phone'])) : '' ?><?= $o['user_id'] ? ' · <i class="bi bi-person-check text-ok" title="Sakin alanı hesabı var"></i>' : '' ?></div></div>
            <?php if (can('people.manage')): ?><div x-data="{f:false}"><button class="btn btn-sm btn-ghost" @click="f=!f" title="Sonlandır"><i class="bi bi-box-arrow-right"></i></button><form x-show="f" x-cloak method="post" action="<?= e(route('occupancies.end', ['id' => $o['id']])) ?>" class="flex gap-1 mt-1"><?= csrf_field() ?><input type="date" class="input" name="end_date" value="<?= $today ?>" style="height:30px;width:150px"><button class="btn btn-sm btn-danger">Bitir</button></form></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($activeOcc === []): ?><div class="alert warn"><i class="bi bi-person-x"></i><div>Bu bölümde aktif malik/kiracı yok. Borçlandırmada sorumlu kişi boş kalır.</div></div><?php endif; ?>
        <?php if (can('people.manage')): ?>
        <form x-show="add" x-cloak method="post" action="<?= e(route('occupancies.store')) ?>" class="mt-4" novalidate><?= csrf_field() ?><input type="hidden" name="unit_id" value="<?= $u['id'] ?>">
          <div class="form-grid">
            <div class="field"><label>Kişi</label><select class="select" name="person_id" required><option value="">Seçin…</option><?php foreach ($people as $pp): ?><option value="<?= $pp['id'] ?>"><?= e($pp['company_name'] ?: trim($pp['first_name'] . ' ' . $pp['last_name'])) ?></option><?php endforeach; ?></select><div class="help">Listede yoksa <a href="<?= e(route('people.create', ['bolum' => $u['id']])) ?>">yeni kişi oluşturun</a>.</div></div>
            <?= Form::select('role', 'Sıfat', $occupancy_roles, ['value' => 'malik', 'col' => 'c6']) ?>
            <?= Form::select('liability', 'Borç sorumluluğu', $liability_modes, ['value' => $u['liability_mode'], 'col' => 'c6']) ?>
            <?= Form::date('start_date', 'Başlangıç', ['value' => $today, 'col' => 'c6', 'required' => true]) ?>
            <?= Form::checkbox('is_notify_contact', 'Tebligat / bildirim kişisi', ['checked' => true, 'col' => 'c6']) ?>
          </div>
          <div class="form-actions"><button type="button" class="btn btn-ghost" @click="add=false">Vazgeç</button><button class="btn btn-primary">Bağla</button></div>
        </form>
        <?php endif; ?>
        <?php if ($pastOcc !== []): ?>
          <details class="mt-3"><summary class="small text-muted" style="cursor:pointer">Geçmiş oturumlar (<?= count($pastOcc) ?>)</summary>
            <?php foreach ($pastOcc as $o): ?><div class="small text-muted" style="padding:4px 0"><?= e($o['company_name'] ?: trim($o['first_name'] . ' ' . $o['last_name'])) ?> · <?= e(list_label('occupancy_roles', $o['role'])) ?> · <?= e(tr_date($o['start_date'])) ?> – <?= e(tr_date($o['end_date'])) ?></div><?php endforeach; ?>
          </details>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><i class="bi bi-car-front"></i>Araçlar</h3></div>
      <div class="card-body">
        <?php foreach ($vehicles as $v): ?><div class="flex between center" style="padding:6px 0;border-bottom:1px solid var(--rule-soft)"><span><span class="badge"><?= e($v['plate']) ?></span> <span class="small text-muted"><?= e($v['description']) ?></span></span><?php if (can('units.manage')): ?><form method="post" action="<?= e(route('units.vehicle_remove', ['id' => $u['id'], 'vid' => $v['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost text-bad"><i class="bi bi-x"></i></button></form><?php endif; ?></div><?php endforeach; ?>
        <?php if ($vehicles === []): ?><p class="small text-muted">Kayıtlı araç yok.</p><?php endif; ?>
        <?php if (can('units.manage')): ?><form method="post" action="<?= e(route('units.vehicle_add', ['id' => $u['id']])) ?>" class="flex gap-2 mt-3"><?= csrf_field() ?><input class="input mono" name="plate" placeholder="34ABC123" required style="width:130px"><input class="input" name="description" placeholder="Açıklama"><button class="btn btn-sm"><i class="bi bi-plus"></i></button></form><?php endif; ?>
      </div>
    </div>

    <?php if ($meters !== []): ?>
    <div class="card"><div class="card-head"><h3><i class="bi bi-speedometer2"></i>Sayaçlar</h3><a class="btn btn-sm btn-ghost" href="<?= e(route('meters.index')) ?>">Sayaçlar</a></div>
      <div class="card-body"><dl class="dl"><?php foreach ($meters as $m): ?><dt><?= e(list_label('meter_types', $m['type'])) ?> <span class="small text-muted"><?= e($m['serial_no']) ?></span></dt><dd class="num" style="text-align:left"><?= $m['last_value'] !== null ? number_tr((float) $m['last_value'], 2) . ' <span class="small text-muted">(' . e(tr_period($m['last_period'])) . ')</span>' : '—' ?></dd><?php endforeach; ?></dl></div></div>
    <?php endif; ?>

    <?php if ($handovers !== []): ?>
    <div class="card"><div class="card-head"><h3><i class="bi bi-arrow-left-right"></i>Devirler</h3></div>
      <div class="card-body"><ul class="timeline"><?php foreach ($handovers as $h): ?><li><span class="pt"></span><div class="tt"><a href="<?= e(route('handovers.show', ['id' => $h['id']])) ?>"><?= e(trim(($h['from_first'] ?? '') . ' ' . ($h['from_last'] ?? '')) ?: '—') ?> → <?= e(trim(($h['to_first'] ?? '') . ' ' . ($h['to_last'] ?? ''))) ?></a></div><div class="tm"><?= e(tr_date($h['handover_date'])) ?> · <?= $h['balance_mode'] === 'devreder' ? 'bakiye devretti: ' . e(money($h['transferred_balance'])) : 'bakiye eski tarafta kaldı' ?></div></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>

    <?php if ($requests !== []): ?>
    <div class="card"><div class="card-head"><h3><i class="bi bi-tools"></i>Talepler</h3></div>
      <div class="table-wrap"><table class="table compact"><tbody><?php foreach ($requests as $r): ?><tr data-href="<?= e(route('requests.show', ['id' => $r['id']])) ?>"><td><div class="primary-cell"><?= e($r['title']) ?></div><div class="sub-cell"><?= e(tr_date($r['created_at'])) ?></div></td><td><span class="pill <?= status_tone($r['status']) ?>"><?= e(list_label('request_statuses', $r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php endif; ?>

    <?php if ($u['notes']): ?><div class="card"><div class="card-head"><h3><i class="bi bi-sticky"></i>Notlar</h3></div><div class="card-body small"><?= nl2br(e($u['notes'])) ?></div></div><?php endif; ?>
  </div>
</div>
