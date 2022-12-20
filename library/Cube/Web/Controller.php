<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Icinga\Module\Cube\Cube;
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
use ipl\Html\HtmlString;
use ipl\Orm\Query;
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

    /** @var View This helps IDEs to understand that this is not ZF view */
    public $view;

    /** @var IcingaDbCube */
    protected $cube;

    /** @var string[] Preserved params for searchbar and search editor controls */
    protected $preserveParams = [
        'dimensions',
        'showSettings',
        'wantNull',
        'problems'
    ];

    /** @var Filter\Rule Filter from query string parameters */
    private $filter;

    /**
     * Return this controllers' cube
     *
     * @return IcingaDbCube
     */
    abstract protected function getCube(): IcingaDbCube;

    protected function moduleInit()
    {
        $this->cube = $this->getCube();
        $this->cube->chooseFacts(array_keys($this->cube->getAvailableFactColumns()));
        $this->prepareCube();
    }

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

    public function filter(Query $query, Filter\Rule $filter = null): self
    {
        $this->applyRestrictions($query);

        $query->filter($filter ?: $this->getFilter());

        return $this;
    }

    public function detailsAction(): void
    {
        $this->getTabs()->add('details', [
            'label' => $this->translate('Cube details'),
            'url'   => $this->getRequest()->getUrl()
        ])->activate('details');

        $this->setTitle($this->cube->getSlicesLabel());
        $this->view->links = IcingaDbActionsHook::renderAll($this->cube);

        $this->addContent(
            HtmlString::create($this->view->render('/cube-details.phtml'))
        );
    }

    protected function renderCube(): void
    {
        $this->setTitle(sprintf(
            $this->translate('Cube: %s'),
            $this->cube->getPathLabel()
        ));

        $showSettings = $this->params->shift('showSettings');

        $query = $this->cube->innerQuery();
        $problemsOnly = $this->params->shift('problems');
        $problemToggle = (new ProblemToggle($problemsOnly))
            ->setIdProtector([$this->getRequest(), 'protectId'])
            ->on(ProblemToggle::ON_SUCCESS, function (ProblemToggle $form) {
                if (! $form->getElement('problems')->isChecked()) {
                    $this->redirectNow(Url::fromRequest()->remove('problems'));
                } else {
                    $this->redirectNow(Url::fromRequest()->setParam('problems'));
                }
            })->handleRequest($this->getServerRequest());

        if ($problemsOnly) {
            $query->filter(Filter::equal('state.is_problem', 1));
            $this->cube->problemsOnly();
        }

        $this->addControl($problemToggle);

        $sortControl = SortControl::create([
            IcingaDbCube::DIMENSION_VALUE_SORT_PARAM => t('Value'),
            IcingaDbCube::DIMENSION_SEVERITY_SORT_PARAM . ' desc' => t('Severity'),
        ]);

        $this->params->shift($sortControl->getSortParam());
        $this->cube->sortBy($sortControl->getSort());
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

        $this->filter($query, $filter);

        $this->addControl($searchBar);

        if (count($this->cube->listDimensions()) > 0) {
            $this->view->cube = $this->cube;
        } else {
            $showSettings = true;
        }

        if ($showSettings) {
            $this->view->form = (new DimensionsForm())->setCube($this->cube);
            $this->view->form->handleRequest();
        } else {
            $this->setAutorefreshInterval(15);
        }

        $this->addContent(
            HtmlString::create($this->view->render('/cube-index.phtml'))
        );

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
            //TODO: find better solution
            // Currently there is no way to get the newly added filter when sendMultipartUpdate() is triggered.
            // The $searchbar->getFilter() returns null, only the searchbar's redirect url contains the new filter
            $this->redirectNow($searchBar->getRedirectUrl());
        }
    }

    private function prepareCube(): void
    {
        $dimensions = DimensionParams::fromString(
            $this->params->shift('dimensions', '')
        )->getDimensions();

        if ($this->hasLegacyDimensionParams($dimensions)) {
            $this->transformLegacyDimensionParamsAndRedirect($dimensions);
        }

        $wantNull = $this->params->shift('wantNull');
        foreach ($dimensions as $dimension) {
            $this->cube->addDimensionByName($dimension);
            if ($wantNull) {
                $this->cube->getDimension($dimension)->wantNull();
            }

            $sliceParamWithPrefix = Cube::SLICE_PREFIX . $dimension;

            if ($this->params->has($sliceParamWithPrefix)) {
                $this->preserveParams[] = $sliceParamWithPrefix;
                $this->cube->slice(rawurldecode($dimension), rawurldecode($this->params->shift($sliceParamWithPrefix)));
            }
        }
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

            if ($slice = $this->params->shift($param)) {
                $slices[Cube::SLICE_PREFIX . $newParam] = $slice;
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
        $params = Url::fromRequest()->getParams()->toString();
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
