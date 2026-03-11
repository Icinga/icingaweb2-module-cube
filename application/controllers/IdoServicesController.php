<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
