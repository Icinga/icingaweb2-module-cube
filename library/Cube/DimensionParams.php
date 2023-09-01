<?php

// Icinga Web 2 Cube Module | (c) 2020 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Icinga\Web\Url;
use ipl\Stdlib\Str;

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

    public static function fromUrl(Url $url)
    {
        return static::fromString($url->getParam('dimensions'));
    }

    public static function fromArray(array $dimensions = [])
    {
        $self = new static();

        $self->dimensions = array_filter($dimensions);

        return $self;
    }

    public static function fromString($dimensions)
    {
        return static::fromArray(Str::trimSplit($dimensions));
    }

    /**
     * @param $dimension
     *
     * @return $this
     */
    public function add($dimension)
    {
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
     * @return DimensionParams
     */
    public static function update($dimensions)
    {
        $self = new static();
        $self->dimensions = $dimensions;

        return $self;
    }

    /**
     * @return string encoded dimensions separated by coma
     */
    public function getParams()
    {
        return implode(',', $this->dimensions);
    }

    /**
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }
}
