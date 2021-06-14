<?php

require_once 'includes/config.php';

if ($_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR) {
    throw new Exception('You are not authorized to view this page.');
}

if ($_POST) {
    if (isset($_POST['id'])) {
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteUser($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'User deleted successfully';
        } else {
            try {
                ISLE\Service::editUser($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'User updated successfully';
                unset($_SESSION['post']);
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=userForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        try {
            ISLE\Service::addUser($_POST);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'User added successfully';
            unset($_SESSION['post']);
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=userForm`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
require_once 'includes/views/users.php';
require_once 'includes/views/layouts/pageend.php';
