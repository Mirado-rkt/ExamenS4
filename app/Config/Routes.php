<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('login', 'Login::index');
$routes->post('login', 'Login::authenticate');
$routes->get('logout', 'Login::logout');

$routes->get('dashboard', 'Dashboard::index');
$routes->post('dashboard/deposit', 'Dashboard::deposit');
$routes->post('dashboard/withdraw', 'Dashboard::withdraw');
$routes->post('dashboard/transfer', 'Dashboard::transfer');

$routes->get('operateur', 'Operator::index');
$routes->post('operateur/prefix', 'Operator::addPrefix');
$routes->post('operateur/fee', 'Operator::updateFeeBand');
