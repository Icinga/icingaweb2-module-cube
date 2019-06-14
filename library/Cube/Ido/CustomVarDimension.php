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
    const TYPE_HOST = 'host';

    const TYPE_SERVICE = 'service';

    /**
     * @var string custom variable name
     */
    protected $varName;

    /**
     * @var bool Whether null values should be shown
     */
    protected $wantNull = false;

    /** @var string Type of the custom var */
    protected $type;

    /**
     * CustomVarDimension constructor.
     *
     * @param $varName
     * @param   string  $type   Type of the custom var
     */
    public function __construct($varName, $type = null)
    {
        $this->varName = $varName;
        $this->type = $type;
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
        switch ($this->type) {
            case self::TYPE_HOST:
                $objectId = 'ho.object_id';
                break;
            case self::TYPE_SERVICE:
                $objectId = 'so.object_id';
                break;
            default:
                $objectId = 'o.object_id';
        }
        $name = $this->varName;
        $alias = 'c_' . $this->safeVarname($name);
        /** @var $cube IdoCube */
        $cube->innerQuery()->joinLeft(
            array($alias => $cube->tableName('icinga_customvariablestatus')),
            $cube->db()->quoteInto($alias . '.varname = ?', $name)
            . ' AND ' . $alias . '.object_id = ' . $objectId,
            array()
        )->group($alias . '.varvalue');
    }
}
