<?php
// Icinga Web 2 Cube Module | (c) 2021 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido\Query;

use Exception;
use Icinga\Application\Version;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotImplementedError;
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

    protected function createSubQueryFilter(FilterExpression $filter, $queryName)
    {
        try {
            return parent::createSubQueryFilter($filter, $queryName);
        } catch (Exception $e) {
            if (version_compare(Version::VERSION, '2.10.0', '>=')) {
                throw $e;
            }

            if ($e->getMessage() === 'Undefined array key 0' && basename($e->getFile()) === 'IdoQuery.php') {
                // Ensures compatibility with earlier Icinga Web 2 versions
                throw new NotImplementedError('');
            } else {
                throw $e;
            }
        }
    }
}
