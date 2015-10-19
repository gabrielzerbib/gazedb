<?php
namespace gazedb;

abstract class StructureManager
{
  /** @var Database */
  protected $database;

  public function __construct(Database $database)
  {
    $this->database = $database;
  }

  public abstract function createTable($className);
  public abstract function dropTable($className);
}
