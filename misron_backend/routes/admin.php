<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\Admin\Report\EarningReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BalanceTransferController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\BlogCategoryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandBulkUploadController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomAlertController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\DigitalProductController;
use App\Http\Controllers\DynamicPopupController;
use App\Http\Controllers\FlashDealController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MeasurementPointsController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTypeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PickupPointController;
use App\Http\Controllers\ProductBulkUploadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductQueryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerWithdrawRequestController;
use App\Http\Controllers\SizeChartController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\SteadfastSettingsController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ZoneController;
use App\Models\Purchase;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\PathaoCourierSettingsController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\BDcourierController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CourierSettlementController;
use App\Http\Controllers\CourierSetupController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\ProductDetailAccordianController;
use App\Http\Controllers\FeaturedCollectionController;
use App\Http\Controllers\DeliveryModalController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpensePaymentController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\TransactionMethodController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\IpTrackingController;

/*
  |--------------------------------------------------------------------------
  | Admin Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register admin routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */
//Update Routes
Route::controller(UpdateController::class)->group(function () {
    Route::post('/update', 'step0')->name('update');
    Route::get('/update/step1', 'step1')->name('update.step1');
    Route::get('/update/step2', 'step2')->name('update.step2');
    Route::get('/update/step3', 'step3')->name('update.step3');
    Route::post('/purchase_code', 'purchase_code')->name('update.code');
});

Route::controller(ProductDetailAccordianController::class)->prefix('accordions')->name('accordions.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/edit/{id}', 'edit')->name('edit');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
});
Route::get('/order-settlement/{order}/details', [AccountController::class, 'orderSettlementDetails'])
    ->name('order.settlement.details');

// Courier Settlement Part Controller
Route::controller(CourierSettlementController::class)->prefix('/courier-settlement')->name('courier-settlement.')->group(function () {
    Route::get('/index', 'index')->name('index');
    Route::post('/settle', 'settle')->name('settle');

    Route::post('/unsettle', 'unsettle')->name('unsettle');
    Route::get('/settlement-details/{id}', 'getSettlementDetails')->name('details');
    Route::get('/report',  'report')->name('report');
    Route::get('/export-pdf', 'exportPDF')->name('export-pdf');
    Route::get('/{settlement}/details', 'details');
});

// Draft part Controller
Route::controller(DraftController::class)->prefix('/draft')->name('draft.')->group(function () {
    Route::get('/index', 'index')->name('index');
    Route::get('/show/{id}', 'show')->name('show');
    Route::get('/delete/{id}', 'delete')->name('delete');
});

//supplier controller
Route::controller(SupplierController::class)->group(function () {
    Route::get('/supplier/index', 'supplier_index')->name('suppliers.index');
    Route::get('/supplier/view/{id}', 'supplierView')->name('supplier.view');
    Route::get('/supplier/edit/{id}', 'supplierEdit')->name('supplier.edit');
    Route::post('/supplier/update/{id}', 'supplierUpdate')->name('supplier.update');
    Route::get('/supplier/delete/{id}', 'supplierDelete')->name('supplier.delete');
    Route::post('/supplier/store', 'store')->name('supplier.store');
    Route::get('/fetch-ledger-data', 'fetchLedgerData')->name('ledger_data');
    Route::get('/supplier/ledger/pdf', 'printLedgerPDF')->name('supplier.ledger.pdf');
    Route::get('/fetch-supplier-data', 'fetchSupplierData')->name('fetchSupplierData');
    Route::post('/supplier/payment/process', 'processPayment')->name('supplier.payment.process');
});
// product search
// purchase Controller
Route::controller(PurchaseController::class)->group(function () {
    Route::get('/purchase/create', 'create')->name('purchase.create');
    Route::get('/purchase/index', 'purchaseIndex')->name('purchase.index');
    Route::get('/purchase/edit/{id}', 'purchaseEdit')->name('purchase.edit');
    Route::get('/purchase/view/{id}', 'purchaseView')->name('purchase.view');
    Route::get('/purchase/delete/{id}', 'purchaseDelete')->name('purchase.delete');
    Route::get('/products/search/ajax', 'search')->name('product.ajaxsearch');
    Route::post('/purchase/store', 'store')->name('purchase.store');
    Route::put('/purchase/update/{id}', 'update')->name('purchase.update');
    Route::post('/product/delete', 'delete')->name('product.delete');
    // supplier opening balance in ajax
    Route::get('/supplier-balance/{id}', 'getSupplierBalance')->name('supplier_balance');
    // Purchase Return Part
    Route::get('/purchase/return', 'purchaseReturnList')->name('purchase.return');
    Route::get('/purchase/return/add', 'purchaseReturnAdd')->name('purchase_return.add');
    Route::get('/supplier-wise-search-product', 'searchProducts')->name('supplier-wise-search-products');
    Route::post('/purchase-return/store', 'storePurchaseReturn')->name('purchase-return.store');
    Route::get('/purchase/return/edit/{id}', 'purchaseReturnEdit')->name('purchase_return.edit');
    Route::put('/purchase-return/update/{id}', 'updatePurchaseReturn')->name('purchase-return.update');
    // purchase view payment update
    Route::post('/purchase/payment/update', 'purchaseUpdatePayment')->name('purchase.payment.update');

    //  new added payment in create purchase
    Route::get('/get-payment-accounts', 'getPaymentAccounts')->name('get.payment.accounts');

    Route::get('/product/last-purchase-price', 'getLastPurchasePrice')
    ->name('product.last.purchase.price');
});


//purchse Order
Route::controller(PurchaseOrderController::class)->prefix('/purchase-order')->group(function () {
    Route::get('/create', 'create')->name('purchase.order.create');
    Route::get('/index', 'purchaseOrderIndex')->name('purchase.order.index');
    Route::get('/edit/{id}', 'purchaseOrderEdit')->name('purchase.order.edit');
    Route::get('/view/{id}', 'purchaseOrderView')->name('purchase.order.view');
    Route::get('/delete/{id}', 'purchaseOrderDelete')->name('purchase.order.delete');
    Route::get('/products/search/ajax', 'search')->name('purchase.order.ajaxsearch');
    Route::post('/store', 'store')->name('purchase.order.store');
    Route::post('/update/{id}', 'update')->name('purchase.order.update');
    // Route::post('/product/delete','delete')->name('product.order.delete');

    Route::get('review-modal/{id}', 'getReviewModalData')->name('purchase.order.review-modal');
    // Route::post('/purchase-order/complete/{id}', [PurchaseController::class, 'completePurchase'])->name('purchase.order.complete');
    Route::post('convert/{id}', 'convertToPurchase')->name('purchase.order.convert');
    // Route::get('/pdf', 'downloadPdfPO')->name('purchase.order.pdf');
    Route::get('/{id}/download-pdf', 'downloadPdfSinglePO')->name('purchase.order.pdf');
});


Route::get('/admin', [AdminController::class, 'admin_dashboard'])->name('admin.dashboard')->middleware(['auth', 'admin', 'prevent-back-history']);

Route::get('/dashboard/order-status', [AdminController::class, 'doughnutData'])->name('dashboard.doughnut.get');
Route::get('/dashboard/sales-review', [AdminController::class, 'salesChartData'])->name('dashboard.sales.chart');
Route::get('/dashboard/top-categories', [AdminController::class, 'getTopCategories'])->name('dashboard.top.categories');
Route::get('/dashboard/top-selling-products', [AdminController::class, 'getTopSellingProducts'])->name('dashboard.top.products');


Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin', 'prevent-back-history']], function () {
    // Featured Collection
    Route::controller(FeaturedCollectionController::class)->group(function () {
        Route::get('/featured/collection/index', 'index')->name('featured_collection.index');
        Route::get('/featured/collection/create', 'create')->name('featured_collection.create');
        Route::post('/featured/collection/store', 'store')->name('featured_collection.store');
        Route::get('/featured/collection/edit/{id}', 'edit')->name('featured_collection.edit');
        Route::get('/featured/collection/destroy/{id}', 'destroy')->name('featured_collection.destroy');
        Route::put('/featured/collection/update/{id}', 'update')->name('featured_collection.update');
    });
    // category
    Route::resource('categories', CategoryController::class);
    Route::controller(CategoryController::class)->group(function () {
        Route::get('/categories/edit/{id}', 'edit')->name('categories.edit');
        Route::get('/categories/destroy/{id}', 'destroy')->name('categories.destroy');
        Route::post('/categories/featured', 'updateFeatured')->name('categories.featured');
        Route::post('/categories/categoriesByType', 'categoriesByType')->name('categories.categories-by-type');

        // category-wise discount set
        Route::get('/categories-wise-product-discount', 'categoriesWiseProductDiscount')->name('categories_wise_product_discount');
    });


    // Transaction Methods Routes
Route::prefix('transaction-method')->group(function () {
    Route::get('/get/{id}', [TransactionMethodController::class, 'getMethod']);
    Route::post('/store', [TransactionMethodController::class, 'store']);
    Route::post('/update/{id}', [TransactionMethodController::class, 'update']);
    Route::delete('/delete/{id}', [TransactionMethodController::class, 'destroy']);
});

    // (Optional) cleaner prefix for future use
    Route::prefix('payment-methods')->name('payment-methods.')->controller(TransactionMethodController::class)->group(function () {
            Route::get('/bank-accounts', 'getBankAccounts')->name('bank-accounts');
        });

    // ========== NEW ACCOUNTS ROUTES ==========
    Route::prefix('accounts')->name('accounts.')->group(function () {

        Route::controller(AccountController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/load-tab', 'loadTab')->name('load-tab');
            Route::get('/account-statement/filter', 'accountStatementFilter')->name('account.statement.filter');
            Route::get('/accounts/export', 'export')->name('export');
            Route::get('/order-settlement/{id}/details', 'settlementDetails')->name('settlement.details');
        });

     // Finance Reports
        Route::controller(FinanceReportController::class)->group(function () {
            Route::get('/reports/profit-loss', 'profitLoss')->name('reports.profit-loss');
            Route::get('/reports/balance-sheet', 'balanceSheet')->name('reports.balance-sheet');
            Route::get('/reports/trial-balance', 'trialBalance')->name('reports.trial-balance');
            Route::get('/reports/cash-book', 'cashBook')->name('reports.cash-book');
            Route::get('/reports/day-closing', 'dayClosing')->name('reports.day-closing');
            Route::get('/reports/expense-detail', 'expenseDetail')->name('reports.expense-detail');
            Route::get('/reports/expense-summary', 'expenseSummary')->name('reports.expense-summary');
            Route::get('/reports/today-transactions', 'todayTransactions')->name('reports.today-transactions');
        });

        // Account Statement & Reports
        Route::get('/statement/{accountId}', [AccountController::class, 'getStatement'])->name('statement');
        Route::get('/balance-sheet', [AccountController::class, 'getBalanceSheet'])->name('balance-sheet');
        Route::get('/balance-sheet/export', [AccountController::class, 'exportBalanceSheet'])->name('balance-sheet.export');
        Route::get('/trial-balance', [AccountController::class, 'getTrialBalance'])->name('trial-balance');

        // Chart of Accounts CRUD
        Route::prefix('chart-of-accounts')->name('chart-of-accounts.')->group(function () {
            Route::get('/{id}/edit', [ChartOfAccountController::class, 'edit'])->name('edit');
            Route::post('/store', [ChartOfAccountController::class, 'store'])->name('store');
            Route::post('/update/{id}', [ChartOfAccountController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [ChartOfAccountController::class, 'destroy'])->name('delete');
            Route::post('/adjust-balance/{id}', [ChartOfAccountController::class, 'updateBalance'])->name('adjust-balance');
            Route::get('/parents', [ChartOfAccountController::class, 'getParentAccounts'])->name('parents');
        });

        // Supplier Payments

        Route::prefix('supplier-payments')->name('supplier-payments.')->group(function () {
            Route::get('/', [SupplierPaymentController::class, 'index'])->name('index');
            Route::get('/create', [SupplierPaymentController::class, 'create'])->name('create');
            Route::post('/store', [SupplierPaymentController::class, 'store'])->name('store');
            Route::get('/edit/{id}', [SupplierPaymentController::class, 'edit'])->name('edit');
            Route::put('/update/{id}', [SupplierPaymentController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [SupplierPaymentController::class, 'destroy'])->name('delete');
            Route::get('/show/{id}', [SupplierPaymentController::class, 'show'])->name('show');
            Route::get('/due/{supplierId}', [SupplierPaymentController::class, 'getSupplierDue'])->name('due');
            // Route::get('/accounts/{method}', [SupplierPaymentController::class, 'getAccountsByMethod'])->name('accounts');
            Route::get('/accounts/{subType}', [SupplierPaymentController::class, 'getAccountsBySubType'])->name('accounts');
        });


        // Customer Payments
        Route::prefix('customer-payments')->name('customer-payments.')->group(function () {
            Route::get('/', [CustomerPaymentController::class, 'index'])->name('index');
            Route::post('/store', [CustomerPaymentController::class, 'store'])->name('store');
            Route::get('/show/{id}', [CustomerPaymentController::class, 'show'])->name('show');
            Route::get('/paid-orders/{customerId}', [CustomerPaymentController::class, 'getPaidOrders'])->name('paid-orders');
            Route::get('/due/{customerId}', [CustomerPaymentController::class, 'getCustomerDue'])->name('due');
            Route::delete('/delete/{id}', [CustomerPaymentController::class, 'destroy'])->name('delete');
            Route::post('/auto-import', [CustomerPaymentController::class, 'autoImport'])->name('auto-import');
        });

        // Bank Transactions
        Route::prefix('bank-transactions')->name('bank-transactions.')->group(function () {
            Route::get('/', [BankTransactionController::class, 'index'])->name('index');
            Route::get('/accounts', [BankTransactionController::class, 'getAccounts'])->name('accounts');
            Route::post('/deposit', [BankTransactionController::class, 'deposit'])->name('deposit');
            Route::post('/withdraw', [BankTransactionController::class, 'withdraw'])->name('withdraw');
            Route::post('/transfer', [BankTransactionController::class, 'transfer'])->name('transfer');
            Route::post('/cash-out', [BankTransactionController::class, 'cashOut'])->name('cash-out');
            Route::post('/cheque-clearing', [BankTransactionController::class, 'chequeClearing'])->name('cheque-clearing');
            Route::get('/show/{id}', [BankTransactionController::class, 'show'])->name('show');
            Route::get('/export', [BankTransactionController::class, 'export'])->name('export');
        });

        // Voucher Routes
        Route::prefix('vouchers')->name('vouchers.')->group(function () {
            Route::get('/', [VoucherController::class, 'index'])->name('index');
            Route::get('/create', [VoucherController::class, 'create'])->name('create');
            Route::post('/store', [VoucherController::class, 'store'])->name('store');
            Route::get('/edit/{id}', [VoucherController::class, 'edit'])->name('edit');
            Route::put('/update/{id}', [VoucherController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [VoucherController::class, 'destroy'])->name('delete');
            Route::get('/print/{id}', [VoucherController::class, 'print'])->name('print');
            Route::get('/filter', [VoucherController::class, 'filter'])->name('filter');
            Route::get('/get-accounts', [VoucherController::class, 'getAccountsByType'])->name('get-accounts');
            Route::get('/get-cash-bank-accounts', [VoucherController::class, 'getCashBankAccounts'])->name('get-cash-bank-accounts');
        });

        // Expense Payment Routes
        Route::prefix('expense-payments')->name('expense-payments.')->group(function () {
            Route::get('/', [ExpensePaymentController::class, 'index'])->name('index');
            Route::get('/create', [ExpensePaymentController::class, 'create'])->name('create');
            Route::post('/store', [ExpensePaymentController::class, 'store'])->name('store');
            Route::get('/edit/{id}', [ExpensePaymentController::class, 'edit'])->name('edit');
            Route::put('/update/{id}', [ExpensePaymentController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [ExpensePaymentController::class, 'destroy'])->name('delete');
            Route::get('/show/{id}', [ExpensePaymentController::class, 'show'])->name('show');
            Route::get('/print/{id}', [ExpensePaymentController::class, 'print'])->name('print');
            Route::get('/filter', [ExpensePaymentController::class, 'filter'])->name('filter');
        });

        // Income Routes
        Route::prefix('incomes')->name('incomes.')->group(function () {
            Route::get('/', [IncomeController::class, 'index'])->name('index');
            Route::get('/create', [IncomeController::class, 'create'])->name('create');
            Route::post('/store', [IncomeController::class, 'store'])->name('store');
            Route::get('/edit/{id}', [IncomeController::class, 'edit'])->name('edit');
            Route::put('/update/{id}', [IncomeController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [IncomeController::class, 'destroy'])->name('delete');
            Route::get('/show/{id}', [IncomeController::class, 'show'])->name('show');
            Route::get('/print/{id}', [IncomeController::class, 'print'])->name('print');
            Route::get('/filter', [IncomeController::class, 'filter'])->name('filter');
        });

        // // Reports
        // Route::prefix('reports')->name('reports.')->group(function () {
        //     Route::get('/profit-loss', [AccountController::class, 'getProfitLoss'])->name('profit-loss');
        //     Route::get('/cash-flow', [AccountController::class, 'getCashFlow'])->name('cash-flow');
        //     Route::get('/receivable-payable', [AccountController::class, 'getReceivablePayable'])->name('receivable-payable');
        //     Route::get('/export', [AccountController::class, 'exportReport'])->name('export');
        // });

        // Balance Transfer Routes
        Route::prefix('balance-transfers')->name('balance-transfers.')->group(function () {
            Route::get('/', [BalanceTransferController::class, 'index'])->name('index');
            Route::get('/create', [BalanceTransferController::class, 'create'])->name('create');
            Route::post('/store', [BalanceTransferController::class, 'store'])->name('store');
            Route::get('/edit/{id}', [BalanceTransferController::class, 'edit'])->name('edit');
            Route::put('/update/{id}', [BalanceTransferController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [BalanceTransferController::class, 'destroy'])->name('delete');
            Route::get('/show/{id}', [BalanceTransferController::class, 'show'])->name('show');
            Route::get('/print/{id}', [BalanceTransferController::class, 'print'])->name('print');
            Route::get('/filter', [BalanceTransferController::class, 'filter'])->name('filter');
        });
    });

    // Accounts part routes end

    // Brand
    Route::resource('brands', BrandController::class);
    Route::controller(BrandController::class)->group(function () {
        Route::get('/brands/edit/{id}', 'edit')->name('brands.edit');
        Route::get('/brands/destroy/{id}', 'destroy')->name('brands.destroy');
    });

    Route::controller(BrandBulkUploadController::class)->group(function () {
        Route::get('/brand-bulk-upload', 'index')->name('brand_bulk_upload.index');
        Route::post('/brand-bulk-upload/store', 'bulk_upload')->name('brand_bulk_upload');
    });

    Route::controller(AdminController::class)->group(function () {
        Route::post('/dashboard/top-category-products-section', 'top_category_products_section')->name('dashboard.top_category_products_section');
        Route::post('/dashboard/inhouse-top-brands', 'inhouse_top_brands')->name('dashboard.inhouse_top_brands');
        Route::post('/dashboard/inhouse-top-categories', 'inhouse_top_categories')->name('dashboard.inhouse_top_categories');
        Route::post('/dashboard/top-sellers-products-section', 'top_sellers_products_section')->name('dashboard.top_sellers_products_section');
        Route::post('/dashboard/top-brands-products-section', 'top_brands_products_section')->name('dashboard.top_brands_products_section');
    });

    // Products
    Route::controller(ProductController::class)->group(function () {
        Route::get('/products/admin', 'admin_products')->name('products.admin');
        Route::get('/products/seller/{product_type}', 'seller_products')->name('products.seller');
        Route::get('/products/all', 'all_products')->name('products.all');
        Route::get('/products/create', 'create')->name('products.create');
        Route::post('/products/store/', 'store')->name('products.store');
        Route::get('/products/admin/{id}/edit', 'admin_product_edit')->name('products.admin.edit');
        Route::get('/products/seller/{id}/edit', 'seller_product_edit')->name('products.seller.edit');
        Route::post('/products/update/{product}', 'update')->name('products.update');
        Route::post('/products/todays_deal', 'updateTodaysDeal')->name('products.todays_deal');
        Route::post('/products/featured', 'updateFeatured')->name('products.featured');
        Route::post('/products/published', 'updatePublished')->name('products.published');
        Route::post('/products/approved', 'updateProductApproval')->name('products.approved');
        Route::post('/products/get_products_by_subcategory', 'get_products_by_subcategory')->name('products.get_products_by_subcategory');
        Route::get('/products/duplicate/{id}', 'duplicate')->name('products.duplicate');
        Route::get('/products/destroy/{id}', 'destroy')->name('products.destroy');
        Route::post('/bulk-product-delete', 'bulk_product_delete')->name('bulk-product-delete');

        Route::post('/products/sku_combination', 'sku_combination')->name('products.sku_combination');
        Route::post('/products/sku_combination_edit', 'sku_combination_edit')->name('products.sku_combination_edit');
        Route::post('/products/add-more-choice-option', 'add_more_choice_option')->name('products.add-more-choice-option');
        Route::post('/product-search', 'product_search')->name('product.search');
        Route::post('/get-selected-products', 'get_selected_products')->name('get-selected-products');
        Route::post('/set-product-discount', 'setProductDiscount')->name('set_product_discount');
    });

    // Digital Product
    Route::resource('digitalproducts', DigitalProductController::class);
    Route::controller(DigitalProductController::class)->group(function () {
        Route::get('/digitalproducts/edit/{id}', 'edit')->name('digitalproducts.edit');
        Route::get('/digitalproducts/destroy/{id}', 'destroy')->name('digitalproducts.destroy');
        Route::get('/digitalproducts/download/{id}', 'download')->name('digitalproducts.download');
    });

    Route::controller(ProductBulkUploadController::class)->group(function () {
        //Product Export
        Route::get('/product-bulk-export', 'export')->name('product_bulk_export.index');

        //Product Bulk Upload
        Route::get('/product-bulk-upload/index', 'index')->name('product_bulk_upload.index');
        Route::post('/bulk-product-upload', 'bulk_upload')->name('bulk_product_upload');
        Route::get('/product-csv-download/{type}', 'import_product')->name('product_csv.download');
        Route::get('/vendor-product-csv-download/{id}', 'import_vendor_product')->name('import_vendor_product.download');
        Route::group(['prefix' => 'bulk-upload/download'], function () {
            Route::get('/category', 'pdf_download_category')->name('pdf.download_category');
            Route::get('/brand', 'pdf_download_brand')->name('pdf.download_brand');
            Route::get('/seller', 'pdf_download_seller')->name('pdf.download_seller');
        });
    });

    // Seller
    Route::resource('sellers', SellerController::class);
    Route::controller(SellerController::class)->group(function () {
        Route::get('sellers_ban/{id}', 'ban')->name('sellers.ban');
        Route::get('/sellers/destroy/{id}', 'destroy')->name('sellers.destroy');
        Route::post('/bulk-seller-delete', 'bulk_seller_delete')->name('bulk-seller-delete');
        Route::get('/sellers/view/{id}/verification', 'show_verification_request')->name('sellers.show_verification_request');
        Route::get('/sellers/approve/{id}', 'approve_seller')->name('sellers.approve');
        Route::get('/sellers/reject/{id}', 'reject_seller')->name('sellers.reject');
        Route::get('/sellers/login/{id}', 'login')->name('sellers.login');
        Route::post('/sellers/payment_modal', 'payment_modal')->name('sellers.payment_modal');
        Route::post('/sellers/profile_modal', 'profile_modal')->name('sellers.profile_modal');
        Route::post('/sellers/approved', 'updateApproved')->name('sellers.approved');
    });

    // Seller Payment
    Route::controller(PaymentController::class)->group(function () {
        Route::get('/seller/payments', 'payment_histories')->name('sellers.payment_histories');
        Route::get('/seller/payments/show/{id}', 'show')->name('sellers.payment_history');
    });

    // Seller Withdraw Request
    Route::resource('/withdraw_requests', SellerWithdrawRequestController::class);
    Route::controller(SellerWithdrawRequestController::class)->group(function () {
        Route::get('/withdraw_requests_all', 'index')->name('withdraw_requests_all');
        Route::post('/withdraw_request/payment_modal', 'payment_modal')->name('withdraw_request.payment_modal');
        Route::post('/withdraw_request/message_modal', 'message_modal')->name('withdraw_request.message_modal');
    });

    // Customer
    Route::resource('customers', CustomerController::class);
    Route::controller(CustomerController::class)->group(function () {
        Route::get('customers_ban/{customer}', 'ban')->name('customers.ban');
        Route::get('/customers/login/{id}', 'login')->name('customers.login');
        Route::get('/customers/destroy/{id}', 'destroy')->name('customers.destroy');
        Route::post('/bulk-customer-delete', 'bulk_customer_delete')->name('bulk-customer-delete');
    });

    // IP Tracking
    Route::controller(IpTrackingController::class)->prefix('ip-tracking')->name('ip-tracking.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/toggle-block/{id}', 'toggleBlock')->name('toggle-block');
        Route::delete('/delete/{id}', 'destroy')->name('destroy');
        Route::post('/bulk-delete', 'bulkDelete')->name('bulk-delete');
        Route::get('/block-all-user/{userId}', 'blockAllByUser')->name('block-all-user');
        Route::get('/unblock-all-user/{userId}', 'unblockAllByUser')->name('unblock-all-user');
    });

    // Newsletter
    Route::controller(NewsletterController::class)->group(function () {
        Route::get('/newsletter', 'index')->name('newsletters.index');
        Route::post('/newsletter/send', 'send')->name('newsletters.send');
        Route::post('/newsletter/test/smtp', 'testEmail')->name('test.smtp');
    });

    // Dynamic Popup
    Route::resource('dynamic-popups', DynamicPopupController::class);
    Route::controller(DynamicPopupController::class)->group(function () {
        Route::get('/dynamic-popups/destroy/{id}', 'destroy')->name('dynamic-popups.destroy');
        Route::post('/bulk-dynamic-popup-delete', 'bulk_dynamic_popup_delete')->name('bulk-dynamic-popup-delete');
        Route::post('/dynamic-popups-update-status', 'update_status')->name('dynamic-popups.update-status');
    });

    // Custom Alert
    Route::resource('custom-alerts', CustomAlertController::class);
    Route::controller(CustomAlertController::class)->group(function () {
        Route::get('/custom-alerts/destroy/{id}', 'destroy')->name('custom-alerts.destroy');
        Route::post('/bulk-custom-alerts-delete', 'bulk_custom_alerts_delete')->name('bulk-custom-alerts-delete');
        Route::post('/custom-alerts-update-status', 'update_status')->name('custom-alerts.update-status');
    });

    Route::resource('profile', ProfileController::class);

    // Business Settings
    Route::controller(BusinessSettingsController::class)->group(function () {
        Route::post('/business-settings/update', 'update')->name('business_settings.update');
        Route::post('/business-settings/update/activation', 'updateActivationSettings')->name('business_settings.update.activation');
        Route::post('/payment-activation', 'updatePaymentActivationSettings')->name('payment.activation');
        Route::get('/general-setting', 'general_setting')->name('general_setting.index');
        Route::get('/activation', 'activation')->name('activation.index');
        Route::get('/payment-method', 'payment_method')->name('payment_method.index');
        Route::get('/file_system', 'file_system')->name('file_system.index');
        Route::get('/social-login', 'social_login')->name('social_login.index');
        Route::get('/smtp-settings', 'smtp_settings')->name('smtp_settings.index');
        Route::get('/google-analytics', 'google_analytics')->name('google_analytics.index');
        Route::get('/google-recaptcha', 'google_recaptcha')->name('google_recaptcha.index');
        Route::get('/google-map', 'google_map')->name('google-map.index');
        Route::get('/google-firebase', 'google_firebase')->name('google-firebase.index');

        //Facebook Settings
        Route::get('/facebook-chat', 'facebook_chat')->name('facebook_chat.index');
        Route::post('/facebook_chat', 'facebook_chat_update')->name('facebook_chat.update');
        Route::get('/facebook-comment', 'facebook_comment')->name('facebook-comment');
        Route::post('/facebook-comment', 'facebook_comment_update')->name('facebook-comment.update');
        Route::post('/facebook_pixel', 'facebook_pixel_update')->name('facebook_pixel.update');

        // Whatsapp Chat
        Route::get('/whatsapp-chat', 'whatsapp_chat')->name('whatsapp_chat.index');
        Route::post('/whatsapp_chat', 'whatsapp_chat_update')->name('whatsapp_chat.update');

        Route::post('/env_key_update', 'env_key_update')->name('env_key_update.update');
        Route::post('/payment_method_update', 'payment_method_update')->name('payment_method.update');
        Route::post('/google_analytics', 'google_analytics_update')->name('google_analytics.update');
        Route::post('/google_recaptcha', 'google_recaptcha_update')->name('google_recaptcha.update');
        Route::post('/google-map', 'google_map_update')->name('google-map.update');
        Route::post('/google-firebase', 'google_firebase_update')->name('google-firebase.update');

        Route::get('/verification/form', 'seller_verification_form')->name('seller_verification_form.index');
        Route::post('/verification/form', 'seller_verification_form_update')->name('seller_verification_form.update');
        Route::get('/vendor_commission', 'vendor_commission')->name('business_settings.vendor_commission');
        Route::post('/vendor_commission_update', 'vendor_commission_update')->name('business_settings.vendor_commission.update');

        //Shipping Configuration
        Route::get('/shipping_configuration', 'shipping_configuration')->name('shipping_configuration.index');
        Route::post('/shipping_configuration/update', 'shipping_configuration_update')->name('shipping_configuration.update');

        // Order Configuration
        Route::get('/order-configuration', 'order_configuration')->name('order_configuration.index');
    });


    //Currency
    Route::controller(CurrencyController::class)->group(function () {
        Route::get('/currency', 'currency')->name('currency.index');
        Route::post('/currency/update', 'updateCurrency')->name('currency.update');
        Route::post('/your-currency/update', 'updateYourCurrency')->name('your_currency.update');
        Route::get('/currency/create', 'create')->name('currency.create');
        Route::post('/currency/store', 'store')->name('currency.store');
        Route::post('/currency/currency_edit', 'edit')->name('currency.edit');
        Route::post('/currency/update_status', 'update_status')->name('currency.update_status');
    });

    //Tax
    Route::resource('tax', TaxController::class);
    Route::controller(TaxController::class)->group(function () {
        Route::get('/tax/edit/{id}', 'edit')->name('tax.edit');
        Route::get('/tax/destroy/{id}', 'destroy')->name('tax.destroy');
        Route::post('tax-status', 'change_tax_status')->name('taxes.tax-status');
    });

    // Language
    Route::resource('/languages', LanguageController::class);
    Route::controller(LanguageController::class)->group(function () {
        Route::post('/languages/{id}/update', 'update')->name('languages.update');
        Route::get('/languages/destroy/{id}', 'destroy')->name('languages.destroy');
        Route::post('/languages/update_rtl_status', 'update_rtl_status')->name('languages.update_rtl_status');
        Route::post('/languages/update-status', 'update_status')->name('languages.update-status');
        Route::post('/languages/key_value_store', 'key_value_store')->name('languages.key_value_store');

        //App Trasnlation
        Route::post('/languages/app-translations/import', 'importEnglishFile')->name('app-translations.import');
        Route::get('/languages/app-translations/show/{id}', 'showAppTranlsationView')->name('app-translations.show');
        Route::post('/languages/app-translations/key_value_store', 'storeAppTranlsation')->name('app-translations.store');
        Route::get('/languages/app-translations/export/{id}', 'exportARBFile')->name('app-translations.export');
    });


    // website setting
    Route::group(['prefix' => 'website'], function () {
        Route::controller(WebsiteController::class)->group(function () {
            Route::get('/footer', 'footer')->name('website.footer');
            Route::get('/header', 'header')->name('website.header');
            Route::get('/appearance', 'appearance')->name('website.appearance');
            Route::get('/select-homepage', 'select_homepage')->name('website.select-homepage');
            Route::get('/authentication-layout-settings', 'authentication_layout_settings')->name('website.authentication-layout-settings');
            Route::get('/pages', 'pages')->name('website.pages');
        });

        // Custom Page
        Route::resource('custom-pages', PageController::class);
        Route::controller(PageController::class)->group(function () {
            Route::get('/custom-pages/edit/{id}', 'edit')->name('custom-pages.edit');
            Route::get('/custom-pages/destroy/{id}', 'destroy')->name('custom-pages.destroy');
        });
    });

    // Staff Roles
    Route::resource('roles', RoleController::class);
    Route::controller(RoleController::class)->group(function () {
        Route::get('/roles/edit/{id}', 'edit')->name('roles.edit');
        Route::get('/roles/destroy/{id}', 'destroy')->name('roles.destroy');

        // Add Permissiom
        Route::post('/roles/add_permission', 'add_permission')->name('roles.permission');
    });

    // Staff
    Route::resource('staffs', StaffController::class);
    Route::get('/staffs/destroy/{id}', [StaffController::class, 'destroy'])->name('staffs.destroy');

    // Flash Deal
    Route::resource('flash_deals', FlashDealController::class);
    Route::controller(FlashDealController::class)->group(function () {
        Route::get('/flash_deals/edit/{id}', 'edit')->name('flash_deals.edit');
        Route::get('/flash_deals/destroy/{id}', 'destroy')->name('flash_deals.destroy');
        Route::post('/flash_deals/update_status', 'update_status')->name('flash_deals.update_status');
        Route::post('/flash_deals/update_featured', 'update_featured')->name('flash_deals.update_featured');
        Route::post('/flash_deals/product_discount', 'product_discount')->name('flash_deals.product_discount');
        Route::post('/flash_deals/product_discount_edit', 'product_discount_edit')->name('flash_deals.product_discount_edit');
    });

    //Subscribers
    Route::controller(SubscriberController::class)->group(function () {
        Route::get('/subscribers', 'index')->name('subscribers.index');
        Route::get('/subscribers/destroy/{id}', 'destroy')->name('subscriber.destroy');
    });

    // Order
    // Add this route with your other order routes
    Route::post('/orders/fix-all-payment', [OrderController::class, 'fixAllPayment'])->name('orders.fix-all-payment');
    Route::resource('orders', OrderController::class);
    Route::controller(OrderController::class)->group(function () {
        // All Orders
        Route::get('/all_orders', 'all_orders')->name('all_orders.index');
        Route::get('/all_orders/sidebar/{order}', 'sidebar')->name('all_orders.sidebar');
        Route::get('/inhouse-orders', 'all_orders')->name('inhouse_orders.index');
        Route::get('/seller_orders', 'all_orders')->name('seller_orders.index');
        Route::get('orders_by_pickup_point', 'all_orders')->name('pick_up_point.index');

        Route::get('/orders/{id}/show', 'show')->name('all_orders.show');
        Route::get('/inhouse-orders/{id}/show', 'show')->name('inhouse_orders.show');
        Route::get('/seller_orders/{id}/show', 'show')->name('seller_orders.show');
        Route::get('/orders_by_pickup_point/{id}/show', 'show')->name('pick_up_point.order_show');

        Route::post('/bulk-order-status', 'bulk_order_status')->name('bulk-order-status');

        Route::get('/orders/destroy/{id}', 'destroy')->name('orders.destroy');
        Route::post('/bulk-order-delete', 'bulk_order_delete')->name('bulk-order-delete');

        Route::get('/orders/destroy/{id}', 'destroy')->name('orders.destroy');
        Route::post('/orders/details', 'order_details')->name('orders.details');
        Route::post('/orders/update_delivery_status', 'update_delivery_status')->name('orders.update_delivery_status');
        Route::post('/orders/update_payment_status', 'update_payment_status')->name('orders.update_payment_status');
        Route::post('/orders/update_tracking_code', 'update_tracking_code')->name('orders.update_tracking_code');
        Route::post('/orders/update_order_note', 'update_order_note')->name('orders.update_order_note');

        // Bulk Courier Assign
        Route::post('/orders/bulk-assign-courier', 'bulkAssignCourier')->name('orders.bulk-assign-courier');

        //Delivery Boy Assign
        Route::post('/orders/delivery-boy-assign', 'assign_delivery_boy')->name('orders.delivery-boy-assign');

        // Order bulk export
        Route::get('/order-bulk-export', 'orderBulkExport')->name('order-bulk-export');

        Route::get('/order-bulk-document-export', 'bulkDocumentDownload')->name('bulk.document.export');
        Route::get('/order-bulk-document-print', 'bulkDocumentPrintView')->name('bulk.document.print');

        Route::get('/order-edit/{id}', 'orderEdit')->name('order-edit');
        Route::post('/order-edit/update', 'orderEditUpdate')->name('order.edit.update');
        Route::get('/order/edit/product', 'orderEditProductPart')->name('order.edit.product');
        Route::get('/order/edit/variation', 'orderEditVariationPart')->name('order.edit.variation');
        Route::get('order/edit/delete', 'orderEditDelete')->name('order.edit.delete');

        Route::get('/orders/{order}/items', 'fetchOrderItemsJson')->name('orders.items');
        Route::post('/orders/update-items-status','processUpdateItemsStatus')->name('orders.update-items-status');
    });

    Route::post('/pay_to_seller', [CommissionController::class, 'pay_to_seller'])->name('commissions.pay_to_seller');

    //Reports
    Route::controller(ReportController::class)->group(function () {
        Route::get('/in_house_sale_report', 'in_house_sale_report')->name('in_house_sale_report.index');
        Route::get('/seller_sale_report', 'seller_sale_report')->name('seller_sale_report.index');
        Route::get('/stock_report', 'stock_report')->name('stock_report.index');
        Route::get('/stock-report/download', 'stock_report_download')->name('stock_report.download');
        Route::get('/wish_report', 'wish_report')->name('wish_report.index');
        Route::get('/user_search_report', 'user_search_report')->name('user_search_report.index');
        Route::get('/commission-log', 'commission_history')->name('commission-log.index');
        Route::get('/wallet-history', 'wallet_transaction_history')->name('wallet-history.index');
        Route::get('/sales_report', 'salesReport')->name('sales_report.index');

        Route::get('/in_house_sale_report/csv', 'inHouseSaleReportCSV')->name('in_house_sale_report.csv');
        Route::get('/stock_report/csv', 'stockReportCSV')->name('stock_report.csv');
        Route::get('/sales_report/csv', 'salesReportCSV')->name('sales_report.csv');
    });

    // Earning Report
    Route::group(['prefix' => 'reports'], function () {
        Route::get('/earning-payout-report', [EarningReportController::class, 'index'])->name('earning_payout_report.index');
        Route::post('/earning-payout-report/net-sales', [EarningReportController::class, 'net_sales']);
        Route::post('/earning-payout-report/payouts', [EarningReportController::class, 'payouts']);
        Route::post('/earning-payout-report/sale-analytic', [EarningReportController::class, 'sale_analytic']);
        Route::post('/earning-payout-report/payout-analytic', [EarningReportController::class, 'payout_analytic']);
    });

    //Blog Section
    //Blog cateory
    Route::resource('blog-category', BlogCategoryController::class);
    Route::get('/blog-category/destroy/{id}', [BlogCategoryController::class, 'destroy'])->name('blog-category.destroy');

    // Blog
    Route::resource('blog', BlogController::class);
    Route::controller(BlogController::class)->group(function () {
        Route::get('/blog/destroy/{id}', 'destroy')->name('blog.destroy');
        Route::post('/blog/change-status', 'change_status')->name('blog.change-status');
    });

    //Coupons
    Route::resource('coupon', CouponController::class);
    Route::controller(CouponController::class)->group(function () {
        Route::post('/coupon/update-status', 'updateStatus')->name('coupon.update_status');
        Route::get('/coupon/destroy/{id}', 'destroy')->name('coupon.destroy');

        //Coupon Form
        Route::post('/coupon/get_form', 'get_coupon_form')->name('coupon.get_coupon_form');
        Route::post('/coupon/get_form_edit', 'get_coupon_form_edit')->name('coupon.get_coupon_form_edit');
    });

    //Reviews
    Route::controller(ReviewController::class)->group(function () {
        Route::get('/reviews', 'index')->name('reviews.index');
        Route::post('/reviews/published', 'updatePublished')->name('reviews.published');
    });

    //Support_Ticket
    Route::controller(SupportTicketController::class)->group(function () {
        Route::get('support_ticket/', 'admin_index')->name('support_ticket.admin_index');
        Route::get('support_ticket/{id}/show', 'admin_show')->name('support_ticket.admin_show');
        Route::post('support_ticket/reply', 'admin_store')->name('support_ticket.admin_store');
    });

    //Pickup_Points
    Route::resource('pick_up_points', PickupPointController::class);
    Route::controller(PickupPointController::class)->group(function () {
        Route::get('/pick_up_points/edit/{id}', 'edit')->name('pick_up_points.edit');
        Route::get('/pick_up_points/destroy/{id}', 'destroy')->name('pick_up_points.destroy');
    });

    //conversation of seller customer
    Route::controller(ConversationController::class)->group(function () {
        Route::get('conversations', 'admin_index')->name('conversations.admin_index');
        Route::get('conversations/{id}/show', 'admin_show')->name('conversations.admin_show');
    });

    // product Queries show on Admin panel
    Route::controller(ProductQueryController::class)->group(function () {
        Route::get('/product-queries', 'index')->name('product_query.index');
        Route::get('/product-queries/{id}', 'show')->name('product_query.show');
        Route::put('/product-queries/{id}', 'reply')->name('product_query.reply');
    });

    // Product Attribute
    Route::resource('attributes', AttributeController::class);
    Route::controller(AttributeController::class)->group(function () {
        Route::get('/attributes/edit/{id}', 'edit')->name('attributes.edit');
        Route::get('/attributes/destroy/{id}', 'destroy')->name('attributes.destroy');

        //Attribute Value
        Route::post('/store-attribute-value', 'store_attribute_value')->name('store-attribute-value');
        Route::get('/edit-attribute-value/{id}', 'edit_attribute_value')->name('edit-attribute-value');
        Route::post('/update-attribute-value/{id}', 'update_attribute_value')->name('update-attribute-value');
        Route::get('/destroy-attribute-value/{id}', 'destroy_attribute_value')->name('destroy-attribute-value');

        //Colors
        Route::get('/colors', 'colors')->name('colors');
        Route::post('/colors/store', 'store_color')->name('colors.store');
        Route::get('/colors/edit/{id}', 'edit_color')->name('colors.edit');
        Route::post('/colors/update/{id}', 'update_color')->name('colors.update');
        Route::get('/colors/destroy/{id}', 'destroy_color')->name('colors.destroy');
    });

    // Size Chart
    Route::resource('size-charts', SizeChartController::class);
    Route::get('/size-charts/destroy/{id}',  [SizeChartController::class, 'destroy'])->name('size-charts.destroy');
    Route::post('size-charts/get-combination',   [SizeChartController::class, 'get_combination'])->name('size-charts.get-combination');

    // Measurement Points
    Route::resource('measurement-points', MeasurementPointsController::class);
    Route::get('/measurement-points/destroy/{id}',  [MeasurementPointsController::class, 'destroy'])->name('measurement-points.destroy');

    // Addon
    Route::resource('addons', AddonController::class);
    Route::post('/addons/activation', [AddonController::class, 'activation'])->name('addons.activation');

    //Customer Package
    Route::resource('customer_packages', CustomerPackageController::class);
    Route::controller(CustomerPackageController::class)->group(function () {
        Route::get('/customer_packages/edit/{id}', 'edit')->name('customer_packages.edit');
        Route::get('/customer_packages/destroy/{id}', 'destroy')->name('customer_packages.destroy');
    });

    //Classified Products
    Route::controller(CustomerProductController::class)->group(function () {
        Route::get('/classified_products', 'customer_product_index')->name('classified_products');
        Route::post('/classified_products/published', 'updatePublished')->name('classified_products.published');
        Route::get('/classified_products/destroy/{id}', 'destroy_by_admin')->name('classified_products.destroy');
    });

    // Countries
    Route::resource('countries', CountryController::class);
    Route::post('/countries/status', [CountryController::class, 'updateStatus'])->name('countries.status');

    // States
    Route::resource('states', StateController::class);
    Route::post('/states/status', [StateController::class, 'updateStatus'])->name('states.status');

    // Carriers
    Route::resource('carriers', CarrierController::class);
    Route::controller(CarrierController::class)->group(function () {
        Route::get('/carriers/destroy/{id}', 'destroy')->name('carriers.destroy');
        Route::post('/carriers/update_status', 'updateStatus')->name('carriers.update_status');
    });


    // Zones
    Route::resource('zones', ZoneController::class);
    Route::get('/zones/destroy/{id}', [ZoneController::class, 'destroy'])->name('zones.destroy');

    Route::resource('cities', CityController::class);
    Route::controller(CityController::class)->group(function () {
        Route::get('/cities/edit/{id}', 'edit')->name('cities.edit');
        Route::get('/cities/destroy/{id}', 'destroy')->name('cities.destroy');
        Route::post('/cities/status', 'updateStatus')->name('cities.status');
    });

    Route::view('/system/update', 'backend.system.update')->name('system_update');
    Route::view('/system/server-status', 'backend.system.server_status')->name('system_server');
    Route::view('/system/import-demo-data', 'backend.system.import_demo_data')->name('import_demo_data');

    Route::post('/import-data', [BusinessSettingsController::class, 'import_data'])->name('import_data');

    // uploaded files
    Route::resource('/uploaded-files', AizUploadController::class);
    Route::controller(AizUploadController::class)->group(function () {
        Route::any('/uploaded-files/file-info', 'file_info')->name('uploaded-files.info');
        Route::get('/uploaded-files/destroy/{id}', 'destroy')->name('uploaded-files.destroy');
        Route::post('/bulk-uploaded-files-delete', 'bulk_uploaded_files_delete')->name('bulk-uploaded-files-delete');
        Route::get('/all-file', 'all_file');
    });

    // All Courier credential
    Route::get('/courier-setup', [CourierSetupController::class, 'index'])->name('courier_setup.index');
    // steadfast courier routes
    Route::get('/settings/api-keys', [SteadfastSettingsController::class, 'editSteadfastApiKeys'])->name('settings.edit.api.keys');
    Route::put('/settings/api-keys', [SteadfastSettingsController::class, 'updateSteadfastApiKeys'])->name('settings.update.api.keys');



    // pathao courier routes
    Route::get('/settings/pathao-edit', [PathaoCourierSettingsController::class, 'editPathaoSettings'])->name('settings.edit.pathao');
    Route::put('/settings/pathao-update', [PathaoCourierSettingsController::class, 'updatePathaoSettings'])->name('settings.update.pathao');
    Route::get('/pathao/cities', [PathaoCourierSettingsController::class, 'cities'])->name('pathao.cities');
    Route::get('/pathao/zones/{city}', [PathaoCourierSettingsController::class, 'zones'])->name('pathao.zones');
    // About us routes


    Route::controller(NotificationController::class)->group(function () {
        Route::get('/all-notifications', 'adminIndex')->name('admin.all-notifications');
        Route::get('/notification-settings', 'notificationSettings')->name('notification.settings');

        Route::post('/notifications/bulk-delete', 'bulkDeleteAdmin')->name('admin.notifications.bulk_delete');
        Route::get('/notification/read-and-redirect/{id}', 'readAndRedirect')->name('admin.notification.read-and-redirect');

        Route::get('/custom-notification', 'customNotification')->name('custom_notification');
        Route::post('/custom-notification/send', 'sendCustomNotification')->name('custom_notification.send');

        Route::get('/custom-notification/history', 'customNotificationHistory')->name('custom_notification.history');
        Route::get('/custom-notifications.delete/{identifier}', 'customNotificationSingleDelete')->name('custom-notifications.delete');
        Route::post('/custom-notifications.bulk_delete', 'customNotificationBulkDelete')->name('custom-notifications.bulk_delete');
        Route::post('/custom-notified-customers-list', 'customNotifiedCustomersList')->name('custom_notified_customers_list');
    });

    Route::resource('notification-type', NotificationTypeController::class);
    Route::controller(NotificationTypeController::class)->group(function () {
        Route::get('/notification-type/edit/{id}', 'edit')->name('notification-type.edit');
        Route::post('/notification-type/update-status', 'updateStatus')->name('notification-type.update-status');
        Route::get('/notification-type/destroy/{id}', 'destroy')->name('notification-type.destroy');
        Route::post('/notification-type/bulk_delete', 'bulkDelete')->name('notifications-type.bulk_delete');
        Route::post('/notification-type.get-default-text', 'getDefaulText')->name('notification_type.get_default_text');
    });

    Route::get('/clear-cache', [AdminController::class, 'clearCache'])->name('cache.clear');

    Route::get('/admin-permissions', [RoleController::class, 'create_admin_permissions']);

    Route::controller(InvoiceController::class)->group(function () {
        Route::get('pay-slip/{id}', 'paySlip')->name('pay-slip');
    });


    Route::controller(BDcourierController::class)->group(function () {
        Route::get('bd-courier/show', 'show')->name('bd-courier.show');
        Route::post('/bd-courier/create-or-update', 'createOrUpdate')->name('bd-courier.create-or-update');
    });

    // delivery return modal all routes
    Route::get('/delivery/modal', [DeliveryModalController::class, 'index'])->name('delivery_return_modal.index');

     // Stock adjustments all routes
    Route::controller(StockAdjustmentController::class)->group(function () {
        Route::get('stock-adjustments/export-csv', 'exportCsv')->name('stock-adjustments.export-csv');
        Route::get('stock-adjustments/export-excel', 'exportExcel')->name('stock-adjustments.export-excel');
        Route::get('stock-adjustments/export-pdf', 'exportPdf')->name('stock-adjustments.export-pdf');
        Route::post('stock-adjustments/search-product', 'searchProduct')->name('stock-adjustments.search-product');
    });

    Route::resource('stock-adjustments', StockAdjustmentController::class);

    // Sales funnel analytics (orders and revenue from the order records).
    Route::get('funnels/analytics', [\App\Http\Controllers\FunnelAnalyticsController::class, 'index'])->name('funnels.analytics');

    // Sales funnel library: templates (built-in and the merchant's own), saved sections, settings.
    Route::controller(\App\Http\Controllers\FunnelLibraryController::class)->prefix('funnels')->name('funnels.')->group(function () {
        Route::get('/templates', 'templates')->name('templates');
        Route::post('/{id}/save-as-template', 'saveAsTemplate')->name('save_as_template');
        Route::post('/templates/{id}', 'renameTemplate')->name('templates.update');
        Route::post('/templates/{id}/duplicate', 'duplicateTemplate')->name('templates.duplicate');
        Route::delete('/templates/{id}', 'deleteTemplate')->name('templates.destroy');
        Route::get('/saved-sections', 'savedSections')->name('sections');
        Route::post('/saved-sections/{id}', 'renameSection')->name('sections.update');
        Route::delete('/saved-sections/{id}', 'deleteSection')->name('sections.destroy');
        Route::get('/settings', 'settings')->name('settings');
    });

    // Sales funnels. State changes are POST/DELETE so they carry the CSRF token.
    Route::controller(FunnelController::class)->prefix('funnels')->name('funnels.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/products', 'products')->name('products');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}/builder', 'builder')->name('builder');
        Route::post('/{id}/duplicate', 'duplicate')->name('duplicate');
        Route::post('/{id}/publish', 'publish')->name('publish');
        Route::post('/{id}/unpublish', 'unpublish')->name('unpublish');
        Route::post('/{id}/archive', 'archive')->name('archive');
        Route::post('/{id}/restore', 'restore')->name('restore');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

});
