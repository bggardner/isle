<?php
ISLE\Settings::get('hooks')['pageend']();
if (isset($_SESSION['message'])) {
    ISLE\ViewUtility::alert($_SESSION['message']);
    unset($_SESSION['message']);
}
?>
    </main>
    <footer class="bg-dark mt-auto py-2">
      <div class="d-flex">
        <div class="col ms-2"><?= ISLE\Settings::get('footer')['start'] ?? ''; ?></div>
        <div class="col text-center"><?= ISLE\Settings::get('footer')['center'] ?? ''; ?></div>
        <div class="col text-end me-2"><?= ISLE\Settings::get('footer')['end'] ?? ''; ?></div>
      </div>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-p34f1UUtsS3wqzfto5wAAmdvj+osOnFyQFpp4Ua3gs/ZVWx6oOypYoCJhGGScy+8" crossorigin="anonymous"></script>
    <script src="<?= ISLE\Settings::get('web_root'); ?>/scripts/main.js"></script>
    <script>
window['web_root'] = '<?= ISLE\Settings::get('web_root'); ?>';
<?php
if (isset($_SESSION['appendJS'])) {
    echo "
document.addEventListener('DOMContentLoaded', event => {
" . $_SESSION['appendJS'] . "
})";
    unset($_SESSION['appendJS']);
}
?>
    </script>
  </body>
</html>
