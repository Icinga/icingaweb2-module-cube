<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Generator;
use Icinga\Data\Tree\TreeNode;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Web\Url as IcingaUrl;
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
    protected View $view;

    protected Cube $cube;

    /** @var array Our dimensions */
    protected array $dimensions;

    /** @var array Our dimensions in regular order */
    protected array $dimensionOrder;

    /** @var array Our dimensions in reversed order as a quick lookup source */
    protected array $reversedDimensions;

    /** @var array Level (deepness) for each dimension (0, 1, 2...) */
    protected array $dimensionLevels;

    protected object|array $facts;

    /** @var object The row before the current one */
    protected object $lastRow;

    /**
     * Current summaries
     *
     * This is an object of objects, with dimension names being the keys and
     * a facts row containing current (rollup) summaries for that dimension
     * being its value
     *
     * @var object
     */
    protected object $summaries;

    protected bool $started;

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
     * Render the given facts.
     *
     * @param object $facts
     *
     * @return string
     */
    abstract public function renderFacts(object $facts): string;

    /**
     * Returns the base url for the details action.
     *
     * @return string
     */
    abstract protected function getDetailsBaseUrl(): string;

    /**
     * Get the severity sort columns.
     *
     * @return Generator
     */
    abstract protected function getSeveritySortColumns(): Generator;

    /**
     * Render the badges for the Icinga DB cube.
     *
     * @param object $parts An object of state class => count pairs
     * @param object $facts The facts object containing information about the current cube
     *
     * @return string
     */
    abstract protected function renderIcingaDbCubeBadges(object $parts, object $facts): string;

    /**
     * Initialize all.
     */
    protected function initialize(): void
    {
        $this->started = false;
        $this->initializeDimensions()
            ->initializeFacts()
            ->initializeLastRow()
            ->initializeSummaries();
    }

    /**
     * Initialize the last row object.
     *
     * @return $this
     */
    protected function initializeLastRow(): static
    {
        $object = (object) array();
        foreach ($this->dimensions as $dimension) {
            $object->{$dimension->getName()} = null;
        }

        $this->lastRow = $object;

        return $this;
    }

    /**
     * Initialize the dimensions order and reversed order and the levels.
     *
     * @return $this
     */
    protected function initializeDimensions(): static
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
     * Initialize the cube facts.
     *
     * @return $this
     */
    protected function initializeFacts(): static
    {
        $this->facts = $this->cube->listFacts();

        return $this;
    }

    /**
     * Initialize the summaries object.
     *
     * @return $this
     */
    protected function initializeSummaries(): static
    {
        $this->summaries = (object) array();

        return $this;
    }

    /**
     * Get whether the given row starts a new dimension.
     * If so store the values as summary for the new dimension.
     *
     * @param object $row
     *
     * @return bool
     */
    protected function startsDimension(object $row): bool
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
     * Extract the facts from a row object.
     *
     * @param object $row
     *
     * @return object
     */
    protected function extractFacts(object $row): object
    {
        $res = (object) array();

        foreach ($this->facts as $fact) {
            $res->$fact = $row->$fact;
        }

        return $res;
    }

    public function render(View $view): string
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
     * Sort the results by severity.
     *
     * @param $results       array The fetched results
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

    /**
     * Render a single row
     *
     * @param object $row
     *
     * @return string
     */
    protected function renderRow(object $row): string
    {
        $htm = '';
        if ($this->startsDimension($row)) {
            return $htm;
        }

        $htm .= $this->closeDimensionsForRow($row);
        $htm .= $this->beginDimensionsForRow($row);
        $htm .= $this->renderFacts($row);
        $this->lastRow = $row;

        return $htm;
    }

    /**
     * Begin the dimensions for a given row
     *
     * @param object $row
     *
     * @return string
     */
    protected function beginDimensionsForRow(object $row): string
    {
        $last = $this->lastRow;
        foreach ($this->dimensionOrder as $name) {
            if ($last->$name !== $row->$name) {
                return $this->beginDimensionsUpFrom($name, $row);
            }
        }

        return '';
    }

    /**
     * Begin the dimensions up from a given dimension
     *
     * @param string $dimension The dimension to begin from
     * @param object $row       The current row
     *
     * @return string
     */
    protected function beginDimensionsUpFrom(string $dimension, object $row): string
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

    /**
     * Close the dimensions for a given row
     *
     * @param object $row
     *
     * @return string
     */
    protected function closeDimensionsForRow(object $row): string
    {
        $last = $this->lastRow;
        foreach ($this->dimensionOrder as $name) {
            if ($last->$name !== $row->$name) {
                return $this->closeDimensionsDownTo($name);
            }
        }

        return '';
    }

    /**
     * Close the dimensions down to a given dimension
     *
     * @param string $name The dimension to close down to
     *
     * @return string
     */
    protected function closeDimensionsDownTo(string $name): string
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

    /**
     * Close all dimensions
     *
     * @return string
     */
    protected function closeDimensions(): string
    {
        $htm = '';
        foreach ($this->reversedDimensions as $name) {
            $htm .= $this->closeDimension($name);
        }

        return $htm;
    }

    /**
     * Close a dimension
     *
     * @param string $name The name of the dimension to close
     *
     * @return string
     */
    protected function closeDimension(string $name): string
    {
        if (! $this->started) {
            return '';
        }

        $indent = $this->getIndent($name);
        return $indent . '  </div>' . "\n" . $indent . "</div><!-- $name -->\n";
    }

    /**
     * Get the indent for a given dimension name
     *
     * @param string $name The name of the dimension
     *
     * @return string
     */
    protected function getIndent(string $name): string
    {
        return str_repeat('    ', $this->getLevel($name));
    }

    /**
     * Begin a dimension
     *
     * @param string $name The name of the dimension to begin
     * @param object $row  The current row
     *
     * @return string
     */
    protected function beginDimension(string $name, object $row): string
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
     * @param string $name
     * @param object $row
     *
     * @return string
     */
    protected function renderDimensionLabel(string $name, object $row): string
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

    /**
     * Get the URL for a slice.
     *
     * This is used to create a link to slice the cube by a given dimension.
     *
     * @param string $name The name of the dimension
     * @param object $row  The current row
     *
     * @return Url
     */
    protected function getSliceUrl(string $name, object $row): IcingaUrl
    {
        return $this->view->url()
            ->setParam($this->cube::SLICE_PREFIX . $name, $row->$name);
    }

    /**
     * Get the class string for a given dimension name and row.
     *
     * This is used to create the class attribute for the dimension container.
     *
     * @param string $name The name of the dimension
     * @param object $row  The current row
     *
     * @return string
     */
    protected function getDimensionClassString(string $name, object $row): string
    {
        return implode(' ', $this->getDimensionClasses($name, $row));
    }

    /**
     * Get the classes for a given dimension name and row.
     *
     * This is used to create the class attribute for the dimension container.
     *
     * @param string $name The name of the dimension
     * @param object $row  The current row
     *
     * @return array
     */
    protected function getDimensionClasses(string $name, object $row): array
    {
        return ['cube-dimension' . $this->getLevel($name)];
    }

    /**
     * Get the level (deepness) of a given dimension name.
     *
     * This is used to determine the indentation level for the dimension
     * container.
     *
     * @param string $name The name of the dimension
     *
     * @return int
     */
    protected function getLevel(string $name): int
    {
        return $this->dimensionLevels[$name];
    }

    /**
     * Begin the container for the cube
     *
     * @return string
     */
    protected function beginContainer(): string
    {
        return '<div class="cube">' . "\n";
    }

    /**
     * End the container for the cube
     *
     * @return string
     */
    protected function endContainer(): string
    {
        return '</div>' . "\n";
    }

    /**
     * Make a badge HTML snippet.
     *
     * @param string $class The class to use for the badge
     * @param int    $count The count to display in the badge
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
     * Render the badges for the IDO cube as HTML snippet.
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
     * Get the filter for the badges.
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
     * Get the main badge and remove it from the parts.
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
     * Well... just to be on the safe side.
     */
    public function __destruct()
    {
        unset($this->cube);
    }
}
