<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Icinga\Exception\IcingaException;
use Icinga\Web\View;

abstract class Cube
{
    /** @var array Fact names */
    protected $chosenFacts;

    /** @var Dimension[] */
    protected $dimensions = array();

    protected $slices = array();

    protected $renderer;

    abstract public function fetchAll();

    public function removeDimension($name)
    {
        unset($this->dimensions[$name]);
        unset($this->slices[$name]);
        return $this;
    }

    /**
     * @return CubeRenderer
     * @throws IcingaException
     */
    public function getRenderer()
    {
        throw new IcingaException('Got no cube renderer');
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

    public function getDimensionsLabel()
    {
        $dimensions = $this->listDimensions();
        if (empty($dimensions)) {
            return null;
        }

        return implode(' -> ', $dimensions);
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

    public function listDimensionsUpTo($name)
    {
        $res = array();
        foreach ($this->listDimensions() as $d) {
            $res[] = $d;
            if ($d === $name) {
                break;
            }
        }

        return $res;
    }

    public function moveDimensionUp($name)
    {
        $last = $found = null;
        $positions = array_keys($this->dimensions);

        while (list($k, $v) = each($positions)) {
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
        $positions = array_keys($this->dimensions);

        while (list($k, $v) = each($positions)) {
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
        $dimensions = array();
        foreach ($positions as $pos => $key) {
            $dimensions[$key] = $this->dimensions[$key];
        }

        $this->dimensions = $dimensions;
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
        return array_key_exists($name, $this->dimensions);
    }

    public function hasSlice($name)
    {
        return array_key_exists($name, $this->slices);
    }

    public function listSlices()
    {
        return array_keys($this->slices);
    }

    public function getSlices()
    {
        return $this->slices;
    }

    public function hasFact($name)
    {
        return array_key_exists($name, $this->chosenFacts);
    }

    public function getDimension($name)
    {
        return $this->dimensions[$name];
    }

    /**
     * Return a list of chosen facts
     *
     * @return array
     */
    public function listFacts()
    {
        return $this->chosenFacts;
    }

    /**
     * Choose a list of facts
     *
     * @param array $facts
     * @return $this
     */
    public function chooseFacts(array $facts)
    {
        $this->chosenFacts = $facts;
        return $this;
    }

    public function listDimensions()
    {
        return array_values(
            array_diff(array_keys($this->dimensions), $this->listSlices())
        );
    }

    public function listColumns()
    {
        return array_merge($this->listDimensions(), $this->listFacts());
    }

    /**
     * @param View $view
     * @param CubeRenderer $renderer
     * @return string
     */
    public function render(View $view, CubeRenderer $renderer = null)
    {
        if ($renderer === null) {
            $renderer = $this->getRenderer();
        }

        return $renderer->render($view);
    }
}
