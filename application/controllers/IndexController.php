<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->redirectNow('cube/hosts' . ($this->params->toString() === '' ? '' : '?' . $this->params->toString()));
    }
}
