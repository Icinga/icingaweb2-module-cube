<?php

namespace Icinga\Module\Cube;

abstract class Cube
{
    protected $chosenFacts;

    protected $dimensions;

    abstract public function fetchAll();

    public function addDimension(Dimension $dimension)
    {
        $this->dimensions[$dimension->getName()] = $dimension;
        return $this;
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
        return array_keys($this->dimensions);
    }

    public function listColumns()
    {
        return array_merge($this->listDimensions(), $this->listFacts());
    }
}
