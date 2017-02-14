<?php
namespace gazedb;

class UnmappedFieldException extends \Exception
{
    protected $table;
    protected $column;
    protected $class;
    /**
     * @param string $column
     * @param string $table
     * @param string $class
     */
    public function __construct($column, $table, $class)
    {
        $this->class = $class;
        $this->table = $table;
        $this->column = $column;
    }
}
