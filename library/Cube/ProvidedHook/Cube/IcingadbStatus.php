<?php
// Icinga Web 2 Cube Module | (c) 2020 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\ProvidedHook\Cube;

use Icinga\Module\Cube\BaseCube;
use Icinga\Module\Cube\Hook\IcingadbHook;
use Icinga\Module\Cube\ServiceCube;
use Icinga\Web\View;

class IcingadbStatus extends IcingadbHook
{

    /**
     * @param BaseCube $cube
     * @param View $view
     */
    public function prepareActionLinks(BaseCube $cube, View $view)
    {
        $type = 'host';
        if ($cube instanceof ServiceCube) {
            $type = 'service';
        }

        $url = 'icingadb/'. $type . 's';

        $paramsWithPrefix = [];
        foreach ($cube->getSlices() as $dimension => $slice) {
            $paramsWithPrefix[$type . '.vars.' . $dimension] = $slice;
        }

        if ($type === 'host') {
            $this->addActionLink(
                $this->makeUrl($url, $paramsWithPrefix),
                $view->translate('Show hosts status'),
                $view->translate('This shows all matching hosts and their current state in the monitoring module'),
                'host'
            );
        } else {
            $this->addActionLink(
                $this->makeUrl($url, $paramsWithPrefix),
                $view->translate('Show services status'),
                $view->translate('This shows all matching hosts and their current state in the monitoring module'),
                'service'
            );
        }
    }
}
