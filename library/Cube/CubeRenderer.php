<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Generator;
use Icinga\Data\Tree\TreeNode;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\Web\Widget\DimensionWidget;
use Icinga\Web\View;
use ipl\Html\Attributes;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Filter\Rule;
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
     * @return HtmlDocument
     */
    abstract public function createFacts(object $facts): HtmlDocument;

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
     * @return HtmlDocument
     */
    abstract protected function createIcingaDbCubeBadges(object $parts, object $facts): HtmlDocument;

    /**
     * Create a dimension widget for the given dimension cache.
     *
     * @param array $dimensionCache
     * @param View  $view
     *
     * @return DimensionWidget
     */
    abstract protected function createDimensionWidget(array $dimensionCache, View $view): DimensionWidget;

    /**
     * Initialize all.
     */
    protected function initialize(): void
    {
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
        $lastRow = $this->lastRow;
        $cubeContainer = new HtmlElement('div', new Attributes(['class' => 'cube']));

        /**
         * Cache dimension names, rows and summaries to add to the next lower dimension.
         *
         * - 'body'      => The body of the dimension container as a HtmlElement
         * - 'name'      => The name of the dimension
         * - 'row'       => The row object for the dimension
         * - 'summaries' => The summaries object for the dimension
         */
        $dimensionCache = [];

        // Initialize the previous level to -1, so that the first dimension level is never smaller.
        $lastLevel = -1;

        // Store the lowest dimension level to determine which dimension containers should
        // be added directly to the cube container.
        $lowestLevel = array_key_first($this->dimensionOrder);

        // If dimension 2 is the only dimension, we use a container for flexbox layout.
        if ($lowestLevel === 2) {
            $dimension2Container = new HtmlElement(
                'div',
                new Attributes(['class' => 'dimension-2-container'])
            );
        }

        $results = $this->cube->fetchAll();

        if (! empty($results) && $this->cube::isUsingIcingaDb()) {
            $sortBy = $this->cube->getSortBy();
            if ($sortBy && $sortBy[0] === $this->cube::DIMENSION_SEVERITY_SORT_PARAM) {
                $isSortDirDesc = isset($sortBy[1]) && $sortBy[1] !== 'asc';
                $results = $this->sortBySeverity($results, $isSortDirDesc);
            }
        }

        $isStartingRow = false;

        foreach ($results as $row) {
            // The first row of each dimension contains the dimension summaries, so we skip it.
            if ($this->startsDimension($row)) {
                $isStartingRow = true;
                continue;
            }
            foreach ($this->dimensionOrder as $level => $dimensionName) {
                if (
                    (! $isStartingRow || $level === array_key_first($this->dimensionOrder))
                    && $lastRow->$dimensionName === $row->$dimensionName
                ) {
                    continue;
                }

                // If the current dimension level is lower than the previous one, add the previous dimension level
                // container to the current dimension level container or to the cube container if the current level
                // is the lowest level.
                if ($level < $lastLevel) {
                    if ($level === 0) {
                        // Add last dimension 1 container to dimension 0 container body.
                        $dimensionCache[0]['body']->addHtml(
                            $this->createDimensionWidget($dimensionCache[1], $view)
                        );

                        // Add dimension 0 container to cube
                        $cubeContainer->addHtml(
                            $this->createDimensionWidget($dimensionCache[0], $view)
                        );
                    } elseif ($level === 1) {
                        if ($lowestLevel === 0) {
                            // Add dimension 1 container to dimension 0 container body.
                            $dimensionCache[0]['body']->addHtml(
                                $this->createDimensionWidget($dimensionCache[1], $view)
                            );
                        } else {
                            // Add dimension 1 container directly to cube if dimension 1 is the lowest level.
                            $cubeContainer->addHtml(
                                $this->createDimensionWidget($dimensionCache[1], $view)
                            );
                        }
                    }
                }

                if ($level < 2) {
                    // Initialize the dimension body cache for the current level.
                    $dimensionCache[$level]['body'] = new HtmlDocument();

                    // Store the dimension name, row, and summaries for the current level.
                    $dimensionCache[$level]['name'] = $dimensionName;
                    $dimensionCache[$level]['row'] = clone $row;
                    $dimensionCache[$level]['summaries'] = clone $this->summaries;
                } elseif ($level === 2) {
                    if ($lowestLevel < 2) {
                        // Add dimension 2 rect to dimension 1 container body.
                        $dimensionCache[1]['body']->addHtml(
                            $this->createDimensionWidget(
                                [
                                    'body'      => $this->createFacts($row),
                                    'name'      => $dimensionName,
                                    'row'       => $row,
                                    'summaries' => $this->summaries
                                ],
                                $view
                            )
                        );
                    } else {
                        // Initialize the dimension 2 container if it does not exist yet.
                        if (! isset($dimensionCache[2]['body'])) {
                            $dimensionCache[2]['body'] = new HtmlDocument();
                        }

                        // Add dimension 2 rect to dimension 2 container.
                        $dimension2Container->addHtml(
                            $this->createDimensionWidget(
                                [
                                    'body'      => $this->createFacts($row),
                                    'name'      => $dimensionName,
                                    'row'       => $row,
                                    'summaries' => $this->summaries
                                ],
                                $view
                            )
                        );
                    }
                }

                // Store the current dimension level as last level to compare with the next row.
                $lastLevel = $level;
            }

            $isStartingRow = false;

            // Store the current row as the last row to compare with the next row.
            $lastRow = $row;
        }

        switch ($lowestLevel) {
            case 0:
                if (isset($dimensionCache[0]['body'])) {
                    // Add last dimension 1 container to dimension 0 container body.
                    $dimensionCache[0]['body']->addHtml(
                        $this->createDimensionWidget($dimensionCache[1], $view)
                    );
                    // Add dimension 0 container to cube.
                    $cubeContainer->addHtml(
                        $this->createDimensionWidget($dimensionCache[0], $view)
                    );
                }
                break;
            case 1:
                if (isset($dimensionCache[1]['body'])) {
                    // Add dimension 1 container directly to cube if no dimension 1 is the lowest level.
                    $cubeContainer->addHtml(
                        $this->createDimensionWidget($dimensionCache[1], $view)
                    );
                }
                break;
            case 2:
                if (isset($dimensionCache[2]['body'])) {
                    // Add dimension 2 container directly to cube if dimension 2 is the lowest level.
                    $cubeContainer->addHtml($dimension2Container);
                }
                break;
        }

        return $cubeContainer->render();
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
     * Create the badges for the IDO cube.
     *
     * @param array $parts An array of state class => count pairs
     *
     * @return HtmlDocument
     */
    protected function createIdoCubeBadges(array $parts): HtmlDocument
    {
        $badges = new HtmlDocument();
        $others = new HtmlElement('span', new Attributes(['class' => 'others']));
        $mainDone = false;

        foreach ($parts as $class => $count) {
            if ($count === 0) {
                continue;
            }

            if (! $mainDone) {
                $badges->addHtml(new HtmlElement('span', new Attributes(['class' => $class]), new Text($count)));
                $mainDone = true;
            } else {
                $others->addHtml(new HtmlElement('span', new Attributes(['class' => $class]), new Text($count)));
            }
        }

        $badges->addHtml($others);

        return $badges;
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
