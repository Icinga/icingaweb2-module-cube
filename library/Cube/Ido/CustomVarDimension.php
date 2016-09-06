<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\Dimension;

class CustomVarDimension implements Dimension
{
    protected $varname;

    public function __construct($varname)
    {
        $this->varname = $varname;
    }

    public function getName()
    {
        return $this->varname;
    }

    public function getColumnExpression()
    {
        return 'COALESCE(c_' . $this->varname . ".varvalue, '-')";
    }

    protected function safeVarname($name)
    {
        return $name;
    }

    public function addToQuery($query)
    {
        $name = $this->varname;
        $alias = 'c_' . $this->safeVarname($name);
        return $query->joinLeft(
            array($alias => $this->dbName . '.icinga_customvariablestatus'),
            $this->db->quoteInto($alias . '.varname = ?', $name)
            . ' AND ' . $alias . '.object_id = o.object_id',
            array()
        )->group($alias . '.varvalue');
    }
}
