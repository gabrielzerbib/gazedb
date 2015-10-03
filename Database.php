<?php

namespace gazedb;

/**
 * Class Database
 * @package gazedb
 *
 * A simple wrapper for a \PDO singleton.
 * Enables for credentials injection, so that
 * the bootstrap of the app injects the creds without
 * performing an actual connection, and then
 * anywhere in the app, when the DB is needed,
 * just invoke Database::pdo()
 */
class Database
{

  /**
   * @var \PDO
   */
  private $pdo = null;

  /**
   * @var Database[]
   */
  private static $connections = [];

  private $dsn;
  private $username;
  private $password;
  private $connectionName;


  private function __construct($connectionName)
  {
    $this->connectionName = $connectionName;
  }

  /**
   * @param string $connectionName
   * @return Database
   */
  public static function get($connectionName = '')
  {
    if (! isset(self::$connections[$connectionName])) {
      self::$connections[$connectionName] = new self($connectionName);
    }
    return self::$connections[$connectionName];
  }


  public static function injectIniFile($iniFilename, $section)
  {
    if (('' == $iniFilename) || (!file_exists($iniFilename))) {
      throw new \Exception('Missing DB config file.');
    }

    $config = parse_ini_file($iniFilename, true);


    self::get()->dsn = $config[$section]['dsn'];
    self::get()->username = $config[$section]['username'];
    self::get()->password = $config[$section]['password'];
  }


  public static function injectPdo(\PDO $pdo, $connectionName = '') {
    self::get($connectionName)->pdo = $pdo;
  }

  /**
   * This static injector is mainly useful for Unit Tests,
   * until we implement a proper Dependency Injection container in the project.
   *
   * @param $dsn
   * @param $username
   * @param $password
   */
  public static function injectDsn($dsn, $username, $password)
  {
    self::get()->dsn = $dsn;
    self::get()->username = $username;
    self::get()->password = $password;
  }

  /*
     * @return \PDO
     */
  public static function pdo($connectionName = '')
  {
    return self::get($connectionName)->getPdo();
  }

  /**
   * @return \PDO
   */
  public function getPdo()
  {
    if (null == $this->pdo) {
      $this->pdo = new \PDO($this->dsn, $this->username, $this->password);
      $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
    return $this->pdo;
  }


  /**
   * Inserts an entity record in the database, corresponding to the specified GAZE_ModelObject.
   * All the fields are specified in the VALUES clause, not only the dirty ones.
   * If the object already exists in database, the method throws a DuplicateKeySQLException,
   * and the object remains unmodified.
   * If the insertion is successful, the objet becomes clean, and its autoIncrement mapped field
   * is assigned with the new id.
   *
   * @param ModelObject $object
   * @return integer
   * @throws DuplicateKeySQLException
   */
  public static function insert(ModelObject & $object) {
    $table = $object->table();

    //Prepare the list of inserted fields and values
    $clone = clone $object;
    $clone->setAllDirty();
    $dirtyFields = $clone->getDirtyFields();

    $autoIncrementField = $object->mapAutoIncrement();

    $insertFields = array();
    $insertValues = array();
    foreach($dirtyFields as $dirtyField) {
      $insertValue = $object->column($dirtyField);
      if(null === $insertValue) {
        if($dirtyField != $autoIncrementField) {
          $insertFields[] = '`' . str_replace('`', '', $dirtyField) . '`';
          $insertValues[] = 'null';
        }
      }
      else if(false === $insertValue) {
        $insertFields[] = '`' . str_replace('`', '', $dirtyField) . '`';
        $insertValues[] = "0";
      }
      else if (is_numeric($insertValue) && (! is_string($insertValue))) {
        $insertFields[] = '`' . str_replace('`', '', $dirtyField) . '`';
        $insertValues[] = $insertValue;
      }
      else {
        $insertFields[] = '`' . str_replace('`', '', $dirtyField) . '`';
        $insertValues[] = self::pdo()->quote($insertValue);
      }
    }

    $insertFieldsList = implode(', ', $insertFields);
    $insertValuesList = implode(', ', $insertValues);


    //Perform the insert query.
    $query = "
      insert into `$table` ( $insertFieldsList )
        values ( $insertValuesList )
		";


    $throw = function (\PDOException $ex = null) use ($query) {
      $errorCode = self::pdo()->errorInfo() [1];
      $errorMsg = self::pdo()->errorInfo() [2];

      $errCodeDuplicateKey = constant(__NAMESPACE__.'\\dialects\\'.self::get()->getDriverName().'\\ErrorCodes::DUPLICATE_KEY');

      if ($errorCode == $errCodeDuplicateKey) {
        throw new DuplicateKeySQLException($query, $errorMsg, $ex);
      }
      throw new SQLException($query, $errorMsg, $errorCode, $ex);
    };

    try {
      if (false === self::pdo()->exec($query)) {
        $throw ();
      }
    } catch (\PDOException $ex) {
      $throw ($ex);
    }

    $insertID = self::pdo()->lastInsertId();
    $object->clean($insertID);

    //Return the inserted id
    return $insertID;
  }

  /**
   * @param ModelObject $object
   * @param array $sorting
   * @return bool
   * @throws ObjectNotFoundException
   */
  public function loadFirst(ModelObject $object, array $sorting = [])
  {
    return $this->loadWithOptions($object, true, $sorting);
  }

  /**
   * @param ModelObject $object
   * @param bool|false $loadFirst
   * @param array $sorting
   * @return bool
   * @throws MoreThanOneObjectException
   * @throws ObjectNotFoundException
   */
  private function loadWithOptions(ModelObject $object, $loadFirst = false, array $sorting = [])
  {
    $object->clean();
    $table = $object->table();

    //Prepare the Select clause: retrieve all the fields mapped by the object,
    $selectFields = array_keys($object->mapFields());
    //prefix the field names with the name of the table:
    for($i = 0; $i < count($selectFields); ++$i) {
      $selectFields[$i] = "`$table`." . $selectFields[$i];
    }
    $selectList = implode(', ', $selectFields);


    //Prepare the WHERE clause
    $whereKeys = $object->getInitialPK();
    $whereFields = array();
    foreach($whereKeys as $whereKey => $whereValue)
    {
      if(null === $whereValue)
        $whereCompareAndValue = ' is null';
      else if (is_numeric($whereValue) && (! is_string($whereValue)))
        $whereCompareAndValue = " = $whereValue";
      else
        $whereCompareAndValue = " = " . $this->getPdo()->quote($whereValue);
      $whereFields[] = "`$table`.`$whereKey` $whereCompareAndValue";
    }
    $whereList = implode(' and ', $whereFields);


    // If we're only loading the first occurrence of a given object's example.
    // we can optionally sort by the 'field' => 'asc|desc' prescription in $sorting.
    $orderBy = '';
    $orderByClause = '';
    if ($loadFirst && count($sorting)) {
      $orderBy = 'order by';
      $orderPieces = array_map(function ($fieldName, $direction) {
        return $fieldName . ' ' . $direction;
      }, array_keys($sorting), array_values($sorting));
      $orderByClause = implode(', ', $orderPieces);
    }

    $query = "
      select
        $selectList
      from `$table`
      where
        $whereList
      $orderBy
        $orderByClause
      limit 2
    ";

    $recordset = $this->getPdo()->query($query);


    $record = $recordset->fetch(\PDO::FETCH_ASSOC);

    if (null == $record) {
      $recordset->closeCursor();
      throw new ObjectNotFoundException($query, get_class($object), $object->getInitialPK());
    }
    // Optionally throw a "more than one object found" in case of load() normal (not first)
    else if ( (! $loadFirst) && (null != ($recordset->fetch())) ) {
      $recordset->closeCursor();
      throw new MoreThanOneObjectException($query);
    }

    $recordset->closeCursor();
    $object->wrap($record);
    return true;
  }

  /**
   * @param ModelObject $object
   * @return bool
   * @throws MoreThanOneObjectException
   * @throws ObjectNotFoundException
   */
  public static function load(ModelObject $object)
  {
    return self::get()->loadWithOptions($object);
  }

  /**
   * Deletes a record from the database,
   * corresponding to specified model object.
   * Returns true if the deletion occurred really.
   *
   * @param ModelObject $object
   * @return boolean
   */
  public static function delete(& $object) {
    $object->clean();
    $table = $object->table();

    //Prepare the WHERE clause
    $whereKeys = $object->getInitialPK();
    $whereFields = [];
    foreach($whereKeys as $whereKey => $whereValue) {
      if(null === $whereValue) {
        $whereCompareAndValue = ' is null';
      }
      else {
        $whereCompareAndValue = " = " . self::pdo()->quote($whereValue);
      }
      $whereFields[] = "`$table`.`$whereKey` $whereCompareAndValue";
    }
    $whereList = implode(' and ', $whereFields);

    $query = "
      delete
      from `$table`
      where
        $whereList
    ";

    $rowCount = self::pdo()->exec($query);
    return ($rowCount == 0 ? false : true);
  }



  /**
   * Updates an entity record.
   *
   * @param ModelObject $object
   * @param array $criteria An assoc array <field, matching value>, conveying specific criteria for the where clause.
   * @return boolean
   */
  public static function update(ModelObject $object, $criteria = null) {
    $table = $object->table();

    //Prepare the SET clause
    $dirtyFields = $object->getDirtyFields();

    //If nothing to update, get out.
    if(sizeof($dirtyFields) == 0) {
      return false;
    }

    $setFields = array();
    foreach($dirtyFields as $dirtyField) {
      $setValue = $object->column($dirtyField);
      if(null === $setValue)
        $setValue = 'null';
      else
        $setValue = self::pdo()->quote($setValue);
      $setFields[] = "`$dirtyField` = $setValue";
    }

    $setList = implode(', ', $setFields);

    //Prepare the WHERE clause:

    //Use the initial values of the PK fields.
    //But if a Criteria array was passed, it must be used instead.
    if(null != $criteria) {
      $whereKeys = $criteria;
    }
    else {
      $whereKeys = $object->getInitialPK();
    }
    $whereFields = array();
    foreach($whereKeys as $whereKey => $whereValue) {
      if(null === $whereValue) {
        $whereCompareAndValue = ' is null';
      }
      else if(is_numeric($whereValue) && (! is_string($whereValue))) {
        $whereCompareAndValue = " = $whereValue";
      }
      else {
        $whereCompareAndValue = " = " . self::pdo()->quote($whereValue);
      }
      $whereFields[] = "`$whereKey` $whereCompareAndValue";
    }
    $whereList = implode(' and ', $whereFields);

    $query = "
      update
        `$table`
      set
        $setList
      where
        $whereList
    ";

    $affectedRows = self::pdo()->exec($query);

    //Return Success if one and only one record was modified.
    if($affectedRows == 1) {
      $object->clean();
      return true;
    }
    return false;
  }


  /**
   * Returns the comma-separated list of fields of the model object,
   * to be used in a SELECT clause.
   *
   * @param string $tablePrefix If specified, each item in the selected list is fully qualified with this table prefix.
   * @return comma-separated list of fields, suitable for the SELECT clause of a MySQL query.
   */
  public static function selectClause($fieldsMap, $tablePrefix = null, $aliasPrefix = null) {
    $fields = array_keys($fieldsMap);
    for($i = 0; $i < count($fields); ++$i) {
      $initialName = $fields[$i];
      if( ($initialName[0] != '`') && (null == $tablePrefix) ) {
        $fields[$i] = '`'.$initialName.'`';
      }
      if($tablePrefix !== null) {
        $fields[$i] = $tablePrefix . '.' . $fields[$i];
      }
      if($aliasPrefix !== null) {
        $fields[$i] .= ' as ' . $aliasPrefix . $initialName;
      }
    }
    return implode(',', $fields);
  }


  public function getDriverName()
  {
    return $this->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
  }
}
