<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Icinga\Module\Cube\DimensionParams;
use Icinga\Module\Cube\Forms\DimensionsForm;
use Icinga\Module\Cube\IcingaDb\CustomVariableDimension;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\Ido\IdoCube;
use Icinga\Web\View;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Controller;
use Icinga\Web\Widget\Tabs;
use ipl\Stdlib\Str;
use ipl\Web\Url;

abstract class IdoController extends Controller
{
    /** @var View This helps IDEs to understand that this is not ZF view */
    public $view;

    /** @var IdoCube */
    protected $cube;

    /** @var bool Whether showSettings param is set */
    protected $showSettings;

    /**
     * Return this controllers' cube
     *
     * @return IdoCube
     */
    abstract protected function getCube(): IdoCube;

    protected function moduleInit()
    {
        $this->cube = $this->getCube();
        $this->cube->chooseFacts(array_keys($this->cube->getAvailableFactColumns()));

        $this->showSettings = $this->params->shift('showSettings');
        $this->prepareCube();
    }

    public function detailsAction(): void
    {
        $this->getTabs()->add('details', [
            'label' => $this->translate('Cube details'),
            'url'   => $this->getRequest()->getUrl()
        ])->activate('details');

        $this->view->title = $this->cube->getSlicesLabel();

        $this->view->links = ActionLinks::renderAll($this->cube, $this->view);

        $this->render('cube-details', null, true);
    }

    protected function renderCube(): void
    {
        $this->view->title = sprintf(
            $this->translate('Cube: %s'),
            $this->cube->getPathLabel()
        );

        if (count($this->cube->listDimensions()) > 0) {
            $this->view->cube = $this->cube;
        } else {
            $this->showSettings = true;
        }

        if ($this->showSettings) {
            $this->view->form = (new DimensionsForm())->setCube($this->cube);
            $this->view->form->handleRequest();
        } else {
            $this->setAutorefreshInterval(15);
        }

        $this->render('cube-index', null, true);
    }

    private function prepareCube(): void
    {
        $vars = DimensionParams::fromString($this->params->shift('dimensions', ''))->getDimensions();

        if ($this->hasIcingadbDimensionParams($vars)) {
            $this->transformicingadbDimensionParamsAndRedirect($vars);
        }

        $wantNull = $this->params->shift('wantNull');

        foreach ($vars as $var) {
            $this->cube->addDimensionByName($var);
            if ($wantNull) {
                $this->cube->getDimension($var)->wantNull();
            }
        }

        foreach (['renderLayout', 'showFullscreen', 'showCompact', 'view'] as $p) {
            $this->params->shift($p);
        }

        foreach ($this->params->toArray() as $param) {
            $this->cube->slice(rawurldecode($param[0]), rawurldecode($param[1]));
        }
    }

    /**
     * Get whether the dimensions contain icingadb dimension
     *
     * @param array $dimensions
     *
     * @return bool
     */
    private function hasIcingadbDimensionParams(array $dimensions): bool
    {
        $prefix = CustomVariableDimension::HOST_PREFIX;
        if ($this->getRequest()->getControllerName() === 'ido-services') {
            $prefix = CustomVariableDimension::SERVICE_PREFIX;
        }

        foreach ($dimensions as $dimension) {
            if (Str::startsWith($dimension, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transform icingadb dimension and slice params and redirect
     *
     * This remove the new icingadb prefix from params and remove sort, problems-only, filter params
     *
     * @param array $icingadbDimensions
     */
    private function transformIcingadbDimensionParamsAndRedirect(array $icingadbDimensions): void
    {
        $dimensions = [];
        $slices = [];
        $toRemoveSlices = [];

        $prefix = CustomVariableDimension::HOST_PREFIX;
        if ($this->getRequest()->getControllerName() === 'ido-services') {
            $prefix = CustomVariableDimension::SERVICE_PREFIX;
        }

        foreach ($icingadbDimensions as $param) {
            $newParam = $param;
            if (strpos($param, $prefix) !== false) {
                $newParam = substr($param, strlen($prefix));
            }

            $slice = $this->params->shift(IcingaDbCube::SLICE_PREFIX . $param);
            if ($slice) {
                $slices[$newParam] = $slice;
                $toRemoveSlices[] = IcingaDbCube::SLICE_PREFIX . $param;
            }

            $dimensions[] = $newParam;
        }

        $icingadbParams = array_merge(
            $icingadbDimensions,
            $toRemoveSlices,
            array_keys($this->params->toArray(false))
        );

        $this->redirectNow(
            Url::fromRequest()
                ->setParam('dimensions', DimensionParams::fromArray($dimensions)->getParams())
                ->addParams($slices)
                ->without($icingadbParams)
        );
    }


    public function createTabs(): Tabs
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
