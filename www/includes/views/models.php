<div class="d-flex">
  <div class="d-table flex-grow-1">
    <div class="d-flex">
<?php
if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
    echo '
    <a href="#" class="btn btn-primary me-3" data-form="modelForm">
      <i class="bi-plus-lg me-2"></i>
      Add Model
    </a>';
}
$filter['clause'] = ' WHERE 1';
$filter['params'] = [];
$count = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  FROM `' . ISLE\Settings::get('table_prefix') . 'models`
  ' . $filter['clause'] . '
    ',
    $filter['params']
)->fetchColumn();
ISLE\ViewUtility::resultsHeader($count);
?>
    </div>
<?php
$sort_fields = [
    'manufacturer' => ['direction' => 'ASC', 'type' => 'alpha'],
    'model' => ['direction' => 'ASC', 'type' => 'alpha'],
    'series' => ['direction' => 'ASC', 'type' => 'alpha'],
    'description' => ['direction' => 'ASC', 'type' => 'alpha']
];
if ($count) {
    echo '
    <div class="table-responsive">
      <table data-node="model" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th role="col"></th>
            <th role="col"></th>
            <th role="col">Manufacturer' . ISLE\ViewUtility::sortLink('manufacturer', $sort_fields). '</th>
            <th role="col">Model' . ISLE\ViewUtility::sortLink('model', $sort_fields). '</th>
            <th role="col">Series' . ISLE\ViewUtility::sortLink('series', $sort_fields). '</th>
            <th role="col">Description' . ISLE\ViewUtility::sortLink('description', $sort_fields). '</th>
            <th role="col">URL</th>
            <th role="col"></th>
          </tr>
        </thead>
        <tbody>';
    $stmt = ISLE\Service::executeStatement(
        '
  SELECT
    `models`.*,
    `manufacturers`.`name` AS `manufacturer_name`
  FROM `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers` ON `models`.`manufacturer` = `manufacturers`.`id`
  ' . $filter['clause'] . '
  ' . ISLE\ViewUtility::sortClause($sort_fields) . '
  ' . ISLE\ViewUtility::limitClause() . '
        ');
    while ($row = $stmt->fetch()) {
        echo '
          <tr data-id="' . $row['id'] . '">
            <td>';
        if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
            echo '<button type="button" class="btn" data-role="edit"><i class="bi-pencil-square"></i></button>';
        }
        echo '<td>';
        if ($row['image']) {
            $web_path = ISLE\Settings::get('web_root') . '/uploads/images/thumbs/' . $row['image'] . '.jpg';
            if ($row['image']) {
                echo '<img src="' . $web_path . '">';
            }
        }
        echo '  </td>
            <td>' . htmlspecialchars($row['manufacturer_name']) . '</td>
            <td>' . htmlspecialchars($row['model']) . '</td>
            <td>' . htmlspecialchars($row['series']) . '</td>
            <td>' . htmlspecialchars($row['description']) . '</td>
            <td>' . ($row['url'] ? '<a href="' . $row['url'] . '" target="_blank" class="btn btn-link" title="Open external URL"><i class="bi-globe2"></i></a>' : '') . '</td>
            <td><a href="' . ISLE\Settings::get('web_root') . '/assets?model=' . $row['id'] . '" class="btn btn-sm btn-info"><i class="bi-search"></i></a></td>
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
        'text' => 'No' . (count($filter['params']) ? ' matching' : '') . ' models found.'
    ]);
    echo '
    </div>';
}
?>
  </div>
</div>
