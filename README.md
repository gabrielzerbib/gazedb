# gazedb
Simple PDO wrapper for agile yet safe ORM and direct SQL queries for PHP


## Abstract
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

## Model Object

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

### Table name


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

### Columns
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


And you must write mutators for your columns:

~~~~php
public function getLastname() { return $this->column(self::LASTNAME); }

/**
 * @return Employee
 */
public function setLastname($value) { return $this->assign(self::LASTNAME, $value); }
~~~~

Make your setters chainable by hinting the return type to same class.

### Primary key
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

### Auto-increment
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

## Build Your Queries

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

## Database object

gazedb lets you handle several simultaneous connections to different data sources.
It uses the injectable Singleton pattern, with named instances.

~~~~php
$db = Database::get();
$archiveDB = Database::get('archive');
~~~~

You must invoke `injectDsn` on a Database instance, to specify the PDO DSN string.

Then, you manipulate the underlying PDO instance directly, with:

~~~~php
$db->pdo()
~~~~

which returns your plain and well-known PDO object, for you to execuyte:

~~~~php
$recordset = $db->pdo()->query($query);
~~~~

### CRUD operations

...