<?php
declare(strict_types=1);
$cfg = require __DIR__.'/config/config.php';
if ($cfg['app_debug']) { error_reporting(E_ALL); ini_set('display_errors','1'); }
else { error_reporting(0); ini_set('display_errors','0'); }
date_default_timezone_set($cfg['timezone']);
require_once __DIR__.'/helpers/Database.php';
require_once __DIR__.'/helpers/Auth.php';
require_once __DIR__.'/helpers/Theme.php';
require_once __DIR__.'/helpers/Cart.php';
require_once __DIR__.'/helpers/functions.php';
require_once __DIR__.'/helpers/ProductImage.php';
require_once __DIR__.'/helpers/Security.php';
require_once __DIR__.'/helpers/Customer.php';
require_once __DIR__.'/helpers/Mailer.php';
require_once __DIR__.'/helpers/ReportExport.php';
Auth::startSession();
Theme::load();
