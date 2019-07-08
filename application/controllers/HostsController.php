<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Web\IdoController;

class HostsController extends IdoController
{
    public function indexAction()
    {
        $this->getTabs()->activate('cube/hosts');

        $this->renderCube();
    }

    protected function getCube()
    {
        return new IdoHostStatusCube();
    }
}
