<?php

namespace Icinga\Module\Cube;

use Icinga\Exception\IcingaException;

abstract class Cube
{
    protected $chosenFacts;

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

    public function getRenderer()
    {
        throw new IcingaException('Got no cube renderer');
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

        $this->reorderDimensions($positions);
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

        $this->reorderDimensions($positions);
        return $this;
    }

    protected function flipPositions(& $array, $pos1, $pos2)
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

    public function hasFact($name)
    {
        return array_key_exists($name, $this->chosenFacts);
    }

    public function getDimension($name)
    {
        return $this->dimensions[$name];
    }

    public function listFacts()
    {
        return $this->chosenFacts;
    }

    public function chooseFacts($facts)
    {
        $this->chosenFacts = $facts;
        return $this;
    }

    public function listDimensions()
    {
        return array_values(array_diff(array_keys($this->dimensions), $this->listSlices()));
    }

    public function listColumns()
    {
        return array_merge($this->listDimensions(), $this->listFacts());
    }

    public function render($view, CubeRenderer $renderer = null)
    {
        if ($renderer === null) {
            $renderer = $this->getRenderer();
        }

        return $renderer->render($view);
    }
}
