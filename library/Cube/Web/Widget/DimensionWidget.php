<?php

// Icinga Web 2 Cube Module | (c) 2025 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web\Widget;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;
use Icinga\Module\Cube\DimensionParams;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Web\Url as IcingaUrl;
use Icinga\Web\View;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Stdlib\Filter\Rule;
use ipl\Web\Url;

abstract class DimensionWidget extends BaseHtmlElement
{
    protected $tag = 'div';

    public function __construct(
        protected array $dimension,
        protected Cube $cube,
        protected View $view,
        protected int $level
    ) {
        $this->addAttributes(new Attributes(['class' => $this->getDimensionClassString()]));
    }

    /**
     * Get the base url for the details action.
     *
     * @return string
     */
    abstract protected function getDetailsBaseUrl(): string;

    /**
     * Get the state and handled classes for a given dimension name and row.
     *
     * This is used to create class attributes for the dimension container.
     *
     * @return array
     */
    abstract protected function getDimensionClasses(): array;

    /**
     * Get the sum for a given dimension.
     *
     * This is used to display the sum of the next dimension in the label.
     *
     * @param Dimension $next The next dimension
     *
     * @return int
     */
    abstract protected function getDimensionSum(Dimension $next): int;

    public function assemble(): void
    {
        $this->addHtml(
            new HtmlElement(
                'div',
                new Attributes(['class' => 'header']),
                new HtmlElement(
                    'a',
                    new Attributes([
                        'href'             => $this->getDetailsUrl(),
                        'title'            => sprintf(
                            'Show details for %s: %s',
                            $this->cube->getDimension($this->dimension['name'])->getLabel(),
                            $this->dimension['row']->{$this->dimension['name']}
                        ),
                        'data-base-target' => '_next'
                    ]),
                    $this->getDimensionLabel()
                ),
                new HtmlElement('a', new Attributes([
                    'class' => 'icon-filter',
                    'href'  => $this->getSliceUrl(),
                    'title' => 'Slice this cube'
                ]))
            )
        );

        $this->addHtml(new HtmlElement('div', new Attributes(['class' => 'body']), $this->dimension['body']));
    }

    /**
     * Get the URL for the details action.
     *
     * This is used to create a link to the details action of the cube.
     *
     * @return Url
     * @throws ProgrammingError
     */
    protected function getDetailsUrl(): Url
    {
        $prefix = '';
        $url = Url::fromPath($this->getDetailsBaseUrl());

        if ($this->cube instanceof IcingaDbCube && $this->cube->hasBaseFilter()) {
            /** @var Rule $baseFilter */
            $baseFilter = $this->cube->getBaseFilter();
            $url->setFilter($baseFilter);
        }

        $urlParams = $url->getParams();

        if (! $this->cube::isUsingIcingaDb()) {
            $dimensions = array_merge(array_keys($this->cube->listDimensions()), $this->cube->listSlices());
            $urlParams->add('dimensions', DimensionParams::update($dimensions)->getParams());
            $prefix = $this->cube::SLICE_PREFIX;
        }

        foreach ($this->cube->listDimensionsUpTo($this->dimension['name']) as $dimensionName) {
            $urlParams->add($prefix . $dimensionName, $this->dimension['row']->$dimensionName);
        }

        foreach ($this->cube->getSlices() as $key => $val) {
            $urlParams->add($prefix . $key, $val);
        }

        return $url;
    }

    /**
     * Get the URL for a slice.
     *
     * This is used to create a link to slice the cube by a given dimension.
     *
     * @return Url
     */
    protected function getSliceUrl(): IcingaUrl
    {
        return $this->view->url()
            ->setParam(
                $this->cube::SLICE_PREFIX . $this->dimension['name'],
                $this->dimension['row']->{$this->dimension['name']}
            );
    }

    /**
     * Get the class string for a given dimension name and row.
     *
     * This is used to create the class attribute for the dimension container.
     *
     * @return string
     */
    protected function getDimensionClassString(): string
    {
        return implode(' ', $this->getDimensionClasses());
    }

    /**
     * Get the base class for the dimension that indicates the dimension level.
     *
     * @return string
     */
    protected function getDimensionBaseClass(): string
    {
        return 'cube-dimension' . $this->level;
    }

    /**
     * Render the label for a given dimension name.
     *
     * @return HtmlDocument
     */
    protected function getDimensionLabel(): HtmlDocument
    {
        $label = new HtmlDocument();
        $label->addHtml(
            new Text($this->dimension['row']->{$this->dimension['name']} ?: '_')
        );

        // If there is a next dimension and it has a summary, append the sum to the label
        if (
            ($next = $this->cube->getDimensionAfter($this->dimension['name']))
            && isset($this->dimension['summaries']->{$next->getName()})
        ) {
            $label->addHtml(
                new HtmlElement(
                    'span',
                    new Attributes(['class' => 'sum']),
                    new Text(' (' . $this->getDimensionSum($next) . ')')
                )
            );
        }

        return $label;
    }
}
