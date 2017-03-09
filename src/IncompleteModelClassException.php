<?php
namespace gazedb;

use \Exception;

class IncompleteModelClassException extends Exception
{
    /**
     * @param string $class
     * @param string $message
     */
    public function __construct($class, $message = '')
  {
    parent::__construct($message ? $class . ': ' . $message : $class);
  }
}
