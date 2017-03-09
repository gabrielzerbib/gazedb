<?php

namespace gazedb;

/**
 * @author Gabriel Zerbib <gabriel@plenitech.fr>
 */

abstract class ModelObject {

    private static $_accessorsMap = [];

    /**
     * Array of the name of the fields that have been
     * modified from their original DB value.
     * @access protected
     * @var array
     */
    protected $dirty;

    /**
     * Associative array of the columns=>values of the entity in DB.
     * @var array
     */
    protected $columns;

    /**
     * Associative array : field names constituting the primary key of
     * the entity represented by the object => initial value.
     * The initial value is stored at every call to clean(), so that
     * the original value of a key field is kept despite subsequent modifications?
     * in order to build the proper Where clause in case of Update.
     *
     * @var array
     */
    protected $pk;

    /**
     * Name of the auto-increment field, if any.
     * After successful insert, the column whose name is specified in this attribute
     * receives the newly generated id.
     *
     * @return string
     */
    public function mapAutoIncrement() {
        return null;
    }

    /**
     * @param object|array $record Object with columns as properties, or array with columns as keys.
     * @param string $prefix The optional prefix to every natural object's field, in the recordset column names.
     * @param array $columnTranslator Optional. An associative array mapping recordset columns to real object's fields.
     */
    public function __construct($record = null, $prefix = null, $columnTranslator = null) {
        $this->dirty = array();

        //Store the current value of the fields that are part of the primary key
        //so that these copies will build up the proper Where clause in a subsequent Update.
        // Force the name of columns to lowercase.
        $this->columns = $this->mapFields();
        $this->pk = array_fill_keys($this->mapPK(), null);

        if(null !== $record) {
            $this->wrap($record, $prefix, $columnTranslator);
        }
    }

    /**
     * Workaround for php5.5+ ::class magic keyword
     * @return string
     */
    public static function clazz()
    {
        return get_called_class();
    }

    /**
     * Must override. Returns the associative array of the columns mapping
     * (column name => default value)
     * @return array
     */
    public static function mapFields() {
        throw new IncompleteModelClassException(get_called_class());
    }
    /**
     * Returns the name of the table.
     * Any subclass MUST implement.
     * @return string
     */
    protected static function tableName()
    {
        throw new IncompleteModelClassException(get_called_class());
    }

    public static function table() {
        return static::tableName();
    }

    /**
     * Should override, if the table has a PK (single- or multiple-field).
     * Returns an array of field names.
     * @return array
     */
    public function mapPK() {
        return array();
    }

    /**
     * Returns a copy of the PK Fields => their initial values
     * for the current object since the last time it was saved.
     *
     * @return array
     */
    public function getInitialPK() {
        return $this->pk;
    }

    public function setAllDirty() {
        $this->dirty = array_fill_keys(array_keys($this->columns), true);
    }

    /**
     * Set the object in a saved state,
     * which means that it is in sync with its database version.
     * Capture the current state of the Primary Key fields,
     * in order to be able to build the Where clause of a subsequent
     * Update query.
     *
     * @param string|null $insertID
     * @return ModelObject
     */
    public function clean($insertID = null) {
        //Assign the passed arugment
        //to the field mapped as the auto-increment field of the object.
        if ((null != $insertID) && (null != $this->mapAutoIncrement()))
            $this->assign($this->mapAutoIncrement(), $insertID);

        $this->dirty = array();

        //Take a snapshot of the fields' values that are part of the primary key.
        $pkeys = $this->mapPK();
        $this->pk = array();
        foreach($pkeys as $key) {
            $this->pk[$key] = $this->column($key);
        }
        return $this;
    }

    /**
     * Tells the object to consider the specified field
     * as synchronized with the database.
     * If the field is part of the primary key,
     * the local copy of the pk of the object is updated to the current
     * value of the cleaned field.
     *
     * @param string $field
     */
    public function cleanField($field) {
        unset($this->dirty[$field]);
        if(in_array($field, $this->mapPK())) {
            $this->pk[$field] = $this->columns[$field];
        }
    }
    /**
     * Returns the name of columns whose values were
     * changed since the last time the object was saved.
     *
     * @return array
     */
    public function getDirtyFields()
    {
        return array_keys($this->dirty);
    }

    /**
     * @param string $fieldName
     */
    protected function dirty($fieldName) {
        $this->dirty[$fieldName] = true;
    }
    /**
     * @param string $fieldName
     */
    protected function clear($fieldName)
    {
        unset($this->dirty[$fieldName]);
    }
    /**
     * @param string $fieldName
     * @param mixed $value
     * @return ModelObject
     */
    public function assign($fieldName, $value) {
        $this->columns[$fieldName] = $value;
        $this->dirty($fieldName);
        return $this;
    }

    /**
     * Returns the value of the specified column
     *
     * @param string $columnName
     * @return mixed
     * @throws UnmappedFieldException
     */
    public function column($columnName) {
        if(! array_key_exists($columnName, $this->columns)) {
            throw new UnmappedFieldException($columnName, static::table(), get_class($this));
        }
        return $this->columns[$columnName];
    }

    /**
     * Returns the associative array of the columns and values
     * representing the record.
     *
     * @return array
     */
    public function getSelectClause() {
        $selectColumns = array();
        foreach($this->columns as $column => $value) {
            $selectColumns["`$column`"] = $value;
        }
        return $selectColumns;
    }

    /**
     * This method allows the binding of a ModelObject
     * to the object representing a recordset row
     * resulting from a query that would select a subset of
     * the entity fields of the ModelObject class.
     * Thus binded, the object is supposed to reflect the DB state,
     * therefore the corresponding wrapped fields are clean (un-dirty).
     *
     * One can provide an optional parameter as an associative array,
     * wich is used to translate the name of the fields
     * from the recordset, into the original names in the db model.
     *
     * @param object $row Can be an associative array as well.
     * @param string $prefix The optional prefix to every field.
     * @param array $columnTranslator
     * @return ModelObject
     */
    public function wrap($row, $prefix = '', $columnTranslator = null ) {

        // Array keys are case-sensitive. Let's work all lowercase.
        $prefix = strtolower($prefix);

        if(is_array($row)) {
            $obj = new \stdClass();
            foreach($row as $key => $value) {
                //Force db field name to lowercase, as must be the columns mapped in ModelObject,
                //because object properties are case-sensitive in PHP.
                $key = strtolower($key);
                $obj->$key = $value;
            }
            $row = $obj;
        }

        //Detect whether $columnTranslator is an associative array
        $bAssociative = false;
        if( ($columnTranslator !== null) && (count($columnTranslator) > 0) ) {
            if(! is_numeric(key($columnTranslator)))
                $bAssociative = true;
        }

        $recordColumns = get_object_vars($row);

        foreach($recordColumns as $column=>$value) {
            //Strip the prefix from the column alias name.
            if($prefix)
                $modelColumn = preg_replace('#^' . $prefix . '#', '', $column);
            else
                $modelColumn = $column;

            //If no translator was provided, we simply need to detect whether
            //the current column name of the recordset is a field attribute of our object.
            if($columnTranslator === null) {
                if(array_key_exists($modelColumn, $this->columns))
                {
                    $this->columns[$modelColumn] = $value;
                    $this->clear($modelColumn);
                }
            }

            //If a sequential array was passed, then it contains the subset
            //of the row's attributes that we want to map.
            else if(! $bAssociative) {
                if(in_array($column, $columnTranslator))
                    if(array_key_exists($modelColumn, $this->columns))
                    {
                        $this->columns[$modelColumn] = $value;
                        $this->clear($modelColumn);
                    }
            }

            //If an associative array was passed, then it contains the dictionary
            //row's column => object's field.
            //Caution: here we don't need to strip the prefix. Normally,
            //a prefix is not provided alongside with a translation map,
            //because a translation map is here to explicit which aliased columns must map which object's field.
            else {
                if(isset($columnTranslator[$column]))
                    if(array_key_exists($columnTranslator[$column], $this->columns))
                    {
                        $this->columns[$columnTranslator[$column]] = $value;
                        $this->clear($columnTranslator[$column]);
                    }
            }
        }

        $this->clean();
        return $this;
    }

    /**
     * Returns an associative array representing the changes that occurred between Source and Target objects
     * array( [field] => array(oldValue, newValue), ... )
     * or null if no change to report.
     *
     * @param ModelObject $source
     * @return array
     */
    public function changeSet($source) {
        $sourceValues = $source->getSelectClause();
        $targetValues = $this->getSelectClause();

        $changeSet = array();
        foreach($targetValues as $field => $value) {
            if(! array_key_exists($field, $sourceValues)) {
                $changeSet[$field] = array(null, $value);
            }
            else if($value != $sourceValues[$field]) {
                $changeSet[$field] = array($sourceValues[$field], $value);
            }
        }

        if(count($changeSet)) {
            return $changeSet;
        }
        return null;
    }

    /**
     * Returns the associative array representation of current field values
     * You can provide an optional field name transformation map: [dbFieldName => representation key].
     * @param array|null $fieldsMap
     * @return array
     */
    public function record(array $fieldsMap = null)
    {
        // If no map, simply return the internal representation as is
        if (null == $fieldsMap) {
            return $this->columns;
        }

        // With a map, transform every key for which the map gives a transformed name
        $result = [];
        foreach ($this->columns as $key => $value) {
            if (array_key_exists($key, $fieldsMap)) {
                $result[$fieldsMap[$key]] = $value;
            }
            else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Returns a hash representation of the current state of the object,
     * based on its record() values. This is useful in Edit forms, when
     * you want to make sure that the object you're about to storing back to db
     * is not in conflict with what the user thought he was modifying.
     * @return string
     */
    public function shadow()
    {
        return md5(print_r($this->record(), true));
    }

    /**
     * Catch-all function for the get/set accessors.
     * The function resolves the underlying database field for specified get/setSomething() invocation,
     * and caches the map for future use.
     * @param $name
     * @param $arguments
     * @return ModelObject|mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        $accessMode = substr($name, 0, 3);
        if (($accessMode == 'get') || ($accessMode == 'set')) {


            if (isset(self::$_accessorsMap[static::class])) {
                $accessors = self::$_accessorsMap[static::class];
            }
            else {
                // Firstly
                // Build an associative array: CamelCasePropName => database_field
                $accessors = array_combine(array_map(function ($field) {
                    return str_replace('_', '', ucwords($field, '_'));
                }, array_keys(static::mapFields())), array_keys(static::mapFields()));


                // Then, override the default mapping with what is explicitly defined
                // on the mapFields method phpdoc block

                $classReflector = new \ReflectionClass($this);
                $methodReflector = new \ReflectionMethod($this, 'mapFields');
                $docBlock = $methodReflector->getDocComment();
                // We'll extract the @column lines in the doc block:
                // they're in the form:
                //  @column <CONST_NAME> <accessor>
                // where <CONST_NAME> is the const declared in the class, holding the column name,
                // and <accessor> is the name on which the accessor is based (eg. getAccessor)
                foreach (explode("\n", $docBlock) as $line) {
                    $matches = null;
                    if (preg_match('#@column\\s+([^\\s]+)\\s+([^\\s]+)#', $line, $matches)) {
                        $accessorName = ucfirst($matches[2]);
                        $fieldName = $classReflector->getConstant($matches[1]);
                        $accessors[$accessorName] = $fieldName;
                    }
                }


                self::$_accessorsMap[static::class] = $accessors;
            }


            // Retrieve which field is targated by our current get/set method
            $methodPrincipal = substr($name, 3);
            if (array_key_exists($methodPrincipal, $accessors)) {
                $targetField = $accessors[$methodPrincipal];

                // Field is found: work. Otherwise, an exception is thrown.

                if ('get' == $accessMode) {
                    return $this->column($targetField);
                } else {
                    return $this->assign($targetField, $arguments[0]);
                }


            }


        }

        throw new \Exception('Method not found: ' . static::class . '::' . $name);

    }
}
