<?php

/**
 * This file violates PSR-1, as it generates output.  TODO: return html instead of echoing.
 */

namespace ISLE;

class ViewUtility {
    protected static $query_params;

    public static function alert($message)
    {
        echo '
    <div role="alert" aria-label="' . htmlspecialchars($message['text']) . '" class="alert alert-dismissible alert-' . $message['type'] . ' fade show">
      ' . $message['text'] . '
      <button type="button" class="btn-close" data-bs-dismiss="alert" arialabel="close"></button>
    </div>';
    }

    public static function buildQuery($merge = [], $diff_keys = [])
    {
        return http_build_query(array_merge(array_diff_key(static::queryParams(), array_fill_keys($diff_keys, null)), $merge));
    }

    public static function attachments($node, $id)
    {
        return Service::executeStatement(
            '
  SELECT
    `uploads`.*
  FROM `' . Settings::get('table_prefix') . $node . '_attachments` AS `attachments`
  LEFT JOIN `' . Settings::get('table_prefix') . 'uploads` AS `uploads`
    ON `attachments`.`attachment` = `uploads`.`hash`
  WHERE `attachments`.`' . $node . '` = ?
            ',
            [['value' => $id, 'type' => \PDO::PARAM_STR]]
        )->fetchAll();
    }

    public static function attributes($node, $id)
    {
        return Service::executeStatement(
            '
  SELECT
    `' . $node . '_attributes`.*,
    `attributes`.`name`
  FROM `' . Settings::get('table_prefix') . $node . '_attributes` AS `' . $node . '_attributes`
  LEFT JOIN `' . Settings::get('table_prefix') . 'attributes` AS `attributes`
    ON `' . $node . '_attributes`.`attribute` = `attributes`.`id`
  WHERE `' . $node . '_attributes`.`' . $node . '` = ?
            ',
            [['value' => $id, 'type' => \PDO::PARAM_STR]]
        )->fetchAll();
    }

    public static function attributeSelects($ids)
    {
        if (!count($ids)) {
            return;
        }
        $stmt = Service::executeStatement(
            '
  SELECT *
  FROM `' . Settings::get('table_prefix') . 'attributes`
  WHERE `id` IN (' . implode(',', array_fill(0, count($ids), '?')). ')
            ',
            array_map(function($id) { return ['value' => intval($id), 'type' => \PDO::PARAM_INT]; }, $ids)
        );
        while ($attribute = $stmt->fetch()) {
            echo '
      <div class="col-auto">
        <label for="attribute-' . $attribute['id'] . '" class="fw-bold">' . $attribute['name'] . '</label>
        <select id="attribute-' . $attribute['id'] . '" name="attributes[' . $attribute['id'] . '][]" class="form-select" multiple>';
            $values = Service::executeStatement(
                '
  SELECT DISTINCT `value`
  FROM `' . Settings::get('table_prefix') . 'model_attributes` AS `model_attributes`
  WHERE `attribute` = ?
  UNION DISTINCT
  SELECT DISTINCT `value`
  FROM `' . Settings::get('table_prefix') . 'asset_attributes` AS `asset_attributes`
  WHERE `attribute` = ?
  ORDER BY `value`
                ',
                [
                    ['value' => $attribute['id'], 'type' => \PDO::PARAM_INT],
                    ['value' => $attribute['id'], 'type' => \PDO::PARAM_INT]
                ]
            );
            $any_selected = false;
            while ($value = $values->fetchColumn()) {
                $selected = in_array($value, $_GET['attributes'][$attribute['id']] ?? []) ? ' selected' : '';
                $any_selected = $any_selected || $selected;
                echo '
          <option' . $selected . '>' . htmlspecialchars($value) . '</option>';
            }
            echo '
        </select>
        <a href="#" data-role="clear" class="link-secondary' . ($any_selected ? '' : ' d-none') . '">Clear</a>
     </div>';
        }
    }


    public static function autocomplete($field, $term)
    {
        switch ($field) {
            case 'attributes':
                $query = '
  SELECT
    `id` AS `value`,
    `name` AS `label`
  FROM `' . Settings::get('table_prefix') . 'attributes`
  WHERE
    `name` LIKE ?
    OR `name` LIKE ?
                ';
                break;
            case 'models':
                $query = '
  SELECT DISTINCT
    `models`.`id` AS `value`,
    CONCAT(
      `manufacturers`.`name`,
      " ",
      `models`.`model`,
      " ",
      IF(ISNULL(`models`.`series`), "", CONCAT("(", `models`.`series`, " Series) ")),
      `models`.`description`
    ) AS `label`
  FROM `' . Settings::get('table_prefix') . 'models` AS `models`
  LEFT JOIN `' . Settings::get('table_prefix') . 'manufacturers` AS `manufacturers` ON `models`.`manufacturer` = `manufacturers`.`id`
  WHERE
    `manufacturers`.`name` LIKE ?
    OR `manufacturers`.`name` LIKE ?
    OR `models`.`model` LIKE ?
    OR `models`.`model` LIKE ?
    OR `models`.`series` LIKE ?
    OR `models`.`series` LIKE ?
    OR `models`.`description` LIKE ?
    OR `models`.`description` LIKE ?
                ';
                break;
            case 'manufacturers':
                $query = '
  SELECT
    `id` AS `value`,
    `name` AS `label`
  FROM `' . Settings::get('table_prefix') . $field . '`
  WHERE
    `name` LIKE ?
    OR `name` LIKE ?
                ';
                break;
            case 'categories':
            case 'locations':
                $query = '
  SELECT
    `nodes`.`id` AS `value`,
    GROUP_CONCAT(`parents`.`name` ORDER BY `parents`.`left` ASC SEPARATOR " &gt; ") AS `label`
  FROM `' . Settings::get('table_prefix') . $field . '` AS `nodes`
  CROSS JOIN `' . Settings::get('table_prefix') . $field . '` AS `parents`
  WHERE
    `nodes`.`left` BETWEEN `parents`.`left` AND `parents`.`right`
    AND `parents`.`id` != 1
    AND (
      `nodes`.`name` LIKE ?
      OR `nodes`.`name` LIKE ?
    )
  GROUP BY `nodes`.`id`
                ';
                break;
            case 'users':
                if ($_SESSION['user']['role'] < DataModels\Role::ADMINISTRATOR) {
                    throw new \Exception('You are not authorized to view users');
                }
                $query = '
  SELECT
    `id` AS `value`,
    CONCAT(`name`, " &lt;", `email`, "&gt;") AS `label`
  FROM `' . Settings::get('table_prefix') . 'users`
  WHERE
    `name` LIKE ?
    OR `name` LIKE ?
                ';
                break;
            default:
                throw new \Exception('Autocomplete not available for ' . $field);
        }
        $params = [];
        for ($i = 0; $i < substr_count($query, '?') / 2; $i++) {
            $params[] = ['value' => $term . '%', 'type' => \PDO::PARAM_STR];
            $params[] = ['value' => '% ' . $term . '%', 'type' => \PDO::PARAM_STR];
        }
        $stmt = Service::executeStatement(
            $query . ' ORDER BY `label` LIMIT ' . Settings::get('autocomplete_limit'),
            $params
        );
        return $stmt->fetchAll();
    }

    public static function categoryTreeStrings($id, $separator = ' > ')
    {
        return Service::executeStatement(
            '
  SELECT
    GROUP_CONCAT(`ancestors`.`name` ORDER BY `ancestors`.`left` ASC SEPARATOR "' . $separator . '") AS `name`
  FROM `' . Settings::get('table_prefix') . 'categories` AS `nodes`
  CROSS JOIN `' . Settings::get('table_prefix') . 'categories` AS `ancestors`
  WHERE
    `nodes`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
    AND `ancestors`.`id` != 1
    AND `nodes`.`id` IN (
      SELECT `category`
      FROM `' . Settings::get('table_prefix') . 'model_categories`
      WHERE `model` = ?
    )
  GROUP BY `nodes`.`id`
  ORDER BY `nodes`.`name`
            ',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        )->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public static function collapsibleTree($tree)
    {
        $stmt = Service::executeStatement('
          SELECT
            `nodes`.*,
            (
              SELECT `id`
              FROM `' . Settings::get('table_prefix') . $tree . '` AS `ancestors`
              WHERE
                `nodes`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
                AND `nodes`.`id` != `ancestors`.`id`
              ORDER BY `ancestors`.`right` - `ancestors`.`left` ASC
              LIMIT 1
            ) AS `parent`
          FROM `' . Settings::get('table_prefix') . $tree . '` AS `nodes`
          ORDER BY `nodes`.`name`
        ');
        $nodes = [];
        $parents = [];
        while ($node = $stmt->fetch()) {
            $nodes[$node['id']] = $node;
            $parents[$node['parent']][] = $node['id'];
        }

        echo '
    <ul class="btn-toggle-nav list-unstyled my-2">';
        static::recurseCollapsibleTree($tree, $nodes, $parents);
        echo '
    </ul>';
    }

    public static function limitClause()
    {
        $limit = Settings::get('results_per_page');
        $offset = (intval(static::queryParams()['page'] ?? 1) - 1) * $limit;
        return 'LIMIT ' . $offset . ', ' . $limit;
    }


    public static function listAttachments($attachments)
    {
        echo implode(
            '<br>',
            array_map(
                function($attachment) {
                    $filename = $attachment['hash'];
                    if ($attachment['extension']) {
                        $filename .= '.' . $attachment['extension'];
                    }
                    if ($attachment['image']) {
                        return '
          <a href="' . Settings::get('web_root') . '/uploads/images/' . $filename . '" target="_blank">
            <img src="' . Settings::get('web_root') . '/uploads/images/thumbs/' . $filename . '">
          </a>';
                    } else {
                        return '
      <a href="' . Settings::get('web_root') . '/uploads/' . $filename . '" target="_blank">
        ' . $attachment['name'] . '
      </a>';
                    }
                },
                $attachments
            )
        );
    }

    public static function modalWrapper($title, $body, $delete_button = false)
    {
        echo '
<div class="modal fade" data-bs-backdrop="static" aria-labelledby="modalTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="modalTitle">' . $title . '</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
' . $body . '
      </div>
      <div class="modal-footer">';
        if ($delete_button) {
          echo '
        <div class="flex-grow-1">
          <div class="btn-group">
            <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              Delete
            </button>
            <ul class="dropdown-menu">
              <li><span class="dropdown-item fw-bold lh-1 disabled text-reset">Are you sure?</span><li>
              <li><span class="dropdown-item lh-1 disabled text-reset">All related data will be deleted.</span><li>
              <li><span class="dropdown-item lh-1 disabled text-reset">This action cannot be undone.</span><li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="#">Confirm Delete</a><li>
            </ul>
          </div>
        </div>';
        }
        echo '
        <button type="submit" class="btn btn-primary">Submit</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>';
    }

    public static function pager($count, $page_limit = 10)
    {
        $query_params = static::queryParams();
        $limit = Settings::get('results_per_page');
        $page = intval($query_params['page'] ?? 1);
        $start = ($page - 1) * $limit + 1;
        echo '
<nav class="table-responsive">
  <ul class="pagination justify-content-center mb-0">
    <li class="page-item disabled"><a class="page-link" href="#">' . min($count, $start) . ' - ' . min($count, $start + $limit - 1) . ' of ' . $count . '</a></li>';
        if ($count > 1) {
            if ($count > $limit) {
                echo '
    <li class="page-item' . ($start < $limit ? ' disabled"><a class="page-link" href="#' : '"><a class="page-link" href="?' . static::buildQuery(['page' => $page - 1])) . '" aria-label="Previous"><span aria-hidden="true"><i class="bi-chevron-compact-left"></i>
</span></a></li>';
                $pages = ceil($count / $limit);
                $min_page = max(1, min($pages - $page_limit + 1, $page - floor($page_limit / 2)));
                $max_page = min($pages, max($page_limit, $page + floor($page_limit / 2) - 1));
                if ($min_page > 1) {
                    echo '
    <li class="page-item"><a class="page-link" href="?' . static::buildQuery(['page' => max(1, $page - $page_limit)]) . '">&hellip;</a></li>';
                }
                for ($i = $min_page; $i <= $max_page; $i++) {
                    $active = $i == $page ? ' active' : '';
                   echo '
    <li class="page-item' . $active . '"><a class="page-link" href="?' . static::buildQuery(['page' => $i]) . '">' . $i . '</a></li>';
                }
                if ($max_page < $pages) {
                    echo '
    <li class="page-item"><a class="page-link" href="?' . static::buildQuery(['page' => min($pages, $page + $page_limit)]) . '">&hellip;</a></li>';
                }
                echo '
    <li class="page-item' . ($start > ($count - $limit) ? ' disabled"><a class="page-link" href="#' : '"><a class="page-link" href="?' . static::buildQuery(['page' => $page + 1])) . '" aria-label="Next"><span aria-hidden="true"><i class="bi-chevron-compact-right"></i></span></a></li>';
            }
        }
        echo '
  </ul>
</nav>';
    }

    protected static function parseSort($fields)
    {
        $existing_sorts = explode(',', static::queryParams()['sort'] ?? '');
        $existing_sort = [];
        foreach ($existing_sorts as $sort) {
            @list($field, $direction) = explode(' ', $sort);
            $existing_sort[$field] = ['direction' => (strtoupper($direction ?? 'ASC') == 'ASC') ? 'ASC' : 'DESC'];
        }
        // Filter non-matching fields
        $existing_sort = array_intersect_key($existing_sort, $fields);
        // Append remaining fields, maintaining existing order
        return array_replace_recursive($existing_sort, $fields, $existing_sort);
    }

    public static function queryParams()
    {
        if (!isset(static::$query_params)) {
            parse_str($_SERVER['QUERY_STRING'], static::$query_params);
        }
        return static::$query_params;
    }

    protected static function recurseCollapsibleTree($tree, $nodes, $parents, $root_id = 1)
    {
        $active_node = intval($_GET[$tree] ?? 1);
        $expand = $nodes[$root_id]['left'] < $nodes[$active_node]['left'] && $nodes[$root_id]['right'] > $nodes[$active_node]['right'];
        $has_children = isset($parents[$root_id]);
        echo '
      <li>
        <button class="btn btn-toggle align-items-center rounded' . ($expand ? '' : ' collapsed') . ($has_children ? '" data-bs-toggle="collapse" data-bs-target="#' . $tree . '-' . $root_id . '" aria-expanded="' . ($expand ? 'true' : 'false') : ' invisible') . '"></button>
        <a href="?' . static::buildQuery([$tree => $root_id], ['start']) . '" class="link-dark' . ($active_node == $root_id ? ' fw-bold' : '') . '">
            ' . htmlspecialchars($nodes[$root_id]['name']) . '
        </a>';
        if ($has_children) {
            echo '
        <div class="collapse ' . $tree . ' ms-3' . ($expand ? ' show' : '') . '" id="' . $tree . '-' . $root_id . '">
          <ul class="btn-toggle-nav list-unstyled">';
            foreach ($parents[$root_id] as $child_id) {
                static::{__FUNCTION__}($tree, $nodes, $parents, $child_id);
            }
            echo '
          </ul>
        </div>';
        }
        echo '
      </li>';
    }

    public static function resultsHeader($count)
    {
        static::pager($count);
        echo '
      <form class="ms-3" method="get" action="?' . static::buildQuery([], ['q', 'start']) . '">
        <input type="text" class="form-control" name="q" placeholder="Search results" value="' . (static::queryParams()['q'] ?? '') . '" aria-label="Search results">
      </form>';
    }

    public static function sortClause($fields)
    {
        $fields = static::parseSort($fields);
        if (count($fields)) {
            $subclauses = [];
            foreach ($fields as $field => $opts) {
                $subclauses[] = '`' . $field . '` ' . $opts['direction'];
            }
            $clause = 'ORDER BY ' . implode(', ', $subclauses);
        } else {
            $clause = '';
        }
        return $clause;
    }

    public static function sortLink($field, $fields)
    {
        $fields = static::parseSort($fields);
        if (count($fields)) {
            $sorts = [];
            $has_priority = array_key_first($fields) == $field;
            foreach ($fields as $name => $opts) {
                if ($field == $name) {
                    $icon = 'bi-sort-' . $opts['type'] . '-';
                    if ($opts['direction'] == 'ASC') {
                        $icon .= 'down';
                    } else {
                        $icon .= 'down-alt';
                    }
                    if ($has_priority) {
                        if ($opts['direction'] == 'ASC') {
                            $opts['direction'] = 'DESC';
                        } else {
                            $opts['direction'] = 'ASC';
                        }
                    }
                    array_unshift($sorts, $name . ' ' . $opts['direction']);
                } else {
                    $sorts[] = $name . ' ' . $opts['direction'];
                }
            }
            $url = static::buildQuery(['sort' => implode(',', $sorts)]);
            if ($has_priority) {
                $link_class = 'link-dark';
            } else {
                $link_class = 'link-secondary';
            }
            $link = '<a href="?' . $url . '" class="' . $link_class . ' ms-2"><i class="' . $icon . '"></i></a>';
        } else {
            $link = '';
        }
        return $link;
    }
    public static function treeStrings($tree, $ids, $separator = ' > ')
    {
        if (!count($ids)) {
            return [];
        }
        return Service::executeStatement(
            '
  SELECT
    GROUP_CONCAT(`ancestors`.`name` ORDER BY `ancestors`.`left` ASC SEPARATOR "' . $separator . '") AS `name`
  FROM `' . Settings::get('table_prefix') . $tree . '` AS `nodes`
  CROSS JOIN `' . Settings::get('table_prefix') . $tree . '` AS `ancestors`
  WHERE
    `nodes`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
    AND `ancestors`.`id` != 1
    AND `nodes`.`id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
  GROUP BY `nodes`.`id`
  ORDER BY `nodes`.`name`
            ',
            array_map(
                function($id) { return ['value' => $id, 'type' => \PDO::PARAM_INT]; },
                $ids
            )
        )->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public static function treeNodeForm($tree, $title)
    {
        $stmt = Service::executeStatement(
                '
  SELECT
    `nodes`.*,
    (
      SELECT `id`
      FROM `' . Settings::get('table_prefix') . $tree . '` AS `ancestors`
      WHERE
        `nodes`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
        AND `nodes`.`id` != `ancestors`.`id`
      ORDER BY `ancestors`.`right` - `ancestors`.`left` ASC
      LIMIT 1
    ) AS `parent`
  FROM `' . Settings::get('table_prefix') . $tree . '` AS `nodes`
  ORDER BY `nodes`.`name`
                '
        );
        $match = false;
        $nodes_by_id = [];
        $nodes_by_parent = [];
        while ($node = $stmt->fetch()) {
            $match = $match || ($node['id'] ?? true) == ($_GET['id'] ?? false);
            $nodes_by_id[$node['id']] = $node;
            $nodes_by_parent[$node['parent']][] = $node['id'];
        }
        if (isset($_GET['id'])) {
            if (!$match) {
                throw new Exception($title . ' id [' . $_GET['id'] . '] does not exist!');
            }
            $node = $nodes_by_id[$_GET['id']];
        } else {
            $node = [];
        }
        ob_start();
        echo '
        <form action="" method="post" class="needs-validation" novalidate>
          <input type="hidden" name="csrfToken" value="' . $_SESSION['csrfToken'] . '">
          ' . (isset($node['id']) ? '<input type="hidden" name="id" value="' . $node['id'] . '">' : '') . '
          <div class="form-floating">
            <input id="name" type="text" name="name" class="form-control" value="' . ($_SESSION['post']['name'] ?? ($node['name'] ?? '')) . '" required>
            <label for="name">Name</label>
          </div>
          <div class="form-floating">
            <input type="text" name="parent_name" class="form-control" data-autocomplete="' . $tree . '" data-target="parent" value="' . ($_SESSION['post']['parent_name'] ?? ($nodes_by_id[$node['parent'] ?? null]['name'] ?? $nodes_by_id[1]['name'])) . '" required>
            <input type="hidden" id="parent" name="parent" value="' . ($_SESSION['post']['parent'] ?? ($node['id'] ?? 1)) . '">
            <label for="parent">Parent</label>
          </div>';
        if (isset($_SESSION['post']['error'])) {
            echo '
  <div class="is-invalid"></div>
  <div class="invalid-feedback">' . htmlspecialchars($_SESSION['post']['error']) . '</div>';
        }
        echo '
        </form>';
        static::modalWrapper((isset($_GET['id']) ? 'Edit' : 'Add') . ' ' . $title, ob_get_clean(), isset($_GET['id']));
    }

    public static function treePage($tree, $title)
    {
        $query_params = static::queryParams();
        echo '
<div class="d-flex">
  <aside class="sidebar pe-3">';
        if ($_SESSION['user']['role'] >= DataModels\Role::CONTRIBUTOR) {
            echo '
    <a href="#" data-form="' . strtolower($title) . 'Form" class="btn btn-primary">
      <i class="bi-plus-lg me-2"></i> Add ' . $title . '
    </a>';
        } else {
            echo '<div class="mt-5"></div>';
        }
        static::collapsibleTree($tree);
        echo '
  </aside>
  <div class="d-table flex-grow-1">';
        echo '
    <div class="d-flex">';
        $filter = ['clause' => ' WHERE `id` != 1', 'params' => []];
        if (isset($query_params[$tree])) {
            $filter['clause'] .= '
 AND `left` > (SELECT `left` FROM `'. Settings::get('table_prefix') . $tree . '` WHERE `id` = ?)
 AND `right` < (SELECT `right` FROM `' . Settings::get('table_prefix') . $tree . '` WHERE `id` = ?)
            ';
            $filter['params'][] = ['value' => $query_params[$tree], 'type' => \PDO::PARAM_INT];
            $filter['params'][] = ['value' => $query_params[$tree], 'type' => \PDO::PARAM_INT];
        }
        if (isset($query_params['q'])) {
            $filter['clause'] .= ' AND (`name` LIKE ? OR `name` LIKE ?)';
            $filter['params'][] = ['value' => $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
            $filter['params'][] = ['value' => '% ' . $query_params['q'] . '%', 'type' => \PDO::PARAM_STR];
        }
        $count = Service::executeStatement(
            'SELECT COUNT(*) FROM `' . Settings::get('table_prefix') . $tree . '`' . $filter['clause'],
            $filter['params']
        )->fetchColumn();
        static::resultsHeader($count);
        echo '
    </div>';
        $sort_fields = [
            'name' => ['direction' => 'ASC', 'type' => 'alpha'],
            'ancestry' => ['direction' => 'ASC', 'type' => 'alpha']
        ];
        if ($count) {
            echo '
    <div class="table-responsive">
      <table data-node="' . strtolower($title) . '" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th scope="col"></td>
            <th scope="col">Name' . static::sortLink('name', $sort_fields) . '</td>
            <th scope="col">Ancestry' . static::sortLink('ancestry', $sort_fields) . '</td>
            <th scope="col"></td>
          </tr>
        </thead>
        <tbody>';
            $start = isset($query_params['start']) ? intval($query_params['start']) - 1 : 0;
            $start = max(0, min(floor($count / Settings::get('results_per_page')), $start));
            $stmt = Service::executeStatement(
                '
SELECT
  `nodes`.`id`,
  `nodes`.`name` AS `name`,
  GROUP_CONCAT(`ancestors`.`name` ORDER BY `ancestors`.`left` ASC SEPARATOR " &gt; ") AS `ancestry`
FROM `' . Settings::get('table_prefix') . $tree . '` AS `nodes`
CROSS JOIN `' . Settings::get('table_prefix') . $tree . '` AS `ancestors`
WHERE
  `nodes`.`left` BETWEEN `ancestors`.`left` AND `ancestors`.`right`
  AND `ancestors`.`left` > (
    SELECT `left` FROM `' . Settings::get('table_prefix') . $tree . '`
    WHERE `id` = ?
  )
  AND `nodes`.`id` IN (
    SELECT `id`
    FROM `' . Settings::get('table_prefix') . $tree . '`
    ' . $filter['clause'] . '
  )
GROUP BY `nodes`.`id`
' . static::sortClause($sort_fields) . '
' . static::limitClause() . '
                ',
                array_merge([['value' => $query_params[$tree] ?? 1, 'type' => \PDO::PARAM_INT]], $filter['params'])
            );
            while ($node = $stmt->fetch()) {
                $ancestry = array_map('htmlspecialchars', explode(' &gt; ', $node['ancestry']));
                array_pop($ancestry);
                $ancestry = implode(' &gt; ', $ancestry);
                echo '
          <tr data-id="' . $node['id'] . '">
            <td>';
                if ($_SESSION['user']['role'] >= DataModels\Role::CONTRIBUTOR) {
                    echo '<button type="button" data-role="edit" class="btn"><i class="bi-pencil-square"></i></button>';
                }
                echo '</td>
            <td>' . $node['name'] . '</td>
            <td>' . ($ancestry ?: '<span class="fst-italic text-secondary">(root ' . strtolower($title) . ')</span>') . '</td>
            <td>
              <a class="btn btn-sm btn-info" href="' . Settings::get('web_root') . 'assets?' . strtolower($title) . '=' . $node['id'] . '">
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
            static::alert([
                'type' => 'info',
                'text' => 'No' . (count($filter['params']) ? ' matching' : '') . ' ' . $tree . ' found.'
            ]);
            echo '
    </div>';
        }
        echo '
  </div>
</div>';
    }
}
