<?php
if (isset($_GET['id'])) {
    $row = ISLE\Service::executeStatement(
        '
  SELECT
    `models`.*,
    `manufacturers`.`name` AS `manufacturer_name`
  FROM
    `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'manufacturers` AS `manufacturers` ON `models`.`manufacturer` = `manufacturers`.`id`
  WHERE `models`.`id` = ?
        ',
        [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
    )->fetch();
    if (!$row) {
        throw new Exception('Model ID ' . htmlspecialchars($_GET['id']) . ' does not exist!');
    }
    $attributes = ISLE\Service::executeStatement(
        '
  SELECT
    `model_attributes`.`attribute` AS `id`,
    `model_attributes`.`value`,
    `attributes`.`name`
  FROM `' . ISLE\Settings::get('table_prefix') . 'model_attributes` AS `model_attributes`
  JOIN `' . ISLE\Settings::get('table_prefix') . 'attributes` AS `attributes`
    ON `model_attributes`.`attribute` = `attributes`.`id`
  WHERE `model` = ?
        ',
        [['value' => $row['id'], 'type' => \PDO::PARAM_INT]]
    );
    $row['attributes'] = [];
    $row['attribute_names'] = [];
    $row['attribute_values'] = [];
    while ($attribute = $attributes->fetch()) {
        $row['attributes'] = $attribute['id'];
        $row['attribute_names'] = $attribute['name'];
        $row['attribute_values'] = $attribute['value'];
    }
    $attachments = ISLE\Service::executeStatement(
        '
  SELECT
    `uploads`.`hash`,
    CONCAT(`uploads`.`name`, IF(`uploads`.`extension`, CONCAT(".", `uploads`.`extension`), "")) AS `name`
  FROM `' . ISLE\Settings::get('table_prefix') . 'model_attachments` AS `attachments`
  JOIN `' . ISLE\Settings::get('table_prefix') . 'uploads` AS `uploads` ON `attachments`.`attachment` = `uploads`.`hash`
  WHERE `model` = ?
        ',
        [['value' => $row['id'], 'type' => \PDO::PARAM_INT]]
    );
    $row['attachments'] = [];
    $row['attachment_names'] = [];
    while ($attachment = $attachments->fetch()) {
        $row['attachments'][] = $attachment['id'];
        $row['attachment_names'][] = $attachment['name'] . ($attachment['extension'] ? ' . ' . $attachment['extension'] : '');
    }
} else {
    $row = [
        'id' => $_SESSION['post']['id'] ?? null,
        'description' => $_SESSION['post']['description'] ?? '',
        'model' => $_SESSION['post']['model'] ?? '',
        'manufacturer' => $_SESSION['post']['manufacturer'] ?? '',
        'manufacturer_name' => $_SESSION['post']['manufacturer_name'] ?? '',
        'series' => $_SESSION['post']['series'] ?? '',
        'url' => $_SESSION['post']['url'] ?? '',
        'categories' => $_SESSION['post']['categories'] ?? [],
        'attributes' => $_SESSION['post']['attributes'] ?? [],
        'attribute_names' => $_SESSION['post']['attribute_names'] ?? [],
        'attribute_values' => $_SESSION['post']['attribute_values'] ?? [],
        'attachments' => $_SESSION['post']['attachments'] ?? [],
        'attachment_names' => $_SESSION['post']['attachment_names'] ?? []
    ];
}
ob_start();
?>
<form method="post" action="<?= ISLE\Settings::get('web_root'); ?>/models" enctype="multipart/form-data" class="needs-validation<?= isset($_SESSION['post']) ? ' was-validated' : ''; ?>" novalidate>
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
  <?= isset($row['id']) ? '<input type="hidden" name="id" value="' . $row['id'] . '">' : ''; ?>
  <div class="form-floating">
    <input id="description" type="text" name="description" class="form-control" value="<?= $row['description']; ?>" required>
    <label for="description">Description</label>
    <div class="invalid-feedback">Description must be at least 16 characters</div>
  </div>
  <div class="form-floating">
    <input id="model" type="text" name="model" class="form-control" value="<?= $row['model']; ?>" required>
    <label for="model">Model</label>
    <div class="invalid-feedback">Model is required</div>
  </div>
  <div class="form-floating">
    <input id="manufacturer_name" type="text" name="manufacturer_name" class="form-control" data-autocomplete="manufacturers" data-target="manufacturer" value="<?= $row['manufacturer_name']; ?>" required> 
    <input id="manufacturer" type="hidden" name="manufacturer" value="<?= $row['manufacturer']; ?>" required>
    <label for="manufacturer_name">Manufacturer</label>
    <div class="invalid-feedback">Manufacturer is required</div>
  </div>
  <div class="form-floating">
    <input id="series" type="text" name="series" class="form-control" value="<?= $row['series']; ?>">
    <label for="series">Series</label>
  </div>
  <div class="form-floating mb-3">
    <input id="url" type="url" name="url" class="form-control" value="<?= $row['url']; ?>">
    <label for="url">URL</label>
    <div class="invalid-feedback">URL is invalid</div>
  </div>
  <div class="form-floating mb-3">
    <div class="form-control bg-body h-auto tagsinput">
<?php
$stmt = false;
if (isset($row['categories'])) {
    if (is_array($row['categories']) && count($row['categories'])) {
        $stmt = ISLE\Service::executeStatement(
            '
  SELECT `id`, `name`
  FROM `' . ISLE\Settings::get('table_prefix') . 'categories`
  WHERE `id` IN (' . implode(',', array_fill(0, count($row['categories']), '?')) . ')
            ',
            array_map(function($category) { return [
                'value' => $category,
                'type' => PDO::PARAM_INT
            ];}, $row['categories'])
        );
    }
} else if (isset($_GET['id'])) {
    $stmt = ISLE\Service::executeStatement(
        '
  SELECT `id`, `name`
  FROM `' . ISLE\Settings::get('table_prefix') . 'categories`
  WHERE `id` IN (
    SELECT `category` FROM `' . ISLE\Settings::get('table_prefix') . 'model_categories` WHERE `model` = ?
  )
        ',
        [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
    );
}
if ($stmt) {
    while ($category = $stmt->fetch()) {
        echo '
      <span class="badge bg-primary">
        ' . $category['name'] . '
        <span data-role="remove"></span>
        <input type="hidden" name="categories[]" value="' . $category['id'] . '">
      </span>';
    }
}
?>
      <input id="categoryNames" type="text" data-autocomplete="categories" data-target="tag">
      <input class="tag" type="hidden" id="tag">
    </div>
    <label for="categoryNames">Categories</label>
  </div>
  <div class="form-floating mb-3">
    <div class="form-control bg-transparent h-auto text-center" id="attributesLabel">
      <div class="mt-1" data-container="attribute">
<?php
foreach ($row['attributes'] as $key => $attribute) {
    echo '
        <div class="input-group my-1">
          <input type="hidden" name="attributes[]" value="' . $attribute . '">
          <input type="text" class="form-control" name="attribute_names[]" value="' . $row['attribute_names'][$key] . '">
          <input type="text" class="form-control" name="attribute_values[]" value="' . $row['attribute_values'][$key] . '">
          <div class="btn btn-danger" data-role="remove"><i class="bi-x-lg"></i></div>
        </div>';
}
?>
      </div>
      <div class="input-group mt-1">
        <input type="hidden" id="attribute" name="attributes[]">
        <input type="text" class="form-control" name="attribute_names[]" data-autocomplete="attributes" data-target="attribute" placeholder="Attribute">
        <input type="text" class="form-control" name="attribute_values[]" placeholder="Value">
        <div class="btn btn-primary" data-role="add"><i class="bi-plus-lg"></i></div>
      </div>
      <div class="invalid-feedback text-start">Attribute name and value are required</div>
    </div>
    <label for="attributesLabel">Attributes</label>
  </div>
  <div class="form-floating mb-3">
    <div class="form-control bg-transparent h-auto text-center" id="imageLabel">
      <div class="<?= ($_SESSION['post']['image'] ?? ($row['image'] ?? false)) ? '' : 'd-none'; ?>" data-container="image">
        <div class="btn btn-danger position-absolute top-0 end-0 mt-2 me-2" data-role="remove"><i class="bi-x-lg"></i></div>
        <input type="hidden" name="image" value="<?= $_SESSION['post']['image'] ?? ($row['image'] ?? ''); ?>">
        <img class="img-fluid" src="<?= ($_SESSION['post']['image'] ?? ($row['image'] ?? false)) ? ISLE\Settings::get('web_root') . '/uploads/images/' . ($_SESSION['post']['image'] ?? $row['image']) . '.jpg' : ''; ?>">
      </div>
      <div class="input-group mt-1">
        <input type="file" class="form-control" accept="image/*" data-upload="image">
        <div class="input-group-text d-none">
          <span class="spinner-border spinner-border-sm"></span>
        </div>
      </div>
    </div>
    <label for="imageLabel">Image</label>
  </div>
  <div class="form-floating mb-3">
    <div class="form-control bg-transparent h-auto text-center" id="attachmentsLabel">
      <div class="mt-1" data-container="file">
<?php
foreach ($row['attachments'] as $attachment) {
    echo '
        <div class="input-group my-1">
          <input type="hidden" name="attachments[]" value="' . $attachment['hash'] . '">
          <input type="text" class="form-control" value="' . $attachment['name'] . '" disabled>
          <div class="btn btn-danger" data-role="remove"><i class="bi-x-lg"></i></div>
        </div>';
}
?>
      </div>
      <div class="input-group mt-1">
        <input type="file" class="form-control" data-upload="multiple" data-target="attachments[]">
        <div class="input-group-text d-none">
          <span class="spinner-border spinner-border-sm"></span>
        </div>
      </div>
    </div>
    <label for="attachmentsLabel">Attachments</label>
  </div>
<?php
if (isset($_SESSION['post']['error'])) {
    echo '
  <div class="is-invalid"></div>
  <div class="invalid-feedback">' . htmlspecialchars($_SESSION['post']['error']) . '</div>';
}
?>
</form>
<?php
ISLE\ViewUtility::modalWrapper(
    (isset($_GET['id']) ? 'Edit' : 'Add') . ' Model',
    ob_get_clean(),
    isset($_GET['id'])
);
?>
