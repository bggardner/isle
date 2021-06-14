<?php

require_once 'includes/config.php';

if ($_POST) {
    if ($_SESSION['user']['role'] < ISLE\DataModels\Role::CONTRIBUTOR) {
        throw new Exception('You are not authorized to edit categories.');
    }

    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteCategory($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Category deleted successfully';
        } else {
            try {
                ISLE\Service::editCategory($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Category has been updated';
                unset($_SESSION['post']);
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=categoryForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            ISLE\Service::addCategory($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Category has been added successfully';
            unset($_SESSION['post']);
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=categoryForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
ISLE\ViewUtility::treePage('categories', 'Category');
require_once 'includes/views/layouts/pageend.php';
