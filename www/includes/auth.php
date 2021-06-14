<?php

if (isset($_GET['logout'])) {
    $_SESSION = ['state' => 'logout'];
    header('Location: ' . ISLE\Settings::get('web_root') . '/');
    exit;
}

if ($_POST) {
    if (($_POST['csrfToken'] ?? null) != $_SESSION['csrfToken']) {
        throw new Exception('Possible CSRF attack, execution halted.');
    }
}

// Authenticate user
if (!isset($_SESSION['user'])) {
    $is_js = false;
    foreach (headers_list() as $header) {
        $is_js = $is_js || preg_match('/javascript/', $header);
        if ($is_js) {
            throw new Exception('Session expired. Please refresh the page.');
        }
    }
    if (!isset($_GET['login']) && (($_SESSION['state'] ?? null) != 'logout')) {
        $_SESSION['message']['type'] = 'warning';
        $_SESSION['message']['text'] = 'Session expired.';
    }
    try {
        $_SESSION['user'] = array_merge(
            ISLE\DataModels\User::DEFAULT,
            ISLE\Settings::get('hooks')['authentication']()
        );
        unset($_SESSION['state']);
    } catch (Exception $e) {
        $_SESSION['message']['type'] = 'danger';
        $_SESSION['message']['text'] = $e->getMessage();
        header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
        exit;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . ISLE\ViewUtility::buildQuery([], ['login'])); // Clear POST data
    exit;
}

// Authorize user
$user = ISLE\Service::executeStatement(
    'SELECT * FROM `' . ISLE\Settings::get('table_prefix') . 'users` WHERE `id` = ?',
    [['value' => $_SESSION['user']['id'], 'type' => \PDO::PARAM_INT]]
)->fetch();
if (!$user) {
    $user = ISLE\DataModels\User::DEFAULT;
}

// Admin overrides
if (isset($_GET['admin']) && $user['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {

    if (isset($_GET['admin']['uninstall'])) {
        ISLE\Service::uninstall();
        header('Location: ' . ISLE\Settings::get('web_root') . '/');
        exit;
    }

    if (isset($_GET['admin']['user']) && is_numeric($_GET['admin']['user'])) {
        $user = ISLE\Service::executeStatement(
            'SELECT * FROM ' . ISLE\Settings::get('table_prefix') . 'users WHERE `id` = ?',
            [['value' => $_GET['admin']['user'], 'type' => \PDO::PARAM_INT]]
        )->fetch();
        if (!$user) {
            $user = ISLE\DataModels\User::DEFAULT;
        }
    }

    if (isset($_GET['admin']['role']) && is_numeric($_GET['admin']['role'])) {
        $user['role'] = intval($_GET['admin']['role']);
    }
}

// Validate role
if ($user['role'] == ISLE\DataModels\Role::DOES_NOT_EXIST) {
    throw new Exception('User does not exist!');
}
if ($user['role'] == ISLE\DataModels\Role::DISABLED) {
    throw new Exception('User is disabled!');
}

// Initialize session variables
$_SESSION['user'] = $user;
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['returns'] = $_SESSION['returns'] ?? [];


