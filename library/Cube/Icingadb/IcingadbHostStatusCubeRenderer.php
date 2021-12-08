<?php

namespace Icinga\Module\Cube\Icingadb;

use Icinga\Module\Cube\CubeRenderer;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

/**
 * Class IcingadbHostStatusCubeRenderer
 * @package Icinga\Module\Cube\Icingadb
 */
class IcingadbHostStatusCubeRenderer extends CubeRenderer
{
    protected $labelUrlSuffix = [];

    protected $row;

    protected $name;

    protected function renderDimensionLabel($name, $row)
    {
        $this->row = $row;

        $this->name = $name;

        $htm = parent::renderDimensionLabel($name, $row);

        if (($next = $this->cube->getDimensionAfter($name)) && isset($this->summaries->$next)) {
            $htm .= ' <span class="sum">(' . $this->summaries->$next->hosts_cnt . ')</span>';
        }

        return $htm;
    }

    protected function getPath()
    {
        return 'icingadb/hosts';
    }

    protected function getParamUrlPrefix()
    {
        return 'host.vars.';
    }

    protected function getDimensionClasses($name, $row)
    {
        $classes = parent::getDimensionClasses($name, $row);

        $sums = $row;
        $this->labelUrlSuffix = [];
        if ($sums->hosts_down > 0) {
            $classes[] = 'critical';
            if ((int) $sums->hosts_unhandled_down === 0) {
                $classes[] = 'handled';
            }
        } elseif ($sums->hosts_unreachable > 0) {
            $classes[] = 'unreachable';
            if ((int) $sums->hosts_unhandled_unreachable === 0) {
                $classes[] = 'handled';
            }
        } else {
            $this->labelUrlSuffix = $this->getStateInfo(0);
            $classes[] = 'ok';
        }

        return $classes;
    }

    /**
     * Set given params in an array
     *
     * @param int $state
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

    public function renderFacts($facts)
    {
        $parts = array();

        if ($facts->hosts_unhandled_down > 0) {
            $parts['critical'] = $facts->hosts_unhandled_down;
            $this->labelUrlSuffix['critical'] = $this->getStateInfo(1, false);
        }

        if ($facts->hosts_down > 0 && $facts->hosts_down > $facts->hosts_unhandled_down) {
            $parts['critical handled'] = $facts->hosts_down - $facts->hosts_unhandled_down;
            $this->labelUrlSuffix['critical handled'] = $this->getStateInfo(1, true);
        }

        if ($facts->hosts_unhandled_unreachable > 0) {
            $parts['unreachable'] = $facts->hosts_unhandled_unreachable;
            $this->labelUrlSuffix['unreachable'] = ['state.is_reachable' => 'n'];
        }

        if ($facts->hosts_unreachable > 0 && $facts->hosts_unreachable > $facts->hosts_unhandled_unreachable) {
            $parts['unreachable handled'] = $facts->hosts_unreachable - $facts->hosts_unhandled_unreachable;
            $labelUrlSuffix['state.is_reachable'] = 'n';
            $labelUrlSuffix['state.is_handled'] = 'n';
            $this->labelUrlSuffix['unreachable handled'] = $labelUrlSuffix;
        }

        if ($facts->hosts_cnt > $facts->hosts_down && $facts->hosts_cnt > $facts->hosts_unreachable) {
            $parts['ok'] = $facts->hosts_cnt - $facts->hosts_down - $facts->hosts_unreachable;
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

    protected function makeBadgeHtml($class, $count)
    {
        return Html::tag('span', ['class' => $class], $count);
    }

    protected function getDetailsBaseUrl()
    {
        return 'cube/hosts/details';
    }
}
