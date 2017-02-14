# gazedb
Simple PDO wrapper for agile yet safe ORM and direct SQL queries for PHP


## 1. Abstract
Use this lib whenever you don't need a full-fledged ORM framework.

What gazedb **does not** provide:

- Comprehensive mapping of the full database schema
- Caching layer
- Declarative relationship syntax
- Lazy loading of collections

If you do not know how to write clean efficient SQL queries,
or if you do not understand how an index can help your architecture,
or if you plan on replacing your underlying DBMS vendor every other weekend,
you should use a different library (such as Doctrine). You might need to learn a new
querying language (Hibernate QL, Doctrine QL, etc.) and a complex family of framework-specific
annotation-based grammar to declare the relationship between your tables.



What gazedb **does** provide:

- Simple [PDO](http://php.net/manual/en/book.pdo.php) wrapper
- Normative constant-based notation for tables and columns names,
- Simple helpers for single-object CRUD operations,
- Simple syntax to assist in creating *your own real SQL queries* in a reusable way.

In most cases, your gazedb Model Objects do not know how they relate to each other.
The database intelligence remains in your hands (indexes, best way to join, when and how to fetch, etc.).

## 2. Model Object

Create one class per table, as child class of `ModelObject`.

~~~~php
class Employee extends ModelObject
~~~~


The subclass **must** implement the following methods:
- `tableName`
- `mapFields`

The ModelObject subclass **may** implement the following methods:
- `mapPK`
- `mapAutoIncrement`

### 2.1. Table name


~~~~php
/**
 * Must override. Returns the name of the table.
 * @return string
 */
protected static function tableName()
{
  return 'employees';
}
~~~~

You will never use `tableName()` directly.
This method is used in the ancestor's `table()` static method.

`Employee::table()` returns the DB table name that you configured for `Employee` class.

Usage:

~~~~php
$query = "select * from ".Employee::table()." limit 10"
~~~~

### 2.2. Columns
Declare Class constants for the field names of your table.

~~~~php
class Employee extends ModelObject
{
  public const ID = 'employee_id';
  public const LASTNAME = 'lastname';
  public const SALARY = 'salary';
  public const DEPARTMENT = 'dept_id';

~~~~

The `const` are not explicitly used by the library ; rather, they are a reusability helper, for you
to write non hard-coded DB identifiers in your code and queries.

You must indicate gazedb what columns are part of the data exchange, via method `mapFields`:

~~~~php
/**
 * Must override. Returns the associative array of the columns mapping
 * (column name => default value)
 * @return array
 */
public static function mapFields()
{
  return [
    self::ID => null,
    self::LASTNAME => '',
    self::SALARY => 0,
    self::DEPARTMENT => null
  ];
}
~~~~


And you may write mutators for your columns:

~~~~php
public function getLastname() { return $this->column(self::LASTNAME); }

/**
 * @return Employee
 */
public function setLastname($value) { return $this->assign(self::LASTNAME, $value); }
~~~~

Make your setters chainable by hinting the return type to same class.

#### Magic Accessors
You are not required to write explicit accessors. Instead, you can leverage the magic
`__get` and `__set` methods by declaring the mutators in the DocBlock of the class:

~~~~php
/**
 * @method string   getLastname()
 * @method Employee setLastname(string $value)
 * @method int      getSalary()
 * @method Employee setSalary(int $value)
 */
class Employee extends ModelObject
{
 ...
}
~~~~

By doing so, calling `$employee->getSalary()` will resolve to returning the value for
 column `salary`, following a camel-case, lower case first, naming convention. Underscores
 are discarded and used as camel-case word separator.


Alternatively, if the name of the fields are not human-readable and you still wish to not
develop explicitly your mutators, you can declare `@column` mappings in the DocBlock of 
the `mapFields` method:

~~~~php
  /**
   * @column <CONST_NAME> <accessor_name>
   * @column ... ...
   */
  public static function mapFields()
  {
    ...
  }
~~~~

This will instruct the Magic resolution that the field whose name is defined in const *CONST_NAME* of your class,
yields to a pair of accessors `getAccessorName()` and `setAccessorName()` with the same naming
convention as above.


### 2.3. Primary key
You can specify a primary key (incl. a multi-column one) by overriding the method:

~~~~php
    /**
     * Should override, if the table has a PK (single- or multiple-field).
     * Returns an array of field names.
     * @return array
     */
    public function mapPK()
~~~~

You never need to invoke this method explicitly.
A typical example of `mapPK` implementation is:

~~~~php
  public function mapPK()
  {
    return [ self::ID ];
  }
~~~~

### 2.4. Auto-increment
Provide an implementation for `mapAutoIncrement` if your table has an auto-numbering column which the
database assigns alone at insertion time.

~~~~php
/**
 * The name of the auto-increment column.
 * @return string
 */
public function mapAutoIncrement() {
    return self::ID;
}
~~~~

## 3. Build Your Queries

You write typical queries in your code by taking benefit from the `const` names.

~~~~php
$query = "
  select
    Employee.".Employee::ID.",
    Employee.".Employee::LASTNAME.",
    Dept.".Department::FLOOR."
  from
    ".Employee::table()." Employee
    inner join ".Department::table()." Dept
      on Dept.".Department::ID." = Employee.".Employee::DEPARTMENT."
  where
    Employee.".Employee::SALARY." > 10000
";
~~~~

So, you write real, valid SQL, in the target dialect of your choice (i.e. specific
syntax and functions of the actual SQL engine).

You may use the usual parameter binding that comes with PDO (the `:param` syntax) and
you should take care of SQL injection protection.

The only benefit you gain from writing your queries using the above recommendation, is
consistency and syntax checking on the names of tables and fields.

## 4. Database object

gazedb lets you handle several simultaneous connections to different data sources.
It uses the injectable Singleton pattern, with named instances.

~~~~php
$db = Database::get();
$archiveDB = Database::get('archive');
~~~~

You must invoke `injectDsn($dsnString, $username, $password)` on a Database instance, to specify the PDO connection string.

Then, you manipulate the underlying PDO instance directly, with:

~~~~php
$db->pdo()
~~~~

which returns your plain and well-known PDO object, for you to execute:

~~~~php
$recordset = $db->pdo()->query($query);
~~~~

### 4.1. CRUD operations

In addition to the underlying PDO operations, made directly on the $database->pdo() instance,
**gazedb** offers simple CRUD auto-mapping methods for those Model objects that map a primary key.

#### 4.1.1. Load

~~~~php
// "new" does not create anything in DB
$employee = new Employee();

// Specify the key value for the employee you wish to load:
$employee->setId(12);

// Fetch the record and auto-set all the mapped columns (see mapFields() method)
$database->load($employee);

echo $employee->getLastname();
~~~~


In case the record could not be found for specified primary key, the `Database::load` method
will throw an `ObjectNotFoundException`.

#### 4.1.2. Update

Once you have a populated object, you can modify any field with its mutators, and save it back
to the database with the `udpate` method.

~~~~php
$employee->setFloor(8);
$database->update($employee);
~~~~

The `update` method produces a query which only changes the values you modified explicitly,
leaving all the other fields untouched.

*Notice*: gazedb does not check the state of the object before storing it back to database.
If the record was changed by another process or connection, your **udpate** statement will
still be issued without your knowing it.

Likewise, `update` does not re-fetch the object's fields if they were changed in database since
your previous call to `load`.

#### 4.1.3. Insert

~~~~php
$employee = new Employee();
$employee
  ->setLastname('Smith')
  ->setFloor(10)
  ->setSalary(60000);

$database->insert($employee);

echo $employee->getId();
~~~~

The `insert` method fires an insert query, optionally assigning the auto-increment value
to the mapped auto-increment field if you specified one in your Model object.

After the `insert` call, your object is considered "clean" and you can modify its values again
through the mutators, before performing an `update` on the modified values only.
