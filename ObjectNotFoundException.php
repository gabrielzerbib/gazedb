<?php
/**
 * @author Gabriel Zerbib <gabriel@bumpt.net>
 * @copyright 2004-2011, Gabriel Zerbib.
 * @version 1.0.0
 * @package GAZE
 * @subpackage GAZE.database
 */

namespace gazedb;

class ObjectNotFoundException extends SQLException {
  /**
   * @param string $query
   */
  public function __construct($query, $objClass, array $PKs = null) {
    $keys = array();
    if(null != $PKs) {
      foreach($PKs as $key => $value) {
        $keys []= $key . ' => ' . $value;
      }
    }
    if(count($keys)) {
      $listKeys = implode(', ', $keys);
    }
    else {
      $listKeys = '';
    }
    $message = 'Could not find object of class ' . $objClass . ' for key: (' . $listKeys . ').';
    parent::__construct($query, $message);
  }
}
