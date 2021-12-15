<?php
// Icinga Web 2 Cube Module | (c) 2021 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido\Query;

use Icinga\Module\Monitoring\Backend\Ido\Query\IdoQuery;

class HoststatusQuery extends \Icinga\Module\Monitoring\Backend\Ido\Query\HoststatusQuery
{
    protected $subQueryTargets = array(
        'hostgroups'    => 'hostgroup',
        'servicegroups' => 'servicegroup',
        'services'      => 'servicestatus'
    );

    protected function joinSubQuery(IdoQuery $query, $name, $filter, $and, $negate, &$additionalFilter)
    {
        if ($name === 'servicestatus') {
            return ['s.host_object_id', 'ho.object_id'];
        }

        return parent::joinSubQuery($query, $name, $filter, $and, $negate, $additionalFilter);
    }
}
