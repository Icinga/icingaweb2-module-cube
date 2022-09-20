<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\IcingaDb\IcingaDbServiceStatusCube;
use Icinga\Module\Cube\Ido\IdoServiceStatusCube;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\Web\IdoController;

class ServicesController extends IdoController
{
    public function indexAction()
    {
        $this->createTabs()->activate('cube/services');

        $this->renderCube();
    }

    protected function getCube()
    {
        if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
            return new IcingaDbServiceStatusCube();
        }

        return new IdoServiceStatusCube();
    }
}
