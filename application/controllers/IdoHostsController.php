<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\Ido\IdoCube;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Web\IdoController;

class IdoHostsController extends IdoController
{
    public function indexAction(): void
    {
        $this->createTabs()->activate('cube/hosts');

        $this->renderCube();
    }

    protected function getCube(): IdoCube
    {
        return new IdoHostStatusCube();
    }
}
