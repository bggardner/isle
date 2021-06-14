<div class="d-flex">
  <aside class="sidebar pe-3">
<?php
if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
    echo '
    <a href="#" class="btn btn-primary" data-form="assetForm">
      <i class="bi-plus-lg me-2"></i> Add Asset
    </a>';
} else {
    echo '<div class="mt-5"></div>';
}
?>
    <ul class="btn-toggle-nav list-unstyled my-2">
      <li>
        <button class="btn btn-toggle align-items-center rounded" data-bs-toggle="collapse" data-bs-target="#roles" aria-expanded="<?= isset($_GET['state']) ? 'true' : 'false'; ?>"></button>
        <a href="?<?= ISLE\ViewUtility::buildQuery([], ['state', 'start']); ?>" class="link-dark fw-bold">State</a>
        <div class="roles ms-3 collapse<?= isset($_GET['state']) ? ' show' : ''; ?>" id="roles">
          <ul class="btn-toggle-nav list-unstyled">
<?php
$states = [
    'Available',
    'Out',
    'Restricted'
];
foreach ($states as $state) {
    $bold = (($_GET['state'] ?? null) == $state) ? ' fw-bold' : '';
    echo '
            <li>
              <button class="btn btn-toggle invisible"></button>
              <a href="?' . ISLE\ViewUtility::buildQuery(['state' => $state], ['start']) . '" class="link-dark' . $bold . '">
                ' . $state . '
              </a>
            </li>';
}
?>
          </ul>
        </div>
      </li>
    </ul>
<?php
ISLE\ViewUtility::collapsibleTree('categories');
ISLE\ViewUtility::collapsibleTree('locations');
?>
  </aside>
  <div class="d-table flex-grow-1">
    <div class="d-flex">
<?php
$filter = ['clause' => 'WHERE 1', 'params' => []];
$query_params = ISLE\ViewUtility::queryParams();
if (isset($query_params['state'])) {
    switch ($query_params['state']) {
        case 'Out':
            $types = ISLE\DataModels\TransactionType::CHECK_OUT;
            break;
        case 'Restricted':
            $types = ISLE\DataModels\TransactionType::RESTRICT;
            break;
        default:
            $types = implode(',', [
                'NULL',
                ISLE\DataModels\TransactionType::CHECK_IN,
                ISLE\DataModels\TransactionType::UNRESTRICT
            ]);
    }
    $filter['clause'] .= ' AND `transactions`.`type` IN (' . $types . ')';
}
if (isset($query_params['categories'])) {
    $filter['clause'] .= ' AND `models`.`id` IN (
  SELECT `model`
  FROM `' . ISLE\Settings::get('table_prefix') . 'model_categories`
  WHERE `category` IN (
    SELECT `categories`.`id`
    FROM
      `' . ISLE\Settings::get('table_prefix') . 'categories` AS `categories`,
      `' . ISLE\Settings::get('table_prefix') . 'categories` AS `ancestors`
    WHERE
      `categories`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
      AND `ancestors`.`id` = ?
  )
)';
    $filter['params'][] = ['value' => $query_params['categories'], 'type' => \PDO::PARAM_INT];
}
if (isset($query_params['locations'])) {
    $filter['clause'] .= ' AND `locations`.`id` IN (
  SELECT `locations`.`id`
  FROM
    `' . ISLE\Settings::get('table_prefix') . 'locations` AS `locations`,
    `' . ISLE\Settings::get('table_prefix') . 'locations` AS `ancestors`
  WHERE
    `locations`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
    AND `ancestors`.`id` = ?
)';
    $filter['params'][] = ['value'=> $query_params['locations'], 'type' => \PDO::PARAM_INT];
}
if (isset($query_params['q'])) {
    $filter['clause'] .= '
  AND (
    `manufacturers`.`name` LIKE ?
    OR `manufacturers`.`name` LIKE ?
    OR `models`.`model` LIKE ?
    OR `models`.`model` LIKE ?
    OR `models`.`description` LIKE ?
    OR `models`.`description` LIKE ?
    OR `assets`.`serial` LIKE ?
    OR `assets`.`serial` LIKE ?
    OR `assets`.`id` IN (
      SELECT DISTINCT `asset`
      FROM `' . ISLE\Settings::get('table_prefix') . 'asset_attributes`
      WHERE `value` LIKE ?
    )
    OR `models`.`id` IN (
      SELECT DISTINCT `model`
      FROM `' . ISLE\Settings::get('table_prefix') . 'model_attributes`
      WHERE `value` LIKE ?
    )
  )
    ';
    $filter['params'][] = ['value' =>  $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' =>  $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' =>  $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' =>  $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'], 'type' => \PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'], 'type' => \PDO::PARAM_STR];
}
if (isset($query_params['user'])) {
    if ($query_params['user'] != $_SESSION['user']['id'] && $_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR) {
        throw Exception('User is unauthorized to filter by other users.');
    }
    $filter['clause'] .= ' AND `users`.`id` = ? AND `transactions`.`type` = 1';
    $filter['params'][] = ['value' => $query_params['user'], 'type' => \PDO::PARAM_INT];
}
$sort_fields = [
    'manufacturer' => ['direction' => 'ASC', 'type' => 'alpha'],
    'model' => ['direction' => 'ASC', 'type' => 'alpha'],
    'series' => ['direction' => 'ASC', 'type' => 'alpha'],
    'description' => ['direction' => 'ASC', 'type' => 'alpha'],
    'serial' => ['direction' => 'ASC', 'type' => 'alpha']
];
$count = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  FROM `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers`
    ON `models`.`manufacturer` = `manufacturers`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'locations` AS `locations`
    ON `assets`.`location` = `locations`.`id`
  LEFT JOIN (SELECT `asset`, MAX(`ts`) AS `ts` FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` GROUP BY `asset`) AS `last_transactions`
    ON `assets`.`id` = `last_transactions`.`asset`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
    ON `transactions`.`asset` = `last_transactions`.`asset`
    AND `transactions`.`ts` = `last_transactions`.`ts`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
    ON `transactions`.`user` = `users`.`id`
  ' . $filter['clause'] . '
    ',
    $filter['params']
)->fetchColumn();
ISLE\ViewUtility::resultsHeader($count);
$checked_out = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
  LEFT JOIN (SELECT `asset`, MAX(`ts`) AS `ts` FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` GROUP BY `asset`) AS `last_transactions`
    ON `transactions`.`asset` = `last_transactions`.`asset`
    AND `transactions`.`ts` = `last_transactions`.`ts`
WHERE
  `transactions`.`user` = ?
  AND `transactions`.`type` = ?
    ',
    [
        ['value' => $_SESSION['user']['id'], 'type' => PDO::PARAM_INT],
        ['value' => ISLE\DataModels\TransactionType::CHECK_OUT, 'type' => PDO::PARAM_INT]
    ]
)->fetchColumn();
if ($checked_out && $_SESSION['user']['role'] >= ISLE\DataModels\Role::USER) {
    echo '
      <div class="flex-grow-1 text-end">
        <button class="btn btn-secondary ms-2" title="Returns"' . (count($_SESSION['returns']) ? '' : ' disabled') . ' data-cart="returns">
          <i class="bi-cart-fill"></i>
          <span class="badge bg-warning ms-2">' . count($_SESSION['returns']) . '</span>
        </button>
      </div>';
}
?>
    </div>
    <form id="filters" class="row bg-light mt-3 mb-1 py-3" method="get" action="?<?= ISLE\ViewUtility::buildQuery([]); ?>">
      <div class="col-auto">
        <label for="manufacturers[]" class="fw-bold">Manufacturer</label>
        <select class="form-select" name="manufacturers[]" multiple>
<?php
$stmt = ISLE\Service::executeStatement('SELECT * FROM `' . ISLE\Settings::get('table_prefix') . 'manufacturers`');
$any_selected = false;
while ($manufacturer = $stmt->fetch()) {
    $selected = in_array($manufacturer['id'], $_GET['manufacturers'] ?? []) ? ' selected' : '';
    $any_selected = $any_selected || $selected;
    echo '
          <option value="' . $manufacturer['id'] . '"' . $selected . '>' . $manufacturer['name'] . '</option>';
}
?>
        </select>
        <a href="#" data-role="clear" class="link-secondary<?= $any_selected ? '' : ' d-none'; ?>">Clear</a>
      </div>
      <div class="col-auto">
        <label for="models[]" class="fw-bold">Model</label>
        <select class="form-select" name="models[]" multiple>
<?php
$stmt = ISLE\Service::executeStatement('SELECT DISTINCT `model` FROM `' . ISLE\Settings::get('table_prefix') . 'models`');
$any_selected = false;
while ($model = $stmt->fetchColumn()) {
    $selected = in_array($model, $_GET['models'] ?? []) ? ' selected' : '';
    $any_selected = $any_selected || $selected;
    echo '
          <option' . $selected . '>' . $model . '</option>';
}
?>
        </select>
        <a href="#" data-role="clear" class="link-secondary<?= $any_selected ? '' : ' d-none'; ?>">Clear</a>
      </div>
      <div class="col-auto">
        <label for="series[]" class="fw-bold">Series</label>
        <select class="form-select" name="series[]" multiple>
<?php
$stmt = ISLE\Service::executeStatement('SELECT DISTINCT `series` FROM `' . ISLE\Settings::get('table_prefix') . 'models`');
$any_selected = false;
while ($series = $stmt->fetchColumn()) {
    $selected = in_array($series, $_GET['series'] ?? []) ? ' selected' : '';
    $any_selected = $any_selected || $selected;
    echo '
          <option' . $selected . '>' . $series . '</option>';
}
?>
        </select>
        <a href="#" data-role="clear" class="link-secondary<?= $any_selected ? '' : ' d-none'; ?>">Clear</a>
      </div>
<?php
ISLE\ViewUtility::attributeSelects(array_keys($_GET['attributes'] ?? []));
?>
      <div class="col-auto d-flex align-items-start flex-column">
        <input type="text" class="form-control" data-autocomplete="attributes" data-target="attribute" placeholder="Add Attribute...">
        <input type="hidden" id="attribute">
        <button type="submit" class="btn btn-primary mt-auto"><i class="bi-funnel me-2"></i>Apply</button>
      </div>
    </form>
<?php
if ($count) {
    echo '
    <div class="table-responsive">
      <table data-node="asset" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th scope="col"></th>
            <th scope="col"></th>
            <th scope="col">Manufacturer' . ISLE\ViewUtility::sortLink('manufacturer', $sort_fields) . '</th>
            <th scope="col">Model' . ISLE\ViewUtility::sortLink('model', $sort_fields) . '</th>
            <th scope="col">Series' . ISLE\ViewUtility::sortLink('series', $sort_fields) . '</th>
            <th scope="col">Description' . ISLE\ViewUtility::sortLink('description', $sort_fields) . '</th>
            <th scope="col">Serial' . ISLE\ViewUtility::sortLink('serial', $sort_fields) . '</th>
            <th scope="col"></th>
            <th scope="col"></th>
          </tr>
        </thead>
        <tbody>';
    $stmt = ISLE\Service::executeStatement(
        '
  SELECT
    `assets`.`id`,
    `assets`.`serial`,
    `manufacturers`.`name` AS `manufacturer`,
    `models`.`model`,
    `models`.`series`,
    `models`.`description`,
    `models`.`image`,
    `locations`.`name` AS `location`,
    `transactions`.`type` AS `transaction_type`,
    `transactions`.`returning`,
    `users`.`id` AS `user_id`,
    `users`.`name` AS `user_name`,
    `users`.`email` AS `user_email`
  FROM `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers`
    ON `models`.`manufacturer` = `manufacturers`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'locations` AS `locations`
    ON `assets`.`location` = `locations`.`id`
  LEFT JOIN (
    SELECT `asset`, MAX(`ts`) AS `ts`
    FROM `' . ISLE\Settings::get('table_prefix') . 'transactions`
    GROUP BY `asset`
  ) AS `last_transactions`
    ON `assets`.`id` = `last_transactions`.`asset`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
    ON
      `transactions`.`asset` = `last_transactions`.`asset`
      AND `transactions`.`ts` = `last_transactions`.`ts`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
    ON `transactions`.`user` = `users`.`id`
  ' . $filter['clause'] . '
  ' . ISLE\ViewUtility::sortClause($sort_fields) . '
  ' . ISLE\ViewUtility::limitClause() . '
        ',
        $filter['params']
    );
    while ($row = $stmt->fetch()) {
        echo '
          <tr data-id="' . $row['id'] . '">
            <td>';
        if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
            echo '<button type="button" class="btn" data-role="edit"><i class="bi-pencil-square"></i></button>';
        }
        echo '</td>
            <td>';
        if ($row['image']) {
            $img_dir = ISLE\Settings::get('web_root') . '/uploads/images/';
            $img_path = $img_dir  . $row['image'] . '.jpg';
            $thumb_path = $img_dir . 'thumbs/' . $row['image'] . '.jpg';
            echo '<a href="' . $img_path . '" target="_blank"><img src="' . $thumb_path . '"></a>';
        }
        echo '  </td>
            <td>' . htmlspecialchars($row['manufacturer']) . '</td>
            <td>' . htmlspecialchars($row['model']) . '</td>
            <td>' . htmlspecialchars($row['series']) . '</td>
            <td>' . htmlspecialchars($row['description']) . '</td>
            <td>' . htmlspecialchars($row['serial']) . '</td>
            <td>
              <a href="' . ISLE\Settings::get('web_root') . '/assets/' . $row['id'] . '" class="btn btn-sm btn-info">
                <i class="bi-info-circle me-2"></i>Details
              </a>
            </td>
            <td>';
        switch ($row['transaction_type']) {
            case ISLE\DataModels\TransactionType::CHECK_OUT:
                if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::USER && $row['user_id'] == $_SESSION['user']['id']) {
                    $in_returns = in_array($row['id'], $_SESSION['returns']);
                    echo '
              <button class="btn btn-sm btn-warning" title="Check-in" data-transact="' . ISLE\DataModels\TransactionType::CHECK_IN . '">
                <i class="bi-arrow-down-circle me-1"></i>Check-in
              </button>
              <button class="btn btn-sm btn-success' . ($in_returns ? ' d-none' : '') . '" title="Add to Returns" data-role="add" data-cart="returns">
                <i class="bi-cart-plus"></i>
              </button>
              <button class="btn btn-sm btn-danger' . ($in_returns ? '' : ' d-none') . '" title="Remove from Returns" data-role="remove" data-cart="returns">
                <i class="bi-cart-dash"></i>
              </button>';
                } else {
                    echo '
              Out to <a href="mailto:' . $row['user_email'] . '" target="_blank">' . $row['user_name'] . '</a> until ' . strftime('%x', DateTime::createFromFormat('Y-m-d', $row['returning'])->getTimestamp());
                }
                break;
            case ISLE\DataModels\TransactionType::RESTRICT:
                echo '
              <span class="text-danger">Restricted</span>';
                if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {
                    echo '
              <button type="button" class="btn btn-danger ms-2" data-transact="' . ISLE\DataModels\TransactionType::UNRESTRICT . '">
                <i class="bi-unlock me-2"></i>Unrestrict
              </button>';
                }
                break;
            default:
                if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::USER) {
                    $in_cart = in_array($row['id'], $_SESSION['cart']);
                    echo '
              <button class="btn btn-sm btn-primary" title="Check-out" data-transact="' . ISLE\DataModels\TransactionType::CHECK_OUT . '">
                <i class="bi-arrow-up-circle me-1"></i>Check-out
              </button>
              <button class="btn btn-sm btn-success' . ($in_cart ? ' d-none' : '') . '" title="Add to Cart" data-role="add" data-cart="cart">
                <i class="bi-cart-plus"></i>
              </button>
              <button class="btn btn-sm btn-danger' . ($in_cart ? '' : ' d-none') . '" title="Remove from Cart" data-role="remove" data-cart="cart">
                <i class="bi-cart-dash"></i>
              </button>';
                } else {
                    echo 'Idle';
                }
                break;
        }
        echo '
            </td>
          </tr>';
    }
    echo '
        </tbody>
      </table>
    </div>';
} else {
   echo '
    <div class="mt-3">';
    ISLE\ViewUtility::alert([
        'type' => 'info',
        'text' => 'No' . (count($filter['params']) ? ' matching' : '') . ' assets found.'
    ]);
    echo '
    </div>';
}
?>
  </div>
</div>

