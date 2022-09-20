<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Icinga\Web\Widget\Tabextension\DashboardAction;

abstract class IdoController extends Controller
{
    public function createTabs()
    {
        return $this->getTabs()
            ->add('cube/hosts', [
                'label' => $this->translate('Hosts'),
                'url'   => 'cube/hosts' . ($this->params->toString() === '' ? '' : '?' . $this->params->toString())
            ])
            ->add('cube/services', [
                'label' => $this->translate('Services'),
                'url'   => 'cube/services' . ($this->params->toString() === '' ? '' : '?' . $this->params->toString())
            ])
            ->extend(new DashboardAction());
    }
}
