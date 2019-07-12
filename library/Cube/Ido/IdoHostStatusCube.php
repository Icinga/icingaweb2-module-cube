<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido;

class IdoHostStatusCube extends IdoCube
{
    public function getRenderer()
    {
        return new IdoStatusCubeRenderer($this);
    }

    /**
     * @inheritdoc
     */
    public function getAvailableFactColumns()
    {
        return array(
            'hosts_cnt'           => 'COUNT(*)',
            'hosts_nok'           => 'SUM(CASE WHEN hs.current_state = 0 THEN 0 ELSE 1 END)',
            'hosts_unhandled_nok' => 'SUM(CASE WHEN hs.current_state != 0'
                . ' AND hs.problem_has_been_acknowledged = 0 AND hs.scheduled_downtime_depth = 0'
                . ' THEN 1 ELSE 0 END)',
        );
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
        if (count($this->filterProtectedCustomvars(array($name))) === 1) {
            $this->addDimension(new CustomVarDimension($name, CustomVarDimension::TYPE_HOST));
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
        $this->requireBackend();

        $view = $this->backend->select()->from('hoststatus');

        $view->applyFilter($this->getMonitoringRestriction());

        $select = $view->getQuery()->getSelectQuery();

        $select
            ->columns('cv.varname')
            ->join(
                ['cv' => $this->tableName('icinga_customvariablestatus')],
                'cv.object_id = ho.object_id'
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

        $view = $this->backend->select()->from('hoststatus');

        $view->getQuery()->requireColumn('host_state');

        $view->applyFilter($this->getMonitoringRestriction());

        $select = $view->getQuery()->getSelectQuery();

        return $select;
    }
}
