<?php

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Module\Cube\IcingadbCube;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Orm\Query;

class IcingadbServiceStatusCube extends IcingadbCube
{
    /**
     * @return Query|\ipl\Sql\Select
     */
    public function prepareInnerQuery()
    {
        $query = Service::on($this->getDb())->with('state');
        $query->disableDefaultSort();

        $this->applyRestrictions($query);

        $this->innerQuery = $query;

        return $this->innerQuery;
    }

    /**
     * @return string[]
     */
    public function getAvailableFactColumns()
    {
        return [
            'services_cnt'           => 'SUM(1)',
            'services_critical'           => 'SUM(CASE WHEN service_state.soft_state = 2 THEN  1 ELSE 0 END)',
            'services_unhandled_critical' => 'SUM(CASE WHEN service_state.soft_state = 2'
                . ' AND service_state.is_handled = "n" THEN  1 ELSE 0 END)',
            'services_warning'           => 'SUM(CASE WHEN service_state.soft_state = 1 THEN  1 ELSE 0 END)',
            'services_unhandled_warning' => 'SUM(CASE WHEN service_state.soft_state = 1'
                . ' AND service_state.is_handled = "n" THEN  1 ELSE 0 END)',
            'services_unknown'           => 'SUM(CASE WHEN service_state.soft_state = 3 THEN  1 ELSE 0 END)',
            'services_unhandled_unknown' => 'SUM(CASE WHEN service_state.soft_state = 3'
                . ' AND service_state.is_handled = "n" THEN  1 ELSE 0 END)',
        ];
    }

    /**
     * @return \Generator
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function listAvailableDimensions()
    {
        $query = Service::on($this->getDb());

        $this->applyRestrictions($query);

        $query->getSelectBase()
            ->columns('customvar.name as varname')
            ->join('service_customvar', 'service_customvar.service_id = service.id')
            ->join('customvar', 'customvar.id = service_customvar.customvar_id')
            ->groupBy('customvar.name')
            ->orderBy('customvar.name');

        foreach ($query as $row) {
            yield $row->varname;
        }
    }

    public function getRenderer()
    {
        return new IcingadbStatusCubeRenderer($this);
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
