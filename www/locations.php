<?php

require_once 'includes/config.php';

if ($_POST) {
    if ($_SESSION['user']['role'] < ISLE\DataModels\Role::CONTRIBUTOR) {
        throw new Exception('You are not authorized to edit locations.');
    }

    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteLocation($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Location deleted successfully';
        } else {
            try {
                ISLE\Service::editLocation($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Location updated successfully';
                unset($_SESSION['post']);
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=locationForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            ISLE\Service::addLocation($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Location added successfully';
            unset($_SESSION['post']);
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=locationForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
ISLE\ViewUtility::treePage('locations', 'Location');
require_once 'includes/views/layouts/pageend.php';
