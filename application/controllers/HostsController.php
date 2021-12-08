<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\Icingadb\IcingadbHostStatusCube;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Web\Controller;

class HostsController extends Controller
{
    public function indexAction()
    {
        $this->createTabs()->activate('cube/hosts');

        $this->renderCube();
    }

    /**
     * @return IcingadbHostStatusCube|IdoHostStatusCube
     */
    protected function getCube()
    {
        if (!(Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())) {
            return new IdoHostStatusCube();
        }

        return (new IcingadbHostStatusCube());
    }
}
