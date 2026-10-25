<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\FasilitasController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | Web Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register web routes for your application. These
 * | routes are loaded by the RouteServiceProvider and all of them will
 * | be assigned to the "web" middleware group. Make something great!
 * |
 */

Route::get('/', function () {
    return view('welcome');
})->name('welcome.landing');

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::group(['middleware' => ['auth']], function () {
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);
    Route::resource('products', ProductController::class);
});
Route::prefix('master-room')->group(function () {
    Route::GET('/', [RoomController::class, 'index'])->name('room.index');
    Route::GET('get-room', [RoomController::class, 'getroom'])->name('room.getroom');
    Route::GET('/jenis-kamar', [RoomController::class, 'roomtype'])->name('room.roomtype');
    Route::GET('create', [RoomController::class, 'create'])->name('room.create');
    Route::POST('store', [RoomController::class, 'store'])->name('room.store');
    Route::POST('addType', [RoomController::class, 'addType'])->name('room.addType');
    Route::GET('show/{id}', [RoomController::class, 'show'])->name('room.show');
    Route::GET('detail/{id}', [RoomController::class, 'detail'])->name('room.detail');
    Route::GET('show-jenis-kamar/{id}', [RoomController::class, 'showType'])->name('room.showType');
    Route::get('edit/{id}', [RoomController::class, 'edit'])->name('room.edit');
    Route::get('edit-jenis-kamar/{id}', [RoomController::class, 'editType'])->name('room.editType');
    Route::delete('destroy/{id}', [RoomController::class, 'destroy'])->name('room.destroy');
    Route::delete('destroy-jenis-kamar/{id}', [RoomController::class, 'destroyType'])->name('room.destroyType');
    Route::put('update/{id}', [RoomController::class, 'update'])->name('room.update');
    Route::put('update-jenis-kamar/{id}', [RoomController::class, 'updateType'])->name('room.updateType');
});
Route::prefix('booking')->group(function () {
    Route::GET('/daftar-kamar', [BookingController::class, 'index'])->name('booking.index');
    Route::GET('list', [BookingController::class, 'listBooking'])->name('booking.listBooking');
    Route::GET('kalender', [BookingController::class, 'kalender'])->name('booking.kalender');
    Route::GET('ketersediaan', [BookingController::class, 'ketersediaan'])->name('booking.ketersediaan');
    Route::POST('quote', [BookingController::class, 'quote'])->name('booking.quote');
    Route::GET('create/{id}', [BookingController::class, 'create'])->name('booking.create');
    Route::POST('store', [BookingController::class, 'store'])->name('booking.store');
    Route::POST('onlinestore', [BookingController::class, 'onlinestore'])->name('booking.onlinestore');
    Route::get('edit/{id}', [BookingController::class, 'edit'])->name('booking.edit');
    Route::get('getBukti/{id}', [BookingController::class, 'getBukti'])->name('booking.getBukti');
    // Tidak ada route destroy: pembatalan WAJIB lewat booking.batal agar
    // unit dilepas, refund dicatat, dan histori status tertulis.
    Route::post('konfirmasi/{id}', [BookingController::class, 'konfirmasi'])->name('booking.konfirmasi');
    Route::post('checkin/{id}', [BookingController::class, 'checkin'])->name('booking.checkin');
    Route::post('checkout', [BookingController::class, 'checkout'])->name('booking.checkout');
    Route::post('batal/{id}', [BookingController::class, 'batal'])->name('booking.batal');
    Route::GET('status/{kode}', [BookingController::class, 'status'])->name('booking.status');
    Route::GET('detail/{id}', [BookingController::class, 'show'])->name('booking.detail');
    Route::GET('loginuser', [BookingController::class, 'loginuser'])->name('booking.loginuser');
});

// Pembayaran (publik, berbasis kode booking)
// Route statis HARUS didaftarkan sebelum wildcard {kode}.
Route::prefix('payment')->group(function () {
    Route::POST('snap-token', [PaymentController::class, 'snapToken'])->name('payment.snap');
    Route::GET('return', [PaymentController::class, 'returnFromGateway'])->name('payment.return');
    Route::GET('{kode}', [PaymentController::class, 'pay'])->name('payment.pay');
    Route::POST('{kode}/upload', [PaymentController::class, 'uploadProof'])->name('payment.upload');
});

// Webhook gateway: dikecualikan dari CSRF di VerifyCsrfToken
Route::post('webhook/midtrans', [PaymentWebhookController::class, 'midtrans'])->name('webhook.midtrans');
Route::prefix('fasilitas')->group(function () {
    Route::GET('/', [FasilitasController::class, 'index'])->name('fasilitas.index');
    Route::GET('create', [FasilitasController::class, 'create'])->name('fasilitas.create');
    Route::POST('store', [FasilitasController::class, 'store'])->name('fasilitas.store');
    Route::GET('show/{id}', [FasilitasController::class, 'show'])->name('fasilitas.show');
    Route::get('edit/{id}', [FasilitasController::class, 'edit'])->name('fasilitas.edit');
    Route::delete('destroy/{id}', [FasilitasController::class, 'destroy'])->name('fasilitas.destroy');
    Route::put('update/{id}', [FasilitasController::class, 'update'])->name('fasilitas.update');
});
Route::GET('client/booking/{id}', [BookingController::class, 'BookingOnline'])->name('booking.online');
Route::group(['middleware' => ['auth']], function () {
    Route::prefix('client')->group(function () {
        Route::GET('payment', [BookingController::class, 'myBookings'])->name('booking.payment');
        Route::get('payment/list/', [BookingController::class, 'myBookings'])->name('booking.show');
        Route::post('update/{id}', [BookingController::class, 'update'])->name('booking.update');
    });
});

// Verifikasi pembayaran (admin)
Route::group(['middleware' => ['auth']], function () {
    Route::prefix('payments')->group(function () {
        Route::GET('{payment}/bukti', [PaymentController::class, 'proof'])->name('payment.proof');
        Route::POST('{payment}/verify', [PaymentController::class, 'verify'])->name('payment.verify');
        Route::POST('{payment}/reject', [PaymentController::class, 'reject'])->name('payment.reject');
        Route::POST('cash/{booking}', [PaymentController::class, 'cash'])->name('payment.cash');
    });
});
Route::prefix('laporan')->group(function () {
    Route::GET('/', [LaporanController::class, 'index'])->name('laporan.index');
    Route::POST('cetak-laporan', [LaporanController::class, 'cetakLaporan'])->name('laporan.cetak');
    Route::get('/filter-laporan', [LaporanController::class, 'filter'])->name('laporan.filter');
    Route::get('/kpi', [LaporanController::class, 'kpi'])->name('laporan.kpi');
    Route::get('/okupansi', [LaporanController::class, 'occupancy'])->name('laporan.okupansi');
    Route::get('/pendapatan', [LaporanController::class, 'revenue'])->name('laporan.pendapatan');
    Route::get('/tamu', [LaporanController::class, 'guests'])->name('laporan.tamu');
});

// Pengaturan Sistem (identitas & branding pemilik)
Route::prefix('pengaturan')->middleware('auth')->group(function () {
    Route::GET('/', [SystemSettingController::class, 'index'])->name('setting.system.index');
    Route::POST('/', [SystemSettingController::class, 'update'])->name('setting.system.update');
    Route::POST('upload/{type}', [SystemSettingController::class, 'upload'])->name('setting.system.upload');
    Route::POST('hapus/{type}', [SystemSettingController::class, 'destroyFile'])->name('setting.system.destroy-file');
    Route::POST('reset', [SystemSettingController::class, 'reset'])->name('setting.system.reset');
});

// Master: tarif, unit, season, add-on, promo, pengaturan
Route::prefix('master')->middleware('auth')->group(function () {
    Route::GET('/', [MasterController::class, 'index'])->name('master.index');
    Route::PUT('tipe/{room}', [MasterController::class, 'updateType'])->name('master.type.update');
    Route::GET('units', [MasterController::class, 'units'])->name('master.units');
    Route::POST('unit', [MasterController::class, 'storeUnit'])->name('master.unit.store');
    Route::PUT('unit/{roomUnit}', [MasterController::class, 'updateUnit'])->name('master.unit.update');
    Route::POST('season', [MasterController::class, 'storeSeason'])->name('master.season.store');
    Route::DELETE('season/{season}', [MasterController::class, 'destroySeason'])->name('master.season.destroy');
    Route::POST('addon', [MasterController::class, 'storeAddon'])->name('master.addon.store');
    Route::PUT('addon/{addon}', [MasterController::class, 'updateAddon'])->name('master.addon.update');
    Route::DELETE('addon/{addon}', [MasterController::class, 'destroyAddon'])->name('master.addon.destroy');
    Route::POST('promo', [MasterController::class, 'storePromo'])->name('master.promo.store');
    Route::DELETE('promo/{promo}', [MasterController::class, 'destroyPromo'])->name('master.promo.destroy');
    Route::POST('setting', [MasterController::class, 'updateSettings'])->name('master.setting.update');
    Route::POST('override', [MasterController::class, 'storeOverride'])->name('master.override.store');
    Route::POST('override/hapus', [MasterController::class, 'destroyOverride'])->name('master.override.destroy');
    Route::POST('override/bulk', [MasterController::class, 'bulkOverride'])->name('master.override.bulk');
    Route::GET('kalender', [MasterController::class, 'kalender'])->name('master.kalender');
    Route::POST('blokir', [MasterController::class, 'storeBlock'])->name('master.block.store');
    Route::DELETE('blokir/{block}', [MasterController::class, 'destroyBlock'])->name('master.block.destroy');
    Route::GET('unit-options', [MasterController::class, 'unitOptions'])->name('master.unit.options');
});
Route::prefix('whatsapp')->group(function () {
    Route::GET('/', [WhatsappController::class, 'index'])->name('whatsapp.index');
    Route::POST('store', [WhatsappController::class, 'store'])->name('whatsapp.store');
    Route::delete('destroy/{id}', [WhatsappController::class, 'destroy'])->name('whatsapp.destroy');
});
