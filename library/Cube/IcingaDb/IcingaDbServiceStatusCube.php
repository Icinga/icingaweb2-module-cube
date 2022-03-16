<?php
// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\CubeRenderer\ServiceStatusCubeRenderer;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use ipl\Stdlib\Filter;

class IcingaDbServiceStatusCube extends IcingaDbCube
{
    public function getRenderer()
    {
        return new ServiceStatusCubeRenderer($this);
    }

    public function createDimension($name)
    {
        $this->registerAvailableDimensions();

        if (isset($this->availableDimensions[$name])) {
            return clone $this->availableDimensions[$name];
        }

        return new CustomVariableDimension($name, CustomVariableDimension::TYPE_SERVICE);
    }

    public function getAvailableFactColumns()
    {
        return [
            'services_cnt' => 'services_total',
            'services_critical' => 'services_critical_handled + f.services_critical_unhandled',
            'services_unhandled_critical' => 'services_critical_unhandled',
            'services_warning' => 'services_warning_handled + f.services_warning_unhandled',
            'services_unhandled_warning' => 'services_warning_unhandled',
            'services_unknown' => 'services_unknown_handled + f.services_unknown_unhandled',
            'services_unhandled_unknown' => 'services_unknown_unhandled',
        ];
    }

    public function listAvailableDimensions()
    {
        $db = $this->getDb();

        $query = CustomvarFlat::on($db);
        $this->applyRestrictions($query);

        $query
            ->columns('flatname')
            ->orderBy('flatname')
            ->filter(Filter::equal('service.id', '*'));
        $query->getSelectBase()->groupBy('flatname');

        return $db->fetchCol($query->assembleSelect());
    }

    public function prepareInnerQuery()
    {
        $query = ServicestateSummary::on($this->getDb())->with(['service.state']);
        $query->columns(array_diff_key($query->getModel()->getColumns(), (new Service())->getColumns()));
        $query->disableDefaultSort();
        $this->applyRestrictions($query);

        return $query;
    }
}
