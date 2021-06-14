<?php
if (isset($_GET['id'])) {
    $row = ISLE\Service::executeStatement(
        '
  SELECT * FROM `' . ISLE\Settings::get('table_prefix') . 'users` WHERE `id` = ?
        ',
        [['value' => $_GET['id'], 'type' => PDO::PARAM_INT]]
    )->fetch();
} else {
    $row = [];
}
ob_start();
?>
<form method="post" action="<?= ISLE\Settings::get('web_root'); ?>/users" class="needs-validation<?= isset($_SESSION['post']) ? ' was-validated' : ''; ?>" novalidate>
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
  <?= isset($row['id']) ? '<input type="hidden" name="id" value="' . $row['id'] . '">' : ''; ?>
  <div class="form-floating">
    <input id="name" type="text" name="name" class="form-control" value="<?= $_SESSION['post']['name'] ?? ($row['name'] ?? ''); ?>" required>
    <label for="name">Name</label>
    <div class="invalid-feedback">Name is required</div>
  </div>
  <div class="form-floating">
    <input id="email" type="email" name="email" class="form-control" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$" value="<?= $_SESSION['post']['email'] ?? ($row['email'] ?? ''); ?>" required>
    <label for="email">Email</label>
    <div class="invalid-feedback">Valid email is required</div>
  </div>
  <div class="form-floating">
    <input id="password" type="password" name="password" class="form-control" autocomplete="off" required>
    <label for="password">Password</label>
    <div class="invalid-feedback">Password must be at least 8 characters</div>
  </div>
  <div class="form-floating">
    <select id="role" name="role" class="form-select" required>
<?php
$roles = array_filter(ISLE\DataModels\Role::getValues());
foreach ($roles as $key => $value) {
    $selected = $value == ($_SESSION['post']['role'] ?? ($row['role'] ?? ISLE\DataModels\Role::USER)) ? ' selected' : '';
    echo '
     <option value="' . $value . '"' . $selected . '>' . $key . '</option>';
}
?>
    </select>
    <label for="role">Role</label>
  </div>
<?php
if (isset($_SESSION['post']['error'])) {
    echo '
  <div class="is-invalid"></div>
  <div class="invalid-feedback">' . $_SESSION['post']['error'] . '</div>';
}
?>
</form>
<?php
ISLE\ViewUtility::modalWrapper(
    (isset($_GET['id']) ? 'Edit' : 'Add') . ' User',
    ob_get_clean(),
    isset($_GET['id'])
);
?>
