<?php

namespace gazedb;

use \Exception;

class SQLException extends Exception {
  private $query;

  public function __construct($query, $message, $code = 0, Exception $previous = null) {
    parent::__construct($message, $code, $previous);
    $this->query = $query;
  }
  /**
   * @return string
   */
  public function getQuery() {
    return $this->query;
  }
}
