<div class="d-flex">
  <div class="d-table flex-grow-1">
    <div class="d-flex">
<?php
if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
    echo '
    <a href="#" data-form="manufacturerForm" class="btn btn-primary me-3">
      <i class="bi-plus-lg me-2"></i>
      Add Manufacturer
    </a>';
}
$filter['clause'] = ' WHERE 1';
$filter['params'] = [];
$count = ISLE\Service::executeStatement(
    '
  SELECT COUNT(*)
  FROM `' . ISLE\Settings::get('table_prefix') . 'manufacturers`
  ' . $filter['clause'] . '
    ',
    $filter['params']
)->fetchColumn();
ISLE\ViewUtility::resultsHeader($count);
?>
    </div>
<?php
$sort_fields = ['name' => ['direction' => 'ASC', 'type' => 'alpha']];
if ($count) {
    echo '
    <div class="table-responsive">
      <table data-node="manufacturer" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th scope="col"></th>
            <th scope="col">Name' . ISLE\ViewUtility::sortLink('name', $sort_fields) . '</th>
            <th scope="col"></th>
          </tr>
        </thead>
        <tbody>';
    $manufacturers = ISLE\Service::executeStatement(
        '
  SELECT *
  FROM `' . ISLE\Settings::get('table_prefix') . 'manufacturers`
  ' . $filter['clause'] . '
  ' . ISLE\ViewUtility::sortClause($sort_fields) . '
  ' . ISLE\ViewUtility::limitClause() . '
        ',
        $filter['params']
    );
    while ($row = $manufacturers->fetch()) {
        echo '
          <tr data-id="' . $row['id'] . '">
            <td>';
        if ($_SESSION['user']['role'] >= ISLE\DataModels\Role::CONTRIBUTOR) {
            echo '<button type="button" class="btn" data-role="edit"><i class="bi-pencil-square"></i></button>';
        }
        echo '</td>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td>
              <a class="btn btn-sm btn-info" href="' . ISLE\Settings::get('web_root') . '/assets?manufacturer=' . $row['id'] . '" title="Find Assets">
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
        'text' => 'No' . (count($filter['params']) ? ' matching' : '') . ' manufacturers found.'
    ]);
    echo '
    </div>';
}

?>
  </div>
</div>
