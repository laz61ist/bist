<?php

declare(strict_types=1);

/**
 * Yetki anahtarları ve rol ön tanımları.
 * Ekran gizlemek güvenlik değildir: her denetim sunucuda Gate ile yapılır.
 */
$groups = [
    'Genel' => [
        'dashboard.view' => 'Genel bakış ekranı',
    ],
    'Yapı yönetimi' => [
        'buildings.manage' => 'Yapı ve blok bilgilerini düzenleme',
        'units.view' => 'Bağımsız bölümleri görme',
        'units.manage' => 'Bağımsız bölüm ekleme/düzenleme',
        'people.view' => 'Kişileri görme',
        'people.manage' => 'Kişi ve oturum kaydı ekleme/düzenleme/devir',
        'people.identity' => 'TCKN/VKN maskesiz görme',
    ],
    'Aidat ve borçlar' => [
        'charges.view' => 'Tahakkuk ve borçları görme',
        'charges.manage' => 'Tahakkuk planı, toplu ve tekil borçlandırma',
        'charges.cancel' => 'Tahakkuk iptali (ters kayıt)',
        'latefee.manage' => 'Gecikme tazminatı hesaplama ve uygulama',
    ],
    'Tahsilat' => [
        'payments.view' => 'Tahsilat ve makbuzları görme',
        'payments.create' => 'Tahsilat girişi ve makbuz kesme',
        'payments.cancel' => 'Tahsilat iptal/iade',
        'payments.import' => 'Banka ekstresi içe aktarma ve eşleştirme',
    ],
    'Gelir-gider' => [
        'expenses.view' => 'Giderleri görme',
        'expenses.manage' => 'Gider ekleme/düzenleme',
        'expenses.cancel' => 'Gider iptali',
        'incomes.view' => 'Diğer gelirleri görme',
        'incomes.manage' => 'Diğer gelir ekleme/düzenleme',
        'accounts.view' => 'Kasa/banka hesaplarını ve hareketleri görme',
        'accounts.manage' => 'Kasa/banka hesabı ekleme ve virman',
        'vendors.manage' => 'Tedarikçi ve sözleşme yönetimi',
        'budgets.view' => 'Bütçe / işletme projesini görme',
        'budgets.manage' => 'Bütçe / işletme projesi hazırlama ve onaylama',
    ],
    'Operasyon' => [
        'meters.view' => 'Sayaçları görme',
        'meters.manage' => 'Sayaç okuma girişi ve tüketim dağıtımı',
        'requests.view' => 'Talep ve iş emirlerini görme',
        'requests.manage' => 'Talep atama, durum ve masraf güncelleme',
        'staff.manage' => 'Personel kayıtları',
        'assets.manage' => 'Demirbaş kayıtları',
    ],
    'İletişim' => [
        'announcements.manage' => 'Duyuru yayınlama',
        'notifications.send' => 'Bildirim ve borç hatırlatma gönderme',
        'documents.view' => 'Belgeleri görme',
        'documents.manage' => 'Belge yükleme/silme',
        'meetings.manage' => 'Toplantı ve karar defteri',
        'polls.manage' => 'Anket ve oylama yönetimi',
    ],
    'Raporlar' => [
        'reports.view' => 'Raporları görme',
        'reports.export' => 'PDF/Excel/CSV dışa aktarma',
        'audit.view' => 'İşlem izini görme',
    ],
    'Yönetim' => [
        'users.manage' => 'Kullanıcı ve yetki yönetimi',
        'settings.manage' => 'Yapı ayarları, numaralandırma, gecikme kuralları',
        'periods.manage' => 'Dönem kapama / açma',
        'backup.manage' => 'Yedek alma ve veri dışa aktarma',
    ],
];

$all = [];
foreach ($groups as $items) {
    foreach ($items as $key => $label) {
        $all[] = $key;
    }
}
$views = array_values(array_filter($all, static fn (string $k) => str_ends_with($k, '.view')));

return [
    'groups' => $groups,
    'all' => $all,
    'roles' => [
        'admin' => ['label' => 'Süper yönetici', 'description' => 'Tüm yapılar, kullanıcılar, dönem kapama, ayarlar, geri alma.', 'permissions' => ['*'], 'portal' => false],
        'manager' => ['label' => 'Yönetici', 'description' => 'Yapı, sakin, tahakkuk, tahsilat, gelir-gider, rapor ve duyuru.', 'permissions' => $all, 'portal' => false],
        'accountant' => ['label' => 'Muhasebe sorumlusu', 'description' => 'Finansal kayıt ve rapor; kullanıcı/yetki yönetemez.', 'permissions' => array_values(array_diff($all, ['users.manage', 'settings.manage', 'buildings.manage', 'periods.manage', 'backup.manage'])), 'portal' => false],
        'auditor' => ['label' => 'Denetçi', 'description' => 'Tüm finans ve belgelerde salt okunur; işlem izi ve dönem raporu.', 'permissions' => array_values(array_unique(array_merge($views, ['reports.export', 'audit.view']))), 'portal' => false],
        'staff' => ['label' => 'Görevli', 'description' => 'Atanan işler, sayaç okumaları, izin verilen gider girişi.', 'permissions' => ['dashboard.view', 'requests.view', 'requests.manage', 'meters.view', 'meters.manage', 'expenses.view', 'expenses.manage', 'units.view', 'documents.view'], 'portal' => false],
        'owner' => ['label' => 'Malik', 'description' => 'Kendi bağımsız bölümleri, borç/alacak, makbuzlar, gider belgeleri, duyurular.', 'permissions' => [], 'portal' => true],
        'tenant' => ['label' => 'Kiracı', 'description' => 'Sorumlu olduğu dönemler, makbuzlar, duyurular ve talepler.', 'permissions' => [], 'portal' => true],
    ],
];
