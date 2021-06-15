<!doctype html>
<html lang="en" class="h-100">
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= ISLE\Settings::get('web_root'); ?>favicon.svg" type="image/svg+xml" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-wEmeIV1mKuiNpC+IOBjI7aAzPcEZeedi5yW5f2yOq55WWLwNGmvvx4Um1vskeMj0" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" integrity="sha256-PDJQdTN7dolQWDASIoBVrjkuOEaI137FI15sqI3Oxu8=" crossorigin="anonymous">
    <link href="<?= ISLE\Settings::get('web_root'); ?>/styles/main.css" rel="stylesheet">
    <title><?= ISLE\Settings::get('title'); ?></title>
  </head>
<?php
flush(); /* Allows the browser to start getting content while the server is still loading the rest of the page. http://developer.yahoo.com/performance/rules.html#page-nav */
?>
  <body class="d-flex flex-column h-100">
    <header>
      <nav class="navbar navbar-expand-lg navbar-dark fixed-top bg-dark">
        <div class="container-fluid">
          <a class="navbar-brand d-flex" href="<?= ISLE\Settings::get('web_root'); ?>/">
            <h3 class="mb-0">
<?php
$logo = ISLE\Settings::get('logo');
if ($logo) {
    echo '
              <img class="me-2" src="' . $logo . '">';
}
?>
              <span class="align-middle"><?= ISLE\Settings::get('title'); ?></span>
            </h3>
          </a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse justify-content-center" id="mainNavbar">
            <ul class="navbar-nav mb-2 mb-lg-0">
<?php
$nav_items = [
    ['name' => 'Assets', 'path' => 'assets', 'role' => ISLE\DataModels\Role::VIEWER],
    ['name' => 'Models', 'path' => 'models', 'role' => ISLE\DataModels\Role::VIEWER],
    ['name' => 'Manufacturers', 'path' => 'manufacturers', 'role' => ISLE\DataModels\Role::VIEWER],
    ['name' => 'Locations', 'path' => 'locations', 'role' => ISLE\DataModels\Role::VIEWER],
    ['name' => 'Categories', 'path' => 'categories', 'role' => ISLE\DataModels\Role::VIEWER],
    ['name' => 'Attributes', 'path' => 'attributes', 'role' => ISLE\DataModels\Role::VIEWER],
    ['name' => 'Transactions', 'path' => 'transactions' , 'role' => ISLE\DataModels\Role::USER],
    ['name' => 'Users', 'path' => 'users', 'role' => ISLE\DataModels\Role::ADMINISTRATOR],
];
foreach ($nav_items as $nav_item) {
    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] >= $nav_item['role']) {
        echo '
              <li class="nav-item">
                <a class="nav-link' . ($nav_item['path'] == basename($_SERVER['PHP_SELF'], '.php') ? ' active' : '') . '" href="' . ISLE\Settings::get('web_root') . '/' . $nav_item['path'] . '">
                ' . $nav_item['name'] . '
                </a>
              </li>';
    }
}
ISLE\Settings::get('hooks')['menu']();
?>
           </ul>
          </div>
<?php
if (isset($_SESSION['user']) && $_SESSION['user']['role'] >= ISLE\DataModels\Role::VIEWER) {
    echo '
          <div class="navbar-nav dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="' . $_SESSION['user']['name'] . '">
              <i class="bi-person-circle"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="userDropDown">
              <li><a class="dropdown-item" href="?logout=1">Sign Out</a></li>
              <li><a class="dropdown-item" href="' . ISLE\Settings::get('web_root') . '/assets?user=' . $_SESSION['user']['id'] . '">My Assets</a></li>
              <li><a class="dropdown-item" href="#" data-form="userForm&id=' . $_SESSION['user']['id'] . '">Edit Profile</a></li>';

    if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {
        echo '
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="#" data-sql="import">Import Data</a></li>
              <li><a class="dropdown-item" href="#" data-sql="export">Export Data</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="?admin[uninstall]=1">Uninstall</a></li>';
    }

    echo '
            </ul>
          </div>
          <form class="d-flex" method="get">
            <input type="text" name="q" class="form-control" placeholder="Search" aria-label="Search">
          </form>';
}
if (isset($_SESSION['user']) && $_SESSION['user']['role'] >= ISLE\DataModels\Role::USER) {
    echo '
          <button type="button" class="btn btn-secondary ms-2" ' . (count($_SESSION['cart']) ? '' : ' disabled') . ' data-cart="cart">
            <i class="bi-cart-fill"></i>
            <span class="badge bg-primary ms-2">' . count($_SESSION["cart"]) . '</span>
          </button>';
}
?>
        </nav>
      </header>
      <main class="container-fluid py-3">
<?php
if (isset($_SESSION['message'])) {
    ISLE\ViewUtility::alert($_SESSION['message']);
    unset($_SESSION['message']);
}
?>
