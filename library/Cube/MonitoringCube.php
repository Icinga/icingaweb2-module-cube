<?php

namespace Icinga\Module\Cube;

use Icinga\Module\Cube\Common\IcingaDb;
use Icinga\Module\Cube\Icingadb\IcingadbCube;
use Icinga\Module\Icingadb\Common\Auth;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Sql\Connection;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

/**
 *  Class MonitoringCube
 *
 * This class includes HTML structure of cube dimension and measure
 */
abstract class MonitoringCube extends BaseCube
{
    use Auth;
    use IcingaDb;

    protected $tag = 'div';

    /**
     * @var Url Base url
     */
    protected $url;

    /**
     * @var string Url prefix depending on the host or service
     */
    protected $paramUrlPrefix;

    /**
     * @var HtmlElement Parent dimension of current measure
     */
    protected $parentDimension;

//    /** @var IcingaDb */
//    protected $backend;

    /**
     * To get all needed information like class name, count, etc
     *
     * use setters to save information for measure
     *
     * @param $measure
     *
     * @param $header
     *
     * @return MeasureInfo
     */
    abstract protected function createMeasureInfo($measure, $header);

    /**
     * prepare all that small cube footer span with count information
     *
     * @param $measure
     *
     * @return HtmlElement
     */
    abstract protected function prepareMeasureCountDetails($measure);

    /**
     * @return string path depending on the host or service
     */
    abstract protected function getPath();

    /**
     * @return string type depending on the host or service
     */
    abstract protected function getDetailPath();

    /**
     * @var string Url prefix depending on the host or service
     */
    abstract protected function getParamUrlPrefix();


    public function init()
    {
        $this->url = Url::fromPath($this->getPath());

        $this->paramUrlPrefix = $this->getParamUrlPrefix();
    }

    /**
     * Set current parent dimension
     *
     * @param HtmlElement $parentDimension
     */
    private function setParentDimension($parentDimension)
    {
        $this->parentDimension = $parentDimension;
    }

    /**
     * Get current parent dimension
     *
     * @return HtmlElement
     */
    private function getParentDimension()
    {
        return $this->parentDimension;
    }

    protected function getUrlParams($dimension)
    {
        $prefix = $this->getParamUrlPrefix();
        $dimensionArr = $dimension->vars;
        $urlParams = [];

        foreach ($this->getDimensions() as $value) {
            if (isset($dimensionArr[$value]) && $dimensionArr[$value] !== null) {
                $urlParams[$prefix . $value] = $dimensionArr[$value];
            }
        }

        return $urlParams;
    }

    protected function getUrlParamsWithoutPrefix($dimension)
    {
        $dimensionArr = $dimension->vars;
        $urlParams = [];

        foreach ($this->getDimensions() as $value) {
            if (isset($dimensionArr[$value]) && $dimensionArr[$value] !== null) {
                $urlParams[DimensionParams::update($value)->getParams()] = $dimensionArr[$value];
            }
        }

        return $urlParams;
    }
//
//    /**
//     * We can steal the DB connection directly from a Monitoring backend
//     *
//     * @param IcingaDb $backend
//     * @return $this
//     */
//    public function setBackend(Connection $backend)
//    {
//        $this->backend = $backend;
//
//        return $this;
//    }
//
//    /**
//     * Provice access to our DB resource
//     *
//     * This lazy-loads the default monitoring backend in case no DB has been
//     * given
//     *
//     * @return Connection
//     */
//    public function db()
//    {
//        return $this->getDb();
//    }

    protected function preparedUrl(array $paramToAdd)
    {
        $dimensions = $this->getDimensions();
        $key = array_search(array_values(array_flip($paramToAdd))[0], $dimensions);

        unset($dimensions[$key]);
        $key = array_keys($paramToAdd)[0];
        $val =  array_values($paramToAdd)[0];

        $new[DimensionParams::update($key)->getParams()] = $val;
        $dimensions[] = array_values(array_flip($paramToAdd))[0];

        return Url::fromRequest()
            ->setParam('dimensions', DimensionParams::update($dimensions)->getParams())
            ->addParams($new);
    }

    /**
     * Render dimension
     *
     * @param object $dimension
     *
     * @param string $header
     *
     * @param string|null $level
     *
     * @return HtmlElement
     */
    public function renderDimension($dimension, $header, $level = null)
    {
        $parentDimension =  Html::tag(
            'div',
            ['class' => 'dimension'. ' level'.$level],
            [
                Html::tag(
                    'div',
                    ['class' => 'header-dimension'],
                    [
                        Html::tag(
                            'div',
                            ['class' => 'header'],
                            [
                                (new Link(
                                    $dimension->$header,
                                    Url::fromPath('cube/' . $this->getDetailPath())
                                        ->setParam(
                                            'dimensions',
                                            DimensionParams::update($this->getDimensions())->getParams()
                                        )->addParams($this->getUrlParamsWithoutPrefix($dimension)),
                                    [
                                        'class' => 'cube-link',
                                        'data-base-target' => '_next',
                                        'title' => 'Show details for ' . $header . ': ' . $dimension->$header
                                    ]
                                ))->add(Html::tag('span', ' ('. $dimension->cnt. ')')),
                                new Link(
                                    '',
                                    $this->preparedUrl([$header => $dimension->$header]),
                                    [
                                        'class'            => 'icon-filter',
                                        'data-base-target' => '_self',
                                        'title'            => 'Slice this cube'
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ]
        );

        $this->setParentDimension($parentDimension);

        return $parentDimension;
    }

    public function applyIcingaDbRestrictions($query)
    {
        $this->applyRestrictions($query);

        return $this;
    }

    /**
     * Render measure
     *
     * @param object $measure
     *
     * @param string $header
     *
     * @return HtmlElement
     */
    public function renderMeasure($measure, $header)
    {
        $measureInfo = $this->createMeasureInfo($measure, $header);
        // TODO fix: add multiple critical classes to an element
        if ($measureInfo->hasProblem() && $this->getParentDimension()) {
            $this->getParentDimension()->addAttributes(['class'=> $measureInfo->getMeasureCssClasses()]);
        }
//        var_dump(Url::fromPath('cube/' . $this->getDetailPath()));die;

        return Html::tag(
            'div',
            ['class' => 'measure ' . $measureInfo->getMeasureCssClasses()],
            [
                Html::tag(
                    'div',
                    ['class' => 'header'],
                    [
                        (new Link(
                            $measure->$header,
                            Url::fromPath('cube/' . $this->getDetailPath())
                                ->setParam(
                                    'dimensions',
                                    DimensionParams::update($this->getDimensions())->getParams()
                                )->addParams($this->getUrlParamsWithoutPrefix($measure)),
                            [
                                'class' => 'cube-link',
                                'data-base-target' => '_next',
                                'title' => 'Show details for ' . $header . ': ' . $measure->$header
                            ]
                        )),
                        new Link(
                            '',
                            $this->preparedUrl([$header => $measure->$header]),
                            ['class' => 'icon-filter', 'data-base-target' => '_self', 'title' => 'Slice this cube']
                        )
                    ]
                ),
                Html::tag(
                    'div',
                    ['class' => 'body'],
                    [
                        new Link(
                            Html::tag(
                                'span',
                                ['class' => $measureInfo->getMeasureCssClasses()],
                                $measureInfo->getMeasureLabel()
                            ),
                            $this->url->with(
                                array_merge($this->getUrlParams($measure), $measureInfo->getMeasureLabelUrlSuffix())
                            ),
                            ['data-base-target' => '_next']
                        ),
                        $measureInfo->getMeasureCountDetails()
                    ]
                ),
            ]
        );
    }
}
