<?php

session_start();
$_SESSION['csrfToken'] = base64_encode(hash('sha256', session_id()));

spl_autoload_register(function($class) {
    $class = str_replace('ISLE\\', '', $class);
    @include implode(DIRECTORY_SEPARATOR, [__DIR__, 'classes', str_replace('\\', DIRECTORY_SEPARATOR, $class)]) . '.php';
});

require_once 'includes/error.php';

ISLE\Service::verifyInstall();

require_once 'includes/auth.php';
