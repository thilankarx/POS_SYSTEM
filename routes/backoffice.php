<?php

use App\Http\Controllers\Catalog\ItemImportTemplateController;
use App\Http\Controllers\Documents\SaleReceiptController;
use App\Http\Controllers\Documents\SupplierInvoicePdfController;
use App\Http\Controllers\Reports\CategoryReportExportController;
use App\Http\Controllers\Reports\CategoryReportPdfController;
use App\Http\Controllers\Reports\CommissionReportExportController;
use App\Http\Controllers\Reports\CustomerReportExportController;
use App\Http\Controllers\Reports\CustomerReportPdfController;
use App\Http\Controllers\Reports\InventoryReportExportController;
use App\Http\Controllers\Reports\InventoryReportPdfController;
use App\Http\Controllers\Reports\ItemReportExportController;
use App\Http\Controllers\Reports\ItemReportPdfController;
use App\Http\Controllers\Reports\PaymentReportExportController;
use App\Http\Controllers\Reports\PaymentReportPdfController;
use App\Http\Controllers\Reports\ReceivingReportExportController;
use App\Http\Controllers\Reports\ReceivingReportPdfController;
use App\Http\Controllers\Reports\SalesReportExportController;
use App\Http\Controllers\Reports\SalesReportPdfController;
use App\Http\Controllers\Reports\ShiftReportExportController;
use App\Http\Controllers\Reports\ShiftReportPdfController;
use App\Http\Controllers\Reports\SupplierReportExportController;
use App\Http\Controllers\Reports\SupplierReportPdfController;
use App\Http\Controllers\Reports\TaxReportExportController;
use App\Http\Controllers\Reports\TaxReportPdfController;
use App\Livewire\Catalog\Items\Form;
use App\Livewire\Catalog\Items\Import;
use App\Livewire\Catalog\Items\Index;
use App\Livewire\Inventory\StockLocations\StockLevels;
use App\Livewire\Purchasing\Receivings\Labels;
use App\Livewire\Purchasing\Receivings\Show;
use App\Livewire\Reporting\Categories;
use App\Livewire\Reporting\Commissions;
use App\Livewire\Reporting\Customers;
use App\Livewire\Reporting\Inventory;
use App\Livewire\Reporting\Items;
use App\Livewire\Reporting\Payments;
use App\Livewire\Reporting\Receivings;
use App\Livewire\Reporting\Sales;
use App\Livewire\Reporting\Shifts;
use App\Livewire\Reporting\Suppliers;
use App\Livewire\Reporting\Taxes;
use App\Livewire\Sales\Index as SalesIndex;
use App\Livewire\Sales\Refund;
use App\Livewire\Sales\Shift\History;
use App\Livewire\Sales\Shift\Manage;
use App\Livewire\Sales\Show as SalesShow;
use App\Livewire\Sales\VoidSale;
use App\Livewire\Settings\BusinessProfile;
use App\Livewire\Settings\Labels as LabelSettingsPage;
use App\Livewire\Settings\Numbering;
use App\Livewire\Settings\TaxDefaults;
use Illuminate\Support\Facades\Route;

Route::prefix('shift')->name('shift.')->group(function () {
    Route::get('/', Manage::class)->name('manage');
    Route::get('/history', History::class)->name('history')->middleware('permission:shifts.view_all');
});

Route::prefix('items')->name('items.')->middleware('permission:items.view')->group(function () {
    Route::get('/', Index::class)->name('index');
    Route::get('/create', Form::class)->name('create')->middleware('permission:items.manage');
    Route::get('/import', Import::class)->name('import')->middleware('permission:items.import');
    Route::get('/import/template', ItemImportTemplateController::class)->name('import.template')->middleware('permission:items.import');
    Route::get('/{item}/edit', Form::class)->name('edit')->middleware('permission:items.manage');
});

Route::prefix('categories')->name('categories.')->middleware('permission:items.view')->group(function () {
    Route::get('/', App\Livewire\Catalog\Categories\Index::class)->name('index');
    Route::get('/create', App\Livewire\Catalog\Categories\Form::class)->name('create')->middleware('permission:items.manage');
    Route::get('/{category}/edit', App\Livewire\Catalog\Categories\Form::class)->name('edit')->middleware('permission:items.manage');
});

Route::prefix('attribute-definitions')->name('attribute-definitions.')->middleware('permission:attributes.manage')->group(function () {
    Route::get('/', App\Livewire\Catalog\AttributeDefinitions\Index::class)->name('index');
    Route::get('/create', App\Livewire\Catalog\AttributeDefinitions\Form::class)->name('create');
    Route::get('/{attributeDefinition}/edit', App\Livewire\Catalog\AttributeDefinitions\Form::class)->name('edit');
});

Route::prefix('item-kits')->name('item-kits.')->middleware('permission:item_kits.view')->group(function () {
    Route::get('/', App\Livewire\Catalog\ItemKits\Index::class)->name('index');
    Route::get('/create', App\Livewire\Catalog\ItemKits\Form::class)->name('create')->middleware('permission:item_kits.manage');
    Route::get('/{itemKit}/edit', App\Livewire\Catalog\ItemKits\Form::class)->name('edit')->middleware('permission:item_kits.manage');
});

Route::prefix('suppliers')->name('suppliers.')->middleware('permission:suppliers.view')->group(function () {
    Route::get('/', App\Livewire\Crm\Suppliers\Index::class)->name('index');
    Route::get('/create', App\Livewire\Crm\Suppliers\Form::class)->name('create')->middleware('permission:suppliers.manage');
    Route::get('/{supplier}/edit', App\Livewire\Crm\Suppliers\Form::class)->name('edit')->middleware('permission:suppliers.manage');
});

Route::prefix('customers')->name('customers.')->middleware('permission:customers.view')->group(function () {
    Route::get('/', App\Livewire\Crm\Customers\Index::class)->name('index');
    Route::get('/create', App\Livewire\Crm\Customers\Form::class)->name('create')->middleware('permission:customers.manage');
    Route::get('/{customer}/edit', App\Livewire\Crm\Customers\Form::class)->name('edit')->middleware('permission:customers.manage');
});

Route::prefix('stock-locations')->name('stock-locations.')->middleware('permission:locations.view')->group(function () {
    Route::get('/', App\Livewire\Inventory\StockLocations\Index::class)->name('index');
    Route::get('/create', App\Livewire\Inventory\StockLocations\Form::class)->name('create')->middleware('permission:locations.manage');
    Route::get('/{stockLocation}/edit', App\Livewire\Inventory\StockLocations\Form::class)->name('edit')->middleware('permission:locations.manage');
    Route::get('/{stockLocation}/stock', StockLevels::class)->name('stock');
});

Route::get('/stock-adjustments/create', App\Livewire\Inventory\StockAdjustments\Form::class)->name('stock-adjustments.create')->middleware('permission:inventory.adjust');
Route::get('/stock-transfers/create', App\Livewire\Inventory\StockTransfers\Form::class)->name('stock-transfers.create')->middleware('permission:inventory.transfer');

Route::prefix('users')->name('users.')->middleware('permission:users.view')->group(function () {
    Route::get('/', App\Livewire\Identity\Users\Index::class)->name('index');
    Route::get('/create', App\Livewire\Identity\Users\Form::class)->name('create')->middleware('permission:users.manage');
    Route::get('/{user}/edit', App\Livewire\Identity\Users\Form::class)->name('edit')->middleware('permission:users.manage');
});

Route::prefix('purchase-orders')->name('purchase-orders.')->middleware('permission:purchasing.view')->group(function () {
    Route::get('/', App\Livewire\Purchasing\PurchaseOrders\Index::class)->name('index');
    Route::get('/create', App\Livewire\Purchasing\PurchaseOrders\Form::class)->name('create')->middleware('permission:purchasing.manage');
    Route::get('/{purchaseOrder}/edit', App\Livewire\Purchasing\PurchaseOrders\Form::class)->name('edit');
});

Route::get('reorder-suggestions', App\Livewire\Purchasing\ReorderSuggestions\Index::class)
    ->name('reorder-suggestions.index')
    ->middleware('permission:purchasing.view');

Route::prefix('receivings')->name('receivings.')->middleware('permission:receivings.view')->group(function () {
    Route::get('/', App\Livewire\Purchasing\Receivings\Index::class)->name('index');
    Route::get('/create', App\Livewire\Purchasing\Receivings\Form::class)->name('create')->middleware('permission:receivings.manage');
    Route::get('/{receiving}', Show::class)->name('show');
    Route::get('/{receiving}/labels', Labels::class)->name('labels')->middleware('permission:receivings.manage');
});

Route::prefix('supplier-invoices')->name('supplier-invoices.')->middleware('permission:purchasing.view')->group(function () {
    Route::get('/', App\Livewire\Purchasing\SupplierInvoices\Index::class)->name('index');
    Route::get('/create', App\Livewire\Purchasing\SupplierInvoices\Form::class)->name('create')->middleware('permission:purchasing.manage');
    Route::get('/{supplierInvoice}/edit', App\Livewire\Purchasing\SupplierInvoices\Form::class)->name('edit')->middleware('permission:purchasing.manage');
    Route::get('/{supplierInvoice}/pdf', SupplierInvoicePdfController::class)->name('pdf');
});

Route::prefix('promotions')->name('promotions.')->middleware('permission:promotions.view')->group(function () {
    Route::get('/', App\Livewire\Promotions\Index::class)->name('index');
    Route::get('/create', App\Livewire\Promotions\Form::class)->name('create')->middleware('permission:promotions.manage');
    Route::get('/{promotion}/edit', App\Livewire\Promotions\Form::class)->name('edit')->middleware('permission:promotions.manage');
});

Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
    Route::get('/sales', Sales::class)->name('sales')->middleware('permission:reports.sales');
    Route::get('/sales/export', SalesReportExportController::class)->name('sales.export')->middleware('permission:reports.sales');
    Route::get('/sales/export/pdf', SalesReportPdfController::class)->name('sales.export-pdf')->middleware('permission:reports.sales');

    Route::get('/inventory', Inventory::class)->name('inventory')->middleware('permission:reports.inventory');
    Route::get('/inventory/export', InventoryReportExportController::class)->name('inventory.export')->middleware('permission:reports.inventory');
    Route::get('/inventory/export/pdf', InventoryReportPdfController::class)->name('inventory.export-pdf')->middleware('permission:reports.inventory');

    Route::get('/shifts', Shifts::class)->name('shifts')->middleware('permission:reports.shifts');
    Route::get('/shifts/export', ShiftReportExportController::class)->name('shifts.export')->middleware('permission:reports.shifts');
    Route::get('/shifts/export/pdf', ShiftReportPdfController::class)->name('shifts.export-pdf')->middleware('permission:reports.shifts');

    Route::get('/items', Items::class)->name('items')->middleware('permission:reports.items');
    Route::get('/items/export', ItemReportExportController::class)->name('items.export')->middleware('permission:reports.items');
    Route::get('/items/export/pdf', ItemReportPdfController::class)->name('items.export-pdf')->middleware('permission:reports.items');

    Route::get('/categories', Categories::class)->name('categories')->middleware('permission:reports.categories');
    Route::get('/categories/export', CategoryReportExportController::class)->name('categories.export')->middleware('permission:reports.categories');
    Route::get('/categories/export/pdf', CategoryReportPdfController::class)->name('categories.export-pdf')->middleware('permission:reports.categories');

    Route::get('/suppliers', Suppliers::class)->name('suppliers')->middleware('permission:reports.suppliers');
    Route::get('/suppliers/export', SupplierReportExportController::class)->name('suppliers.export')->middleware('permission:reports.suppliers');
    Route::get('/suppliers/export/pdf', SupplierReportPdfController::class)->name('suppliers.export-pdf')->middleware('permission:reports.suppliers');

    Route::get('/receivings', Receivings::class)->name('receivings')->middleware('permission:reports.receivings');
    Route::get('/receivings/export', ReceivingReportExportController::class)->name('receivings.export')->middleware('permission:reports.receivings');
    Route::get('/receivings/export/pdf', ReceivingReportPdfController::class)->name('receivings.export-pdf')->middleware('permission:reports.receivings');

    Route::get('/customers', Customers::class)->name('customers')->middleware('permission:reports.customers');
    Route::get('/customers/export', CustomerReportExportController::class)->name('customers.export')->middleware('permission:reports.customers');
    Route::get('/customers/export/pdf', CustomerReportPdfController::class)->name('customers.export-pdf')->middleware('permission:reports.customers');

    Route::get('/payments', Payments::class)->name('payments')->middleware('permission:reports.payments');
    Route::get('/payments/export', PaymentReportExportController::class)->name('payments.export')->middleware('permission:reports.payments');
    Route::get('/payments/export/pdf', PaymentReportPdfController::class)->name('payments.export-pdf')->middleware('permission:reports.payments');

    Route::get('/taxes', Taxes::class)->name('taxes')->middleware('permission:reports.taxes');
    Route::get('/taxes/export', TaxReportExportController::class)->name('taxes.export')->middleware('permission:reports.taxes');
    Route::get('/taxes/export/pdf', TaxReportPdfController::class)->name('taxes.export-pdf')->middleware('permission:reports.taxes');

    Route::get('/commissions', Commissions::class)->name('commissions')->middleware('permission:reports.employees');
    Route::get('/commissions/export', CommissionReportExportController::class)->name('commissions.export')->middleware('permission:reports.employees');
});

Route::prefix('stock-counts')->name('stock-counts.')->middleware('permission:inventory.view')->group(function () {
    Route::get('/', App\Livewire\Inventory\StockCounts\Index::class)->name('index');
    Route::get('/create', App\Livewire\Inventory\StockCounts\Form::class)->name('create')->middleware('permission:inventory.count');
    Route::get('/{stockCount}/edit', App\Livewire\Inventory\StockCounts\Form::class)->name('edit');
});

Route::get('serial-numbers', App\Livewire\Inventory\SerialNumbers\Index::class)
    ->name('serial-numbers.index')
    ->middleware('permission:inventory.view');

Route::prefix('sales')->name('sales.')->middleware('permission:sales.view')->group(function () {
    Route::get('/', SalesIndex::class)->name('index');
    Route::get('/{sale}', SalesShow::class)->name('show');
    Route::get('/{sale}/receipt', SaleReceiptController::class)->name('receipt');
    Route::get('/{sale}/refund', Refund::class)->name('refund')->middleware('permission:sales.refund');
    Route::get('/{sale}/void', VoidSale::class)->name('void')->middleware('permission:sales.void');
});

Route::prefix('loyalty-packages')->name('loyalty-packages.')->middleware('permission:loyalty.view')->group(function () {
    Route::get('/', App\Livewire\Loyalty\Packages\Index::class)->name('index');
    Route::get('/create', App\Livewire\Loyalty\Packages\Form::class)->name('create')->middleware('permission:loyalty.manage');
    Route::get('/{loyaltyPackage}/edit', App\Livewire\Loyalty\Packages\Form::class)->name('edit')->middleware('permission:loyalty.manage');
});

Route::prefix('giftcards')->name('giftcards.')->middleware('permission:giftcards.view')->group(function () {
    Route::get('/', App\Livewire\Giftcards\Index::class)->name('index');
    Route::get('/create', App\Livewire\Giftcards\Form::class)->name('create')->middleware('permission:giftcards.manage');
    Route::get('/{giftcard}/edit', App\Livewire\Giftcards\Form::class)->name('edit');
});

Route::prefix('expense-categories')->name('expense-categories.')->middleware('permission:expenses.view')->group(function () {
    Route::get('/', App\Livewire\Finance\ExpenseCategories\Index::class)->name('index');
    Route::get('/create', App\Livewire\Finance\ExpenseCategories\Form::class)->name('create')->middleware('permission:expenses.manage');
    Route::get('/{expenseCategory}/edit', App\Livewire\Finance\ExpenseCategories\Form::class)->name('edit')->middleware('permission:expenses.manage');
});

Route::prefix('expenses')->name('expenses.')->middleware('permission:expenses.view')->group(function () {
    Route::get('/', App\Livewire\Finance\Expenses\Index::class)->name('index');
    Route::get('/create', App\Livewire\Finance\Expenses\Form::class)->name('create')->middleware('permission:expenses.manage');
    Route::get('/{expense}/edit', App\Livewire\Finance\Expenses\Form::class)->name('edit')->middleware('permission:expenses.manage');
});

Route::prefix('terminals')->name('terminals.')->middleware('permission:terminals.manage')->group(function () {
    Route::get('/', App\Livewire\Sales\Terminals\Index::class)->name('index');
    Route::get('/create', App\Livewire\Sales\Terminals\Form::class)->name('create');
    Route::get('/{terminal}/edit', App\Livewire\Sales\Terminals\Form::class)->name('edit');
});

Route::prefix('dinner-tables')->name('dinner-tables.')->middleware(['permission:tables.manage', 'business.type:restaurant'])->group(function () {
    Route::get('/', App\Livewire\Sales\DinnerTables\Index::class)->name('index');
    Route::get('/create', App\Livewire\Sales\DinnerTables\Form::class)->name('create');
    Route::get('/{dinnerTable}/edit', App\Livewire\Sales\DinnerTables\Form::class)->name('edit');
});

Route::prefix('tax-categories')->name('tax-categories.')->middleware('permission:taxes.manage')->group(function () {
    Route::get('/', App\Livewire\Taxation\Categories\Index::class)->name('index');
    Route::get('/create', App\Livewire\Taxation\Categories\Form::class)->name('create');
    Route::get('/{taxCategory}/edit', App\Livewire\Taxation\Categories\Form::class)->name('edit');
});

Route::get('audit', App\Livewire\Audit\Index::class)->name('audit.index')->middleware('permission:audit.view');

Route::prefix('settings')->name('settings.')->middleware('permission:config.manage')->group(function () {
    Route::get('/business-profile', BusinessProfile::class)->name('business-profile');
    Route::get('/numbering', Numbering::class)->name('numbering');
    Route::get('/tax', TaxDefaults::class)->name('tax');
    Route::get('/labels', LabelSettingsPage::class)->name('labels');
});
