<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Generator;
use Icinga\Data\Tree\TreeNode;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Web\View;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Filter\Rule;
use ipl\Web\Url;
use stdClass;

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
     * Get the severity sort columns
     *
     * @return Generator
     */
    abstract protected function getSeveritySortColumns(): Generator;

    /**
     * Render the badges for the Icinga DB cube.
     *
     * @param stdClass $parts An object of state class => count pairs
     * @param object   $facts The facts object containing information about the current cube
     *
     * @return string
     */
    abstract protected function renderIcingaDbCubeBadges(stdClass $parts, object $facts): string;

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

        $results = $this->cube->fetchAll();

        if (! empty($results) && $this->cube::isUsingIcingaDb()) {
            $sortBy = $this->cube->getSortBy();
            if ($sortBy && $sortBy[0] === $this->cube::DIMENSION_SEVERITY_SORT_PARAM) {
                $isSortDirDesc = isset($sortBy[1]) && $sortBy[1] !== 'asc';
                $results = $this->sortBySeverity($results, $isSortDirDesc);
            }
        }

        foreach ($results as $row) {
            $htm .= $this->renderRow($row);
        }

        return $htm . $this->closeDimensions() . $this->endContainer();
    }


    /**
     * Sort the results by severity
     *
     * @param $results array The fetched results
     * @param $isSortDirDesc bool Whether the sort direction is descending
     *
     * @return Generator
     */
    private function sortBySeverity(array $results, bool $isSortDirDesc): Generator
    {
        $perspective = end($this->dimensionOrder);
        $resultsCount = count($results);
        $tree = [new TreeNode()];

        $prepareHeaders = function (array $tree, object $row): TreeNode {
            $node = (new TreeNode())
                ->setValue($row);
            $parent = end($tree);
            $parent->appendChild($node);

            return $node;
        };

        $i = 0;
        do {
            $row = $results[$i];
            while ($row->$perspective === null) {
                $tree[] = $prepareHeaders($tree, $row);

                if (! isset($results[++$i])) {
                    break;
                }

                $row = $results[$i];
            }

            for (; $i < $resultsCount; $i++) {
                $row = $results[$i];

                $anyNull = false;
                foreach ($this->dimensionOrder as $dimension) {
                    if ($row->$dimension === null) {
                        $anyNull = true;
                        array_pop($tree);
                    }
                }

                if ($anyNull) {
                    break;
                }

                $prepareHeaders($tree, $row);
            }
        } while ($i < $resultsCount);

        $nodes = function (TreeNode $node) use (&$nodes, $isSortDirDesc): Generator {
            yield $node->getValue();
            $children = $node->getChildren();

            uasort($children, function (TreeNode $a, TreeNode $b) use ($isSortDirDesc): int {
                foreach ($this->getSeveritySortColumns() as $column) {
                    $comparison = $a->getValue()->$column <=> $b->getValue()->$column;
                    if ($comparison !== 0) {
                        return $comparison * ($isSortDirDesc ? -1 : 1);
                    }
                }

                // $a and $b are equal in terms of $priorities.
                return 0;
            });

            foreach ($children as $node) {
                yield from $nodes($node);
            }
        };

        return $nodes($tree[1]);
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

    protected function getDetailsUrl(string $name, object $row): Url
    {
        $prefix = '';
        $url = Url::fromPath($this->getDetailsBaseUrl());

        if ($this->cube instanceof IcingaDbCube && $this->cube->hasBaseFilter()) {
            /** @var Filter\Rule $baseFilter */
            $baseFilter = $this->cube->getBaseFilter();
            $url->setFilter($baseFilter);
        }

        $urlParams = $url->getParams();

        if (! $this->cube::isUsingIcingaDb()) {
            $dimensions = array_merge(array_keys($this->cube->listDimensions()), $this->cube->listSlices());
            $urlParams->add('dimensions', DimensionParams::update($dimensions)->getParams());
            $prefix = $this->cube::SLICE_PREFIX;
        }

        foreach ($this->cube->listDimensionsUpTo($name) as $dimensionName) {
            $urlParams->add($prefix . $dimensionName, $row->$dimensionName);
        }

        foreach ($this->cube->getSlices() as $key => $val) {
            $urlParams->add($prefix . $key, $val);
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
     * Make a badge HTML snippet
     *
     * @param string $class The class to use for the badge
     * @param int $count The count to display in the badge
     *
     * @return string
     */
    protected function makeBadgeHtml(string $class, int $count): string
    {
        $indent = str_repeat('    ', 3);
        return sprintf(
                '%s<span class="%s">%s</span>',
                $indent,
                $class,
                $count
            ) . "\n";
    }

    /**
     * Render the badges for the IDO cube as HTML snippet
     *
     * @param array $parts An array of state class => count pairs
     *
     * @return string
     */
    protected function renderIdoCubeBadges(array $parts): string
    {
        $indent = str_repeat('    ', 3);
        $main = '';
        $sub = '';
        foreach ($parts as $class => $count) {
            if ($count === 0) {
                continue;
            }

            if ($main === '') {
                $main = $this->makeBadgeHtml($class, $count);
            } else {
                $sub .= $this->makeBadgeHtml($class, $count);
            }
        }
        if ($sub !== '') {
            $sub = $indent
                . '<span class="others">'
                . "\n    "
                . $sub
                . $indent
                . "</span>\n";
        }

        return $main . $sub;
    }

    /**
     * Get the filter for the badges
     *
     * @param object $facts The facts object containing information about the current cube
     *
     * @return Rule
     */
    protected function getBadgeFilter(object $facts): Rule
    {
        $filter = Filter::all();

        if ($this->cube instanceof IcingaDbCube && $this->cube->hasBaseFilter()) {
            $filter->add($this->cube->getBaseFilter());
        }

        foreach ($this->cube->listDimensions() as $dimensionName => $_) {
            $filter->add(Filter::equal($dimensionName, $facts->$dimensionName));
        }

        return $filter;
    }

    /**
     * Get the main badge and remove it from the parts
     *
     * @param stdClass $parts An object of state class => count pairs
     *
     * @return stdClass The main badge as an object with a single property
     */
    protected function getMainBadge(stdClass $parts): stdClass
    {
        $mainKey = array_key_first((array) $parts);
        $mainBadge = new stdClass();
        $mainBadge->$mainKey = $parts->$mainKey;
        $parts->$mainKey = null;

        return $mainBadge;
    }

    /**
     * Well... just to be on the safe side
     */
    public function __destruct()
    {
        unset($this->cube);
    }
}
