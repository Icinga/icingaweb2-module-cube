<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Icinga\Module\Cube\DimensionParams;
use Icinga\Module\Cube\Forms\DimensionsForm;
use Icinga\Module\Cube\Hook\IcingaDbActionsHook;
use Icinga\Module\Cube\IcingaDb\CustomVariableDimension;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Web\Control\ProblemToggle;
use Icinga\Web\View;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use ipl\Html\FormElement\CheckboxElement;
use ipl\Html\HtmlString;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Str;
use ipl\Web\Compat\CompatController;
use ipl\Web\Compat\SearchControls;
use ipl\Web\Control\SortControl;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;
use ipl\Web\Widget\Tabs;

abstract class Controller extends CompatController
{
    use SearchControls;
    use Database;
    use Auth;

    /** @var string[] Preserved params for searchbar and search editor controls */
    protected $preserveParams = [
        'dimensions',
        'showSettings',
        'wantNull',
        'problems',
        'sort'
    ];

    /** @var Filter\Rule Filter from query string parameters */
    private $filter;

    /**
     * Return this controllers' cube
     *
     * @return IcingaDbCube
     */
    abstract protected function getCube(): IcingaDbCube;

    /**
     * Get the filter created from query string parameters
     *
     * @return Filter\Rule
     */
    public function getFilter(): Filter\Rule
    {
        if ($this->filter === null) {
            $this->filter = QueryString::parse((string) $this->params);
        }

        return $this->filter;
    }

    public function detailsAction(): void
    {
        $cube = $this->prepareCube();
        $this->getTabs()->add('details', [
            'label' => $this->translate('Cube details'),
            'url'   => $this->getRequest()->getUrl()
        ])->activate('details');

        $cube->setBaseFilter($this->getFilter());

        $this->setTitle($cube->getSlicesLabel());
        $this->view->links = IcingaDbActionsHook::renderAll($cube);

        $this->addContent(
            HtmlString::create($this->view->render('/cube-details.phtml'))
        );
    }

    protected function renderCube(): void
    {
        $cube = $this->prepareCube();
        $this->setTitle(sprintf(
            $this->translate('Cube: %s'),
            $cube->getPathLabel()
        ));

        $showSettings = $this->params->shift('showSettings');

        $query = $cube->innerQuery();
        $problemsOnly = (bool) $this->params->shift('problems', false);
        $problemToggle = (new ProblemToggle($problemsOnly ?: null))
            ->setIdProtector([$this->getRequest(), 'protectId'])
            ->on(ProblemToggle::ON_SUCCESS, function (ProblemToggle $form) {
                /** @var CheckboxElement $problems */
                $problems = $form->getElement('problems');
                if (! $problems->isChecked()) {
                    $this->redirectNow(Url::fromRequest()->remove('problems'));
                } else {
                    $this->redirectNow(Url::fromRequest()->setParam('problems'));
                }
            })->handleRequest($this->getServerRequest());

        $this->addControl($problemToggle);

        $sortControl = SortControl::create([
            IcingaDbCube::DIMENSION_VALUE_SORT_PARAM                => t('Value'),
            IcingaDbCube::DIMENSION_SEVERITY_SORT_PARAM . ' desc'   => t('Severity'),
        ]);

        $this->params->shift($sortControl->getSortParam());
        $cube->sortBy($sortControl->getSort());
        $this->addControl($sortControl);

        $searchBar = $this->createSearchBar(
            $query,
            $this->preserveParams
        );

        if ($searchBar->hasBeenSent() && ! $searchBar->isValid()) {
            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
            } else {
                $this->addControl($searchBar);
                $this->sendMultipartUpdate();
                return;
            }
        } else {
            $filter = $searchBar->getFilter();
        }

        if ($problemsOnly) {
            $filter = Filter::all($filter, Filter::equal('state.is_problem', true));
        }

        $cube->setBaseFilter($filter);
        $cube->problemsOnly($problemsOnly);

        $this->addControl($searchBar);

        if (count($cube->listDimensions()) > 0) {
            $this->view->cube = $cube;
        } else {
            $showSettings = true;
        }

        $this->view->url = Url::fromRequest()->onlyWith($this->preserveParams);
        $viewUrlParams = $this->view->url->getParams()->toArray(false);
        $this->view->url->setQueryString(QueryString::render($searchBar->getFilter()))
            ->addParams($viewUrlParams);

        if ($showSettings) {
            $form = (new DimensionsForm())
                ->setUrl($this->view->url)
                ->setCube($cube)
                ->on(DimensionsForm::ON_SUCCESS, function ($form) {
                    $this->redirectNow($form->getRedirectUrl());
                })
                ->handleRequest($this->getServerRequest());

            $this->view->form = $form;
        } else {
            $this->setAutorefreshInterval(15);
        }

        $this->addContent(
            HtmlString::create($this->view->render('/cube-index.phtml'))
        );

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }
    }

    private function prepareCube(): IcingaDbCube
    {
        $cube = $this->getCube();
        $cube->chooseFacts(array_keys($cube->getAvailableFactColumns()));

        $dimensions = DimensionParams::fromString(
            $this->params->shift('dimensions', '')
        )->getDimensions();

        if ($this->hasLegacyDimensionParams($dimensions)) {
            $this->transformLegacyDimensionParamsAndRedirect($dimensions);
        }

        $wantNull = $this->params->shift('wantNull');
        foreach ($dimensions as $dimension) {
            $cube->addDimensionByName($dimension);
            if ($wantNull) {
                $cube->getDimension($dimension)->wantNull();
            }

            $sliceParamWithPrefix = rawurlencode($cube::SLICE_PREFIX . $dimension);

            if ($this->params->has($sliceParamWithPrefix)) {
                $this->preserveParams[] = $sliceParamWithPrefix;
                $cube->slice($dimension, $this->params->shift($sliceParamWithPrefix));
            }
        }

        return $cube;
    }

    /**
     * Get whether the given dimension param is legacy dimension param
     *
     * @param string $dimensionParam
     *
     * @return bool
     */
    private function isLegacyDimensionParam(string $dimensionParam): bool
    {
        return ! Str::startsWith($dimensionParam, CustomVariableDimension::HOST_PREFIX)
            && ! Str::startsWith($dimensionParam, CustomVariableDimension::SERVICE_PREFIX);
    }

    /**
     * Get whether the dimensions contain legacy dimension
     *
     * @param array $dimensions
     *
     * @return bool
     */
    private function hasLegacyDimensionParams(array $dimensions): bool
    {
        foreach ($dimensions as $dimension) {
            if ($this->isLegacyDimensionParam($dimension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transform legacy dimension and slice params and redirect
     *
     * This adds the new prefix to params and then redirects so that the new URL contains the prefixed params
     * Slices are prefixed to differ filter and slice params
     *
     * @param array $legacyDimensions
     */
    private function transformLegacyDimensionParamsAndRedirect(array $legacyDimensions): void
    {
        $dimensions = [];
        $slices = [];

        $dimensionPrefix = CustomVariableDimension::HOST_PREFIX;
        if ($this->getRequest()->getControllerName() === 'services') {
            $dimensionPrefix = CustomVariableDimension::SERVICE_PREFIX;
        }

        foreach ($legacyDimensions as $param) {
            $newParam = $param;
            if ($this->isLegacyDimensionParam($param)) {
                $newParam = $dimensionPrefix . $param;
            }

            $slice = $this->params->shift($param);
            if ($slice) {
                $slices[IcingaDbCube::SLICE_PREFIX . $newParam] = $slice;
            }

            $dimensions[] = $newParam;
        }

        $this->redirectNow(
            Url::fromRequest()
                ->setParam('dimensions', DimensionParams::fromArray($dimensions)->getParams())
                ->addParams($slices)
                ->without($legacyDimensions)
        );
    }

    public function createTabs(): Tabs
    {
        $params = Url::fromRequest()
            ->onlyWith($this->preserveParams)
            ->getParams()
            ->toString();

        return $this->getTabs()
            ->add('cube/hosts', [
                'label' => $this->translate('Hosts'),
                'url'   => 'cube/hosts' . ($params === '' ? '' : '?' . $params)
            ])
            ->add('cube/services', [
                'label' => $this->translate('Services'),
                'url'   => 'cube/services' . ($params === '' ? '' : '?' . $params)
            ])
            ->extend(new DashboardAction());
    }
}
