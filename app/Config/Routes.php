<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->get('manifest.webmanifest', 'Auth::manifest');

$routes->group('', ['filter' => 'auth'], static function ($r) {
    $r->get('/', 'Home::index');
    $r->post('logout', 'Auth::logout');
    $r->post('ping', 'Auth::ping');
    $r->post('switch-location', 'Home::switchLocation');

    // Kasir
    $r->group('', ['filter' => 'role:cashier'], static function ($r) {
        $r->get('pos', 'Pos::index');
        $r->post('pos/checkout', 'Pos::checkout');
        $r->get('pos/receipt/(:num)', 'Pos::receipt/$1');
        $r->post('reservations/save', 'Reservations::save');
        $r->post('reservations/(:num)/status', 'Reservations::setStatus/$1');
        $r->get('shift', 'Shifts::index');
        $r->post('shift/open', 'Shifts::open');
        $r->get('shift/close', 'Shifts::closeForm');
        $r->post('shift/close', 'Shifts::close');
        $r->get('shifts', 'Shifts::history');
        $r->get('shifts/(:num)', 'Shifts::show/$1');
        $r->get('shifts/(:num)/receipt', 'Shifts::receipt/$1');
        $r->get('sales', 'Sales::index');
        $r->get('sales/(:num)', 'Sales::show/$1');
        $r->get('receiving', 'Receiving::index');
        $r->get('receiving/receive/(:num)', 'Receiving::form/$1');
        $r->post('receiving/receive/(:num)', 'Receiving::store/$1');
        $r->get('receiving/(:num)', 'Receiving::show/$1');
        $r->post('stock/adjust', 'Stock::adjust');
    });
    $r->post('sales/(:num)/void', 'Sales::void/$1', ['filter' => 'role:admin']);

    // Dapur
    $r->group('', ['filter' => 'role:kitchen'], static function ($r) {
        $r->get('po/create', 'PurchaseOrders::create');
        $r->post('po/store', 'PurchaseOrders::store');
        $r->post('po/(:num)/cancel', 'PurchaseOrders::cancel/$1');
    });

    // Dapur + Kasir
    $r->group('', ['filter' => 'role:cashier,kitchen'], static function ($r) {
        $r->get('po', 'PurchaseOrders::index');
        $r->get('po/(:num)', 'PurchaseOrders::show/$1');
        $r->get('stock', 'Stock::index');
        $r->get('reservations', 'Reservations::index');
        $r->get('reservations/upcoming', 'Reservations::upcoming');
        $r->post('reservations/(:num)/ack', 'Reservations::ack/$1');
    });

    // Backoffice (admin)
    $r->group('admin', ['filter' => 'role:admin', 'namespace' => 'App\Controllers\Admin'], static function ($r) {
        $r->get('/', 'Dashboard::index');
        $r->get('products', 'Products::index');
        $r->post('products/save', 'Products::save');
        $r->post('products/(:num)/toggle', 'Products::toggle/$1');
        $r->get('categories', 'Categories::index');
        $r->post('categories/save', 'Categories::save');
        $r->post('categories/(:num)/delete', 'Categories::delete/$1');
        $r->get('locations', 'Locations::index');
        $r->post('locations/save', 'Locations::save');
        $r->post('locations/(:num)/toggle', 'Locations::toggle/$1');
        $r->get('users', 'Users::index');
        $r->post('users/save', 'Users::save');
        $r->post('users/(:num)/toggle', 'Users::toggle/$1');
        $r->post('users/(:num)/force-logout', 'Users::forceLogout/$1');
        $r->get('payment-methods', 'PaymentMethods::index');
        $r->post('payment-methods/save', 'PaymentMethods::save');
        $r->post('payment-methods/(:num)/toggle', 'PaymentMethods::toggle/$1');
        $r->get('reports', 'Reports::index');
    });
});
