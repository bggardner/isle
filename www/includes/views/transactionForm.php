<?php

if ($_SESSION['user']['role'] < ISLE\DataModels\Role::USER) {
    throw new Exception('You are not authorized to make transactions');
}

if (!isset($_SESSION['post']) && isset($_GET['id'])) {
    $row = ISLE\Service::executeStatement(
        '
  SELECT
    `transactions`.*,
    CONCAT(
      `manufacturers`.`name`,
      " ",
      `models`.`model`,
      " (S/N: ",
      `assets`.`serial`,
      ")"
    ) AS `asset_name`,
    CONCAT(`users`.`name`, " <", `users`.`email`, ">") AS `user_name`
  FROM
    `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
    ON `transactions`.`asset` = `assets`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers`
    ON `models`.`manufacturer` = `manufacturers`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
    ON `transactions`.`user` = `users`.`id`
  WHERE `transactions`.`id` = ?
        ',
        [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
    )->fetch();
    if (!$row) {
        throw new Exception('Invalid transaction ID ' . htmlspecialchars($_GET['id']));
    }
    if (
        $_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR
        && (
            $row['user'] != $_SESSION['user']['id']
            || $row['type'] != ISLE\DataModels\TransactionType::CHECK_OUT
        )
    ) {
        throw new Exception('You are not authorized to edit this transaction');
    }
    $row['location_name'] = ISLE\ViewUtility::treeStrings('locations', [$row['location']])[0];
} else {
    $row = [
        'asset' => $_SESSION['post']['asset'] ?? ($_GET['asset'] ?? 0),
        'id' => $_SESSION['post']['id'] ?? 0,
        'location' => $_SESSION['post']['location'] ?? 0,
        'location_name' => $_SESSION['post']['location_name'] ?? '',
        'notes' => $_SESSION['post']['notes'] ?? '',
        'returning' => $_SESSION['post']['returning'] ?? '',
        'type' => $_SESSION['post']['type'] ?? ($_GET['type'] ?? 0),
        'user' => $_SESSION['post']['user'] ?? $_SESSION['user']['id'],
        'user_name' => $_SESSION['post']['user_name'] ?? $_SESSION['user']['name'] . ' <' . $_SESSION['user']['email'] . '>'
    ];
}

if (
    in_array($row['type'], [
        ISLE\DataModels\TransactionType::RESTRICT,
        ISLE\DataModels\TransactionType::UNRESTRICT
    ])
    && $_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR
) {
    throw new Exception('You are not authorized to edit restrictions');
}

if (
    !$row['asset']
    && (
        $row['type'] == ISLE\DataModels\TransactionType::CHECK_OUT && count($_SESSION['cart']) == 0
        || $row['type'] == ISLE\DataModels\TransactionType::CHECK_IN && count($_SESSION['returns']) == 0
    )
) {
        throw new Exception('Cart is empty');
}

if (!$row['type']) {
    throw new Exception('Transactions cannot be added manually');
}
ob_start();
?>
<form action="<?= ISLE\Settings::get('web_root'); ?>/transactions" method="post" class="needs-validation">
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
  <input type="hidden" name="id" value="<?= $row['id']; ?>">
  <input type="hidden" name="type" value="<?= $row['type']; ?>">
<?php
if (!isset($_GET['type'])) {
    echo '
  <div class="form-floating">
    <div class="form-control">' . $row['ts'] . '</div>
    <label for="type">Timestamp</label>
  </div>
  <div class="form-floating">
    <div class="form-control" id="type">' . array_flip(ISLE\DataModels\TransactionType::getValues())[$row['type']] . '</div>
    <label for="type">Action</label>
  </div>
  <div class="form-floating">
    <div class="form-control" id="asset">' . $row['asset_name'] . '</div>
    <label for="asset">Asset</label>
  </div>';
}
if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {
    echo '
  <div class="form-floating">
    <input type="text" name="user_name" class="form-control" data-autocomplete="users" data-target="user" value="' . htmlspecialchars($row['user_name']) . '">
    <input type="hidden" name="user" id="user" value="' . $row['user'] . '">
    <label for="user">User</label>
  </div>';
}
?>
  <input type="hidden" name="asset" value="<?= $row['asset']; ?>">
  <input id="location" type="hidden" name="location" value="<?= $row['location']; ?>" required>
<?php
if ($row['type'] == ISLE\DataModels\TransactionType::CHECK_OUT) {
    echo '
  <div class="form-floating">
    <input id="location_name" type="text" name="location_name" class="form-control" data-autocomplete="locations" data-target="location" value="' . $row['location_name'] . '" required>
    <label for="location_name">Location</label>
    <div class="invalid-feedback">Required</div>
  </div>
  <div class="form-floating">
    <input type="date" name="returning" id="returning" class="form-control" value="' . $row['returning'] . '" required>
    <label for="returning">Estimated Return Date</label>
    <div class="invalid-feedback">Required</div>
  </div>';
}
?>
  <div class="form-floating">
    <input type="text" name="notes" id="notes" class="form-control" value="<?= $row['notes']; ?>"<?= $row['type'] == ISLE\DataModels\TransactionType::CHECK_OUT ? ' required' : ''; ?>>
    <label for="notes">Notes</label>
    <div class="invalid-feedback">Required</div>
  </div>
<?php
if (!$row['id'] && $row['type'] == ISLE\DataModels\TransactionType::CHECK_IN) {
    echo '
  <div class="form-check mt-3">
    <input type="checkbox" class="form-check-input" id="confirmReturned" required>
    <label class="form-check-label" for="confirmReturned">';
    if ($row['asset']) {
        $count = 1;
    } else {
        $count = count($_SESSION['returns']);
    }
    echo '
      The asset' . ($count > 1 ? 's have' : ' has'). '  been returned to ';
    if ($count > 1) {
        echo 'thier home locations';
    } else {
        $home_location = ISLE\Service::executeStatement(
            '
  SELECT `location`
  FROM `' . ISLE\Settings::get('table_prefix') . 'assets`
  WHERE `id` = ?
            ',
            [['value' => $row['asset'] ?: $_SESSION['returns'][0], 'type' => PDO::PARAM_INT]]
        )->fetchColumn();
        echo '<span class="fw-bold">';
        echo ISLE\ViewUtility::treeStrings('locations', [$home_location])[0];
        echo '</span>';
    }
    echo '
    </label>
  </div>';
}
if (isset($_SESSION['post']['error'])) {
    echo '
  <div class="is-invalid"></div>
  <div class="invalid-feedback">' . htmlspecialchars($_SESSION['post']['error']) . '</div>';
}
?>
</form>
<?php
ISLE\ViewUtility::modalWrapper(
    $row['id'] ? 'Edit Transaction' : ($row['type'] ? array_flip(ISLE\DataModels\TransactionType::getValues())[$row['type']] : 'Add Transaction'),
    ob_get_clean(),
    $row['id'] > 0
);
?>
