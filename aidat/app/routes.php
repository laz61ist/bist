<?php

declare(strict_types=1);

/** @var \Aidat\Core\Router $router */

use Aidat\Controllers\Auth\AuthController;
use Aidat\Controllers\Manager as M;
use Aidat\Controllers\Portal\PortalController;
use Aidat\Controllers\Shared\FileController;
use Aidat\Controllers\Shared\HomeController;
use Aidat\Controllers\Shared\ProfileController;
use Aidat\Controllers\Shared\ReceiptVerifyController;
use Aidat\Http\Middleware as MW;

$router->aliasMiddleware([
    'auth' => MW\AuthMiddleware::class,
    'guest' => MW\GuestMiddleware::class,
    'csrf' => MW\CsrfMiddleware::class,
    'building' => MW\BuildingMiddleware::class,
    'can' => MW\CanMiddleware::class,
    'admin' => MW\AdminMiddleware::class,
    'portal' => MW\PortalMiddleware::class,
]);

// ---------------- Açık rotalar ----------------
$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/saglik', [HomeController::class, 'health'])->name('health');
$router->get('/giris', [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
$router->post('/giris', [AuthController::class, 'login'])->middleware(['guest', 'csrf'])->name('login.post');
$router->post('/cikis', [AuthController::class, 'logout'])->middleware(['auth', 'csrf'])->name('logout');
$router->get('/sifremi-unuttum', [AuthController::class, 'showForgot'])->middleware('guest')->name('password.forgot');
$router->post('/sifremi-unuttum', [AuthController::class, 'sendReset'])->middleware(['guest', 'csrf'])->name('password.email');
$router->get('/sifre-sifirla/{token}', [AuthController::class, 'showReset'])->middleware('guest')->name('password.reset');
$router->post('/sifre-sifirla/{token}', [AuthController::class, 'reset'])->middleware(['guest', 'csrf'])->name('password.update');
$router->get('/makbuz-dogrula/{code}', [ReceiptVerifyController::class, 'show'])->name('receipt.verify');
$router->get('/dosya/{token}', [FileController::class, 'signed'])->name('file.signed');

// ---------------- Oturum açmış herkes ----------------
$router->group(['middleware' => ['auth', 'csrf']], static function ($router): void {
    $router->get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    $router->post('/profil', [ProfileController::class, 'update'])->name('profile.update');
    $router->post('/profil/sifre', [ProfileController::class, 'password'])->name('profile.password');
    $router->get('/belge/{id:\d+}', [FileController::class, 'document'])->name('documents.download');
    $router->post('/bildirimler/okundu', [M\NotificationController::class, 'readAll'])->name('notifications.read_all');
});

// ---------------- Yönetim alanı ----------------
$router->group(['prefix' => '/yonetim', 'middleware' => ['auth', 'csrf', 'building']], static function ($router): void {
    $router->get('', [M\DashboardController::class, 'index'])->middleware('can:dashboard.view')->name('dashboard');
    $router->get('/ara', [M\SearchController::class, 'index'])->name('search');

    // Yapılar
    $router->get('/yapilar', [M\BuildingController::class, 'index'])->name('buildings.index');
    $router->get('/yapilar/yeni', [M\BuildingController::class, 'create'])->name('buildings.create');
    $router->post('/yapilar', [M\BuildingController::class, 'store'])->name('buildings.store');
    $router->get('/yapilar/{id:\d+}/duzenle', [M\BuildingController::class, 'edit'])->middleware('can:buildings.manage')->name('buildings.edit');
    $router->post('/yapilar/{id:\d+}', [M\BuildingController::class, 'update'])->middleware('can:buildings.manage')->name('buildings.update');
    $router->post('/yapilar/{id:\d+}/sec', [M\BuildingController::class, 'switch'])->name('buildings.switch');

    // Bloklar ve aidat grupları
    $router->get('/bloklar', [M\BlockController::class, 'index'])->middleware('can:units.view')->name('blocks.index');
    $router->post('/bloklar', [M\BlockController::class, 'store'])->middleware('can:buildings.manage')->name('blocks.store');
    $router->post('/bloklar/{id:\d+}', [M\BlockController::class, 'update'])->middleware('can:buildings.manage')->name('blocks.update');
    $router->post('/bloklar/{id:\d+}/sil', [M\BlockController::class, 'destroy'])->middleware('can:buildings.manage')->name('blocks.destroy');
    $router->post('/aidat-gruplari', [M\BlockController::class, 'storeGroup'])->middleware('can:buildings.manage')->name('fee_groups.store');
    $router->post('/aidat-gruplari/{id:\d+}/sil', [M\BlockController::class, 'destroyGroup'])->middleware('can:buildings.manage')->name('fee_groups.destroy');

    // Bağımsız bölümler
    $router->get('/bolumler', [M\UnitController::class, 'index'])->middleware('can:units.view')->name('units.index');
    $router->get('/bolumler/yeni', [M\UnitController::class, 'create'])->middleware('can:units.manage')->name('units.create');
    $router->get('/bolumler/toplu', [M\UnitController::class, 'bulk'])->middleware('can:units.manage')->name('units.bulk');
    $router->post('/bolumler/toplu', [M\UnitController::class, 'bulkStore'])->middleware('can:units.manage')->name('units.bulk_store');
    $router->post('/bolumler', [M\UnitController::class, 'store'])->middleware('can:units.manage')->name('units.store');
    $router->get('/bolumler/{id:\d+}', [M\UnitController::class, 'show'])->middleware('can:units.view')->name('units.show');
    $router->get('/bolumler/{id:\d+}/duzenle', [M\UnitController::class, 'edit'])->middleware('can:units.manage')->name('units.edit');
    $router->post('/bolumler/{id:\d+}', [M\UnitController::class, 'update'])->middleware('can:units.manage')->name('units.update');
    $router->post('/bolumler/{id:\d+}/arac', [M\UnitController::class, 'addVehicle'])->middleware('can:units.manage')->name('units.vehicle_add');
    $router->post('/bolumler/{id:\d+}/arac/{vid:\d+}/sil', [M\UnitController::class, 'removeVehicle'])->middleware('can:units.manage')->name('units.vehicle_remove');
    $router->get('/bolumler/{id:\d+}/ekstre', [M\UnitController::class, 'statement'])->middleware('can:units.view')->name('units.statement');

    // Kişiler
    $router->get('/kisiler', [M\PersonController::class, 'index'])->middleware('can:people.view')->name('people.index');
    $router->get('/kisiler/yeni', [M\PersonController::class, 'create'])->middleware('can:people.manage')->name('people.create');
    $router->post('/kisiler', [M\PersonController::class, 'store'])->middleware('can:people.manage')->name('people.store');
    $router->get('/kisiler/{id:\d+}', [M\PersonController::class, 'show'])->middleware('can:people.view')->name('people.show');
    $router->get('/kisiler/{id:\d+}/duzenle', [M\PersonController::class, 'edit'])->middleware('can:people.manage')->name('people.edit');
    $router->post('/kisiler/{id:\d+}', [M\PersonController::class, 'update'])->middleware('can:people.manage')->name('people.update');
    $router->post('/kisiler/{id:\d+}/hesap', [M\PersonController::class, 'createAccount'])->middleware('can:users.manage')->name('people.account');

    // Oturum ve devir
    $router->get('/oturumlar', [M\OccupancyController::class, 'index'])->middleware('can:people.view')->name('occupancies.index');
    $router->post('/oturumlar', [M\OccupancyController::class, 'store'])->middleware('can:people.manage')->name('occupancies.store');
    $router->post('/oturumlar/{id:\d+}/bitir', [M\OccupancyController::class, 'end'])->middleware('can:people.manage')->name('occupancies.end');
    $router->get('/devirler/yeni', [M\OccupancyController::class, 'handoverCreate'])->middleware('can:people.manage')->name('handovers.create');
    $router->post('/devirler', [M\OccupancyController::class, 'handoverStore'])->middleware('can:people.manage')->name('handovers.store');
    $router->get('/devirler/{id:\d+}', [M\OccupancyController::class, 'handoverShow'])->middleware('can:people.view')->name('handovers.show');

    // Tahakkuk planları
    $router->get('/tahakkuk-planlari', [M\PlanController::class, 'index'])->middleware('can:charges.view')->name('plans.index');
    $router->get('/tahakkuk-planlari/yeni', [M\PlanController::class, 'create'])->middleware('can:charges.manage')->name('plans.create');
    $router->post('/tahakkuk-planlari/onizleme', [M\PlanController::class, 'preview'])->middleware('can:charges.manage')->name('plans.preview');
    $router->post('/tahakkuk-planlari', [M\PlanController::class, 'store'])->middleware('can:charges.manage')->name('plans.store');
    $router->get('/tahakkuk-planlari/{id:\d+}', [M\PlanController::class, 'show'])->middleware('can:charges.view')->name('plans.show');
    $router->post('/tahakkuk-planlari/{id:\d+}/onayla', [M\PlanController::class, 'approve'])->middleware('can:charges.manage')->name('plans.approve');
    $router->post('/tahakkuk-planlari/{id:\d+}/isle', [M\PlanController::class, 'process'])->middleware('can:charges.manage')->name('plans.process');
    $router->post('/tahakkuk-planlari/{id:\d+}/iptal', [M\PlanController::class, 'cancel'])->middleware('can:charges.cancel')->name('plans.cancel');

    // Borçlar
    $router->get('/borclar', [M\ChargeController::class, 'index'])->middleware('can:charges.view')->name('charges.index');
    $router->get('/borclar/yeni', [M\ChargeController::class, 'create'])->middleware('can:charges.manage')->name('charges.create');
    $router->post('/borclar', [M\ChargeController::class, 'store'])->middleware('can:charges.manage')->name('charges.store');
    $router->get('/borclar/{id:\d+}', [M\ChargeController::class, 'show'])->middleware('can:charges.view')->name('charges.show');
    $router->post('/borclar/{id:\d+}/iptal', [M\ChargeController::class, 'cancel'])->middleware('can:charges.cancel')->name('charges.cancel');
    $router->get('/gecikme', [M\LateFeeController::class, 'index'])->middleware('can:latefee.manage')->name('latefee.index');
    $router->post('/gecikme/uygula', [M\LateFeeController::class, 'apply'])->middleware('can:latefee.manage')->name('latefee.apply');
    $router->get('/borclular', [M\DebtorController::class, 'index'])->middleware('can:charges.view')->name('debtors.index');
    $router->post('/borclular/hatirlat', [M\DebtorController::class, 'remind'])->middleware('can:notifications.send')->name('debtors.remind');
    $router->get('/avanslar', [M\AdvanceController::class, 'index'])->middleware('can:payments.view')->name('advances.index');
    $router->post('/avanslar/mahsup', [M\AdvanceController::class, 'apply'])->middleware('can:payments.create')->name('advances.apply');

    // Tahsilat ve makbuz
    $router->get('/tahsilatlar', [M\PaymentController::class, 'index'])->middleware('can:payments.view')->name('payments.index');
    $router->get('/tahsilatlar/yeni', [M\PaymentController::class, 'create'])->middleware('can:payments.create')->name('payments.create');
    $router->post('/tahsilatlar', [M\PaymentController::class, 'store'])->middleware('can:payments.create')->name('payments.store');
    $router->get('/tahsilatlar/{id:\d+}', [M\PaymentController::class, 'show'])->middleware('can:payments.view')->name('payments.show');
    $router->post('/tahsilatlar/{id:\d+}/iptal', [M\PaymentController::class, 'cancel'])->middleware('can:payments.cancel')->name('payments.cancel');
    $router->post('/tahsilatlar/{id:\d+}/iade', [M\PaymentController::class, 'refund'])->middleware('can:payments.cancel')->name('payments.refund');
    $router->get('/tahsilatlar/{id:\d+}/makbuz', [M\PaymentController::class, 'receipt'])->middleware('can:payments.view')->name('payments.receipt');
    $router->get('/bolum-borclari/{id:\d+}', [M\PaymentController::class, 'unitCharges'])->middleware('can:payments.create')->name('payments.unit_charges');
    $router->get('/makbuzlar', [M\ReceiptController::class, 'index'])->middleware('can:payments.view')->name('receipts.index');
    $router->get('/banka-aktarim', [M\ImportController::class, 'index'])->middleware('can:payments.import')->name('imports.index');
    $router->post('/banka-aktarim', [M\ImportController::class, 'store'])->middleware('can:payments.import')->name('imports.store');
    $router->get('/banka-aktarim/{id:\d+}', [M\ImportController::class, 'show'])->middleware('can:payments.import')->name('imports.show');
    $router->post('/banka-aktarim/satir/{id:\d+}/eslestir', [M\ImportController::class, 'match'])->middleware('can:payments.import')->name('imports.match');
    $router->post('/banka-aktarim/satir/{id:\d+}/yoksay', [M\ImportController::class, 'ignore'])->middleware('can:payments.import')->name('imports.ignore');

    // Gelir-gider
    $router->get('/giderler', [M\ExpenseController::class, 'index'])->middleware('can:expenses.view')->name('expenses.index');
    $router->get('/giderler/yeni', [M\ExpenseController::class, 'create'])->middleware('can:expenses.manage')->name('expenses.create');
    $router->post('/giderler', [M\ExpenseController::class, 'store'])->middleware('can:expenses.manage')->name('expenses.store');
    $router->get('/giderler/{id:\d+}', [M\ExpenseController::class, 'show'])->middleware('can:expenses.view')->name('expenses.show');
    $router->get('/giderler/{id:\d+}/duzenle', [M\ExpenseController::class, 'edit'])->middleware('can:expenses.manage')->name('expenses.edit');
    $router->post('/giderler/{id:\d+}', [M\ExpenseController::class, 'update'])->middleware('can:expenses.manage')->name('expenses.update');
    $router->post('/giderler/{id:\d+}/ode', [M\ExpenseController::class, 'pay'])->middleware('can:expenses.manage')->name('expenses.pay');
    $router->post('/giderler/{id:\d+}/iptal', [M\ExpenseController::class, 'cancel'])->middleware('can:expenses.cancel')->name('expenses.cancel');
    $router->get('/gider-kategorileri', [M\CategoryController::class, 'index'])->middleware('can:expenses.view')->name('categories.index');
    $router->post('/gider-kategorileri', [M\CategoryController::class, 'store'])->middleware('can:settings.manage')->name('categories.store');
    $router->post('/gider-kategorileri/{id:\d+}', [M\CategoryController::class, 'update'])->middleware('can:settings.manage')->name('categories.update');
    $router->post('/gider-kategorileri/{id:\d+}/sil', [M\CategoryController::class, 'destroy'])->middleware('can:settings.manage')->name('categories.destroy');
    $router->get('/gelirler', [M\IncomeController::class, 'index'])->middleware('can:incomes.view')->name('incomes.index');
    $router->get('/gelirler/yeni', [M\IncomeController::class, 'create'])->middleware('can:incomes.manage')->name('incomes.create');
    $router->post('/gelirler', [M\IncomeController::class, 'store'])->middleware('can:incomes.manage')->name('incomes.store');
    $router->post('/gelirler/{id:\d+}/iptal', [M\IncomeController::class, 'cancel'])->middleware('can:incomes.manage')->name('incomes.cancel');
    $router->get('/hesaplar', [M\AccountController::class, 'index'])->middleware('can:accounts.view')->name('accounts.index');
    $router->get('/hesaplar/yeni', [M\AccountController::class, 'create'])->middleware('can:accounts.manage')->name('accounts.create');
    $router->post('/hesaplar', [M\AccountController::class, 'store'])->middleware('can:accounts.manage')->name('accounts.store');
    $router->get('/hesaplar/{id:\d+}', [M\AccountController::class, 'show'])->middleware('can:accounts.view')->name('accounts.show');
    $router->get('/hesaplar/{id:\d+}/duzenle', [M\AccountController::class, 'edit'])->middleware('can:accounts.manage')->name('accounts.edit');
    $router->post('/hesaplar/{id:\d+}', [M\AccountController::class, 'update'])->middleware('can:accounts.manage')->name('accounts.update');
    $router->get('/virmanlar', [M\TransferController::class, 'index'])->middleware('can:accounts.view')->name('transfers.index');
    $router->get('/virmanlar/yeni', [M\TransferController::class, 'create'])->middleware('can:accounts.manage')->name('transfers.create');
    $router->post('/virmanlar', [M\TransferController::class, 'store'])->middleware('can:accounts.manage')->name('transfers.store');
    $router->post('/virmanlar/{id:\d+}/iptal', [M\TransferController::class, 'cancel'])->middleware('can:accounts.manage')->name('transfers.cancel');
    $router->get('/periyodik-giderler', [M\RecurringController::class, 'index'])->middleware('can:expenses.view')->name('recurring.index');
    $router->get('/periyodik-giderler/yeni', [M\RecurringController::class, 'create'])->middleware('can:expenses.manage')->name('recurring.create');
    $router->post('/periyodik-giderler', [M\RecurringController::class, 'store'])->middleware('can:expenses.manage')->name('recurring.store');
    $router->get('/periyodik-giderler/{id:\d+}/duzenle', [M\RecurringController::class, 'edit'])->middleware('can:expenses.manage')->name('recurring.edit');
    $router->post('/periyodik-giderler/{id:\d+}', [M\RecurringController::class, 'update'])->middleware('can:expenses.manage')->name('recurring.update');
    $router->post('/periyodik-giderler/uret', [M\RecurringController::class, 'generate'])->middleware('can:expenses.manage')->name('recurring.generate');
    $router->get('/tedarikciler', [M\VendorController::class, 'index'])->middleware('can:expenses.view')->name('vendors.index');
    $router->get('/tedarikciler/yeni', [M\VendorController::class, 'create'])->middleware('can:vendors.manage')->name('vendors.create');
    $router->post('/tedarikciler', [M\VendorController::class, 'store'])->middleware('can:vendors.manage')->name('vendors.store');
    $router->get('/tedarikciler/{id:\d+}', [M\VendorController::class, 'show'])->middleware('can:expenses.view')->name('vendors.show');
    $router->get('/tedarikciler/{id:\d+}/duzenle', [M\VendorController::class, 'edit'])->middleware('can:vendors.manage')->name('vendors.edit');
    $router->post('/tedarikciler/{id:\d+}', [M\VendorController::class, 'update'])->middleware('can:vendors.manage')->name('vendors.update');
    $router->get('/sozlesmeler', [M\ContractController::class, 'index'])->middleware('can:expenses.view')->name('contracts.index');
    $router->get('/sozlesmeler/yeni', [M\ContractController::class, 'create'])->middleware('can:vendors.manage')->name('contracts.create');
    $router->post('/sozlesmeler', [M\ContractController::class, 'store'])->middleware('can:vendors.manage')->name('contracts.store');
    $router->get('/sozlesmeler/{id:\d+}/duzenle', [M\ContractController::class, 'edit'])->middleware('can:vendors.manage')->name('contracts.edit');
    $router->post('/sozlesmeler/{id:\d+}', [M\ContractController::class, 'update'])->middleware('can:vendors.manage')->name('contracts.update');
    $router->get('/butceler', [M\BudgetController::class, 'index'])->middleware('can:budgets.view')->name('budgets.index');
    $router->get('/butceler/yeni', [M\BudgetController::class, 'create'])->middleware('can:budgets.manage')->name('budgets.create');
    $router->post('/butceler', [M\BudgetController::class, 'store'])->middleware('can:budgets.manage')->name('budgets.store');
    $router->get('/butceler/{id:\d+}', [M\BudgetController::class, 'show'])->middleware('can:budgets.view')->name('budgets.show');
    $router->post('/butceler/{id:\d+}', [M\BudgetController::class, 'update'])->middleware('can:budgets.manage')->name('budgets.update');
    $router->post('/butceler/{id:\d+}/kalem', [M\BudgetController::class, 'addLine'])->middleware('can:budgets.manage')->name('budgets.line_add');
    $router->post('/butceler/{id:\d+}/kalem/{lid:\d+}/sil', [M\BudgetController::class, 'removeLine'])->middleware('can:budgets.manage')->name('budgets.line_remove');
    $router->post('/butceler/{id:\d+}/onayla', [M\BudgetController::class, 'approve'])->middleware('can:budgets.manage')->name('budgets.approve');
    $router->post('/butceler/{id:\d+}/revizyon', [M\BudgetController::class, 'revise'])->middleware('can:budgets.manage')->name('budgets.revise');
    $router->get('/butceler/{id:\d+}/yazdir', [M\BudgetController::class, 'print'])->middleware('can:budgets.view')->name('budgets.print');

    // Operasyon
    $router->get('/sayaclar', [M\MeterController::class, 'index'])->middleware('can:meters.view')->name('meters.index');
    $router->post('/sayaclar', [M\MeterController::class, 'store'])->middleware('can:meters.manage')->name('meters.store');
    $router->post('/sayaclar/{id:\d+}/okuma', [M\MeterController::class, 'reading'])->middleware('can:meters.manage')->name('meters.reading');
    $router->post('/sayaclar/toplu-okuma', [M\MeterController::class, 'bulkReading'])->middleware('can:meters.manage')->name('meters.bulk_reading');
    $router->post('/sayaclar/dagit', [M\MeterController::class, 'distribute'])->middleware('can:meters.manage')->name('meters.distribute');
    $router->get('/talepler', [M\RequestController::class, 'index'])->middleware('can:requests.view')->name('requests.index');
    $router->get('/talepler/yeni', [M\RequestController::class, 'create'])->middleware('can:requests.manage')->name('requests.create');
    $router->post('/talepler', [M\RequestController::class, 'store'])->middleware('can:requests.manage')->name('requests.store');
    $router->get('/talepler/{id:\d+}', [M\RequestController::class, 'show'])->middleware('can:requests.view')->name('requests.show');
    $router->post('/talepler/{id:\d+}', [M\RequestController::class, 'update'])->middleware('can:requests.manage')->name('requests.update');
    $router->post('/talepler/{id:\d+}/yorum', [M\RequestController::class, 'comment'])->middleware('can:requests.view')->name('requests.comment');
    $router->get('/personel', [M\StaffController::class, 'index'])->middleware('can:staff.manage')->name('staff.index');
    $router->get('/personel/yeni', [M\StaffController::class, 'create'])->middleware('can:staff.manage')->name('staff.create');
    $router->post('/personel', [M\StaffController::class, 'store'])->middleware('can:staff.manage')->name('staff.store');
    $router->get('/personel/{id:\d+}/duzenle', [M\StaffController::class, 'edit'])->middleware('can:staff.manage')->name('staff.edit');
    $router->post('/personel/{id:\d+}', [M\StaffController::class, 'update'])->middleware('can:staff.manage')->name('staff.update');
    $router->get('/demirbas', [M\AssetController::class, 'index'])->middleware('can:assets.manage')->name('assets.index');
    $router->get('/demirbas/yeni', [M\AssetController::class, 'create'])->middleware('can:assets.manage')->name('assets.create');
    $router->post('/demirbas', [M\AssetController::class, 'store'])->middleware('can:assets.manage')->name('assets.store');
    $router->get('/demirbas/{id:\d+}/duzenle', [M\AssetController::class, 'edit'])->middleware('can:assets.manage')->name('assets.edit');
    $router->post('/demirbas/{id:\d+}', [M\AssetController::class, 'update'])->middleware('can:assets.manage')->name('assets.update');

    // İletişim
    $router->get('/duyurular', [M\AnnouncementController::class, 'index'])->middleware('can:announcements.manage')->name('announcements.index');
    $router->get('/duyurular/yeni', [M\AnnouncementController::class, 'create'])->middleware('can:announcements.manage')->name('announcements.create');
    $router->post('/duyurular', [M\AnnouncementController::class, 'store'])->middleware('can:announcements.manage')->name('announcements.store');
    $router->get('/duyurular/{id:\d+}', [M\AnnouncementController::class, 'show'])->middleware('can:announcements.manage')->name('announcements.show');
    $router->get('/duyurular/{id:\d+}/duzenle', [M\AnnouncementController::class, 'edit'])->middleware('can:announcements.manage')->name('announcements.edit');
    $router->post('/duyurular/{id:\d+}', [M\AnnouncementController::class, 'update'])->middleware('can:announcements.manage')->name('announcements.update');
    $router->post('/duyurular/{id:\d+}/sil', [M\AnnouncementController::class, 'destroy'])->middleware('can:announcements.manage')->name('announcements.destroy');
    $router->get('/toplantilar', [M\MeetingController::class, 'index'])->middleware('can:meetings.manage')->name('meetings.index');
    $router->get('/toplantilar/yeni', [M\MeetingController::class, 'create'])->middleware('can:meetings.manage')->name('meetings.create');
    $router->post('/toplantilar', [M\MeetingController::class, 'store'])->middleware('can:meetings.manage')->name('meetings.store');
    $router->get('/toplantilar/{id:\d+}', [M\MeetingController::class, 'show'])->middleware('can:meetings.manage')->name('meetings.show');
    $router->get('/toplantilar/{id:\d+}/duzenle', [M\MeetingController::class, 'edit'])->middleware('can:meetings.manage')->name('meetings.edit');
    $router->post('/toplantilar/{id:\d+}', [M\MeetingController::class, 'update'])->middleware('can:meetings.manage')->name('meetings.update');
    $router->post('/toplantilar/{id:\d+}/karar', [M\MeetingController::class, 'addDecision'])->middleware('can:meetings.manage')->name('meetings.decision_add');
    $router->post('/toplantilar/{id:\d+}/karar/{did:\d+}/sil', [M\MeetingController::class, 'removeDecision'])->middleware('can:meetings.manage')->name('meetings.decision_remove');
    $router->post('/toplantilar/{id:\d+}/katilim', [M\MeetingController::class, 'attendance'])->middleware('can:meetings.manage')->name('meetings.attendance');
    $router->get('/toplantilar/{id:\d+}/yazdir', [M\MeetingController::class, 'print'])->middleware('can:meetings.manage')->name('meetings.print');
    $router->get('/anketler', [M\PollController::class, 'index'])->middleware('can:polls.manage')->name('polls.index');
    $router->get('/anketler/yeni', [M\PollController::class, 'create'])->middleware('can:polls.manage')->name('polls.create');
    $router->post('/anketler', [M\PollController::class, 'store'])->middleware('can:polls.manage')->name('polls.store');
    $router->get('/anketler/{id:\d+}', [M\PollController::class, 'show'])->middleware('can:polls.manage')->name('polls.show');
    $router->post('/anketler/{id:\d+}/kapat', [M\PollController::class, 'close'])->middleware('can:polls.manage')->name('polls.close');
    $router->get('/bildirimler', [M\NotificationController::class, 'index'])->middleware('can:notifications.send')->name('notifications.index');
    $router->get('/bildirimler/gonder', [M\NotificationController::class, 'create'])->middleware('can:notifications.send')->name('notifications.create');
    $router->post('/bildirimler/gonder', [M\NotificationController::class, 'store'])->middleware('can:notifications.send')->name('notifications.store');
    $router->post('/bildirimler/sablon', [M\NotificationController::class, 'saveTemplate'])->middleware('can:settings.manage')->name('notifications.template');
    $router->get('/belgeler', [M\DocumentController::class, 'index'])->middleware('can:documents.view')->name('documents.index');
    $router->post('/belgeler', [M\DocumentController::class, 'store'])->middleware('can:documents.manage')->name('documents.store');
    $router->post('/belgeler/{id:\d+}/sil', [M\DocumentController::class, 'destroy'])->middleware('can:documents.manage')->name('documents.destroy');

    // Raporlar ve iz
    $router->get('/raporlar', [M\ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
    $router->get('/raporlar/{slug}', [M\ReportController::class, 'show'])->middleware('can:reports.view')->name('reports.show');
    $router->get('/raporlar/{slug}/disa-aktar', [M\ReportController::class, 'export'])->middleware('can:reports.export')->name('reports.export');
    $router->get('/islem-izi', [M\AuditController::class, 'index'])->middleware('can:audit.view')->name('audit.index');

    // Yönetim
    $router->get('/kullanicilar', [M\UserController::class, 'index'])->middleware('can:users.manage')->name('users.index');
    $router->get('/kullanicilar/yeni', [M\UserController::class, 'create'])->middleware('can:users.manage')->name('users.create');
    $router->post('/kullanicilar', [M\UserController::class, 'store'])->middleware('can:users.manage')->name('users.store');
    $router->get('/kullanicilar/{id:\d+}/duzenle', [M\UserController::class, 'edit'])->middleware('can:users.manage')->name('users.edit');
    $router->post('/kullanicilar/{id:\d+}', [M\UserController::class, 'update'])->middleware('can:users.manage')->name('users.update');
    $router->post('/kullanicilar/{id:\d+}/yetkiler', [M\UserController::class, 'permissions'])->middleware('can:users.manage')->name('users.permissions');
    $router->post('/kullanicilar/{id:\d+}/sifre', [M\UserController::class, 'resetPassword'])->middleware('can:users.manage')->name('users.password');
    $router->post('/kullanicilar/{id:\d+}/kaldir', [M\UserController::class, 'detach'])->middleware('can:users.manage')->name('users.detach');
    $router->get('/donemler', [M\PeriodController::class, 'index'])->middleware('can:periods.manage')->name('periods.index');
    $router->post('/donemler/kapat', [M\PeriodController::class, 'close'])->middleware('can:periods.manage')->name('periods.close');
    $router->post('/donemler/ac', [M\PeriodController::class, 'reopen'])->middleware('can:periods.manage')->name('periods.reopen');
    $router->get('/ayarlar', [M\SettingsController::class, 'index'])->middleware('can:settings.manage')->name('settings.index');
    $router->post('/ayarlar', [M\SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
    $router->get('/yedek', [M\BackupController::class, 'index'])->middleware('can:backup.manage')->name('backup.index');
    $router->post('/yedek', [M\BackupController::class, 'create'])->middleware('can:backup.manage')->name('backup.create');
    $router->get('/yedek/indir/{name}', [M\BackupController::class, 'download'])->middleware('can:backup.manage')->name('backup.download');
    $router->get('/yedek/veri-disa-aktar', [M\BackupController::class, 'exportData'])->middleware('can:backup.manage')->name('backup.export');
});

// ---------------- Sakin alanı ----------------
$router->group(['prefix' => '/sakin', 'middleware' => ['auth', 'csrf', 'portal'], 'name' => 'portal.'], static function ($router): void {
    $router->get('', [PortalController::class, 'home'])->name('home');
    $router->post('/bolum-sec', [PortalController::class, 'switchUnit'])->name('switch_unit');
    $router->get('/borclarim', [PortalController::class, 'charges'])->name('charges');
    $router->get('/odemelerim', [PortalController::class, 'payments'])->name('payments');
    $router->get('/odemelerim/{id:\d+}/makbuz', [PortalController::class, 'receipt'])->name('receipt');
    $router->get('/ekstre', [PortalController::class, 'statement'])->name('statement');
    $router->get('/mali-durum', [PortalController::class, 'finance'])->name('finance');
    $router->get('/giderler', [PortalController::class, 'expenses'])->name('expenses');
    $router->get('/butce', [PortalController::class, 'budget'])->name('budget');
    $router->get('/borclular', [PortalController::class, 'debtors'])->name('debtors');
    $router->get('/duyurular', [PortalController::class, 'announcements'])->name('announcements');
    $router->get('/duyurular/{id:\d+}', [PortalController::class, 'announcement'])->name('announcement');
    $router->get('/toplantilar', [PortalController::class, 'meetings'])->name('meetings');
    $router->get('/anketler', [PortalController::class, 'polls'])->name('polls');
    $router->post('/anketler/{id:\d+}/oy', [PortalController::class, 'vote'])->name('vote');
    $router->get('/talepler', [PortalController::class, 'requests'])->name('requests');
    $router->get('/talepler/yeni', [PortalController::class, 'requestCreate'])->name('requests_create');
    $router->post('/talepler', [PortalController::class, 'requestStore'])->name('requests_store');
    $router->get('/talepler/{id:\d+}', [PortalController::class, 'requestShow'])->name('request');
    $router->post('/talepler/{id:\d+}/yorum', [PortalController::class, 'requestComment'])->name('request_comment');
    $router->get('/belgeler', [PortalController::class, 'documents'])->name('documents');
    $router->get('/sayaclar', [PortalController::class, 'meters'])->name('meters');
});
