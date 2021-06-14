<?php

if (isset($_GET['id'])) {
    $row = ISLE\Service::executeStatement(
        '
  SELECT *
  FROM `' . ISLE\Settings::get('table_prefix') . 'attributes`
  WHERE `id` = ?
        ',
        [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
    )->fetch();
} else {
    $row = [
        'id' => $_SESSION['post']['id'] ?? '',
        'name' => $_SESSION['post']['name'] ?? ''
    ];
}
ob_start();
?>
<form method="post" action="<?= ISLE\Settings::get('web_root'); ?>/attributes" class="needs-validation<?= isset($_SESSION['post']) ? ' was-validated' : ''; ?>" novalidate>
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
  <?= $row['id'] ? '<input type="hidden" name="id" value="' . $row['id'] . '">' : ''; ?>
  <div class="form-floating">
    <input id="name" type="text" name="name" class="form-control" value="<?= $row['name']; ?>" required>
    <label for="name">Name</label>
    <div class="invalid-feedback">Required</div>
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
    (isset($_GET['id']) ? 'Edit' : 'Add') . ' Attributes',
    ob_get_clean(),
    isset($_GET['id'])
);
?>
