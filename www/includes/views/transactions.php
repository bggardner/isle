<div class="d-flex">
  <aside class="sidebar mt-4 pe-3 pt-3">
    <ul class="btn-toggle-nav list-unstyled my-2">
      <li>
        <button class="btn btn-toggle align-items-center rounded" data-bs-toggle="collapse" data-bs-target="#roles" aria-expanded="false"></button>
        <a href="?<?= ISLE\ViewUtility::buildQuery([], ['type', 'start']); ?>" class="link-dark fw-bold">Types</a>
        <div class="roles ms-3 collapse" id="roles">
          <ul class="btn-toggle-nav list-unstyled">
<?php
$query_params = ISLE\ViewUtility::queryParams();
$stmt = ISLE\Service::executeStatement('
  SELECT * FROM `' . ISLE\Settings::get('table_prefix') . 'transaction_types` ORDER BY `name`
');
while ($row = $stmt->fetch()) {
    $bold = (($_GET['type'] ?? null) == $row['id']) ? ' fw-bold' : '';
    echo '
            <li>
              <button class="btn btn-toggle invisible"></button>
              <a href="?' . ISLE\ViewUtility::buildQuery(['type' => $row['id']], ['start']) . '" class="link-dark' . $bold . '">
                ' . $row['name'] . '
              </a>
            </li>';
}
?>
          </ul>
        </div>
      </li>
    </ul>
  </aside>
  <div class="d-table flex-grow-1">
    <div class="d-flex">
<?php
$filter['clause'] = ' WHERE 1';
$filter['params'] = [];
if (isset($_GET['type'])) {
    $filter['clause'] .= ' AND `transaction_types`.`id` = ?';
    $filter['params'][] = ['value' => $_GET['type'], 'type' => PDO::PARAM_INT];
}
if (isset($_GET['user'])) {
    $filter['clause'] .= ' AND `users`.`id` = ?';
    $filter['params'][] = ['value' => $_GET['user'], 'type' => PDO::PARAM_INT];
}
if (isset($_GET['q'])) {
    $filter['clause'] .= '
  AND (
    `ts` LIKE ?
    OR `transactions`.`returning` LIKE ?
    OR `users`.`name` LIKE ?
    OR `users`.`name` LIKE ?
    OR `users`.`email` LIKE ?
    OR `users`.`email` LIKE ?
    OR `manufacturers`.`name` LIKE ?
    OR `manufacturers`.`name` LIKE ?
    OR `models`.`model` LIKE ?
    OR `models`.`model` LIKE ?
    OR `models`.`series` LIKE ?
    OR `models`.`series` LIKE ?
    OR `assets`.`serial` LIKE ?
    OR `assets`.`serial` LIKE ?
    OR `transactions`.`notes` LIKE ?
    OR `transactions`.`notes` LIKE ?
  )
    ';
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => PDO::PARAM_STR];
}
$from_clause = '
  FROM `' . ISLE\Settings::get('table_prefix') . 'transactions` AS `transactions`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'transaction_types` AS `transaction_types`
    ON `transactions`.`type` = `transaction_types`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
    ON `transactions`.`user` = `users`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
    ON `transactions`.`asset` = `assets`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers`
    ON `manufacturers`.`id` = `models`.`manufacturer`
';
$count = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  ' . $from_clause . '
  ' . $filter['clause'] . '
    ',
    $filter['params']
)->fetchColumn();
ISLE\ViewUtility::pager($count);
?>
      <form class="d-flex ms-3" method="get" action="?<?= ISLE\ViewUtility::buildQuery([], ['q', 'users[]']); ?>">
        <input type="text" class="form-control" name="q" placeholder="Search results" aria-label="Search results">
        <input type="text" class="form-control ms-3" name="user_name" data-autocomplete="users" data-target="user" placeholder="Filter by user" value="<?= htmlspecialchars($_GET['user_name'] ?? ''); ?>">
        <input type="hidden" id="user" name="user" value="<?= htmlspecialchars($_GET['user'] ?? ''); ?>">
      </form>
    </div>
<?php
if ($count) {
  $sort_fields = [
    'ts' => ['direction' => 'DESC', 'type' => 'numeric'],
    'type' => ['direction' => 'ASC', 'type' => 'alpha'],
    'user_name' => ['direction' => 'ASC', 'type' => 'alpha'],
    'model' => ['direction' => 'ASC', 'type' => 'alpha'],
    'serial' => ['direction' => 'ASC', 'type' => 'alpha'],
    'returning' => ['direction' => 'ASC', 'type' => 'numeric'],
    'notes' => ['direction' => 'ASC', 'type' => 'alpha']
  ];
  echo '
    <div class="table-responsive">
      <table data-node="transaction" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th scope="col"></td>
            <th scope="col">Timestamp' . ISLE\ViewUtility::sortLink('ts', $sort_fields) . '</td>
            <th scope="col">Action' . ISLE\ViewUtility::sortLink('type', $sort_fields) . '</td>
            <th scope="col">User' . ISLE\ViewUtility::sortLink('user_name', $sort_fields) . '</td>
            <th scope="col">Model' . ISLE\ViewUtility::sortLink('model', $sort_fields) . '</td>
            <th scope="col">Serial' . ISLE\ViewUtility::sortLink('serial', $sort_fields) . '</td>
            <th scope="col">Returning' . ISLE\ViewUtility::sortLink('returning', $sort_fields) . '</td>
            <th scope="col">Notes' . ISLE\ViewUtility::sortLink('notes', $sort_fields) . '</td>
          </tr>
        </thead>
        <tbody>';
    $stmt = ISLE\Service::executeStatement(
        '
  SELECT
    `transactions`.`id`,
    `transactions`.`ts`,
    `transactions`.`type`,
    `transaction_types`.`name` AS `type_name`,
    `transactions`.`asset`,
    `users`.`id` AS `user_id`,
    `users`.`name` AS `user_name`,
    CONCAT_WS(" ", `manufacturers`.`name`, `models`.`model`, `models`.`description`) AS `model`,
    `assets`.`serial`,
    `transactions`.`returning`,
    `transactions`.`notes`
  ' . $from_clause . '
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
        if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::ADMINISTRATOR) {
            echo '<button type="button" class="btn" data-role="edit"><i class="bi-pencil-square"></i></button>';
        }
        echo '</td>
            <td>' . $row['ts'] . '</td>
            <td>' . $row['type_name'] . '</td>
            <td>' . htmlspecialchars($row['user_name']) . '</td>
            <td>' . htmlspecialchars($row['model']) . '</td>
            <td>
                ' . htmlspecialchars($row['serial']) . '
                <a href="' . ISLE\Settings::get('web_root') . '/assets/' . $row['asset'] . '" class="btn btn-sm btn-info ms-2 float-end">
                    <i class="bi-info-circle"></i></button>
                </a>
            </td>
            <td>' . $row['returning'] . '</td>
            <td>' . $row['notes'] . '</td>
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
        'text' => 'No' . (count($filter['params']) ? ' matching' : '') . ' transactions found.'
    ]);
    echo '
    </div>';
}
?>
  </div>
</div>
