<?php
// Icinga Web 2 Cube Module | (c) 2020 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

class DimensionParams
{

    /**
     * @var array Raw dimensions
     */
    protected $dimensions = [];

    /**
     * @var string encoded dimensions separated by coma
     */
    protected $params;

    /**
     * DimensionParams constructor.
     *
     * @param null $url
     */
    public function __construct($url = null)
    {
        if ($url !== null) {
            $dimension = $url->getParam('dimensions');

            if (! empty($dimension)) {
                $this->dimensions = array_map('rawurldecode', explode(',', $dimension));
            }
        }
    }

    /**
     * @param $dimension
     *
     * @return $this
     */
    public function add($dimension) {
        if (! empty($dimension)) {
            $this->dimensions[] = $dimension;
        }

        return $this;
    }

    /**
     * Overwrite dimensions
     *
     * @param $dimensions
     *
     * @return $this
     */
    public function update($dimensions)
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    /**
     * @return string encoded dimensions separated by coma
     */
    public function getParams()
    {
        return implode(',', array_map('rawurlencode', $this->dimensions));
    }

    /**
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }
}
