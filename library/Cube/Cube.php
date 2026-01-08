<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Icinga\Application\Modules\Module;
use Icinga\Exception\IcingaException;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Web\View;

abstract class Cube
{
    /** @var ?string Prefix for slice params */
    public const SLICE_PREFIX = null;

    /** @var ?bool Whether the icingadb backend is in use */
    public const IS_USING_ICINGADB = null;

    /** @var array<string, Dimension> Available dimensions */
    protected $availableDimensions;

    /** @var array Fact names */
    protected $chosenFacts;

    /** @var Dimension[] */
    protected $dimensions = array();

    protected $slices = array();

    protected $renderer;

    abstract public function fetchAll();

    /**
     * Get whether the icingadb backend is in use
     *
     * @return bool
     */
    public static function isUsingIcingaDb(): bool
    {
        return static::IS_USING_ICINGADB
            ?? (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend());
    }

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

        return implode(' -> ', array_map(function ($d) {
            return $d->getLabel();
        }, $dimensions));
    }

    public function getSlicesLabel()
    {
        $parts = array();

        $slices = $this->getSlices();
        if (empty($slices)) {
            return null;
        }
        foreach ($slices as $key => $value) {
            $parts[] = sprintf('%s = %s', $this->getDimension($key)->getLabel(), $value);
        }

        return implode(', ', $parts);
    }

    /**
     * Create a new dimension
     *
     * @param string $name
     * @return Dimension
     */
    abstract public function createDimension($name);

    protected function registerAvailableDimensions()
    {
        if ($this->availableDimensions !== null) {
            return;
        }

        $this->availableDimensions = [];
        foreach ($this->listAvailableDimensions() as $name => $label) {
            if (! isset($this->availableDimensions[$name])) {
                $this->availableDimensions[$name] = $this->createDimension($name)->setLabel($label);
            } else {
                $this->availableDimensions[$name]->addLabel($label);
            }
        }
    }

    public function listAdditionalDimensions()
    {
        $this->registerAvailableDimensions();

        $list = [];
        foreach ($this->availableDimensions as $name => $dimension) {
            if (! $this->hasDimension($name)) {
                $list[$name] = $dimension->getLabel();
            }
        }

        return $list;
    }

    abstract public function listAvailableDimensions();

    public function getDimensionAfter($name)
    {
        $found = false;
        $after = null;

        foreach ($this->listDimensions() as $k => $d) {
            if ($found) {
                $after = $d;
                break;
            }

            if ($k === $name) {
                $found = true;
            }
        }

        return $after;
    }

    public function listDimensionsUpTo($name)
    {
        $res = array();
        foreach ($this->listDimensions() as $d => $_) {
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
        $positions = array_keys($this->dimensions);

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
        $dimensions = array();
        foreach ($positions as $pos => $key) {
            $dimensions[$key] = $this->dimensions[$key];
        }

        $this->dimensions = $dimensions;
    }

    public function addDimension(Dimension $dimension)
    {
        $name = $dimension->getName();
        if ($this->hasDimension($name)) {
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
        return array_diff_key($this->dimensions, $this->slices);
    }

    public function listColumns()
    {
        return array_merge(array_keys($this->listDimensions()), $this->listFacts());
    }

    /**
     * @param View $view
     * @param ?CubeRenderer $renderer
     * @return string
     */
    public function render(View $view, ?CubeRenderer $renderer = null)
    {
        if ($renderer === null) {
            $renderer = $this->getRenderer();
        }

        return $renderer->render($view);
    }
}
