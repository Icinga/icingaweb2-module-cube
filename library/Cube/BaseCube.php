<?php
// Icinga Web 2 Cube Module | (c) 2020 Icinga GmbH | GPLv2
namespace Icinga\Module\Cube;

use Icinga\Exception\IcingaException;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;

/**
 * Abstract base class for Cube
 *
 * This class includes the main functions and variables, that are necessary to create a cube
 *
 * @package Icinga\Module\Cube
 */
abstract class BaseCube extends BaseHtmlElement
{
    /**
     * @var array Rows from database
     */
    protected $data;

    /**
     * @var array dimensions given in the url
     */
    protected $dimensions;

    /**
     * @var array dimensions without slice values
     */
    protected $dimensionsWithoutSliceValues;

    /**
     * @var array slices
     */
    protected $slices;

    public function __construct($data, array $dimensions, array $slices = null)
    {
        $this->data = $data;
        $this->dimensions = $dimensions;
        $this->slices = $slices;
        $this->init();
    }

    /**
     * Overwrite this function if you want to add more constructor variables
     */
    public function init()
    {
    }


    public function getPathLabel()
    {
        $dimensions = $this->getDimensionsLabel();
        $slices     = $this->getSlicesLabel();

        $parts = array();
        if ($dimensions !== null) {
            $parts[] = $dimensions;
        }

        if ($slices !== null) {
            $parts[] = $slices;
        }

        return implode(', ', $parts);
    }

    public function listAdditionalDimensions()
    {
        $list = array();

        foreach ($this->listAvailableDimensions() as $dimension) {
            if (! array_key_exists($dimension, $this->dimensions)) {
                $list[] = $dimension;
            }
        }

        return $list;
    }

    abstract public function listAvailableDimensions();

    public function getDimensionsLabel()
    {
        $dimensions = $this->listDimensions();
        if (empty($dimensions)) {
            return null;
        }

        return implode(' -> ', $dimensions);
    }

    public function getDimensionAfter($name)
    {
        $found = false;
        $after = null;

        foreach ($this->listDimensions() as $d) {
            if ($found) {
                $after = $d;
                break;
            }

            if ($d === $name) {
                $found = true;
            }
        }

        return $after;
    }

    /**
     * Get the data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the dimensions
     *
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @return bool
     */
    public function hasSlices()
    {
        return ! empty($this->slices);
    }

    /**
     * Get the slices
     *
     * @return array|null
     */
    public function getSlices()
    {
        return $this->slices;
    }

    /**
     * Get all unsliced dimensions
     *
     * @return array
     */
    public function getDimensionsWithoutSliceValues()
    {
        if (isset($this->dimensionsWithoutSliceValues)) {
            return $this->dimensionsWithoutSliceValues;
        }

        if ($this->hasSlices()) {
            foreach ($this->dimensions as $key => $dimension) {
                if (! array_key_exists($dimension, $this->slices)) {
                    $this->dimensionsWithoutSliceValues[] = $dimension;
                }
            }
            return $this->dimensionsWithoutSliceValues;
        }

        return $this->dimensions;
    }

    public function getSlicesLabel()
    {
        $parts = array();

        $slices = $this->getSlices();
        if (empty($slices)) {
            return null;
        }
        foreach ($slices as $key => $value) {
            $parts[] = sprintf('%s = %s', $key, $value);
        }

        return implode(', ', $parts);
    }

    public function listSlices()
    {
        return array_keys($this->slices);
    }

    public function addDimension(Dimension $dimension)
    {
        $name = $dimension->getName();
        if (array_key_exists($name, $this->dimensions)) {
            throw new IcingaException('Cannot add dimension "%s" twice', $name);
        }

        $this->dimensions[$name] = $dimension;
        return $this;
    }

    public function slice($key, $value)
    {
        if ($this->hasDimension($key)) {
            $this->slices[$key] = $value;
        } else {
            throw new IcingaException('Got no such dimension: "%s"', $key);
        }

        return $this;
    }

    public function hasDimension($name)
    {
        return in_array($name, $this->dimensions);
    }

    public function listDimensions()
    {
        return array_values(
            array_diff(array_values($this->dimensions), $this->listSlices())
        );
    }

    public function removeDimension($name)
    {
        if (($key = array_search($name, $this->dimensions)) !== false) {
            unset($this->dimensions[$key]);
            $this->dimensions = array_values($this->dimensions);
        }

        if (($key = array_search($name, $this->slices)) !== false) {
            unset($this->slices[$key]);
            $this->slices = array_values($this->slices);
        }

        return $this;
    }

    public function moveDimensionUp($name)
    {
        $last = $found = null;
        $positions = $this->dimensions;

        foreach ($positions as $k => $v) {
            if ($v === $name) {
                $found = $k;
                break;
            }

            $last = $k;
        }

        if ($found !== null) {
            $this->flipPositions($positions, $last, $found);
        }

        $this->reOrderDimensions($positions);
        return $this;
    }

    public function moveDimensionDown($name)
    {
        $next = $found = null;
        $positions = $this->dimensions;

        foreach ($positions as $k => $v) {
            if ($found !== null) {
                $next = $k;
                break;
            }

            if ($v === $name) {
                $found = $k;
            }
        }

        if ($next !== null) {
            $this->flipPositions($positions, $next, $found);
        }

        $this->reOrderDimensions($positions);
        return $this;
    }

    protected function flipPositions(&$array, $pos1, $pos2)
    {
        list(
            $array[$pos1],
            $array[$pos2]
            ) = array(
            $array[$pos2],
            $array[$pos1]
            );
    }

    protected function reOrderDimensions($positions)
    {
        $this->dimensions = $positions;
    }

    /**
     * Render dimensions
     *
     * @param object $dimension
     *
     * @param string $header
     *
     * @param int $level
     *
     * @return HtmlElement
     */
    abstract public function renderDimension($dimension, $header, $level);

    /**
     * Render measure
     *
     * @param object $measure
     *
     * @param string $header
     *
     * @return HtmlElement
     */
    abstract public function renderMeasure($measure, $header);

    /**
     * @inheritDoc
     */
    protected function assemble()
    {
        CubeBuilder::build($this);
    }
}
