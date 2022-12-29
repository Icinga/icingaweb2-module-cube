<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

use Icinga\Module\Cube\Cube;

$this->provideHook('cube/Actions', 'Cube/MonitoringActions');
$this->provideHook('cube/IcingaDbActions', 'Cube/IcingaDbActions');

$this->provideHook('icingadb/icingadbSupport');

if (! Cube::isUsingIcingaDb()) {
    $this->addRoute('cube/hosts', new Zend_Controller_Router_Route_Static(
        'cube/hosts',
        [
            'controller'    => 'ido-hosts',
            'action'        => 'index',
            'module'        => 'cube'
        ]
    ));

    $this->addRoute('cube/services', new Zend_Controller_Router_Route_Static(
        'cube/services',
        [
            'controller'    => 'ido-services',
            'action'        => 'index',
            'module'        => 'cube'
        ]
    ));
}
