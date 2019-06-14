<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Module\Cube\DbCube;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

/**
 * IdoCube
 *
 * Base class for IDO-related cubes
 *
 * @package Icinga\Module\Cube\Ido
 */
abstract class IdoCube extends DbCube
{
    /** @var array  */
    protected $availableFacts = array();

    /** @var string We ask for the IDO version for compatibility reasons */
    protected $idoVersion;

    /** @var MonitoringBackend */
    protected $backend;

    /**
     * We can steal the DB connection directly from a Monitoring backend
     *
     * @param MonitoringBackend $backend
     * @return $this
     */
    public function setBackend(MonitoringBackend $backend)
    {
        $this->backend = $backend;

        $this->setConnection($backend->getResource());

        return $this;
    }

    /**
     * Provice access to our DB resource
     *
     * This lazy-loads the default monitoring backend in case no DB has been
     * given
     *
     * @return \Zend_Db_Adapter_Abstract
     */
    public function db()
    {
        $this->requireBackend();
        return parent::db();
    }

    /**
     * Returns the Icinga IDO version
     *
     * @return string
     */
    protected function getIdoVersion()
    {
        if ($this->idoVersion === null) {
            $db = $this->db();
            $this->idoVersion = $db->fetchOne(
                $db->select()->from('icinga_dbversion', 'version')
            );
        }

        return $this->idoVersion;
    }

    /**
     * Steal the default monitoring DB resource...
     *
     * ...in case none has been defined otherwise
     *
     * @return void
     */
    protected function requireBackend()
    {
        if ($this->db === null) {
            $this->setBackend(MonitoringBackend::instance());
        }
    }

    protected function getMonitoringRestriction()
    {
        $restriction = Filter::matchAny();
        $restriction->setAllowedFilterColumns(array(
            'host_name',
            'hostgroup_name',
            'instance_name',
            'service_description',
            'servicegroup_name',
            function ($c) {
                return preg_match('/^_(?:host|service)_/i', $c);
            }
        ));

        $filters = Auth::getInstance()->getUser()->getRestrictions('monitoring/filter/objects');

        foreach ($filters as $filter) {
            if ($filter === '*') {
                return Filter::matchAny();
            }
            try {
                $restriction->addFilter(Filter::fromQueryString($filter));
            } catch (QueryException $e) {
                throw new ConfigurationError(
                    'Cannot apply restriction %s using the filter %s. You can only use the following columns: %s',
                    'monitoring/filter/objects',
                    $filter,
                    implode(', ', array(
                        'instance_name',
                        'host_name',
                        'hostgroup_name',
                        'service_description',
                        'servicegroup_name',
                        '_(host|service)_<customvar-name>'
                    )),
                    $e
                );
            }
        }

        return $restriction;
    }
}
