<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\DbCube;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

abstract class IdoCube extends DbCube
{
    protected $availableFacts = array();

    protected $idoVersion;

    public function setBackend(MonitoringBackend $backend)
    {
        return $this->setConnection($backend->getResource());
    }

    public function chooseFacts($facts)
    {
        parent::chooseFacts($facts);

        $this->factColumns = array();
        foreach ($this->chosenFacts as $name) {
            $this->factColumns[$name] = $this->availableFacts[$name];
        }

        return $this;
    }

    public function db()
    {
        $this->requireBackend();
        return parent::db();
    }

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

    protected function requireBackend()
    {
        if ($this->db === null) {
            $this->setBackend(MonitoringBackend::instance());
        }
    }
}
