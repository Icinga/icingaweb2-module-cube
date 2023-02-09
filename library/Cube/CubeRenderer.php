<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Web\View;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;

/**
 * CubeRenderer base class
 *
 * Every Cube Renderer must extend this class.
 *
 * TODO: Should we introduce DimensionRenderer, FactRenderer and SummaryHelper
 *       instead?
 *
 * @package Icinga\Module\Cube
 */
abstract class CubeRenderer
{
    /** @var View */
    protected $view;

    /** @var Cube */
    protected $cube;

    /** @var array Our dimensions */
    protected $dimensions;

    /** @var array Our dimensions in regular order */
    protected $dimensionOrder;

    /** @var array Our dimensions in reversed order as a quick lookup source */
    protected $reversedDimensions;

    /** @var array Level (deepness) for each dimension (0, 1, 2...) */
    protected $dimensionLevels;

    protected $facts;

    /** @var object The row before the current one */
    protected $lastRow;

    /**
     * Current summaries
     *
     * This is an object of objects, with dimension names being the keys and
     * a facts row containing current (rollup) summaries for that dimension
     * being it's value
     *
     * @var object
     */
    protected $summaries;

    protected $started;

    /**
     * CubeRenderer constructor.
     *
     * @param Cube $cube
     */
    public function __construct(Cube $cube)
    {
        $this->cube = $cube;
    }

    /**
     * Render the given facts
     *
     * @param $facts
     * @return string
     */
    abstract public function renderFacts($facts);

    /**
     * Returns the base url for the details action
     *
     * @return string
     */
    abstract protected function getDetailsBaseUrl();

    /**
     * Initialize all we need
     */
    protected function initialize()
    {
        $this->started = false;
        $this->initializeDimensions()
            ->initializeFacts()
            ->initializeLastRow()
            ->initializeSummaries();
    }

    /**
     * @return $this
     */
    protected function initializeLastRow()
    {
        $object = (object) array();
        foreach ($this->dimensions as $dimension) {
            $object->{$dimension->getName()} = null;
        }

        $this->lastRow = $object;

        return $this;
    }

    /**
     * @return $this
     */
    protected function initializeDimensions()
    {
        $this->dimensions = $this->cube->listDimensions();

        $min = 3;
        $cnt = count($this->dimensions);
        if ($cnt < $min) {
            $pos = 0;
            $diff = $min - $cnt;
            $this->dimensionOrder = [];
            foreach ($this->dimensions as $name => $_) {
                $this->dimensionOrder[$pos++ + $diff] = $name;
            }
        } else {
            $this->dimensionOrder = array_keys($this->dimensions);
        }

        $this->reversedDimensions = array_reverse($this->dimensionOrder);
        $this->dimensionLevels = array_flip($this->dimensionOrder);
        return $this;
    }

    /**
     * @return $this
     */
    protected function initializeFacts()
    {
        $this->facts = $this->cube->listFacts();
        return $this;
    }

    /**
     * @return $this
     */
    protected function initializeSummaries()
    {
        $this->summaries = (object) array();
        return $this;
    }

    /**
     * @param object $row
     * @return bool
     */
    protected function startsDimension($row)
    {
        foreach ($this->dimensionOrder as $name) {
            if ($row->$name === null) {
                $this->summaries->$name = $this->extractFacts($row);
                return true;
            }
        }

        return false;
    }

    /**
     * @param $row
     * @return object
     */
    protected function extractFacts($row)
    {
        $res = (object) array();

        foreach ($this->facts as $fact) {
            $res->$fact = $row->$fact;
        }

        return $res;
    }

    public function render(View $view)
    {
        $this->view = $view;
        $this->initialize();
        $htm = $this->beginContainer();
        foreach ($this->cube->fetchAll() as $row) {
            $htm .= $this->renderRow($row);
        }

        return $htm . $this->closeDimensions() . $this->endContainer();
    }

    protected function renderRow($row)
    {
        $htm = '';
        if ($dimension = $this->startsDimension($row)) {
            return $htm;
        }

        $htm .= $this->closeDimensionsForRow($row);
        $htm .= $this->beginDimensionsForRow($row);
        $htm .= $this->renderFacts($row);
        $this->lastRow = $row;
        return $htm;
    }

    protected function beginDimensionsForRow($row)
    {
        $last = $this->lastRow;
        foreach ($this->dimensionOrder as $name) {
            if ($last->$name !== $row->$name) {
                return $this->beginDimensionsUpFrom($name, $row);
            }
        }

        return '';
    }

    protected function beginDimensionsUpFrom($dimension, $row)
    {
        $htm = '';
        $found = false;

        foreach ($this->dimensionOrder as $name) {
            if ($name === $dimension) {
                $found = true;
            }

            if ($found) {
                $htm .= $this->beginDimension($name, $row);
            }
        }

        return $htm;
    }

    protected function closeDimensionsForRow($row)
    {
        $last = $this->lastRow;
        foreach ($this->dimensionOrder as $name) {
            if ($last->$name !== $row->$name) {
                return $this->closeDimensionsDownTo($name);
            }
        }

        return '';
    }

    protected function closeDimensionsDownTo($name)
    {
        $htm = '';

        foreach ($this->reversedDimensions as $dimension) {
            $htm .= $this->closeDimension($dimension);

            if ($name === $dimension) {
                break;
            }
        }

        return $htm;
    }

    protected function closeDimensions()
    {
        $htm = '';
        foreach ($this->reversedDimensions as $name) {
            $htm .= $this->closeDimension($name);
        }

        return $htm;
    }

    protected function closeDimension($name)
    {
        if (! $this->started) {
            return '';
        }

        $indent = $this->getIndent($name);
        return $indent . '  </div>' . "\n" . $indent . "</div><!-- $name -->\n";
    }

    protected function getIndent($name)
    {
        return str_repeat('    ', $this->getLevel($name));
    }

    protected function beginDimension($name, $row)
    {
        $indent = $this->getIndent($name);
        if (! $this->started) {
            $this->started = true;
        }
        $view = $this->view;
        $dimension = $this->cube->getDimension($name);

        return
            $indent . '<div class="'
            . $this->getDimensionClassString($name, $row)
            . '">' . "\n"
            . $indent . '  <div class="header"><a href="'
            . $this->getDetailsUrl($name, $row)
            . '" title="' . $view->escape(sprintf('Show details for %s: %s', $dimension->getLabel(), $row->$name)) . '"'
            . ' data-base-target="_next">'
            . $this->renderDimensionLabel($name, $row)
            . '</a><a class="icon-filter" href="'
            . $this->getSliceUrl($name, $row)
            . '" title="' . $view->escape('Slice this cube') . '"></a></div>' . "\n"
            . $indent . '  <div class="body">' . "\n";
    }

    /**
     * Render the label for a given dimension name
     *
     * To have some context available, also
     *
     * @param $name
     * @param $row
     * @return string
     */
    protected function renderDimensionLabel($name, $row)
    {
        $caption = $row->$name;
        if (empty($caption)) {
            $caption = '_';
        }

        return $this->view->escape($caption);
    }

    protected function getDetailsUrl($name, $row)
    {
        $url = Url::fromPath($this->getDetailsBaseUrl());

        if ($this->cube instanceof IcingaDbCube && $this->cube->hasBaseFilter()) {
            $url->setQueryString(QueryString::render($this->cube->getBaseFilter()));
        }

        $urlParams = $url->getParams();

        $dimensions = array_merge(array_keys($this->cube->listDimensions()), $this->cube->listSlices());
        $urlParams->add('dimensions', DimensionParams::update($dimensions)->getParams());

        foreach ($this->cube->listDimensionsUpTo($name) as $dimensionName) {
            $urlParams->add($this->cube::SLICE_PREFIX . $dimensionName, $row->$dimensionName);
        }

        foreach ($this->cube->getSlices() as $key => $val) {
            $urlParams->add($this->cube::SLICE_PREFIX . $key, $val);
        }

        return $url;
    }

    protected function getSliceUrl($name, $row)
    {
        return $this->view->url()
            ->setParam($this->cube::SLICE_PREFIX . $name, $row->$name);
    }

    protected function isOuterDimension($name)
    {
        return $this->reversedDimensions[0] !== $name;
    }

    protected function getDimensionClassString($name, $row)
    {
        return implode(' ', $this->getDimensionClasses($name, $row));
    }

    protected function getDimensionClasses($name, $row)
    {
        return array('cube-dimension' . $this->getLevel($name));
    }

    protected function getLevel($name)
    {
        return $this->dimensionLevels[$name];
    }

    /**
     * @return string
     */
    protected function beginContainer()
    {
        return '<div class="cube">' . "\n";
    }

    /**
     * @return string
     */
    protected function endContainer()
    {
        return '</div>' . "\n";
    }

    /**
     * Well... just to be on the safe side
     */
    public function __destruct()
    {
        unset($this->cube);
    }
}
