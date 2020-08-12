<?php

namespace Icinga\Module\Cube;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

/**
 * Base class for cube elements
 *
 * This class contains useful data like dimensions, slices and cube related method that helps to create a cube
 *
 * @package Icinga\Module\Cube
 */
abstract class BaseCube extends BaseHtmlElement
{
    /**
     * @var iterable Rows from database
     */
    protected $data;

    /**
     * @var array dimensions given in the url
     */
    protected $dimensions = [];

    /**
     * @var array dimensions without slice values
     */
    protected $dimensionsWithoutSliceValues = [];

    /**
     * @var array slices
     */
    protected $slices = [];

    public function __construct($data, array $dimensions, array $slices = null)
    {
        $this->data = $data;
        $this->dimensions = $dimensions;
        $this->slices = $slices;
    }

    /**
     * @return iterable
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Return all given dimensions
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
     * @return array
     */
    public function getSlices()
    {
        return $this->slices;
    }

    /**
     * Return all unsliced dimensions
     *
     * @return array
     */
    public function getDimensionsWithoutSliceValues()
    {
        if (isset($this->dimensionsWithoutSliceValues))
        {
            return $this->dimensionsWithoutSliceValues;
        }

        if ($this->hasSlices()) {
            foreach ($this->getDimensions() as $key => $dimension) {
                if (! array_key_exists($dimension, $this->slices)) {
                    $this->dimensionsWithoutSliceValues[] = $dimension;
                }
            }
            return $this->dimensionsWithoutSliceValues;
        }

        return $this->getDimensions();
    }

    /**
     * Render the dimension
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
     * Render the measure
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
