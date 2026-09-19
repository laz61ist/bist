<?php

declare(strict_types=1);

/**
 * Yönetim kenar menüsü. route: adlandırılmış rota; can: gerekli yetki (null = herkese);
 * match: aktiflik için yol ön ekleri.
 */
return [
    'manager' => [
        ['section' => null, 'items' => [
            ['label' => 'Genel bakış', 'icon' => 'grid-1x2', 'route' => 'dashboard', 'can' => 'dashboard.view', 'match' => ['/yonetim'], 'exact' => true, 'key' => 'g d'],
        ]],
        ['section' => 'Yapı yönetimi', 'items' => [
            ['label' => 'Yapılar', 'icon' => 'buildings', 'route' => 'buildings.index', 'can' => null, 'match' => ['/yonetim/yapilar']],
            ['label' => 'Bloklar', 'icon' => 'building', 'route' => 'blocks.index', 'can' => 'units.view', 'match' => ['/yonetim/bloklar']],
            ['label' => 'Bağımsız bölümler', 'icon' => 'door-open', 'route' => 'units.index', 'can' => 'units.view', 'match' => ['/yonetim/bolumler'], 'key' => 'g b'],
            ['label' => 'Kişiler', 'icon' => 'people', 'route' => 'people.index', 'can' => 'people.view', 'match' => ['/yonetim/kisiler'], 'key' => 'g k'],
            ['label' => 'Devir ve oturum geçmişi', 'icon' => 'arrow-left-right', 'route' => 'occupancies.index', 'can' => 'people.view', 'match' => ['/yonetim/oturumlar', '/yonetim/devirler']],
        ]],
        ['section' => 'Aidat ve borçlar', 'items' => [
            ['label' => 'Tahakkuk planları', 'icon' => 'journal-text', 'route' => 'plans.index', 'can' => 'charges.view', 'match' => ['/yonetim/tahakkuk-planlari']],
            ['label' => 'Borç kayıtları', 'icon' => 'receipt', 'route' => 'charges.index', 'can' => 'charges.view', 'match' => ['/yonetim/borclar'], 'key' => 'g a'],
            ['label' => 'Tekil borçlandırma', 'icon' => 'plus-square', 'route' => 'charges.create', 'can' => 'charges.manage', 'match' => ['/yonetim/borclar/yeni']],
            ['label' => 'Gecikme tazminatı', 'icon' => 'hourglass-split', 'route' => 'latefee.index', 'can' => 'latefee.manage', 'match' => ['/yonetim/gecikme']],
            ['label' => 'Borçlu listesi', 'icon' => 'exclamation-diamond', 'route' => 'debtors.index', 'can' => 'charges.view', 'match' => ['/yonetim/borclular'], 'badge' => 'debtors'],
            ['label' => 'Avans ve mahsup', 'icon' => 'piggy-bank', 'route' => 'advances.index', 'can' => 'payments.view', 'match' => ['/yonetim/avanslar']],
        ]],
        ['section' => 'Tahsilat', 'items' => [
            ['label' => 'Tahsilatlar', 'icon' => 'cash-coin', 'route' => 'payments.index', 'can' => 'payments.view', 'match' => ['/yonetim/tahsilatlar'], 'key' => 'g t'],
            ['label' => 'Tahsilat girişi', 'icon' => 'plus-circle', 'route' => 'payments.create', 'can' => 'payments.create', 'match' => ['/yonetim/tahsilatlar/yeni'], 'key' => 'n t'],
            ['label' => 'Banka ekstresi içe aktar', 'icon' => 'bank', 'route' => 'imports.index', 'can' => 'payments.import', 'match' => ['/yonetim/banka-aktarim']],
            ['label' => 'Makbuzlar', 'icon' => 'file-earmark-check', 'route' => 'receipts.index', 'can' => 'payments.view', 'match' => ['/yonetim/makbuzlar']],
        ]],
        ['section' => 'Gelir ve gider', 'items' => [
            ['label' => 'Giderler', 'icon' => 'cart-dash', 'route' => 'expenses.index', 'can' => 'expenses.view', 'match' => ['/yonetim/giderler'], 'key' => 'g g'],
            ['label' => 'Diğer gelirler', 'icon' => 'cart-plus', 'route' => 'incomes.index', 'can' => 'incomes.view', 'match' => ['/yonetim/gelirler']],
            ['label' => 'Kasa ve banka', 'icon' => 'safe2', 'route' => 'accounts.index', 'can' => 'accounts.view', 'match' => ['/yonetim/hesaplar']],
            ['label' => 'Virman / transfer', 'icon' => 'arrow-repeat', 'route' => 'transfers.index', 'can' => 'accounts.view', 'match' => ['/yonetim/virmanlar']],
            ['label' => 'Periyodik giderler', 'icon' => 'calendar-week', 'route' => 'recurring.index', 'can' => 'expenses.view', 'match' => ['/yonetim/periyodik-giderler']],
            ['label' => 'Tedarikçiler', 'icon' => 'truck', 'route' => 'vendors.index', 'can' => 'expenses.view', 'match' => ['/yonetim/tedarikciler']],
            ['label' => 'Sözleşmeler', 'icon' => 'file-earmark-ruled', 'route' => 'contracts.index', 'can' => 'expenses.view', 'match' => ['/yonetim/sozlesmeler']],
            ['label' => 'Bütçe / işletme projesi', 'icon' => 'clipboard-data', 'route' => 'budgets.index', 'can' => 'budgets.view', 'match' => ['/yonetim/butceler']],
        ]],
        ['section' => 'Operasyon', 'items' => [
            ['label' => 'Sayaçlar', 'icon' => 'speedometer2', 'route' => 'meters.index', 'can' => 'meters.view', 'match' => ['/yonetim/sayaclar']],
            ['label' => 'Talepler ve iş emirleri', 'icon' => 'tools', 'route' => 'requests.index', 'can' => 'requests.view', 'match' => ['/yonetim/talepler'], 'badge' => 'requests'],
            ['label' => 'Personel', 'icon' => 'person-badge', 'route' => 'staff.index', 'can' => 'staff.manage', 'match' => ['/yonetim/personel']],
            ['label' => 'Demirbaş', 'icon' => 'box-seam', 'route' => 'assets.index', 'can' => 'assets.manage', 'match' => ['/yonetim/demirbas']],
        ]],
        ['section' => 'İletişim', 'items' => [
            ['label' => 'Duyurular', 'icon' => 'megaphone', 'route' => 'announcements.index', 'can' => 'announcements.manage', 'match' => ['/yonetim/duyurular']],
            ['label' => 'Toplantılar ve kararlar', 'icon' => 'people-fill', 'route' => 'meetings.index', 'can' => 'meetings.manage', 'match' => ['/yonetim/toplantilar']],
            ['label' => 'Anketler', 'icon' => 'ui-checks', 'route' => 'polls.index', 'can' => 'polls.manage', 'match' => ['/yonetim/anketler']],
            ['label' => 'Bildirimler', 'icon' => 'send', 'route' => 'notifications.index', 'can' => 'notifications.send', 'match' => ['/yonetim/bildirimler']],
            ['label' => 'Belgeler', 'icon' => 'folder2-open', 'route' => 'documents.index', 'can' => 'documents.view', 'match' => ['/yonetim/belgeler']],
        ]],
        ['section' => 'Raporlar', 'items' => [
            ['label' => 'Raporlar', 'icon' => 'bar-chart-line', 'route' => 'reports.index', 'can' => 'reports.view', 'match' => ['/yonetim/raporlar'], 'key' => 'g r'],
            ['label' => 'İşlem izi', 'icon' => 'shield-check', 'route' => 'audit.index', 'can' => 'audit.view', 'match' => ['/yonetim/islem-izi']],
        ]],
        ['section' => 'Yönetim', 'items' => [
            ['label' => 'Kullanıcılar ve yetkiler', 'icon' => 'person-gear', 'route' => 'users.index', 'can' => 'users.manage', 'match' => ['/yonetim/kullanicilar']],
            ['label' => 'Dönemler', 'icon' => 'calendar-check', 'route' => 'periods.index', 'can' => 'periods.manage', 'match' => ['/yonetim/donemler']],
            ['label' => 'Ayarlar', 'icon' => 'sliders', 'route' => 'settings.index', 'can' => 'settings.manage', 'match' => ['/yonetim/ayarlar']],
            ['label' => 'Yedek ve dışa aktarım', 'icon' => 'cloud-download', 'route' => 'backup.index', 'can' => 'backup.manage', 'match' => ['/yonetim/yedek']],
        ]],
    ],
    'portal' => [
        ['section' => null, 'items' => [
            ['label' => 'Özet', 'icon' => 'house-heart', 'route' => 'portal.home', 'match' => ['/sakin'], 'exact' => true],
            ['label' => 'Borçlarım', 'icon' => 'receipt', 'route' => 'portal.charges', 'match' => ['/sakin/borclarim']],
            ['label' => 'Ödemelerim ve makbuzlar', 'icon' => 'file-earmark-check', 'route' => 'portal.payments', 'match' => ['/sakin/odemelerim']],
            ['label' => 'Hesap ekstrem', 'icon' => 'journal-text', 'route' => 'portal.statement', 'match' => ['/sakin/ekstre']],
        ]],
        ['section' => 'Şeffaflık', 'items' => [
            ['label' => 'Yapının mali durumu', 'icon' => 'bar-chart-line', 'route' => 'portal.finance', 'match' => ['/sakin/mali-durum']],
            ['label' => 'Giderler ve belgeler', 'icon' => 'cart-dash', 'route' => 'portal.expenses', 'match' => ['/sakin/giderler']],
            ['label' => 'Bütçe / işletme projesi', 'icon' => 'clipboard-data', 'route' => 'portal.budget', 'match' => ['/sakin/butce']],
            ['label' => 'Borçlu listesi', 'icon' => 'exclamation-diamond', 'route' => 'portal.debtors', 'match' => ['/sakin/borclular']],
        ]],
        ['section' => 'Yaşam', 'items' => [
            ['label' => 'Duyurular', 'icon' => 'megaphone', 'route' => 'portal.announcements', 'match' => ['/sakin/duyurular']],
            ['label' => 'Toplantı ve kararlar', 'icon' => 'people-fill', 'route' => 'portal.meetings', 'match' => ['/sakin/toplantilar']],
            ['label' => 'Anketler', 'icon' => 'ui-checks', 'route' => 'portal.polls', 'match' => ['/sakin/anketler']],
            ['label' => 'Taleplerim', 'icon' => 'tools', 'route' => 'portal.requests', 'match' => ['/sakin/talepler']],
            ['label' => 'Belgeler', 'icon' => 'folder2-open', 'route' => 'portal.documents', 'match' => ['/sakin/belgeler']],
            ['label' => 'Sayaçlarım', 'icon' => 'speedometer2', 'route' => 'portal.meters', 'match' => ['/sakin/sayaclar']],
        ]],
    ],
];
