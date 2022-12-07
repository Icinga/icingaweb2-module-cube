<?php

// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\CubeRenderer\HostStatusCubeRenderer;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Str;

class IcingaDbHostStatusCube extends IcingaDbCube
{
    public function getRenderer()
    {
        return new HostStatusCubeRenderer($this);
    }

    public function getAvailableFactColumns()
    {
        return [
            'hosts_cnt' => 'hosts_total',
            'hosts_down' => 'hosts_down_handled + f.hosts_down_unhandled',
            'hosts_unhandled_down' => 'hosts_down_unhandled',
            'hosts_unreachable' => 'hosts_unreachable',
            'hosts_unhandled_unreachable' => 'hosts_unreachable_unhandled'
        ];
    }

    public function createDimension($name)
    {
        $this->registerAvailableDimensions();

        if (isset($this->availableDimensions[$name])) {
            return clone $this->availableDimensions[$name];
        }

        return new CustomVariableDimension($name);
    }

    public function hasDimension($name)
    {
        return array_key_exists($name, $this->dimensions)
            || array_key_exists(CustomVariableDimension::HOST_PREFIX . $name, $this->dimensions)
            || (Str::StartsWith($name, CustomVariableDimension::HOST_PREFIX)// required to create select options list
                && array_key_exists(
                    substr($name, strlen(CustomVariableDimension::HOST_PREFIX)),
                    $this->dimensions
                )
            );
    }

    public function listAvailableDimensions()
    {
        return $this->fetchHostVariableDimensions();
    }

    public function prepareInnerQuery()
    {
        $query = HoststateSummary::on($this->getDb());
        $query->columns(array_diff_key($query->getModel()->getColumns(), (new Host())->getColumns()));
        $query->disableDefaultSort();
        $this->applyRestrictions($query);

        $this->innerQuery = $query;
        return $this->innerQuery;
    }

    /**
     * Return Filter for Hosts cube.
     *
     * @return Filter\Any|Filter\Chain
     */
    public function getObjectsFilter()
    {
        if ($this->objectsFilter === null) {
            $this->finalizeInnerQuery();

            $hosts = $this->innerQuery()->columns(['host' => 'host.name']);
            $hosts->getSelectBase()->resetGroupBy();

            $filter = Filter::any();

            foreach ($hosts as $object) {
                $filter->add(Filter::equal('host.name', $object->host));
            }

            $this->objectsFilter = $filter;
        }

        return $this->objectsFilter;
    }
}
