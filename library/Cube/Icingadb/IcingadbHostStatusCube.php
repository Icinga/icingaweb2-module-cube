<?php

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Module\Cube\IcingadbCube;
use Icinga\Web\Hook;

class IcingadbHostStatusCube extends IcingadbCube
{
    public function __construct()
    {
        $this->db = $this->getDb();
    }

    public function getRenderer()
    {
        return new IcingadbHostStatusCubeRenderer($this);
    }

    public function getDb()
    {
        $this->db = $this->icingadbHosts()->getHostStateQuery()->getDb();
        return $this->db;
    }

    /**
     * Returns the IcingadbHosts hook
     *
     * @return mixed|null
     */
    public function icingadbHosts()
    {
        $icingadbHosts = null;

        foreach (Hook::all('Cube/IcingadbHosts') as $hook) {
            $icingadbHosts = $hook;
        }

        return $icingadbHosts;
    }

    public function getAvailableFactColumns()
    {
        return $this->icingadbHosts()->getAvailableFactColumns();
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
            $this->addDimension(new IcingadbCustomVarDimension($name, IcingadbCustomVarDimension::TYPE_HOST));
        }

        return $this;
    }

    /**
     * This returns a list of all available Dimensions
     *
     * @return array
     */
    public function listAvailableDimensions()
    {
        return $this->icingadbHosts()->listAvailableDimensions();
    }

    /**
     * Prepares inner query for obtaining facts
     *
     * @return \ipl\Orm\Query|\ipl\Sql\Select
     */
    public function prepareInnerQuery()
    {
        $query = $this->icingadbHosts()->getHostStateQuery();

        $this->innerQuery = $query;
        return $this->innerQuery;
    }

    /**
     * Returns host names
     *
     * @return mixed
     */
    public function getHostNames()
    {
        return $this->icingadbHosts()->getHostNames($this->getSlices());
    }
}
