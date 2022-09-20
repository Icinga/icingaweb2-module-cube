<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido;

use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Util\GlobFilter;

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
     * Cache for {@link filterProtectedCustomvars()}
     *
     * @var string|null
     */
    protected $protectedCustomvars;

    /** @var GlobFilter The properties to hide from the user */
    protected $blacklistedProperties;

    /**
     * We can steal the DB connection directly from a Monitoring backend
     *
     * @param MonitoringBackend $backend
     * @return $this
     */
    public function setBackend(MonitoringBackend $backend)
    {
        $this->backend = $backend;

        $resource = $backend->getResource();
        $resource->getDbAdapter()
            ->getConnection()
            ->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);

        $this->setConnection($resource);

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

    /**
     * Return the given array without values matching the custom variables protected by the monitoring module
     *
     * @param   string[]    $customvars
     *
     * @return  string[]
     */
    protected function filterProtectedCustomvars(array $customvars)
    {
        if ($this->blacklistedProperties === null) {
            $this->blacklistedProperties = new GlobFilter(
                Auth::getInstance()->getRestrictions('monitoring/blacklist/properties')
            );
        }

        if ($this instanceof IdoServiceStatusCube) {
            $type = 'service';
        } else {
            $type = 'host';
        }

        $customvars = $this->blacklistedProperties->removeMatching(
            [$type => ['vars' => array_flip($customvars)]]
        );

        $customvars = isset($customvars[$type]['vars']) ? array_flip($customvars[$type]['vars']) : [];

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
