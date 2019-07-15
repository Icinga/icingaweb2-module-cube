<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\Ido\IdoServiceStatusCube;
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
        return new IdoServiceStatusCube();
    }
}
