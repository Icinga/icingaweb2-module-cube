<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

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
    public const TYPE_HOST = 'host';

    public const TYPE_SERVICE = 'service';

    /**
     * @var string custom variable name
     */
    protected $varName;

    /**
     * @var string custom variable label
     */
    protected $varLabel;

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
        return $this->varName;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->varLabel ?: $this->getName();
    }

    /**
     * Set the label
     *
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->varLabel = $label;

        return $this;
    }

    /**
     * Add a label
     *
     * @param string $label
     * @return $this
     */
    public function addLabel($label)
    {
        if ($this->varLabel === null) {
            $this->setLabel($label);
        } else {
            $this->varLabel .= ' & ' . $label;
        }

        return $this;
    }

    public function getColumnExpression(Cube $cube)
    {
        /** @var IdoCube $cube */
        if ($this->wantNull) {
            return 'COALESCE(' . $cube->db()->quoteIdentifier(['c_' . $this->varName, 'varvalue']) . ", '-')";
        } else {
            return $cube->db()->quoteIdentifier(['c_' . $this->varName, 'varvalue']);
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
        $name = $this->safeVarname($this->varName);
        /** @var $cube IdoCube */
        $alias = $cube->db()->quoteIdentifier(['c_' . $name]);

        if ($cube->isPgsql()) {
            $on = "LOWER($alias.varname) = ?";
            $name = strtolower($name);
        } else {
            $on = $alias . '.varname = ? COLLATE latin1_general_ci';
        }

        $cube->innerQuery()->joinLeft(
            array($alias => $cube->tableName('icinga_customvariablestatus')),
            $cube->db()->quoteInto($on, $name)
            . ' AND ' . $alias . '.object_id = ' . $objectId,
            array()
        )->group($alias . '.varvalue');
    }
}
