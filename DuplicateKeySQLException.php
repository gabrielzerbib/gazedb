<?php

namespace gazedb;

class DuplicateKeySQLException extends SQLException {
  public function __construct($query, $errMsg, \Exception $previous = null) {
    parent::__construct($query, $errMsg, Database::ERROR_DUPLICATE_KEY, $previous);
  }
}
