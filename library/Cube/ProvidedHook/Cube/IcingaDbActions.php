<?php

namespace Icinga\Module\Cube\ProvidedHook\Cube;

use Icinga\Module\Cube\Hook\IcingaDbActionsHook;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\IcingaDb\IcingaDbServiceStatusCube;
use ipl\Stdlib\Filter;
use ipl\Web\Url;

class IcingaDbActions extends IcingaDbActionsHook
{
    public function createActionLinks(IcingaDbCube $cube)
    {
        $type = 'host';
        if ($cube instanceof IcingaDbServiceStatusCube) {
            $type = 'service';
        }

        $filter = Filter::all();
        if ($cube->hasBaseFilter()) {
            $filter->add($cube->getBaseFilter());
        }

        foreach ($cube->getSlices() as $dimension => $slice) {
            $filter->add(Filter::equal($dimension, $slice));
        }

        $url = Url::fromPath('icingadb/' . $type . 's');
        $url->setFilter($filter);

        if ($type === 'host') {
            $this->addActionLink(
                $url,
                t('Show hosts status'),
                t('This shows all matching hosts and their current state in Icinga DB Web'),
                'server'
            );
        } else {
            $this->addActionLink(
                $url,
                t('Show services status'),
                t('This shows all matching hosts and their current state in Icinga DB Web'),
                'cog'
            );
        }
    }
}
