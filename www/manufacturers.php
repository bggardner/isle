<?php

require_once 'includes/config.php';

if ($_POST) {
    if ($_SESSION['user']['role'] < ISLE\DataModels\Role::CONTRIBUTOR) {
        throw new Exception('You are not authorized to edit manufacturers.');
    }

    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteManufacturer($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Manufacturer deleted successfully';
        } else {
            try {
                ISLE\Service::editManufacturer($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Manufacturer updated successfully';
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=manufacturerForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            ISLE\Service::addManufacturer($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Manufacturer added successfully';
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=manufacturerForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
require_once 'includes/views/manufacturers.php';
require_once 'includes/views/layouts/pageend.php';
