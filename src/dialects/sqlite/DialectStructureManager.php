<?php
namespace gazedb\dialects\sqlite;

use gazedb\ModelObject;
use gazedb\StructureManager;
use ReflectionClass;

class DialectStructureManager extends StructureManager
{

  public function createTable($className)
  {

    /** @var ModelObject $modelClass */
    $modelClass = $className;

    $fields = array_keys($modelClass::mapFields());

    // Let's look for DB Type hints
    $typeHints = [];
    $reflector = new ReflectionClass($className);
    $method = $reflector->getMethod('mapFields');
    $phpDoc = $method->getDocComment();
    if ($phpDoc) {
      $docblockLines = preg_split('/\r?\n/', $phpDoc);
      foreach ($docblockLines as $line) {
        if (preg_match('#^\s*\\*\s*@datatype\s+(\w+)\s+(int)\s*$#', $line, $matches)) {
          $fieldConst = $matches[1];
          $fieldName = constant($className . '::' . $fieldConst);
          $type = $matches[2];
          $typeHints[$fieldName] = $type;
        }
      }
    }

    /** @var ModelObject $modelObject */
    $modelObject= new $modelClass ();
    $autoinc = $modelObject->mapAutoIncrement();

    $fieldsSpec = array_map(
      function($fieldName) use ($autoinc, $typeHints) {
        if ($fieldName == $autoinc) {
          return $fieldName . ' integer';
        }
        if (isset($typeHints[$fieldName])) {
          if ($typeHints[$fieldName] == 'int') {
            return $fieldName . ' integer';
          }
        }
        return $fieldName . ' text';
      }, $fields
    );

    $tableDef = implode(', ', $fieldsSpec);

    $pk = $modelObject->mapPK();
    if (count($pk)) {
      $pkSpec = 'primary key (' . implode(',', $pk) . ')';
      $tableDef .= ', ' . $pkSpec;
    }

    $query = "
      create table ".$modelClass::table()." (".$tableDef.")
    ";
    $this->database->pdo()->query($query);
  }

  public function dropTable($modelClass)
  {
    // Check that argument is a class name of a ModelObject subclass:
    $reflector = new ReflectionClass($modelClass);
    if (! $reflector->isSubclassOf(ModelObject::clazz()) ) {
      throw new \Exception('Invalid call of dropTable: must pass class name of ModelObject subclass.');
    }

    // Obtain name of table:
    $tableName = $reflector->getMethod('table')->invoke(null);

    $query = "drop table $tableName";
    $this->database->pdo()->query($query);
  }
}
