<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Application\Config;

class IdoHostStatusCube extends IdoCube
{
    /**
     * Cache for {@link filterProtectedCustomvars()}
     *
     * @var string|null
     */
    protected $protectedCustomvars;

    public function getRenderer()
    {
        return new IdoHostStatusCubeRenderer($this);
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
            $this->addDimension(new CustomVarDimension($name));
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
        $select = $this->db()->select()->from(
            array('cv' => $this->tableName('icinga_customvariablestatus')),
            array('varname' => 'cv.varname')
        )->join(
            array('o' => $this->tableName('icinga_objects')),
            'cv.object_id = o.object_id AND o.is_active = 1 AND o.objecttype_id = 1',
            array()
        )->group('cv.varname');

        if (version_compare($this->getIdoVersion(), '1.12.0', '>=')) {
            $select->where('cv.is_json = 0');
        }

        return $this->filterProtectedCustomvars($this->db()->fetchCol($select));
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

    /**
     * Return the given array without values matching the custom variables protected by the monitoring module
     *
     * @param   string[]    $customvars
     *
     * @return  string[]
     */
    protected function filterProtectedCustomvars(array $customvars)
    {
        if ($this->protectedCustomvars === null) {
            $config = Config::module('monitoring')->get('security', 'protected_customvars');
            $protectedCustomvars = array();

            foreach (preg_split('~,~', $config, -1, PREG_SPLIT_NO_EMPTY) as $pattern) {
                $regex = array();
                foreach (explode('*', $pattern) as $literal) {
                    $regex[] = preg_quote($literal, '/');
                }

                $protectedCustomvars[] = implode('.*', $regex);
            }

            $this->protectedCustomvars = empty($protectedCustomvars)
                ? '/^$/'
                : '/^(?:' . implode('|', $protectedCustomvars) . ')$/';
        }

        return preg_grep($this->protectedCustomvars, $customvars, PREG_GREP_INVERT);
    }
}
