<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\DimensionParams;
use Icinga\Module\Cube\Ido\IdoServiceStatusCube;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\ServiceCube;
use Icinga\Module\Cube\ServiceDbQuery;
use Icinga\Module\Cube\Web\Controller;

class ServicesController extends Controller
{
    public function indexAction()
    {
        $this->createTabs()->activate('cube/services');

        $this->renderCube();
    }

    protected function getCube()
    {
        if (!(Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())) {
            return new IdoServiceStatusCube();
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

        return (new ServiceCube(
            (new ServiceDbQuery())->getResult($urlDimensions, $slices),
            $urlDimensions,
            $slices
        ));
    }
}
