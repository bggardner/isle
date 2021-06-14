<?php

require_once 'includes/config.php';

if ($_POST) {
    if ($_SESSION['user']['role'] < ISLE\DataModels\Role::CONTRIBUTOR) {
       throw new Exception('You are not authorized to edit assets.');
    }
    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteAsset($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Asset deleted successfully';
        } else {
            try {
                ISLE\Service::editAsset($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Asset updated successfully';
                unset($_SESSION['post']);
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=assetForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            $id = ISLE\Service::addAsset($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Asset has been added successfully';
            unset($_SESSION['post']);
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=assetForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . ($_POST['referrer'] ?? ISLE\Settings::get('web_root') . '/assets')); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
require_once 'includes/views/assets.php';
require_once 'includes/views/layouts/pageend.php';
