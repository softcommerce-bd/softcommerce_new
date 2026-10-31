<?php

use App\Http\Controllers\PurchasingController;
use Illuminate\Support\Facades\Route;

// Loaded inside the authenticated admin group (see routes/admin.php); permissions are set in the controller.
Route::controller(PurchasingController::class)->group(function () {
    Route::get('/', 'dashboard')->name('dashboard');
    Route::get('/quick-purchase', 'quickPurchase')->name('quick');
    Route::get('/products/search', 'searchProducts')->name('products.search');
    Route::post('/suppliers/quick', 'quickSupplier')->name('suppliers.quick');
    Route::post('/quick-purchase', 'quickSubmit')->name('quick.submit');
    Route::get('/bulk/template', 'bulkTemplate')->name('bulk.template');
    Route::post('/bulk/preview', 'bulkPreview')->name('bulk.preview');
    Route::post('/bulk/commit', 'bulkCommit')->name('bulk.commit');
    Route::get('/requirements', 'requirements')->name('requirements');
    Route::post('/requirements/purchase-order', 'createPoFromRequirements')->name('requirements.po');
    Route::get('/receiving', 'receivingIndex')->name('receiving.index');
    Route::get('/receiving/{id}', 'receivingForm')->whereNumber('id')->name('receiving.form');
    Route::post('/receiving/{id}', 'receive')->whereNumber('id')->name('receiving.receive');
    Route::get('/stock', 'stockOverview')->name('stock');
    Route::post('/products/{id}/policy', 'savePolicy')->whereNumber('id')->name('policy');
    Route::get('/movements', 'movements')->name('movements');
    Route::get('/backorders', 'backorders')->name('backorders');
    Route::get('/valuation', 'valuation')->name('valuation');
    Route::get('/supplier-outstanding', 'supplierOutstanding')->name('supplier.outstanding');
    Route::get('/returns', 'returns')->name('returns');
    Route::post('/returns/receive', 'receiveReturn')->name('returns.receive');
    Route::get('/supplier-payments', 'supplierPayments')->name('supplier.payments');
    Route::post('/supplier-invoices', 'recordInvoice')->name('invoices.store');
    Route::post('/supplier-invoices/{id}/pay', 'payInvoice')->whereNumber('id')->name('invoices.pay');
    Route::post('/supplier-advances', 'supplierAdvance')->name('advances.store');
    Route::get('/supplier-returns', 'supplierReturns')->name('supplier-returns');
    Route::post('/supplier-returns', 'returnToSupplier')->name('supplier-returns.store');
    Route::get('/reports/{name}', 'report')->name('report');
});
