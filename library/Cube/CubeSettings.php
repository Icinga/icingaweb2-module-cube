<?php

namespace Icinga\Module\Cube;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

class CubeSettings extends BaseHtmlElement
{
    /** @var Url */
    protected $baseUrl;

    /** @var array */
    protected $dimensions = [];

    /** @var string */
    protected $dimensionsParam = 'dimensions';

    protected $defaultAttributes = ['class' => 'cube-settings'];

    protected $tag = 'div';

    /** @var array */
    protected $slices = [];

    /** @var string */
    protected $dimensionName;

    /**
     * @return string
     */
    public function getDimensionName()
    {
        return $this->dimensionName;
    }

    /**
     * @param string $dimensionName
     */
    public function setDimensionName($dimensionName)
    {
        $this->dimensionName = $dimensionName;
    }

    /**
     * @return Url
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param Url $baseUrl
     *
     * @return $this
     */
    public function setBaseUrl(Url $baseUrl)
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @param array $dimensions
     *
     * @return $this
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    /**
     * @return string
     */
    public function getDimensionsParam()
    {
        return $this->dimensionsParam;
    }

    /**
     * @param string $dimensionsParam
     *
     * @return $this
     */
    public function setDimensionsParam($dimensionsParam)
    {
        $this->dimensionsParam = $dimensionsParam;

        return $this;
    }

    /**
     * @param array $slices
     *
     * @return $this
     */
    public function setSlices($slices)
    {
        $this->slices = $slices;

        return $this;
    }

    /**
     * @return array
     */
    public function getSlices()
    {
        return $this->slices;
    }


    /**
     * @param int $indexToMove index value of array value that has to be moved
     *
     * @param boolean $isDirectionUp move direction
     *
     * @return array swapped associative array
     */
    protected function swapArray($indexToMove, $isDirectionUp)
    {
        $urlDimensions = $this->getDimensions();
        $otherIndex = $isDirectionUp ? $indexToMove - 1 : $indexToMove + 1;
        if (isset($urlDimensions[$otherIndex])) {
            $tempVal = $urlDimensions[$otherIndex];
            $urlDimensions[$otherIndex] = $urlDimensions[$indexToMove];
            $urlDimensions[$indexToMove] = $tempVal;
        }
        return array_combine($urlDimensions, $urlDimensions);
    }

    protected function assemble()
    {
        // Combine for key access
        $allDimensions = array_combine($this->getDimensions(), $this->getDimensions());
        $indexCounter = 0;
        $content = [];
        $slicedContent = [];
        $firstValueInList = true;


        foreach ($allDimensions as $dimension) {
            $dimensions = $allDimensions;
            $isSliced = false;
            // to add all other dimensions in link
            unset($dimensions[$dimension]);

            $this->setDimensionName($dimension);

            $flippedArray = array_flip($this->getSlices());
            if ($value = array_search($dimension, $flippedArray)) {
                $isSliced = true;
                $sliceName = 'Slice/Filter: ' . $flippedArray[$value] . ' = ' . $value;
                $this->setDimensionName($sliceName);
            }

            $element = Html::tag('div');

            // if the given dimension is included in slice values, set cancel link without this dimension
            $cancelUrl = $isSliced ? $this->getBaseUrl()->without($dimension) : $this->getBaseUrl()->with([$this->getDimensionsParam() => implode(',', $dimensions)]);
            $element->add(new Link(
                new Icon('cancel'),
                !empty($dimensions) ? $cancelUrl : Url::fromPath('cube/icingadb'),
                ['class' => 'cube-settings-btn']
            ));

            if (! $isSliced && $indexCounter && count($allDimensions) - count($this->getSlices()) > 1 ) {
                $element->add(new Link(
                    new Icon('angle-double-up'),
                    !empty($dimensions) ? $this->getBaseUrl()->with([$this->getDimensionsParam() => implode(',', $this->swapArray($indexCounter, true))]) : $this->getBaseUrl(),
                    ['class' => 'cube-settings-btn']
                ));
            } else {
                $element->add(Html::tag('span', ['class' => 'cube-settings-btn']));
            }

            // set arrow down if not last,not first, not sliced and difference of all dimension and sliced is bigger then 1
            // because there is no need to set arrow just for one list
            if (! $isSliced && $indexCounter != count($allDimensions) - 1 && count($allDimensions) > 1 && count($allDimensions) - count($this->getSlices()) > 1) {
                $element->add(new Link(
                    new Icon('angle-double-down'),
                    !empty($dimensions) ? $this->getBaseUrl()->with([$this->getDimensionsParam() => implode(',', $this->swapArray($indexCounter, false))]) : $this->getBaseUrl(),
                    ['class' => 'cube-settings-btn']
                ));
            } else {
                $element->add(Html::tag('span', ['class' => 'cube-settings-btn']));
            }

            $element->add(Html::tag('span', ['class' => 'dimension-name'], $this->getDimensionName()));

            // we separate sliced elements, so we can add these at the end of the list
            if ($isSliced) {
                $slicedContent[] = $element;
            } else {
                $content[] = $element;
            }

            $indexCounter++;
        }
        // add sliced content at the end of the list
        while (! empty($slicedContent)) {
            $content[] = array_pop($slicedContent);
        }

        $this->add(Html::tag('ul', ['class' => 'dimension-list'], Html::wrapEach($content, 'li')));
    }
}
