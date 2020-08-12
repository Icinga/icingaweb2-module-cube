<?php

namespace Icinga\Module\Cube;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

/**
 *
 */
class HostCube extends BaseCube
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'host-cube'];

    /**
     * @var HtmlElement
     */
    protected $parentDimension;

    /**
     * @var array
     */
    protected $params;

    /**
     * @param mixed $parentDimension
     */
    public function setParentDimension($parentDimension)
    {
        $this->parentDimension = $parentDimension;
    }

    public function getParentDimension()
    {
        return $this->parentDimension;
    }

    /**
     * set prefix to keys and remove double quotes from string
     *
     * @param array $params
     *
     * @return array
     */
    public function prepareParams(array $params) {
        // remove last three count values from array
        // TODO find better solution
        array_splice($params, -3);

        $prefix = 'host.vars.';
        $rs = [];

        foreach ($params as $key => $value) {
            if (empty($value)) continue;
            $rs[$prefix . $key] = $value;
        }

        return $rs;
    }

    public function renderDimension($dimension, $header, $level = null)
    {
       $this->params = $this->prepareParams(get_object_vars($dimension));

        $parentDimension =  Html::tag('div', ['class' => 'dimension'. ' level'.$level],
            [
                Html::tag('div', ['class' => 'header-dimension'],
                    [
                        Html::tag('div', ['class' => 'header'],
                        [
                            (new Link(
                                $dimension->$header,
                                Url::fromPath('icingadb/hosts')->setParams($this->params),
                                ['class' => 'cube-link', 'data-base-target' => '_next']
                            ))
                                ->add(Html::tag('span', ' ('. $dimension->cnt. ')')),

                            new Link(
                                '',
                                Url::fromRequest()->addParams([$header => $dimension->$header]),
                                ['class' => 'icon-filter', 'data-base-target' => '_self']
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

    public function renderMeasure($measure, $header)
    {
        $class = ' ok';
        $this->params['host.vars.' . $header] =  $measure->$header;

        if ($measure->count_down > 0) {
            $class = ' critical';
            // TODO fix: add multiple critical classes to an element
            if ($this->getParentDimension()) {
                $this->getParentDimension()->addAttributes(['class'=> $class]);
            }
        }

        return Html::tag('div', ['class' => 'measure' .$class],
            [
                Html::tag('div', ['class' => 'header'],
                    [
                        (new Link(
                            $measure->$header,
                            Url::fromPath('icingadb/hosts')->setParams($this->params),
                            ['class' => 'cube-link', 'data-base-target' => '_next']
                        )),

                        new Link(
                            '',
                            Url::fromRequest()->addParams([$header => $measure->$header]),
                            ['class' => 'icon-filter', 'data-base-target' => '_self']
                        )
                    ]
                ),
                Html::tag('div', ['class' => 'body'],
                    [
                        Html::tag('span', ['class' => $class], $measure->count_down > 0 ? $measure->count_down : $measure->count_up), // TODO FIX CLASS
                        Html::tag('span', ['class' => 'others'],
                            [
                                $measure->count_down > 0 ? Html::tag('span', ['class' => 'ok'] , $measure->count_up) : null
                            ]
                        )
                    ]
                ),
            ]
        );
    }
}
