<?php

header('Content-type: text/javascript');
$response = new stdClass();
try {
    require_once 'includes/config.php';
    switch ($_REQUEST['method'] ?? '(none provided)') {
        case 'autocomplete':
            if (!isset($_GET['field']) || !isset($_GET['term'])) {
                throw new Exception('Autocomplete field and term are required');
            }
            $response->term = $_GET['term'];
            $response->results = ISLE\ViewUtility::autocomplete($_GET['field'], $_GET['term']);
            break;
        case 'cart':
        case 'returns':
            switch ($_REQUEST['action']) {
                case 'add':
                    $_SESSION[$_REQUEST['method']] = array_values(
                        array_unique(
                            array_merge($_SESSION[$_REQUEST['method']], [$_REQUEST['asset']])
                        )
                    );
                    break;
                case 'empty':
                    $_SESSION[$_REQUEST['method']] = [];
                    break;
                case 'remove':
                    $_SESSION[$_REQUEST['method']] = array_values(
                        array_diff($_SESSION[$_REQUEST['method']], [$_REQUEST['asset']])
                    );
                    break;
                default:
                    throw new Exception('Unrecognized ' . $_REQUEST['method']  . ' action: ' . $_REQUEST['action']);
            }
            $response->count = count($_SESSION[$_REQUEST['method']]);
            break;
        case 'html':
            ob_start();
            try {
                switch ($_REQUEST['html']) {
                    case 'attributeSelects':
                        ISLE\ViewUtility::attributeSelects($_REQUEST['attributes'] ?? []);
                        break;
                    case 'returns':
                        require 'includes/views/cart.php'; // Offcanvas
                        break;
                    default:
                        if (!preg_match('/^[A-Za-z]+$/', $_REQUEST['html'])) {
                            throw new Exception('Invalid html request: ' . $_REQUEST['html']);
                        }
                        $html_path = 'includes/views/' . $_REQUEST['html'] . '.php';
                        if (!file_exists($html_path)) {
                            throw new Exception('Html request does not exist: ' .$_REQUEST['html']);
                        }
                        try {
                            require $html_path;
                        } catch (Exception $e) {
                            throw $e;
                            throw new Exception('An error occured while handling html request for ' . $_REQUEST['html']);
                        }
                }
            } catch (Exception $e) {
                ob_end_clean();
                throw $e;
            }
            $response->html = trim(ob_get_clean());
            unset($_SESSION['post']);
            break;
        case 'sql':
            if ($_SESSION['user']['role'] < ISLE\DataModels\Role::ADMINISTRATOR) {
                throw new Exception('You are not authorized to perform this action.');
            }
            switch ($_REQUEST['action']) {
                case 'export':
                    $response->sql = ISLE\Service::export();
                    break;
                default:
                    throw new Exception('Unrecogized SQL action: ' . $_REQUEST['action']);
            }
            break;
        case 'upload':
            $response->hash = [];
            foreach ($_FILES as $file) {
                if ($file['error'] ?? false) {
                    $errors = [
                        0 => 'There is no error, the file uploaded with success',
                        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                        3 => 'The uploaded file was only partially uploaded',
                        4 => 'No file was uploaded',
                        6 => 'Missing a temporary folder',
                        7 => 'Failed to write file to disk.',
                        8 => 'A PHP extension stopped the file upload.',
                    ];
                    throw new Exception($errors[$file['error']]);
                }
                $response->hash[] = ISLE\Service::addUpload($file);
            }
            break;
        default:
            throw new Exception('Unrecognized method: ' . $_REQUEST['method']);
    }
} catch (Exception $e) {
    $response->error = $e->getMessage();
}
exit(json_encode($response));
