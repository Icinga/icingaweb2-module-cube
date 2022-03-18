<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\IcingaDb\IcingaDbHostStatusCube;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\Web\IdoController;

class HostsController extends IdoController
{
    public function indexAction()
    {
        $this->createTabs()->activate('cube/hosts');

        $this->renderCube();
    }

    protected function getCube()
    {
        if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
            return new IcingaDbHostStatusCube();
        }

        return new IdoHostStatusCube();
    }
}
