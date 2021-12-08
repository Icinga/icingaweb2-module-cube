<?php

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Cube\IcingadbCube;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Orm\Query;

class IcingadbHostStatusCube extends IcingadbCube
{
    public function getRenderer()
    {
        return new IcingadbHostStatusCubeRenderer($this);
    }

    /**
     * @return Query|\ipl\Sql\Select
     * @throws ConfigurationError
     */
    public function prepareInnerQuery()
    {
        $query = Host::on($this->getDb());
        $query->disableDefaultSort();

        $query->with('state');
        $this->applyRestrictions($query);

        $this->innerQuery = $query;
        return $this->innerQuery;
    }

    /**
     * Provide a list of all available fact columns
     *
     * This is a key/value array with the key being the fact name / column alias
     *
     * @return string[]
     */
    public function getAvailableFactColumns()
    {
        return [
            'hosts_cnt'  => 'SUM(1)',
            'hosts_up'  => 'SUM(CASE WHEN host_state.soft_state = 0 THEN  1 ELSE 0 END)',
            'hosts_down'  => 'SUM(CASE WHEN host_state.soft_state = 1 THEN  1 ELSE 0 END)',
            'hosts_unhandled_down'  => 'SUM(CASE WHEN host_state.soft_state = 1'
                . ' AND host_state.is_handled = "n" THEN  1 ELSE 0 END)',
            'hosts_unreachable'  => 'SUM(CASE WHEN host_state.is_reachable = "n" THEN 1 ELSE 0 END)',
            'hosts_unhandled_unreachable' => 'SUM(CASE WHEN host_state.is_reachable = "n"'
                . ' AND host_state.is_handled = "n" THEN  1 ELSE 0 END)'
        ];
    }

    /**
     * @return \Generator
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function listAvailableDimensions()
    {
        $query = Host::on($this->getDb());

        $this->applyRestrictions($query);

        $query->getSelectBase()
            ->columns('customvar.name as varname')
            ->join('host_customvar', 'host_customvar.host_id = host.id')
            ->join('customvar', 'customvar.id = host_customvar.customvar_id')
            ->groupBy('customvar.name')
            ->orderBy('customvar.name');

        foreach ($query as $row) {
            yield $row->varname;
        }
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
     * Return the host name for the provided slices
     *
     * @return array
     * @throws ConfigurationError
     */
    public function getHostNames()
    {
        $query = Host::on($this->getDb());

        foreach ($this->slices as $dimension => $value) {
            $dimensionJunction = $dimension . '_junction';

            $query->getSelectBase()
                ->join(
                    "host_customvar {$dimensionJunction}",
                    "{$dimensionJunction}.host_id = host.id"
                )
                ->join(
                    "customvar {$dimension}",
                    "{$dimension}.id = {$dimensionJunction}.customvar_id 
                    AND {$dimension}.name = \"{$dimension}\""
                );
        }

        foreach ($this->getSlices() as $dimension => $value) {
            $query->getSelectBase()
                ->where("{$dimension}.value = '\"{$value}\"'");
        }

        $hosts = [];
        foreach ($query as $row) {
            $hosts[] = $row->name;
        }

        return $hosts;
    }
}
