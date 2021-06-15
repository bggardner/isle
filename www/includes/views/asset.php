<?php

if (!isset($_GET['id'])) {
    throw new Exception('Asset ID is required!');
}
$row = ISLE\Service::executeStatement(
    '
  SELECT
    `assets`.`id`,
    `assets`.`model`,
    `assets`.`location`,
    `assets`.`serial`,
    `manufacturers`.`name` AS `manufacturer`,
    `models`.`model` AS `model_name`,
    `models`.`series`,
    `models`.`description`,
    `models`.`url`,
    `models`.`image`
  FROM `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers`
    ON `models`.`manufacturer` = `manufacturers`.`id`
  WHERE `assets`.`id` = ?
  GROUP BY `assets`.`id`
    ',
    [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
)->fetch();
if (!$row) {
    throw new Exception('Asset ID ' . htmlspecialchars($_GET['id']) . ' does not exist!');
}
$row['location'] = ISLE\ViewUtility::treeStrings('locations', [$row['location']])[0];
$row['categories'] = ISLE\ViewUtility::categoryTreeStrings($row['model']);
$row['model_attachments'] = ISLE\ViewUtility::attachments('model', $row['model']);
$row['model_attributes'] = ISLE\ViewUtility::attributes('model', $row['model']);
$row['asset_attachments'] = ISLE\ViewUtility::attachments('asset', $row['id']);
$row['asset_attributes'] = ISLE\ViewUtility::attributes('asset', $row['id']);
?>
<div class="d-flex">
  <div>
<?php
if ($row['image']) {
    echo '
    <img class="me-3" src="' . ISLE\Settings::get('web_root') . '/uploads/images/' . $row['image'] . '.jpg">';
}
?>
  </div>
  <div class="flex-grow-1">
    <h5 class="border-bottom border-2 text-secondary">
      Model Information
<?php
if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
    echo '
        <button type="button" class="btn" data-node="model" data-id="' . $row['model'] . '" data-role="edit">
          <i class="bi-pencil-square text-secondary"></i>
        </button>';
}
?>
    </h5>
    <dl class="row">
      <dt class="col-xl-2">Manufacturer</dt>
      <dd class="col-xl-10"><?= $row['manufacturer']; ?></dd>
      <dt class="col-xl-2">Model</dt>
      <dd class="col-xl-10"><?= $row['model_name']; ?></dd>
      <dt class="col-xl-2">Series</dt>
      <dd class="col-xl-10"><?= $row['series']; ?></dd>
      <dt class="col-xl-2">Description</dt>
      <dd class="col-xl-10"><?= $row['description']; ?></dd>
<?php
if ($row['url']) {
    echo '
      <dt class="col-xl-2">URL</dt>
      <dd class="col-xl-10"><a href="' . $row['url'] . '" target="_blank">' . $row['url'] . '<i class="bi-box-arrow-up-right ms-2"></i></a></dd>';
}
if ($row['categories']) {
    echo '
      <dt class="col-xl-2">Categories</dt>
      <dd class="col-xl-10">';
    if (count($row['categories']) > 1) {
        echo '
        <ul class="mb-0 list-unstyled">';
        foreach ($row['categories'] as $category) {
            echo '
          <li>' . $category . '</li>';
        }
        echo '
        </ul>';
    } else {
        echo $row['categories'][0];
    }
    echo '</dd>';
}
if ($row['model_attributes']) {
    echo '
      <dt class="col-xl-2">Attributes</dt>
      <dd class="col-xl-10">
        <dl class="row mb-0 pt-1 bg-light rounded-pill">';
    foreach ($row['model_attributes'] as $attribute) {
        echo '
          <dt class="col-auto">' . $attribute['name'] . '</dt>
          <dd class="col-auto">' . $attribute['value'] . '</dd>';
    }
    echo '
        </dl>
      </dd>';
}
if ($row['model_attachments']) {
    echo '
      <dt class="col-xl-2">Attachments</dt>
      <dd class="col-xl-10">';
    ISLE\ViewUtility::listAttachments($row['model_attachments']);
}
?>
    </dl>
    <h5 class="border-bottom border-2 text-secondary">
      Asset Information
<?php
if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
    echo '
        <button type="button" class="btn" data-node="asset" data-id="' . $row['id'] . '" data-role="edit">
          <i class="bi-pencil-square text-secondary"></i>
        </button>';
}
?>
    </h5>
    <dl class="row">
      <dt class="col-xl-2">Serial</dt>
      <dd class="col-xl-10"><?= $row['serial']; ?></dd>
      <dt class="col-xl-2">Home Location</dt>
      <dd class="col-xl-10"><?= $row['location']; ?></dd>
      <dt class="col-xl-2">Status</dt>
      <dd class="col-xl-10" data-id="<?= $row['id']; ?>">
<?php
$last_transaction = ISLE\Service::executeStatement(
    '
  SELECT
    `transactions`.`type`,
    `users`.*
  FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
    ON `users`.`id` = `transactions`.`user`
  WHERE `asset` = ?
  ORDER BY `ts` DESC
  LIMIT 1
    ',
        [['value' => $row['id'], 'type' => PDO::PARAM_INT]]
)->fetch();
if (
    !$last_transaction
    || in_array(
        $last_transaction['type'],
        [ISLE\DataModels\TransactionType::CHECK_IN, ISLE\DataModels\TransactionType::UNRESTRICT]
    )
) {
    echo 'Idle';
    if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::USER) {
        echo '
        <button class="btn btn-sm btn-primary ms-2" data-transact="' . ISLE\DataModels\TransactionType::CHECK_OUT . '">
          <i class="bi-arrow-up-circle me-1"></i>Check-out
        </button>';
    }
    if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {
        echo '
        <button class="btn btn-sm btn-danger ms-2" data-transact="' . ISLE\DataModels\TransactionType::RESTRICT . '">
          <i class="bi-lock me-1"></i>Restrict
        </button>';
    }
} elseif ($last_transaction['type'] == ISLE\DataModels\TransactionType::CHECK_OUT) {
    if ($_SESSION['user']['id'] != $last_transaction['id']) {
        echo 'Out to <a href="mailto:' . $last_transaction['email'] . '" target="_blank">' . $last_transaction['name'] . '</a>';
    } else {
        echo 'Checked-out';
    }
    if (
        (
            $_SESSION['user']['id'] == $last_transaction['id']
            && $_SESSION['user']['role'] >= ISLE\DataModels\Role::USER
        )
        || $_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR
    ) {
        echo '
        <button class="btn btn-sm btn-warning ms-2" data-transact="' . ISLE\DataModels\TransactionType::CHECK_IN . '">
          <i class="bi-arrow-down-circle me-1"></i>Check-in
        </button>';
    }
} else { // ISLE\DataModelsTransactionType::RESTRICT
    echo '<span class="text-danger">Restricted</span>';
    if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {
        echo '
        <button class="btn btn-sm btn-danger ms-2" data-transact="' . ISLE\DataModels\TransactionType::UNRESTRICT . '">
          <i class="bi-unlock me-1"></i>Unrestrict
        </button>';
    }
}
?>
      </dd>
<?php
if ($row['asset_attributes']) {
    echo '
      <dt class="col-xl-2">Attributes</dt>
      <dd class="col-xl-10">
        <dl class="row mb-0 pt-2 bg-light rounded">';
    foreach ($row['asset_attributes'] as $attribute) {
        echo '
          <dt class="col-auto">' . $attribute['name'] . '</dt>
          <dd class="col">' . $attribute['value'] . '</dd>
          <dd class="w-100 mb-0"></dd>';
    }
    echo '
        </dl>
      </dd>';
}
if ($row['asset_attachments']) {
    echo '
      <dt class="col-xl-2">Attachments</dt>
      <dd class="col-xl-10">';
    ISLE\ViewUtility::listAttachments($row['asset_attachments']);
}
?>
    </dl>
  </div>
</div>
<h5 class="border-bottom border-2 text-secondary">Transactions</h5>
<?php
$transaction_count = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
  WHERE `asset` = ?
    ',
        [['value' => $row['id'], 'type' => PDO::PARAM_INT]]
)->fetchColumn();
if ($transaction_count) {
  $sort_fields = [
    'ts' => ['direction' => 'DESC', 'type' => 'numeric'],
    'type' => ['direction' => 'ASC', 'type' => 'alpha'],
    'user_name' => ['direction' => 'ASC', 'type' => 'alpha'],
    'returning' => ['direction' => 'ASC', 'type' => 'numeric'],
    'notes' => ['direction' => 'ASC', 'type' => 'alpha']
  ];
    echo '
<div class="d-flex">';
    ISLE\ViewUtility::pager($transaction_count);
    echo '
</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th scope="col">Timestamp' . ISLE\ViewUtility::sortLink('ts', $sort_fields) . '</td>
        <th scope="col">Action' . ISLE\ViewUtility::sortLink('type', $sort_fields) . '</td>
        <th scope="col">User' . ISLE\ViewUtility::sortLink('user_name', $sort_fields) . '</td>
        <th scope="col">Returning' . ISLE\ViewUtility::sortLink('returning', $sort_fields) . '</td>
        <th scope="col">Notes' . ISLE\ViewUtility::sortLink('notes', $sort_fields) . '</td>
      </tr>
    </thead>
    <tbody>';
    $stmt = ISLE\Service::executeStatement(
        '
  SELECT
    `transactions`.`ts`,
    `transaction_types`.`name` AS `type`,
    `users`.`name` AS `user_name`,
    `transactions`.`returning`,
    `transactions`.`notes`
  FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'transaction_types` AS `transaction_types`
    ON `transactions`.`type` = `transaction_types`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
    ON `transactions`.`user` = `users`.`id`
  WHERE `asset` = ?
  ' . ISLE\ViewUtility::sortClause($sort_fields) . '
  ' . ISLE\ViewUtility::limitClause() . '
        ',
        [['value' => $row['id'], 'type' => PDO::PARAM_INT]]
    );
    while ($transaction = $stmt->fetch()) {
        echo '
      <tr>
        <td>' . $transaction['ts'] . '</td>
        <td>' . ucfirst(strtolower(str_replace('_', '-', $transaction['type']))) . '</td>
        <td>' . $transaction['user_name'] . '</td>
        <td>' . $transaction['returning'] . '</td>
        <td>' . $transaction['notes'] . '</td>
      </tr>';
    }
    echo '
    </tbody>
  </table>
</div>';
} else {
    echo '<div class="fst-italic">No transactions found</div>';
}
?>
