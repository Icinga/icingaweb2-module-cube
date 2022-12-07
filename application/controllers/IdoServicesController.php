<?php

// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\Ido\IdoCube;
use Icinga\Module\Cube\Ido\IdoServiceStatusCube;
use Icinga\Module\Cube\Web\IdoController;

class IdoServicesController extends IdoController
{
    public function indexAction(): void
    {
        $this->createTabs()->activate('cube/services');

        $this->renderCube();
    }

    protected function getCube(): IdoCube
    {
        return new IdoServiceStatusCube();
    }
}
