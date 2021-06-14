<h3>Login</h3>
<form method="post" action="?login=1">
  <input type="hidden" name="csrfToken" value="<?= $_SESSION['csrfToken']; ?>">
  <div class="form-floating mb-3">
    <input type="text" id="email" name="email" class="form-control" required>
    <label for="email">Email</label>
  </div>
  <div class="form-floating mb-3">
    <input type="password" id="password" name="password" class="form-control" required>
    <label for="password">Password</label>
  </div>
  <button class="btn btn-primary">Submit</button>
</form>
