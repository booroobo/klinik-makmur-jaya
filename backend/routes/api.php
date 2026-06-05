<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\MedicineImportController as AdminMedicineImportController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PrescriptionController as AdminPrescriptionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\MedicineBatchController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\MedicineDraftController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/catalog/medicines', [CatalogController::class, 'index']);
Route::get('/catalog/medicines/autocomplete', [CatalogController::class, 'autocomplete']);
Route::get('/catalog/medicines/{id}', [CatalogController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/categories/{category}', [CategoryController::class, 'show'])
        ->middleware('role:admin,apoteker,kasir,pelanggan');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('role:admin');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('role:admin');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('role:admin');

    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('role:admin,apoteker');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->middleware('role:admin,apoteker');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('role:admin');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('role:admin');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->middleware('role:admin');

    Route::get('/medicines', [MedicineController::class, 'index'])
        ->middleware('role:admin,apoteker,kasir,pelanggan');
    Route::get('/medicines/{medicine}', [MedicineController::class, 'show'])
        ->middleware('role:admin,apoteker,kasir,pelanggan');
    Route::post('/medicines', [MedicineController::class, 'store'])
        ->middleware('role:admin');
    Route::post('/medicines/{id}/restore', [MedicineController::class, 'restore'])
        ->middleware('role:admin');
    Route::post('/medicines/{medicine}', [MedicineController::class, 'update'])
        ->middleware('role:admin');
    Route::put('/medicines/{medicine}', [MedicineController::class, 'update'])
        ->middleware('role:admin');
    Route::delete('/medicines/{medicine}', [MedicineController::class, 'destroy'])
        ->middleware('role:admin');
    Route::post('/admin/medicines/import', [AdminMedicineImportController::class, 'store'])
        ->middleware('role:admin');
    Route::get('/admin/medicines/imports/{medicineImport}', [AdminMedicineImportController::class, 'show'])
        ->middleware('role:admin');

    Route::get('/medicine-batches', [MedicineBatchController::class, 'index'])
        ->middleware('role:admin,apoteker');
    Route::post('/medicine-batches', [MedicineBatchController::class, 'store'])
        ->middleware('role:admin');
    Route::post('/medicine-batches/{id}/restore', [MedicineBatchController::class, 'restore'])
        ->middleware('role:admin');
    Route::put('/medicine-batches/{medicineBatch}', [MedicineBatchController::class, 'update'])
        ->middleware('role:admin');
    Route::delete('/medicine-batches/{medicineBatch}', [MedicineBatchController::class, 'destroy'])
        ->middleware('role:admin');

    Route::get('/medicine-drafts', [MedicineDraftController::class, 'index'])
        ->middleware('role:admin');
    Route::post('/medicine-drafts', [MedicineDraftController::class, 'store'])
        ->middleware('role:admin');
    Route::get('/medicine-drafts/{medicineDraft}', [MedicineDraftController::class, 'show'])
        ->middleware('role:admin');
    Route::put('/medicine-drafts/{medicineDraft}', [MedicineDraftController::class, 'update'])
        ->middleware('role:admin');
    Route::delete('/medicine-drafts/{medicineDraft}', [MedicineDraftController::class, 'destroy'])
        ->middleware('role:admin');

    Route::get('/cart', [CartController::class, 'show'])
        ->middleware('role:pelanggan');
    Route::post('/cart/items', [CartController::class, 'storeItem'])
        ->middleware('role:pelanggan');
    Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem'])
        ->middleware('role:pelanggan');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroyItem'])
        ->middleware('role:pelanggan');
    Route::delete('/cart/clear', [CartController::class, 'clear'])
        ->middleware('role:pelanggan');

    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('role:pelanggan');
    Route::get('/my-orders', [CustomerOrderController::class, 'index'])
        ->middleware('role:pelanggan');
    Route::get('/my-orders/{order}', [CustomerOrderController::class, 'show'])
        ->middleware('role:pelanggan');

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:admin');

    Route::get('/admin/customers', [AdminCustomerController::class, 'index'])
        ->middleware('role:admin');
    Route::post('/admin/customers', [AdminCustomerController::class, 'store'])
        ->middleware('role:admin');
    Route::get('/admin/customers/{user}', [AdminCustomerController::class, 'show'])
        ->middleware('role:admin');
    Route::put('/admin/customers/{user}', [AdminCustomerController::class, 'update'])
        ->middleware('role:admin');
    Route::delete('/admin/customers/{user}', [AdminCustomerController::class, 'destroy'])
        ->middleware('role:admin');

    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:admin');

    Route::get('/admin/reports/sales', [AdminReportController::class, 'sales'])
        ->middleware('role:admin');
    Route::get('/admin/reports/top-medicines', [AdminReportController::class, 'topMedicines'])
        ->middleware('role:admin');
    Route::get('/admin/reports/expiring-medicines', [AdminReportController::class, 'expiringMedicines'])
        ->middleware('role:admin');
    Route::get('/admin/reports/transactions', [AdminReportController::class, 'transactions'])
        ->middleware('role:admin');
    Route::get('/admin/reports/sales/export/pdf', [AdminReportController::class, 'exportPdf'])
        ->middleware('role:admin');
    Route::get('/admin/reports/sales/export/excel', [AdminReportController::class, 'exportExcel'])
        ->middleware('role:admin');
    Route::post('/admin/reports/queue', [AdminReportController::class, 'queue'])
        ->middleware('role:admin');
    Route::get('/admin/reports/queue', [AdminReportController::class, 'queueIndex'])
        ->middleware('role:admin');
    Route::get('/admin/reports/queue/{reportJob}', [AdminReportController::class, 'queueShow'])
        ->middleware('role:admin');
    Route::get('/admin/reports/queue/{reportJob}/download', [AdminReportController::class, 'queueDownload'])
        ->name('admin.reports.queue.download')
        ->middleware('role:admin');

    Route::get('/admin/orders', [AdminOrderController::class, 'index'])
        ->middleware('role:admin,kasir');
    Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show'])
        ->middleware('role:admin,kasir');
    Route::patch('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
        ->middleware('role:admin,kasir');
    Route::patch('/admin/orders/{order}/payment', [AdminOrderController::class, 'updatePayment'])
        ->middleware('role:admin,kasir');
    Route::post('/admin/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])
        ->middleware('role:admin');

    Route::get('/admin/prescriptions', [AdminPrescriptionController::class, 'index'])
        ->middleware('role:admin,apoteker');
    Route::get('/admin/prescriptions/{prescription}', [AdminPrescriptionController::class, 'show'])
        ->middleware('role:admin,apoteker');
    Route::patch('/admin/prescriptions/{prescription}/approve', [AdminPrescriptionController::class, 'approve'])
        ->middleware('role:admin,apoteker');
    Route::patch('/admin/prescriptions/{prescription}/reject', [AdminPrescriptionController::class, 'reject'])
        ->middleware('role:admin,apoteker');

    Route::get('/apoteker/prescriptions', fn () => response()->json([
        'message' => 'Akses apoteker berhasil.',
    ]))->middleware('role:apoteker,admin');

    Route::get('/kasir/orders', fn () => response()->json([
        'message' => 'Akses kasir berhasil.',
    ]))->middleware('role:kasir,admin');

    Route::get('/pelanggan/orders', fn () => response()->json([
        'message' => 'Akses pelanggan berhasil.',
    ]))->middleware('role:pelanggan');
});
