<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymobController;
use App\Http\Controllers\Pos\PosReturnController;
use App\Http\Controllers\Pos\PosSaleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

/*
 * Storefront. Blade-rendered, guest checkout, cash on delivery or Paymob.
 *
 * There is deliberately no routes/api.php: the POS phase mandates a Blade
 * monolith with session auth throughout (NABTA-POS-Scale-Offline-Prompt.md,
 * "Architecture Constraint"). The cart endpoints below answer JSON for Alpine
 * but are ordinary session+CSRF web routes, not an API layer.
 */

Route::get('/', [ShopController::class, 'home'])->name('home');
Route::get('/categories', [ShopController::class, 'categories'])->name('categories');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/p/{product}', [ShopController::class, 'show'])->name('products.show');

/*
 * Cart endpoints bind on the primary key, not the slug: Product::getRouteKeyName()
 * is 'slug' so that product URLs stay readable and keep the prototype's links
 * working, but the cart is internal plumbing addressed by id.
 */
Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'state')->name('state');
    Route::post('/add/{product:id}', 'add')->name('add');
    Route::patch('/{product:id}', 'update')->name('update');
    Route::delete('/{product:id}', 'remove')->name('remove');
});

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1')   // a public COD endpoint with no auth will get spammed
    ->name('checkout.store');
Route::get('/order/{order}', [CheckoutController::class, 'confirmation'])->name('orders.confirmation');

/*
 * Paymob. The customer's browser returns to the callback; Paymob's servers post
 * to the webhook, which is CSRF-exempt (bootstrap/app.php) because it carries
 * an HMAC signature instead. Both verify that signature before touching an order.
 */
Route::get('/checkout/paymob/callback', [PaymobController::class, 'callback'])
    ->middleware('throttle:30,1')
    ->name('paymob.callback');
Route::post('/paymob/webhook', [PaymobController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('paymob.webhook');

/*
 * POS. Session-authenticated Blade screens for the cashier, on the store PC.
 *
 * The sale endpoint answers JSON because a cart cannot be an HTML form post,
 * but it is an ordinary web route behind the same session auth and CSRF as
 * everything else — there is deliberately no API layer and no token auth.
 *
 * The /pos/sync/* prefix reserved by the brief for the offline outbox is NOT
 * implemented yet; see docs/POS.md.
 */
Route::middleware(['auth', 'permission:pos.use'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('terminal', [PosController::class, 'selectTerminal'])->name('terminal');
    Route::post('terminal', [PosController::class, 'storeTerminal'])->name('terminal.store');

    Route::get('/', [PosController::class, 'index'])->name('index');

    Route::get('shift/open', [PosController::class, 'showOpenShift'])->name('shift.open');
    Route::post('shift/open', [PosController::class, 'openShift'])->name('shift.open.store');
    Route::get('shift/close', [PosController::class, 'showCloseShift'])->name('shift.close');
    Route::post('shift/close', [PosController::class, 'closeShift'])->name('shift.close.store');

    Route::post('sales', [PosSaleController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('sales.store');

    Route::get('receipt/{sale}', [PosController::class, 'receipt'])->name('receipt');

    // Returns need their own permission: a cashier can be trusted to sell
    // without being trusted to hand money back out of the drawer.
    Route::middleware('permission:pos.refund')->group(function () {
        Route::get('returns', [PosReturnController::class, 'search'])->name('returns');
        Route::post('returns/{sale}', [PosReturnController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('returns.store');
    });
});

/*
 * Reports and the PLU export. Printable Blade pages behind the same session
 * auth as the admin panel, linked from it rather than duplicated inside it.
 */
Route::middleware(['auth', 'permission:reports.view,accounting.view'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('party/{party}', [ReportController::class, 'partyStatement'])->name('party-statement');
    Route::get('treasury', [ReportController::class, 'treasury'])->name('treasury');
    Route::get('plu-export', [ReportController::class, 'pluExport'])->name('plu-export');
});

/* Staff interface language (Arabic default, English for setup and support). */
Route::get('locale/{locale}', [LocaleController::class, 'update'])
    ->middleware('auth')
    ->name('locale.update');
