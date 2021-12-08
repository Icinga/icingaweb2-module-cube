<?php

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Module\Cube\CubeRenderer;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

/**
 * Class IcingadbStatusCubeRenderer
 * @package Icinga\Module\Cube\Icingadb
 */
class IcingadbStatusCubeRenderer extends CubeRenderer
{
    protected $labelUrlSuffix = [];

    protected $row;

    protected $name;

    protected function getPath()
    {
        return 'icingadb/services';
    }

    protected function getParamUrlPrefix()
    {
        return 'service.vars.';
    }

    public function renderFacts($facts)
    {
        $parts = [];

        if ($facts->services_unhandled_critical > 0) {
            $parts['critical'] = $facts->services_unhandled_critical;
            $this->labelUrlSuffix['critical'] = $this->getStateInfo(2, false);
        }

        if ($facts->services_critical > 0 && $facts->services_critical > $facts->services_unhandled_critical) {
            $parts['critical handled'] = $facts->services_critical - $facts->services_unhandled_critical;
            $this->labelUrlSuffix['critical handled'] = $this->getStateInfo(2, true);
        }

        if ($facts->services_unhandled_warning > 0) {
            $parts['warning'] = $facts->services_unhandled_warning;
            $this->labelUrlSuffix['warning'] = $this->getStateInfo(1, false);
        }

        if ($facts->services_warning > 0 && $facts->services_warning > $facts->services_unhandled_warning) {
            $parts['warning handled'] = $facts->services_warning - $facts->services_unhandled_warning;
            $this->labelUrlSuffix['warning handled'] = $this->getStateInfo(1, true);
        }

        if ($facts->services_unhandled_unknown > 0) {
            $parts['unknown'] = $facts->services_unhandled_unknown;
            $this->labelUrlSuffix['unknown'] = $this->getStateInfo(3, false);
        }

        if ($facts->services_unknown > 0 && $facts->services_unknown > $facts->services_unhandled_unknown) {
            $parts['unknown handled'] = $facts->services_unknown - $facts->services_unhandled_unknown;
            $this->labelUrlSuffix['unknown handled'] = $this->getStateInfo(3, true);
        }

        if ($facts->services_cnt > $facts->services_critical && $facts->services_cnt > $facts->services_warning
            && $facts->services_cnt > $facts->services_unknown) {
            $parts['ok'] = $facts->services_cnt - $facts->services_critical - $facts->services_warning -
                $facts->services_unknown;
            $this->labelUrlSuffix['ok'] = $this->getStateInfo(0);
        }

        $main = '';
        $mainLink = '';
        $sub = '';
        $subLink = [];
        $name = $this->name;

        foreach ($parts as $class => $count) {
            $params = [$this->getParamUrlPrefix() . $this->name => $this->row->$name];

            if ($this->cube->getSlices()) {
                foreach ($this->cube->getSlices() as $slice => $value) {
                    $params[$this->getParamUrlPrefix() . $slice] = $value;
                }
            }

            $url = Url::fromPath($this->getPath())->with(
                array_merge(
                    $params,
                    $this->labelUrlSuffix[$class]
                )
            );

            if ($main === '') {
                $main = $this->makeBadgeHtml($class, $count);
                $mainLink = (new Link(
                    $main,
                    $url,
                    ['class' => 'icingaDbObjects', 'data-base-target' => '_next']
                ));
            } else {
                $subLink[] = (new Link(
                    $this->makeBadgeHtml($class, $count),
                    $url,
                    ['class' => 'icingaDbObjects', 'data-base-target' => '_next']
                ));
            }
        }

        if ($subLink) {
            $sub = Html::tag('span', ['class' => "others icingadbOthers"], $subLink);
        }

        return $mainLink . $sub;
    }

    /**
     * @inheritdoc
     */
    protected function renderDimensionLabel($name, $row)
    {
        $this->row = $row;

        $this->name = $name;

        $htm = parent::renderDimensionLabel($name, $row);

        if (($next = $this->cube->getDimensionAfter($name)) && isset($this->summaries->$next)) {
            $htm .= ' <span class="sum">(' . $this->summaries->$next->services_cnt . ')</span>';
        }

        return $htm;
    }

    protected function getDimensionClasses($name, $row)
    {
        $classes = parent::getDimensionClasses($name, $row);

        $sums = $row;
        if ($sums->services_critical > 0) {
            $classes[] = 'critical';
            if ((int) $sums->services_unhandled_critical === 0) {
                $classes[] = 'handled';
            }
        } elseif ($sums->services_warning > 0) {
            $classes[] = 'warning';
            if ((int) $sums->services_unhandled_warning === 0) {
                $classes[] = 'handled';
            }
        } elseif ($sums->services_unknown > 0) {
            $classes[] = 'unknown';
            if ((int) $sums->services_unhandled_unknown === 0) {
                $classes[] = 'handled';
            }
        } else {
            $classes[] = 'ok';
        }

        return $classes;
    }

    protected function makeBadgeHtml($class, $count)
    {
        return Html::tag('span', ['class' => $class], $count);
    }

    protected function getDetailsBaseUrl()
    {
        return 'cube/services/details';
    }


    /**
     * Set given params in an array
     *
     * @param int $state
     *  state 1 = ok |
     *  state 2 = warning |
     *  state 3 = critical |
     *  state 4 = unknown
     *
     * @param null|bool $isHandled
     *
     * @return array
     */
    private function getStateInfo($state, $isHandled = null)
    {
        $stateInfo = [];
        $stateInfo['state.soft_state'] = $state;

        if ($isHandled !== null) {
            $isHandledStr = $isHandled ? 'y' : 'n';
            $stateInfo['state.is_handled'] = $isHandledStr;
        }

        return $stateInfo;
    }
}
