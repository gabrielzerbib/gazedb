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
or if plan on replacing your underlying DBMS vendor every other weekend,
you should use a different library (such as Doctrine). You might need to learn a new
querying language (Hibernate QL, Doctrine QL, etc.) and a complex family of framework-specific
annotation-based grammar to declare the relationship between your tables.



What gazedb **does** provide:

- Normative constant-based notation for tables and columns names,
- Simple helpers for single-object CRUD operations,
- Simple syntax to assist in creating *your own real SQL queries* in a reusable way.
