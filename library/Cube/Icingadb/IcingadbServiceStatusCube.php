<?php

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Module\Cube\IcingadbCube;
use Icinga\Web\Hook;

class IcingadbServiceStatusCube extends IcingadbCube
{
    public function __construct()
    {
        $this->db = $this->getDb();
    }

    public function getRenderer()
    {
        return new IcingadbStatusCubeRenderer($this);
    }

    public function getDb()
    {
        $this->db = $this->icingadbServices()->getServiceStateQuery()->getDb();

        return $this->db;
    }

    /**
     * Returns IcingadbServices hook
     *
     * @return mixed|null
     */
    public function icingadbServices()
    {
        $icingadbServices = null;

        foreach (Hook::all('Cube/IcingadbServices') as $hook) {
            $icingadbServices = $hook;
        }
        return $icingadbServices;
    }

    public function getAvailableFactColumns()
    {
        return $this->icingadbServices()->getAvailableFactColumns();
    }

    /**
     * This returns a list of all available Dimensions
     *
     * @return array
     */
    public function listAvailableDimensions()
    {
        return $this->icingadbServices()->listAvailableDimensions();
    }

    /**
     * Prepares innfer query for obtaining facts
     *
     * @return \ipl\Orm\Query|\ipl\Sql\Select
     */
    public function prepareInnerQuery()
    {
        $query = $this->icingadbServices()->getServiceStateQuery();

        $this->innerQuery = $query;

        return $this->innerQuery;
    }

    /**
     * Add a specific named dimension
     *
     * Right now this are just custom vars, we might support group memberships
     * or other properties in future
     *
     * @param string $name
     * @return $this
     */
    public function addDimensionByName($name)
    {
        if (count(array($name)) === 1) {
            $this->addDimension(new IcingadbCustomVarDimension($name, IcingadbCustomVarDimension::TYPE_SERVICE));
        }

        return $this;
    }
}
