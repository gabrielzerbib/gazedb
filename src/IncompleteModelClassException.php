<?php
namespace gazedb;

use \Exception;

class IncompleteModelClassException extends Exception
{
  public function __construct($class)
  {
    parent::__construct($class);
  }
}