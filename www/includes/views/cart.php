<?php

$cart = $_REQUEST['html'];

if (count($_SESSION[$cart]) == 0) {
  throw new Exception(ucfirst($cart) . ' is empty');
}

?>
<div class="offcanvas offcanvas-end" tabindex="-1" aria-labelledby="offcanvasHeaderLabel">
  <div class="offcanvas-header">
    <h5 id="offcanvasHeaderLabel"><?= ucfirst($cart); ?></h5>
    <button type="button" class="btn btn-<?= $cart == 'cart' ? 'primary' : 'warning'; ?> ms-auto"
      data-transact="<?= $cart == 'cart' ? ISLE\DataModels\TransactionType::CHECK_OUT : ISLE\DataModels\TransactionType::CHECK_IN; ?>" data-id="0">
      <i class="bi-arrow-<?= $cart == 'cart' ? 'up' : 'down'; ?>-circle me-1"></i>
      Check-<?= $cart == 'cart' ? 'out' : 'in'; ?>
    </button>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
<?php

$params = [];
foreach ($_SESSION[$cart] as $asset_id) {
    $params[] = ['value' => $asset_id, 'type' => \PDO::PARAM_INT];
}
$stmt = ISLE\Service::executeStatement(
    '
  SELECT
    `assets`.*,
    `models`.`description`,
    `uploads`.`hash` AS `image`
  FROM `' . ISLE\Settings::get('table_prefix') . 'assets` AS `assets`
  JOIN `' . ISLE\Settings::get('table_prefix') . 'models` AS `models`
    ON `assets`.`model` = `models`.`id`
  JOIN `' . ISLE\Settings::get('table_prefix') . 'uploads` AS `uploads`
    ON `models`.`image` = `uploads`.`hash`
  WHERE `assets`.`id` IN (' . implode(',', array_fill(0, count($_SESSION[$cart]), '?')) . ')
    ',
    $params
);
if ($stmt) {
    echo '
<table>
  <tbody>';
    while ($asset = $stmt->fetch()) {
        echo '
    <tr data-id="' . $asset['id'] . '">
      <td class="pb-3">';
        if (!empty($asset['image'])) {
            $img_path = ISLE\Settings::get('web_root') . '/uploads/images/thumbs/' . $asset['image'] . '.jpg';
            echo '<img class="float-start me-2"src="' . $img_path . '">';
        }
        echo '
          <span class="fw-bold float-end mx-2">' . $asset['serial'] . '</span>
          <span class="align-bottom">' . $asset['description'] . '</span>
      </td>
      <td>
        <button class="btn btn-sm btn-danger" title="Remove from ' . ucfirst($cart) . '" data-role="remove">
          <i class="bi-cart-dash"></i>
        </button>
      </td>
    <tr>';
    }
    echo '
  </tbody>
</table>';
} else {
    echo 'Your cart is empty';
}

?>
  <button type="button" class="btn btn-danger" data-role="empty"><i class="bi-cart-x me-2"></i>Empty Cart</button>
  </div>
</div>
