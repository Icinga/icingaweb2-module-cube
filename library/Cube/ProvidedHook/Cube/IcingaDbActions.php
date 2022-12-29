<?php

namespace Icinga\Module\Cube\ProvidedHook\Cube;

use Icinga\Module\Cube\Hook\IcingaDbActionsHook;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\Icingadb\IcingadbServiceStatusCube;
use ipl\Web\Url;

class IcingaDbActions extends IcingaDbActionsHook
{
    public function createActionLinks(IcingaDbCube $cube)
    {
        $type = 'host';
        if ($cube instanceof IcingadbServiceStatusCube) {
            $type = 'service';
        }

        $url = 'icingadb/' . $type . 's';

        $paramsWithPrefix = [];
        foreach ($cube->getSlices() as $dimension => $slice) {
            $paramsWithPrefix[$dimension] = $slice;
        }

        if (Url::fromRequest()->hasParam('problems')) {
            $paramsWithPrefix[$type . '.state.is_problem'] = 'y';
        }

        if ($type === 'host') {
            $this->addActionLink(
                $this->makeUrl($url, $paramsWithPrefix),
                t('Show hosts status'),
                t('This shows all matching hosts and their current state in Icinga DB Web'),
                'server'
            );
        } else {
            $this->addActionLink(
                $this->makeUrl($url, $paramsWithPrefix),
                t('Show services status'),
                t('This shows all matching hosts and their current state in Icinga DB Web'),
                'cog'
            );
        }
    }
}
