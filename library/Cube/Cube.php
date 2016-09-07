<?php

namespace Icinga\Module\Cube;

use Icinga\Exception\IcingaException;

abstract class Cube
{
    protected $chosenFacts;

    protected $dimensions = array();

    protected $slices = array();

    abstract public function fetchAll();

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
}
