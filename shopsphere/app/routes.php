<?php
declare(strict_types=1);

use App\Controllers\{ShopController, CartController, AuthController, AccountController, CheckoutController, AdminController};
use App\Core\Router;

$r = new Router();

// ---- storefront ----
$r->get('/', [ShopController::class, 'home']);
$r->get('/search', [ShopController::class, 'listing']);
$r->get('/deals', [ShopController::class, 'deals']);
$r->get('/category/{slug}', [ShopController::class, 'listing']);
$r->get('/product/{slug}', [ShopController::class, 'product']);
$r->post('/product/{slug}/review', [ShopController::class, 'review']);
$r->post('/product/{slug}/question', [ShopController::class, 'question']);
$r->get('/img/ph/{sku}/{n}', [ShopController::class, 'placeholder']);
$r->get('/page/{page}', [ShopController::class, 'staticPage']);
$r->get('/api/suggest', [ShopController::class, 'suggest']);
$r->post('/api/pin', [ShopController::class, 'setPin']);
$r->post('/api/newsletter', [ShopController::class, 'newsletter']);

// ---- cart / wishlist / compare ----
$r->get('/cart', [CartController::class, 'page']);
$r->post('/api/cart/add', [CartController::class, 'add']);
$r->post('/api/cart/update', [CartController::class, 'update']);
$r->post('/api/cart/remove', [CartController::class, 'remove']);
$r->get('/api/cart/summary', [CartController::class, 'summary']);
$r->post('/api/cart/coupon', [CartController::class, 'coupon']);
$r->get('/wishlist', [CartController::class, 'wishlistPage']);
$r->post('/api/wishlist/toggle', [CartController::class, 'wishlistToggle']);
$r->post('/api/wishlist/move', [CartController::class, 'wishlistMove']);
$r->get('/compare', [CartController::class, 'comparePage']);
$r->post('/api/compare/toggle', [CartController::class, 'compareToggle']);
$r->post('/compare/remove', [CartController::class, 'compareRemove']);

// ---- auth ----
$r->get('/login', [AuthController::class, 'loginForm']);
$r->post('/login', [AuthController::class, 'login']);
$r->get('/register', [AuthController::class, 'registerForm']);
$r->post('/register', [AuthController::class, 'register']);
$r->post('/logout', [AuthController::class, 'logout']);
$r->get('/verify/{token}', [AuthController::class, 'verify']);
$r->post('/verify/resend', [AuthController::class, 'resendVerification']);
$r->get('/forgot', [AuthController::class, 'forgotForm']);
$r->post('/forgot', [AuthController::class, 'forgot']);
$r->get('/reset/{token}', [AuthController::class, 'resetForm']);
$r->post('/reset/{token}', [AuthController::class, 'reset']);

// ---- account ----
$r->get('/account', [AccountController::class, 'dashboard']);
$r->get('/account/profile', [AccountController::class, 'profileForm']);
$r->post('/account/profile', [AccountController::class, 'profileSave']);
$r->post('/account/password', [AccountController::class, 'passwordSave']);
$r->get('/account/addresses', [AccountController::class, 'addresses']);
$r->post('/account/addresses', [AccountController::class, 'addressSave']);
$r->post('/account/addresses/delete', [AccountController::class, 'addressDelete']);
$r->get('/account/orders', [AccountController::class, 'orders']);
$r->post('/order/{no}/cancel', [AccountController::class, 'cancel']);
$r->post('/order/{no}/return', [AccountController::class, 'requestReturn']);

// ---- checkout / orders / payments ----
$r->get('/checkout', [CheckoutController::class, 'page']);
$r->post('/api/checkout/quote', [CheckoutController::class, 'quote']);
$r->post('/checkout/place', [CheckoutController::class, 'place']);
$r->post('/api/payment/start', [CheckoutController::class, 'payStart']);
$r->post('/api/payment/verify', [CheckoutController::class, 'payVerify']);
$r->post('/api/payment/failed', [CheckoutController::class, 'payFailed']);
$r->post('/webhook/razorpay', [CheckoutController::class, 'webhook']);
$r->get('/order/{no}/confirmation', [CheckoutController::class, 'confirmation']);
$r->get('/order/{no}', [CheckoutController::class, 'order']);
$r->get('/invoice/{no}', [CheckoutController::class, 'invoice']);
$r->get('/track', [CheckoutController::class, 'trackForm']);
$r->post('/track', [CheckoutController::class, 'track']);

// ---- admin ----
$r->get('/admin', [AdminController::class, 'dashboard']);
$r->get('/admin/products', [AdminController::class, 'products']);
$r->get('/admin/products/new', [AdminController::class, 'productForm']);
$r->post('/admin/products/new', [AdminController::class, 'productSave']);
$r->get('/admin/products/{id}', [AdminController::class, 'productForm']);
$r->post('/admin/products/{id}', [AdminController::class, 'productSave']);
$r->post('/admin/products/{id}/toggle', [AdminController::class, 'productToggle']);
$r->get('/admin/orders', [AdminController::class, 'orders']);
$r->get('/admin/orders/{no}', [AdminController::class, 'orderDetail']);
$r->post('/admin/orders/{no}/status', [AdminController::class, 'orderStatus']);
$r->post('/admin/orders/{no}/cancel', [AdminController::class, 'orderCancel']);
$r->get('/admin/returns', [AdminController::class, 'returns']);
$r->post('/admin/returns/{id}', [AdminController::class, 'returnHandle']);
$r->get('/admin/refunds', [AdminController::class, 'refunds']);
$r->post('/admin/refunds/{id}/process', [AdminController::class, 'refundProcess']);
$r->get('/admin/coupons', [AdminController::class, 'coupons']);
$r->post('/admin/coupons', [AdminController::class, 'couponSave']);
$r->get('/admin/users', [AdminController::class, 'users']);
$r->post('/admin/users/{id}', [AdminController::class, 'userUpdate']);
$r->post('/admin/questions/answer', [AdminController::class, 'answerQuestion']);

return $r;
