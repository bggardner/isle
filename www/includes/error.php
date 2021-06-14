<?php

function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ISLE\ErrorException($message, 0, $severity, $file, $line);
}

function global_exception_handler($e)
{
    if (is_a($e, 'Error')) {
        $e = ISLE\ErrorException::fromError($e);
    }
    $is_js = false;
    foreach (headers_list() as $header) {
        $is_js = $is_js || preg_match('/javascript/', $header);
        if ($is_js) {
            break;
        }
    }
    if ($is_js) {
        echo $e->getMessage();
    } else {
        $_SESSION['message']['type'] = 'danger';
        $_SESSION['message']['text'] = $e->getMessage();
        require_once 'views/layouts/pagestart.php';
        require_once 'views/layouts/pageend.php';
        exit;
    }
}

function handle_fatal_errors()
{
    $redirect_error_types = array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR);

    $error = error_get_last();
    if ($error !== NULL && isset($error['type']) && in_array($error['type'], $redirect_error_types)) {
        $exception = new ISLE\ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        global_exception_handler($exception);
    }
}

//register_shutdown_function('handle_fatal_errors');
set_error_handler('exception_error_handler');
set_exception_handler('global_exception_handler');
