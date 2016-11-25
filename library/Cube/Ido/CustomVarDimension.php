<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;

/**
 * CustomVarDimension
 *
 * This provides dimenstions for custom variables available in the IDO
 *
 * TODO: create safe aliases for special characters
 *
 * @package Icinga\Module\Cube\Ido
 */
class CustomVarDimension implements Dimension
{
    /**
     * @var string custom variable name
     */
    protected $varName;

    /**
     * @var bool Whether null values should be shown
     */
    protected $wantNull = false;

    /**
     * CustomVarDimension constructor.
     *
     * @param $varName
     */
    public function __construct($varName)
    {
        $this->varName = $varName;
    }

    /**
     * Define whether null values should be shown
     *
     * @param bool $wantNull
     * @return $this
     */
    public function wantNull($wantNull = true)
    {
        $this->wantNull = $wantNull;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return strtolower($this->varName);
    }

    public function getColumnExpression()
    {
        if ($this->wantNull) {
            return 'COALESCE(c_' . $this->varName . ".varvalue, '-')";
        } else {
            return 'c_' . $this->varName . '.varvalue';
        }
    }

    protected function safeVarname($name)
    {
        return $name;
    }

    public function addToCube(Cube $cube)
    {
        /** @var $cube IdoCube */
        $name = $this->varName;
        $alias = 'c_' . $this->safeVarname($name);
        $cube->innerQuery()->joinLeft(
            array($alias => $cube->tableName('icinga_customvariablestatus')),
            $cube->db()->quoteInto($alias . '.varname = ?', $name)
            . ' AND ' . $alias . '.object_id = o.object_id',
            array()
        )->group($alias . '.varvalue');
    }
}
