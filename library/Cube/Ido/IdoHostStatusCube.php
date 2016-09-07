<?php

namespace Icinga\Module\Cube\Ido;

class IdoHostStatusCube extends IdoCube
{
    protected $availableFacts = array(
        'hosts_cnt'           => 'COUNT(*)',
        'hosts_nok'           => 'SUM(CASE WHEN hs.current_state = 0 THEN 0 ELSE 1 END)',
        'hosts_unhandled_nok' => 'SUM(CASE WHEN hs.current_state != 0 AND hs.problem_has_been_acknowledged = 0 AND hs.scheduled_downtime_depth = 0 THEN 1 ELSE 0 END) AS hosts_unhandled_nok',
    );

    public function getRenderer()
    {
        return new IdoHostStatusCubeRenderer($this);
    }

    public function addDimensionByName($name)
    {
        return $this->addDimension(new CustomVarDimension($name));
    }

    public function listAvailableDimensions()
    {
        $select = $this->db()->select()->from(
            array('cv' => $this->tableName('icinga_customvariablestatus')),
            array('varname' => 'cv.varname')
        )->join(
            array('o' => $this->tableName('icinga_objects')),
            'cv.object_id = o.object_id AND o.is_active = 1 AND o.objecttype_id = 1',
            array()
        )->where('cv.is_json = 0')
        ->group('cv.varname');

        return $this->db()->fetchCol($select);
    }

    public function prepareInnerQuery()
    {
        $select = $this->db()->select()->from(
            array('o' => $this->tableName('icinga_objects')),
            array()
        )->join(
            array('h' => $this->tableName('icinga_hosts')),
            'o.object_id = h.host_object_id AND o.is_active = 1',
            array()
        )->joinLeft(
            array('hs' => $this->tableName('icinga_hoststatus')),
            'hs.host_object_id = h.host_object_id',
            array()
        );

        return $select;
    }
}
