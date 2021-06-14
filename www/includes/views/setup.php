<h3 class="border-bottom mb-3">Setup</h3>
<form method="post" class="needs-validation" novalidate>
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
<?php
if (($_SESSION['state'] ?? null) != 'adminonly') {
?>
  <h6 class="border-bottom">Site Information</h6>
  <div class="form-floating mb-3">
    <input type="text" id="web_root" name="web_root" class="form-control" pattern="/.*" value="<?= $_POST['web_root'] ?? ISLE\Settings::get('web_root'); ?>" required>
    <label for="web_root">Web Root Path (relative)</label>
    <div class="invalid-feedback">Required, must begin with a slash</div>
  </div>
  <div class="form-floating mb-3">
    <input type="text" id="uploads_path" name="uploads_path" class="form-control" pattern="/.*" value="<?= $_POST['uploads_path'] ?? ISLE\Settings::get('uploads_path'); ?>" required>
    <label for="uploads_path">Uploads Path (absolute)</label>
    <div class="invalid-feedback">Required, must begin with a slash</div>
  </div>
  <h6 class="border-bottom">Database Information</h6>
  <div class="form-floating mb-3">
    <input type="text" id="pdo_dsn" name="pdo_dsn" class="form-control" value="<?= $_POST['pdo_dsn'] ?? ''; ?>" required>
    <label for="pdo_dsn">PDO DSN</label>
    <div class="invalid-feedback">PDO DSN is required</div>
  </div>
  <div class="form-floating mb-3">
    <input type="text" id="pdo_username" name="pdo_username" class="form-control" value="<?= $_POST['pdo_username'] ?? ''; ?>" required>
    <label for="pdo_username">Username</label>
    <div class="invalid-feedback">PDO Username is required</div>
  </div>
  <div class="form-floating mb-3">
    <input type="password" id="pdo_password" name="pdo_password" class="form-control" autocomplete="off" value="<?= $_POST['pdo_password'] ?? ''; ?>" required>
    <label for="pdo_password">Password</label>
    <div class="invalid-feedback">PDO Password is required</div>
  </div>
  <div class="form-floating mb-3">
    <input type="text" id="table_prefix" name="table_prefix" class="form-control" value="<?= $_POST['table_prefix'] ?? ''; ?>">
    <label for="table_prefix">Table Prefix</label>
  </div>
<?php
}
?>
  <h6 class="border-bottom">Administrator Account</h6>
  <div class="form-floating mb-3">
    <input type="text" id="name" name="name" class="form-control" value="<?= $_POST['name'] ?? ''; ?>" required>
    <label for="name">Name</label>
    <div class="invalid-feedback">Required</div>
  </div>
  <div class="form-floating mb-3">
    <input type="text" id="email" name="email" class="form-control" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$" value="<?= $_POST['email'] ?? ''; ?>" required>
    <label for="email">Email</label>
    <div class="invalid-feedback">Valid email is required</div>
  </div>
  <div class="form-floating mb-3">
    <input type="password" id="password" name="password" class="form-control" pattern=".{8,}" value="<?= $_POST['password'] ?? ''; ?>" required>
    <label for="password">Password</label>
    <div class="invalid-feedback">Password must be at least 8 characters</div>
  </div>
  <button class="btn btn-primary">Submit</button>
</form>
