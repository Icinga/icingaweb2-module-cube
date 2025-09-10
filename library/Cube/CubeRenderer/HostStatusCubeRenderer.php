<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\CubeRenderer;

use Generator;
use Icinga\Module\Cube\CubeRenderer;
use Icinga\Module\Cube\Web\Widget\HostDimensionWidget;
use Icinga\Module\Icingadb\Widget\HostStateBadges;
use Icinga\Web\View;
use ipl\Html\Attributes;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use stdClass;

class HostStatusCubeRenderer extends CubeRenderer
{
    public function createFacts(object $facts): HtmlDocument
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

        if (isset($facts->hosts_pending) && $facts->hosts_pending > 0) {
            $parts['pending'] = $facts->hosts_pending;
            $partsObj->hosts_pending = $facts->hosts_pending;
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
            && (! isset($facts->hosts_pending) || $facts->hosts_cnt > $facts->hosts_pending)
        ) {
            $ok = $facts->hosts_cnt - $facts->hosts_down;
            if (isset($facts->hosts_unreachable)) {
                $ok -= $facts->hosts_unreachable;
            }

            if (isset($facts->hosts_pending)) {
                $ok -= $facts->hosts_pending;
            }

            $parts['ok'] = $ok;
            $partsObj->hosts_up = $ok;
        }

        if ($this->cube::isUsingIcingaDb()) {
            return $this->createIcingaDbCubeBadges($partsObj, $facts);
        }

        return $this->createIdoCubeBadges($parts);
    }

    protected function getSeveritySortColumns(): Generator
    {
        yield from ['hosts_unhandled_down', 'hosts_down'];
    }

    protected function createIcingaDbCubeBadges(object $parts, object $facts): HtmlDocument
    {
        $filter = $this->getBadgeFilter($facts);
        $mainBadge = $this->getMainBadge($parts);

        $partsBottom = new stdClass();
        $bottomKeys = [
            'hosts_unreachable_unhandled',
            'hosts_unreachable_handled',
            'hosts_pending'
        ];

        foreach ($bottomKeys as $key) {
            if (property_exists($parts, $key)) {
                $partsBottom->$key = $parts->$key;
                unset($parts->$key);
            }
        }

        $main = (new HostStateBadges($mainBadge))
            ->setBaseFilter($filter)
            ->addAttributes(new Attributes(['data-base-target' => '_next']));

        $others = new HtmlElement(
            'span',
            new Attributes(['class' => 'others']),
            (new HostStateBadges($parts))
                ->setBaseFilter($filter)
                ->addAttributes(new Attributes(['data-base-target' => '_next'])),
            (new HostStateBadges($partsBottom))
                ->setBaseFilter($filter)
                ->addAttributes(new Attributes(['data-base-target' => '_next']))
        );

        return (new HtmlDocument())->addHtml($main, $others);
    }

    protected function createDimensionWidget(array $dimensionCache, View $view): HostDimensionWidget
    {
        return new HostDimensionWidget($dimensionCache, $this->cube, $view, $this->getLevel($dimensionCache['name']));
    }
}
