<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;

class CustomVarDimension implements Dimension
{
    protected $varname;

    protected $wantNull = false;

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
        if ($this->wantNull) {
            return 'COALESCE(c_' . $this->varname . ".varvalue, '-')";
        } else {
            return 'c_' . $this->varname . '.varvalue';
        }
    }

    protected function safeVarname($name)
    {
        return $name;
    }

    public function addToCube(Cube $cube)
    {
        $name = $this->varname;
        $alias = 'c_' . $this->safeVarname($name);
        return $cube->innerQuery()->joinLeft(
            array($alias => $cube->tableName('icinga_customvariablestatus')),
            $cube->db()->quoteInto($alias . '.varname = ?', $name)
            . ' AND ' . $alias . '.object_id = o.object_id',
            array()
        )->group($alias . '.varvalue');
    }
}
