<?php

require_once 'includes/config.php';

if ($_POST) {
    if ($_SESSION['user']['role'] < ISLE\DataModels\Role::CONTRIBUTOR) {
        throw new Exception('You are not authorized to edit attributes.');
    }

    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteAttribute($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Attribute deleted successfully';
        } else {
            try {
                ISLE\Service::editAttribute($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Attribute updated successfully';
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=attributeForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            ISLE\Service::addAttribute($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Attribute added successfully';
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=attributeForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
require_once 'includes/views/attributes.php';
require_once 'includes/views/layouts/pageend.php';
