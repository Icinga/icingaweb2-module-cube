<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\CubeRenderer;

use Generator;
use Icinga\Module\Cube\CubeRenderer;
use Icinga\Module\Icingadb\Widget\HostStateBadges;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use stdClass;

class HostStatusCubeRenderer extends CubeRenderer
{
    protected function renderDimensionLabel($name, $row)
    {
        $htm = parent::renderDimensionLabel($name, $row);

        if (($next = $this->cube->getDimensionAfter($name)) && isset($this->summaries->{$next->getName()})) {
            $htm .= ' <span class="sum">(' . $this->summaries->{$next->getName()}->hosts_cnt . ')</span>';
        }

        return $htm;
    }

    protected function getDimensionClasses($name, $row)
    {
        $classes = parent::getDimensionClasses($name, $row);
        $sums = $row;

        $next = $this->cube->getDimensionAfter($name);
        if ($next && isset($this->summaries->{$next->getName()})) {
            $sums = $this->summaries->{$next->getName()};
        }

        $severityClass = [];
        if ($sums->hosts_unhandled_down > 0) {
            $severityClass[] = 'critical';
        } elseif (isset($sums->hosts_unhandled_unreachable) && $sums->hosts_unhandled_unreachable > 0) {
            $severityClass[] = 'unreachable';
        }

        if (empty($severityClass)) {
            if ($sums->hosts_down > 0) {
                $severityClass = ['critical', 'handled'];
            } elseif (isset($sums->hosts_unreachable) && $sums->hosts_unreachable > 0) {
                $severityClass = ['unreachable', 'handled'];
            } else {
                $severityClass[] = 'ok';
            }
        }

        return array_merge($classes, $severityClass);
    }

    public function renderFacts($facts)
    {
        $parts = [];
        $partsObj = new stdClass();

        if ($facts->hosts_unhandled_down > 0) {
            $parts['critical'] = $facts->hosts_unhandled_down;
            $partsObj->hosts_down_unhandled = $facts->hosts_unhandled_down;
        }

        if (isset($facts->hosts_unhandled_unreachable) && $facts->hosts_unhandled_unreachable > 0) {
            $parts['unreachable'] = $facts->hosts_unhandled_unreachable;
            $partsObj->hosts_unreachable_unhandled = $facts->hosts_unhandled_unreachable;
        }

        if ($facts->hosts_down > 0 && $facts->hosts_down > $facts->hosts_unhandled_down) {
            $downHandled = $facts->hosts_down - $facts->hosts_unhandled_down;

            $parts['critical handled'] = $downHandled;
            $partsObj->hosts_down_handled = $downHandled;
        }

        if (
            isset($facts->hosts_unreachable, $facts->hosts_unhandled_unreachable)
            && $facts->hosts_unreachable > 0
            && $facts->hosts_unreachable >
            $facts->hosts_unhandled_unreachable
        ) {
            $unreachableHandled = $facts->hosts_unreachable - $facts->hosts_unhandled_unreachable;

            $parts['unreachable handled'] = $unreachableHandled;
            $partsObj->hosts_unreachable_handled = $unreachableHandled;
        }

        if (
            $facts->hosts_cnt > $facts->hosts_down
            && (! isset($facts->hosts_unreachable) || $facts->hosts_cnt > $facts->hosts_unreachable)
        ) {
            $ok = $facts->hosts_cnt - $facts->hosts_down;
            if (isset($facts->hosts_unreachable)) {
                $ok -= $facts->hosts_unreachable;
            }

            $parts['ok'] = $ok;
            $partsObj->hosts_up = $ok;
        }

        if ($this->cube::isUsingIcingaDb()) {
            return $this->renderIcingaDbCubeBadges($partsObj, $facts);
        }

        return $this->renderIdoCubeBadges($parts);
    }

    protected function getDetailsBaseUrl()
    {
        return 'cube/hosts/details';
    }

    protected function getSeveritySortColumns(): Generator
    {
        yield from ['hosts_unhandled_down', 'hosts_down'];
    }

    protected function renderIcingaDbCubeBadges(stdClass $parts, object $facts): string
    {
        $filter = $this->getBadgeFilter($facts);
        $mainBadge = $this->getMainBadge($parts);

        $main = (new HostStateBadges($mainBadge))
            ->setBaseFilter($filter)
            ->addAttributes(new Attributes(['data-base-target' => '_next']));

        $others = new HtmlElement(
            'span',
            new Attributes(['class' => 'others']),
            (new HostStateBadges($parts))
                ->setBaseFilter($filter)
                ->addAttributes(new Attributes(['data-base-target' => '_next']))
        );

        return $main->render() . $others->render();
    }
}
