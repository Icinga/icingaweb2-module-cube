<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\DimensionParams;
use Icinga\Module\Cube\HostCube;
use Icinga\Module\Cube\HostDbQuery;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Web\Controller;

class HostsController extends Controller
{
    public function indexAction()
    {
        $this->createTabs()->activate('cube/hosts');

        $this->renderCube();
    }

    /**
     * @return HostCube|IdoHostStatusCube
     */
    protected function getCube()
    {
        if (!(Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())) {
            return new IdoHostStatusCube();
        }

        $slices = [];
        $urlDimensions = DimensionParams::fromString($this->params->get('dimensions'))->getDimensions();

        $dimensionsWithoutSlices = $urlDimensions;
        // get slices
        foreach ($urlDimensions as $key => $dimension) {
            // because params are double encoded
            $doubleEncodedDimension = DimensionParams::update(rawurlencode($dimension))->getParams();

            if ($value = $this->params->get($doubleEncodedDimension)) {
                unset($dimensionsWithoutSlices[$key]);
                $slices[$dimension] = $value;
            }
        }

        return (new HostCube(
            (new HostDbQuery())->getResult($urlDimensions, $slices),
            $urlDimensions,
            $slices
        ));
    }
}
