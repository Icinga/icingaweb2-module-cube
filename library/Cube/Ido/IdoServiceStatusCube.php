<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido;

use Icinga\Application\Config;

class IdoServiceStatusCube extends IdoCube
{
    public function getRenderer()
    {
        return new IdoStatusCubeRenderer($this);
    }

    public function getAvailableFactColumns()
    {
        return [
            'services_cnt'           => 'COUNT(*)',
            'services_nok'           => 'SUM(CASE WHEN ss.current_state = 0 THEN 0 ELSE 1 END)',
            'services_unhandled_nok' => 'SUM(CASE WHEN ss.current_state != 0'
                . ' AND ss.problem_has_been_acknowledged = 0 AND ss.scheduled_downtime_depth = 0'
                . ' THEN 1 ELSE 0 END)',
        ];
    }

    /**
     * This returns a list of all available Dimensions
     *
     * @return array
     */
    public function listAvailableDimensions()
    {
        $this->requireBackend();

        $view = $this->backend->select()->from('servicestatus');

        $view->applyFilter($this->getMonitoringRestriction());

        $select = $view->getQuery()->clearOrder()->getSelectQuery();

        $select
            ->columns('cv.varname')
            ->join(
                ['cv' => $this->tableName('icinga_customvariablestatus')],
                'cv.object_id = so.object_id',
                []
            )
            ->group('cv.varname');

        if (version_compare($this->getIdoVersion(), '1.12.0', '>=')) {
            $select->where('cv.is_json = 0');
        }

        return $this->filterProtectedCustomvars($this->db()->fetchCol($select));
    }

    public function prepareInnerQuery()
    {
        $this->requireBackend();

        $view = $this->backend->select()->from('servicestatus');

        $view->getQuery()->requireColumn('service_state');

        $view->applyFilter($this->getMonitoringRestriction());

        $select = $view->getQuery()->clearOrder()->getSelectQuery();

        return $select;
    }

    /**
     * Add a specific named dimension
     *
     * Right now this are just custom vars, we might support group memberships
     * or other properties in future
     *
     * @param string $name
     *
     * @return $this
     */
    public function addDimensionByName($name)
    {
        if (count($this->filterProtectedCustomvars([$name])) === 1) {
            $this->addDimension(new CustomVarDimension($name, CustomVarDimension::TYPE_SERVICE));
        }

        return $this;
    }
}
