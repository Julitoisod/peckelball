<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->setDefaultNamespace('App\Controllers');
$routes->setAutoRoute(false);

// ------------------------------------------------------------------ public
$routes->get('/', 'Home::index');

$routes->get('courts', 'Courts::index');
$routes->get('courts/(:segment)', 'Courts::show/$1');

$routes->get('availability', 'AvailabilityPage::index');

$routes->get('open-play', 'OpenPlayPage::index');
$routes->post('open-play/(:num)/join', 'OpenPlayPage::join/$1');

$routes->get('booking', 'Booking::index');
$routes->post('booking', 'Booking::store');
$routes->get('booking/confirmed/(:segment)', 'Booking::confirmed/$1');

$routes->get('membership', 'Membership::index');
$routes->post('membership/join', 'Membership::join');

$routes->get('coaches', 'Coaches::index');
$routes->get('coaches/(:num)', 'Coaches::show/$1');
$routes->get('coaching', 'Coaches::sessions');

$routes->get('tournaments', 'Tournaments::index');
$routes->get('tournaments/(:segment)', 'Tournaments::show/$1');
$routes->post('tournaments/(:segment)/register', 'Tournaments::register/$1');

$routes->get('rankings', 'Rankings::index');
$routes->get('rentals', 'Rentals::index');
$routes->get('shop', 'Shop::index');
$routes->get('shop/(:segment)', 'Shop::show/$1');
$routes->get('about', 'Pages::about');
$routes->get('contact', 'Pages::contact');
$routes->post('contact', 'Pages::submitContact');
$routes->get('offline', 'Pages::offline');

// -------------------------------------------------------------------- auth
$routes->group('', ['filter' => 'guest'], static function ($routes): void {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attemptLogin');
    $routes->get('register', 'Auth::register');
    $routes->post('register', 'Auth::attemptRegister');
});
$routes->get('logout', 'Auth::logout');

// ----------------------------------------------------------------- account
$routes->group('account', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', 'Account::index');
    $routes->get('bookings', 'Account::bookings');
    $routes->post('bookings/(:num)/cancel', 'Account::cancelBooking/$1');
});

// --------------------------------------------------------------------- api
// Read-only board data for the live poller. GET-only; excluded from CSRF in
// Config\Filters because nothing here mutates state.
$routes->get('api/availability', 'Api::availability');
$routes->get('api/slots', 'Api::slots');

// ------------------------------------------------------------------- admin
$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth:staff'], static function ($routes): void {
    $routes->get('/', 'Dashboard::index', ['filter' => 'auth:dashboard.view']);
    $routes->get('audit', 'Dashboard::audit', ['filter' => 'auth:audit.view']);
    $routes->get('bookings', 'Bookings::index', ['filter' => 'auth:bookings.view']);
    $routes->post('bookings/(:num)/cancel', 'Bookings::cancel/$1', ['filter' => 'auth:bookings.manage']);
    $routes->post('bookings/(:num)/checkin', 'Bookings::checkIn/$1', ['filter' => 'auth:checkin.scan']);
    $routes->get('courts', 'Courts::index', ['filter' => 'auth:courts.view']);
    $routes->post('courts/(:num)/update', 'Courts::update/$1', ['filter' => 'auth:courts.manage']);

    $routes->get('sessions', 'Sessions::index', ['filter' => 'auth:sessions.own,coaches.manage']);

    $routes->get('settings', 'Settings::index', ['filter' => 'auth:settings.manage']);
    $routes->post('settings', 'Settings::save', ['filter' => 'auth:settings.manage']);

    $routes->get('announcements', 'Announcements::index', ['filter' => 'auth:announcements.manage']);
    $routes->post('announcements/save', 'Announcements::save', ['filter' => 'auth:announcements.manage']);
    $routes->post('announcements/(:num)/delete', 'Announcements::delete/$1', ['filter' => 'auth:announcements.manage']);

    $routes->get('promos', 'Promos::index', ['filter' => 'auth:promos.manage']);
    $routes->post('promos/save', 'Promos::save', ['filter' => 'auth:promos.manage']);
    $routes->post('promos/(:num)/toggle', 'Promos::toggle/$1', ['filter' => 'auth:promos.manage']);

    $routes->get('equipment', 'Equipment::index', ['filter' => 'auth:equipment.manage']);
    $routes->post('equipment/save', 'Equipment::save', ['filter' => 'auth:equipment.manage']);
    $routes->post('equipment/(:num)/toggle', 'Equipment::toggle/$1', ['filter' => 'auth:equipment.manage']);

    $routes->get('open-play', 'OpenPlay::index', ['filter' => 'auth:openplay.view']);
    $routes->post('open-play/open', 'OpenPlay::open', ['filter' => 'auth:openplay.manage']);
    $routes->post('open-play/(:num)/close', 'OpenPlay::close/$1', ['filter' => 'auth:openplay.manage']);
    $routes->post('open-play/(:num)/reopen', 'OpenPlay::reopen/$1', ['filter' => 'auth:openplay.manage']);
    $routes->post('open-play/(:num)/register', 'OpenPlay::register/$1', ['filter' => 'auth:openplay.manage']);
    $routes->post('open-play/participants/(:num)/paid', 'OpenPlay::paid/$1', ['filter' => 'auth:openplay.manage']);
    $routes->post('open-play/participants/(:num)/remove', 'OpenPlay::remove/$1', ['filter' => 'auth:openplay.manage']);

    $routes->get('walkins', 'Walkins::index', ['filter' => 'auth:bookings.walkin']);
    $routes->post('walkins/create', 'Walkins::create', ['filter' => 'auth:bookings.walkin']);

    $routes->get('checkin', 'Checkin::index', ['filter' => 'auth:checkin.scan']);
    $routes->post('checkin/scan', 'Checkin::scan', ['filter' => 'auth:checkin.scan']);
    $routes->post('checkin/(:num)/admit', 'Checkin::byId/$1', ['filter' => 'auth:checkin.scan']);

    $routes->get('payments', 'Payments::index', ['filter' => 'auth:payments.view']);
    $routes->post('payments/record', 'Payments::record', ['filter' => 'auth:payments.manage']);
    $routes->post('payments/(:num)/refund', 'Payments::refund/$1', ['filter' => 'auth:payments.manage']);

    $routes->get('customers', 'Customers::index', ['filter' => 'auth:customers.manage']);
    $routes->post('customers/(:num)/update', 'Customers::update/$1', ['filter' => 'auth:customers.manage']);

    $routes->get('memberships', 'Memberships::index', ['filter' => 'auth:memberships.manage']);
    $routes->post('memberships/(:num)/activate', 'Memberships::activate/$1', ['filter' => 'auth:memberships.manage']);
    $routes->post('memberships/(:num)/cancel', 'Memberships::cancel/$1', ['filter' => 'auth:memberships.manage']);
    $routes->post('memberships/(:num)/credits', 'Memberships::credits/$1', ['filter' => 'auth:memberships.manage']);

    $routes->get('coaches', 'Coaches::index', ['filter' => 'auth:coaches.manage']);
    $routes->post('coaches/save', 'Coaches::save', ['filter' => 'auth:coaches.manage']);
    $routes->post('coaches/(:num)/toggle', 'Coaches::toggle/$1', ['filter' => 'auth:coaches.manage']);
    $routes->post('coaches/(:num)/slots', 'Coaches::addSlot/$1', ['filter' => 'auth:coaches.manage']);
    $routes->post('coaches/(:num)/slots/(:num)/drop', 'Coaches::dropSlot/$1/$2', ['filter' => 'auth:coaches.manage']);

    $routes->get('staff', 'Staff::index', ['filter' => 'auth:staff.manage']);
    $routes->post('staff/create', 'Staff::create', ['filter' => 'auth:staff.manage']);
    $routes->post('staff/(:num)/update', 'Staff::update/$1', ['filter' => 'auth:staff.manage']);
    $routes->post('staff/(:num)/password', 'Staff::resetPassword/$1', ['filter' => 'auth:staff.manage']);

    $routes->get('tournaments', 'Tournaments::index', ['filter' => 'auth:tournaments.manage']);
    $routes->post('tournaments/save', 'Tournaments::save', ['filter' => 'auth:tournaments.manage']);
    $routes->post('tournaments/(:num)/teams/(:num)', 'Tournaments::team/$1/$2', ['filter' => 'auth:tournaments.manage']);
    $routes->post('tournaments/(:num)/teams/(:num)/pay', 'Tournaments::pay/$1/$2', ['filter' => 'auth:tournaments.manage']);
    $routes->post('tournaments/(:num)/draw', 'Tournaments::draw/$1', ['filter' => 'auth:tournaments.manage']);
    $routes->post('tournaments/(:num)/draw/clear', 'Tournaments::clearDraw/$1', ['filter' => 'auth:tournaments.manage']);

    $routes->get('scoring', 'Scoring::index', ['filter' => 'auth:tournaments.score']);
    $routes->post('scoring/(:num)/score', 'Scoring::score/$1', ['filter' => 'auth:tournaments.score']);
    $routes->post('scoring/(:num)/live', 'Scoring::live/$1', ['filter' => 'auth:tournaments.score']);
    $routes->post('scoring/(:num)/reopen', 'Scoring::reopen/$1', ['filter' => 'auth:tournaments.score']);

    $routes->get('inventory', 'Inventory::index', ['filter' => 'auth:products.manage']);
    $routes->post('inventory/move', 'Inventory::move', ['filter' => 'auth:products.manage']);

    $routes->get('reports', 'Reports::index', ['filter' => 'auth:reports.analytics,reports.sales,reports.bookings,reports.customers']);

    $routes->get('roles', 'Roles::index', ['filter' => 'auth:roles.manage']);
    $routes->post('roles/(:num)/save', 'Roles::save/$1', ['filter' => 'auth:roles.manage']);

    $routes->get('maintenance', 'Maintenance::index', ['filter' => 'auth:maintenance.manage']);
    $routes->post('maintenance/save', 'Maintenance::save', ['filter' => 'auth:maintenance.manage']);
    $routes->post('maintenance/(:num)/status', 'Maintenance::status/$1', ['filter' => 'auth:maintenance.manage']);
});
