<?php
namespace ISLE;

  class UIException extends \Exception
  {
      private $valErrors;

      public function __construct($message = "", $valErrors = [], $code = 0, \Exception $previous = null)
      {
          $this->valErrors = $valErrors;
          parent::__construct($message, $code, $previous);
      }

      public function getValErrors() {
          return $this->valErrors;
      }

  }
