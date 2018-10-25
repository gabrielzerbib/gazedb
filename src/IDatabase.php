<?php
namespace gazedb;

interface IDatabase
{
    /**
     * This is how you pass the DSN to the connection. The connection itself is not attempted until
     * the first effective use of the PDO object.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array|null $pdoOptions Optional array of options to pass to PDO constructor
     * @return $this
     */
    public function injectDsn($dsn, $username, $password, array $pdoOptions = null);

    /**
     * @return \PDO
     */
    public function pdo();

    /**
     * This is the only way to close a PDO connection, as per PHP manual.
     * For it to work, you need to be careful not to capture the ->pdo() object
     * in a variable by yourself, as the null assignment will only perform the connection termination
     * if the property is the last reference to the resource.
     * Caution: any reference to a statement (cursor) must also be closed!
     */
    public function terminate();

    /**
     * Inserts an entity record in the database, corresponding to the specified GAZE_ModelObject.
     * All the fields are specified in the VALUES clause, not only the dirty ones.
     * If the object already exists in database, the method throws a DuplicateKeySQLException,
     * and the object remains unmodified.
     * If the insertion is successful, the objet becomes clean, and its autoIncrement mapped field
     * is assigned with the new id.
     *
     * @param ModelObject $object
     * @throws DuplicateKeySQLException
     * @throws UnmappedFieldException
     */
    public function insert(ModelObject $object);

    /**
     * @param ModelObject $object
     * @return bool
     * @throws ObjectNotFoundException
     */
    public function load(ModelObject $object);

    /**
     * Performs a Select operation for specified object,
     * while specifying to the DB engine that you wish to lock the record
     * for potential update in the same transaction.
     * @param ModelObject $object
     * @return bool
     * @throws ObjectNotFoundException
     */
    public function loadForUpdate(ModelObject $object);

    /**
     * Deletes a record from the database,
     * corresponding to specified model object.
     * Returns true if the deletion occurred really.
     *
     * @param ModelObject $object
     * @return boolean
     */
    public function delete($object);

    /**
     * Updates an entity record.
     *
     * @param ModelObject $object
     * @param array $criteria An assoc array <field, matching value>, conveying specific criteria for the where clause.
     * @return boolean
     */
    public function update(ModelObject $object, $criteria = null);


}
