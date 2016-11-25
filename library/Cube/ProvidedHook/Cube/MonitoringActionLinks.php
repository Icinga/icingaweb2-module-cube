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
    public function getHtml(View $view, Cube $cube)
    {

        if (! $cube instanceof IdoHostStatusCube) {
            return '';
        }

        $vars = array();
        foreach ($cube->getSlices() as $key => $val) {
            $vars['_host_' . $key] = $val;
        }

        $url = 'monitoring/list/hosts';

        return $view->qlink(
            $view->translate('Show filtered hosts status'),
            $url,
            $vars,
            array('class' => 'icon-host')
        );
    }
}
