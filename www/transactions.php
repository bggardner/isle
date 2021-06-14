<?php

require_once 'includes/config.php';

if ($_POST) {
    if (!isset($_POST['type'])) {
        throw new Exception('Transaction type is required.');
    }
    if (
        in_array(
            $_POST['type'],
            [ISLE\DataModels\TransactionType::CHECK_OUT, ISLE\DataModels\TransactionType::CHECK_IN]
        )
        && $_SESSION['user']['role'] < ISLE\DataModels\Role::USER
    ) {
        throw new Exception('You are not authorized to check-in or check out assets.');
    }
    if (
        in_array(
            $_POST['type'],
            [ISLE\DataModels\TransactionType::RESTRICT, ISLE\DataModels\TransactionType::UNRESTRICT]
        )
        && $_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR
    ) {
        throw new Exception('You are not authorized to restrict or unrestrict assets.');
    }
    if (isset($_POST['user'])) {
        if ($_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR) {
            throw new Exception('You are not authorized to transact for another user');
        }
    } else {
      $_POST['user'] = $_SESSION['user']['id'];
    }
    if ($_POST['id'] ?? 0) {
        if ($_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR) {
            throw new Exception('You are not authorized to edit transactions');
        }
        if (isset($_GET['delete'])) {
            unset($_SESSION['post']);
            ISLE\Service::deleteTransaction($_POST['id']);
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Transaction deleted successfully';
        } else {
            try {
                ISLE\Service::editTransaction($_POST);
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Transaction updated successfully';
                unset($_SESSION['post']);
            } catch (Exception $e) {
                $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=transactionForm&id=' . $_POST['id'] . '`);';
                $_SESSION['post'] = $_POST;
                $_SESSION['post']['error'] = $e->getMessage();
            }
        }
    } else {
        if ($_POST['asset'] == 0) {
            switch ($_POST['type']) {
                case ISLE\DataModels\TransactionType::CHECK_OUT:
                    $assets = $_SESSION['cart'];
                    break;
                case ISLE\DataModels\TransactionType::CHECK_IN:
                    $assets = $_SESSION['returns'];
                    break;
                default:
                    throw new Exception('Asset ID not supplied for restriction');
            }
        } else {
            $assets = [$_POST['asset']];
        }
        try {
            foreach ($assets as $asset) {
                ISLE\Service::addTransaction(array_merge($_POST, ['asset' => $asset]));
                switch ($_POST['type']) {
                    case ISLE\DataModels\TransactionType::CHECK_OUT:
                        $_SESSION['cart'] = array_diff($_SESSION['cart'], [$asset]);
                        break;
                    case ISLE\DataModels\TransactionType::CHECK_IN:
                        $_SESSION['returns'] = array_diff($_SESSION['returns'], [$asset]);
                        break;
                    default:
                }
            }
            $_SESSION['message']['type'] = 'success';
            $_SESSION['message']['text'] = 'Transaction has been added successfully';
            unset($_SESSION['post']);
        } catch (Exception $e) {
            $_SESSION['appendJS'] = 'ISLE.form(`method=html&html=transactionForm&type=' . $_POST['type'] . '`);';
            $_SESSION['post'] = $_POST;
            $_SESSION['post']['error'] = $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

require_once 'includes/views/layouts/pagestart.php';
require_once 'includes/views/transactions.php';
require_once 'includes/views/layouts/pageend.php';
