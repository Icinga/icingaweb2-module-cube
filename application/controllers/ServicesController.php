<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\Icingadb\IcingadbServiceStatusCube;
use Icinga\Module\Cube\Ido\IdoServiceStatusCube;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\Web\Controller;

class ServicesController extends Controller
{
    public function indexAction()
    {
        $this->createTabs()->activate('cube/services');

        $this->renderCube();
    }

    /**
     * @return IdoServiceStatusCube|IcingadbServiceStatusCube
     */
    protected function getCube()
    {
        if (!(Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())) {
            return new IdoServiceStatusCube();
        }

        return new IcingadbServiceStatusCube();
    }
}
