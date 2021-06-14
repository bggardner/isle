<?php
if (isset($_GET['id'])) {
    $row = ISLE\Service::executeStatement(
        '
  SELECT
    `assets`.*,
    `models`.`description` AS `model_description`
  FROM
    `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  WHERE `assets`.`id` = ?
        ',
        [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
    )->fetch();
    $row['location_name'] = ISLE\ViewUtility::treeStrings('locations', [$row['location']])[0];
    $attributes = ISLE\Service::executeStatement(
        '
  SELECT
    `attributes`.`id`,
    `attributes`.`name`,
    `asset_attributes`.`value`
  FROM `' . ISLE\Settings::get('table_prefix') . 'asset_attributes` AS `asset_attributes`
  LEFT JOIN `' . ISLE\Settings::get('table_prefix') . 'attributes` AS `attributes`
    ON `asset_attributes`.`attribute` = `attributes`.`id`
  WHERE `asset` = ?
        ',
        [['value' => $row['id'], 'type' => \PDO::PARAM_INT]]
    );
    $row['attributes'] = [];
    $row['attribute_names'] = [];
    $row['attributes_value'] = [];
    while ($attribute = $attributes->fetch()) {
        $row['attributes'][] = $attribute['id'];
        $row['attribute_names'][] = $attribute['name'];
        $row['attribute_values'][] = $attribute['value'];
    }
    $attachments = ISLE\Service::executeStatement(
        '
  SELECT `uploads`.*
  FROM `' . ISLE\Settings::get('table_prefix') . 'asset_attachments` AS `attachments`
  JOIN `' . ISLE\Settings::get('table_prefix') . 'uploads` AS `uploads` ON `attachments`.`attachment` = `uploads`.`hash`
  WHERE `asset` = ?
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
        'model_description' => $_SESSION['post']['model_description'] ?? '',
        'model' => $_SESSION['post']['model'] ?? '',
        'series' => $_SESSION['post']['series'] ?? '',
        'location_name' => $_SESSION['post']['location_name'] ?? '',
        'location' => $_SESSION['post']['location'] ?? '',
        'attributes' => $_SESSION['post']['attributes'] ?? [],
        'attribute_names' => $_SESSION['post']['attribute_names'] ?? [],
        'attribute_values' => $_SESSION['post']['attribute_values'] ?? [],
        'attachments' => $_SESSION['post']['attachments'] ?? [],
        'attachment_names' => $_SESSION['post']['attachment_names'] ?? []
    ];
}
ob_start();
?>
<form method="post" enctype="multipart/form-data" class="needs-validation<?= isset($_SESSION['post']) ? ' was-validated' : ''; ?>" novalidate>
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
  <?= isset($row['id']) ? '<input type="hidden" name="id" value="' . $row['id'] . '">' : ''; ?>
  <div class="form-floating">
    <input id="model_description" type="text" name="model_description" class="form-control" data-autocomplete="models" data-target="model" value="<?= $row['model_description']; ?>" required>
    <input id="model" type="hidden" name="model" value="<?= $row['model']; ?>" required>
    <label for="model_description">Model</label>
    <div class="invalid-feedback">Model is required</div>
  </div>
  <div class="form-floating">
    <input id="serial" type="text" name="serial" class="form-control" value="<?= $_SESSION['post']['serial'] ?? ($row['serial'] ?? ''); ?>" required>
    <label for="serial">Serial</label>
    <div class="invalid-feedback">Serial is required</div>
  </div>
  <div class="form-floating mb-3">
    <input id="location_name" type="text" name="location_name" class="form-control" data-autocomplete="locations" data-target="location" value="<?= $_SESSION['post']['location_name'] ?? ($row['location_name'] ?? ''); ?>" required>
    <input id="location" type="hidden" name="location" value="<?= $_SESSION['post']['location'] ?? ($row['location'] ?? ''); ?>" required>
    <label for="location_name">Home Location</label>
    <div class="invalid-feedback">Home location is required</div>
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
  <div class="form-floating">
    <div class="form-control bg-transparent h-auto text-center" id="attachmentsLabel">
      <div class="mt-1" data-container="file">
<?php
foreach ($row['attachments'] as $key => $attachment) {
    echo '
        <div class="input-group my-1">
          <input type="hidden" name="attachments[]" value="' . $attachment . '">
          <input type="text" name="attachment_names[]" class="form-control" value="' . $row['attachment_names'][$index] . '" disabled>
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
    (isset($_GET['id']) ? 'Edit' : 'Add') . ' Asset',
    ob_get_clean(),
    isset($_GET['id'])
);
?>
