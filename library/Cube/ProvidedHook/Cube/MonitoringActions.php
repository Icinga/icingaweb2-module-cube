<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\ProvidedHook\Cube;

use Icinga\Module\Cube\Hook\ActionsHook;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Ido\IdoServiceStatusCube;
use Icinga\Web\View;

/**
 * MonitoringActionLinks
 *
 * An action link hook implementation linking to matching hosts/services in the
 * monitoring module
 */
class MonitoringActions extends ActionsHook
{
    public function prepareActionLinks(Cube $cube, View $view)
    {
        if ($cube instanceof IdoHostStatusCube) {
            $vars = [];
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
        } elseif ($cube instanceof IdoServiceStatusCube) {
            $vars = [];
            foreach ($cube->getSlices() as $key => $val) {
                $vars['_service_' . $key] = $val;
            }

            $url = 'monitoring/list/services';

            $this->addActionLink(
                $this->makeUrl($url, $vars),
                $view->translate('Show services status'),
                $view->translate('This shows all matching services and their current state in the monitoring module'),
                'host'
            );
        }
    }
}
