<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;
use Icinga\Module\Cube\MonitoringCube;

/**
 * CustomVarDimension
 *
 * This provides dimenstions for custom variables available in the IDO
 *
 * TODO: create safe aliases for special characters
 *
 * @package Icinga\Module\Cube\Icingadb
 */
class IcingadbCustomVarDimension implements Dimension
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
     * @var string custom variable label
     */
    protected $varLabel;

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
        if ($this->wantNull) {
            return "COALESCE({$this->varName}.value' , '-')";
        } else {
            return "{$this->varName}.value";
        }
    }

    protected function safeVarname($name)
    {
        return $name;
    }

    public function addToCube(Cube $cube)
    {
        $name = $this->safeVarname($this->varName);

        $dimensionJunction = $name . '_junction';

        $cube->innerQuery()->getSelectBase()
            ->join(
                "{$this->type}_customvar {$dimensionJunction}",
                "{$dimensionJunction}.{$this->type}_id = {$this->type}.id"
            )
            ->join(
                "customvar {$name}",
                "{$name}.id = {$dimensionJunction}.customvar_id
                    AND {$name}.name = \"{$name}\""
            );
    }
}
