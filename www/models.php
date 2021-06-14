<?php

require_once 'includes/config.php';

if ($_POST) {
    if ($_SESSION['user']['role'] < ISLE\DataModels\Role::CONTRIBUTOR) {
       throw new Exception('You are not authorized to edit asset models.');
    }
    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteModel($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Asset model deleted successfully';
        } else {
            try {
                ISLE\Service::editModel($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Asset model updated successfully';
                unset($_SESSION['post']);
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=modelForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            $id = ISLE\Service::addModel($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Asset model has been added successfully';
            unset($_SESSION['post']);
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=modelForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
require_once 'includes/views/models.php';
require_once 'includes/views/layouts/pageend.php';
