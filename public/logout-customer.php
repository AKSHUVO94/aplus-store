<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
Auth::logoutCustomer();
flash('success', 'Logged out.');
redirect('/');
