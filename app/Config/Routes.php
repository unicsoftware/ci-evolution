<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Whatsapp::index');

$routes->group('whatsapp', function($routes) {
    $routes->get('/', 'Whatsapp::index');
    $routes->post('create', 'Whatsapp::create');
    $routes->get('status/(:num)', 'Whatsapp::status/$1');
    $routes->get('qrcode/(:num)', 'Whatsapp::qrcode/$1');
    $routes->post('restart/(:num)', 'Whatsapp::restart/$1');
    $routes->post('logout/(:num)', 'Whatsapp::disconnect/$1');
    $routes->post('profile/(:num)', 'Whatsapp::profile/$1');
    $routes->post('send/(:num)', 'Whatsapp::send/$1');
    $routes->delete('delete/(:num)', 'Whatsapp::delete/$1');
    $routes->get('export/(:num)', 'Whatsapp::export/$1');
});
