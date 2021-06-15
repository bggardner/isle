<?php
namespace ISLE;

class ErrorException extends \ErrorException
{

    public function __construct($message = "", $code = 0, $severity = E_ERROR, $filename = null, $line = null, $previous = null)
    {
        $error_types = [
            'E_ERROR',
            'E_WARNING',
            'E_PARSE',
            'E_NOTICE',
            'E_CORE_ERROR',
            'E_CORE_WARNING',
            'E_COMPILE_ERROR',
            'E_COMPILE_WARNING',
            'E_USER_ERROR',
            'E_USER_WARNING',
            'E_USER_NOTICE',
            'E_STRICT',
            'E_RECOVERABLE_ERROR',
            'E_DEPRECATED',
            'E_USER_DEPRECATED'
        ];
        $error_types = array_combine($error_types, array_map('constant', $error_types));

        $message =
            'A server error has occurred. Please contact the webmaster with the following details:<br><br><pre>' .
            array_search($severity, $error_types) .
            (empty($message) ? '' : ': ' . $message) .
            (is_null($filename) ? '' : ' in ' . $filename) .
            (is_null($line) ? '' : ' on line ' . $line) .
            '</pre>';

        parent::__construct($message, $code, $severity, $filename, $line, $previous);
    }

    public static function fromError(\Error $e)
    {
        return new self($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine(), $e->getPrevious());
    }

}
