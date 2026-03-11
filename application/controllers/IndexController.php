<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cube\Controllers;

use Icinga\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->redirectNow('cube/hosts' . ($this->params->toString() === '' ? '' : '?' . $this->params->toString()));
    }
}
