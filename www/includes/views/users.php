<?php
if ($_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR) {
    throw new Exception('You are not authorized to view users');
}
?>
<div class="d-flex">
  <aside class="sidebar pe-3">
    <a href="#" class="btn btn-primary" data-form="userForm">
      <i class="bi-plus-lg me-2"></i>
      Add User
    </a>
    <ul class="btn-toggle-nav list-unstyled my-2">
      <li>
        <button class="btn btn-toggle align-items-center rounded" data-bs-toggle="collapse" data-bs-target="#roles" aria-expanded="false"></button>
        <a href="?<?= ISLE\ViewUtility::buildQuery([], ['role', 'start']); ?>" class="link-dark fw-bold">Roles</a>
        <div class="roles ms-3 collapse" id="roles">
          <ul class="btn-toggle-nav list-unstyled">
<?php
$stmt = ISLE\Service::executeStatement('
  SELECT * FROM `' . ISLE\Settings::get('table_prefix') . 'roles` ORDER BY `name`
');
while ($row = $stmt->fetch()) {
    $bold = (($_GET['role'] ?? null) == $row['id']) ? ' fw-bold' : '';
    echo '
            <li>
              <button class="btn btn-toggle invisible"></button>
              <a href="?' . ISLE\ViewUtility::buildQuery(['role' => $row['id']], ['start']) . '" class="link-dark' . $bold . '">
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
if (isset($_GET['role'])) {
    $filter['clause'] .= ' AND `users`.`role` = ?';
    $filter['params'][] = ['value' => $_GET['role'], 'type' => PDO::PARAM_INT];
}
if (isset($_GET['q'])) {
    $filter['clause'] .= '
  AND (
    `users`.`name` LIKE ?
    OR `users`.`name` LIKE ?
    OR `users`.`email` LIKE ?
    OR `users`.`email` LIKE ?
  )';
    $filter['params'][] = ['value' => $_GET['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $_GET['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => $_GET['q'] . '%', 'type' => PDO::PARAM_STR];
    $filter['params'][] = ['value' => '% ' . $_GET['q'] . '%', 'type' => PDO::PARAM_STR];
}
$count = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  FROM `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
  ' . $filter['clause'] . '
    ',
    $filter['params']
)->fetchColumn();
ISLE\ViewUtility::pager($count);
?>
      <form class="ms-3" method="get" action="?<?= ISLE\ViewUtility::buildQuery([], ['q']); ?>">
        <input type="text" class="form-control" name="q" placeholder="Search results" aria-label="Search results" value="<?= $_GET['q'] ?? ''; ?>">
      </form>
    </div>
<?php
$sort_fields = [
    'name' => ['direction' => 'ASC', 'type' => 'alpha'],
    'email' => ['direction' => 'ASC', 'type' => 'alpha'],
    'role' => ['direction' => 'ASC', 'type' => 'alpha']
];
if ($count) {
    echo '
    <div class="table-responsive">
      <table data-node="user" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th scope="col"></td>
            <th scope="col">Name' . ISLE\ViewUtility::sortLink('name', $sort_fields) . '</td>
            <th scope="col">Email' . ISLE\ViewUtility::sortLink('email', $sort_fields) . '</td>
            <th scope="col">Role' . ISLE\ViewUtility::sortLink('role', $sort_fields) . '</td>
            <th scope="col"></td>
          </tr>
        </thead>
        <tbody>';
    $stmt = ISLE\Service::executeStatement(
        '
  SELECT
    `users`.`id`,
    `users`.`name`,
    `users`.`email`,
    `roles`.`name` AS `role`
  FROM `' . ISLE\Settings::get('table_prefix') . 'users` AS `users`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'roles` AS `roles`
    ON `users`.`role` = `roles`.`id`
  ' . $filter['clause'] . '
  ' . ISLE\ViewUtility::sortClause($sort_fields) . '
  ' . ISLE\ViewUtility::limitClause() . '
        ',
        $filter['params']
    );
    while ($row = $stmt->fetch()) {
        echo '
          <tr data-id="' . $row['id'] . '">
            <td><button class="btn" data-role="edit"><i class="bi-pencil-square"></i></button></td>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td>' . htmlspecialchars($row['email']) . '</td>
            <td>' . $row['role'] . '</td>
            <td>
              <a class="btn btn-sm btn-info" href="' . ISLE\Settings::get('web_root') . '/assets?user=' . $row['id'] . '" title="Find Assets">
                <i class="bi-search"></i>
              </a>
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
        'text' => 'No' . (count($filter['params']) ? ' matching' : '') . ' users found.'
    ]);
    echo '
    </div>';
}
?>
  </div>
</div>
