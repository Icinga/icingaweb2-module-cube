<?php

namespace Icinga\Module\Cube\ProvidedHook\Cube;

use Icinga\Module\Cube\Hook\ActionLinksHook;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Web\View;

/**
 * MonitoringActionLinks
 *
 * An action link hook implementation linking to matching hosts in the
 * monitoring module
 *
 * @package Icinga\Module\Cube\ProvidedHook\Cube
 */
class MonitoringActionLinks extends ActionLinksHook
{
    /**
     * @inheritdoc
     */
    public function prepareActionLinks(Cube $cube, View $view)
    {
        if (! $cube instanceof IdoHostStatusCube) {
            return;
        }

        $vars = array();
        foreach ($cube->getSlices() as $key => $val) {
            $vars['_host_' . $key] = $val;
        }

        $url = 'monitoring/list/hosts';

        $this->addActionLink(
            $this->makeUrl($url, $vars),
            $view->translate('Show hosts status'),
            $view->translate('This shows all matching hosts and their current state in the monitoring module'),
            'host'
        );
    }
}
