<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\CubeRenderer;

use Generator;
use Icinga\Module\Cube\CubeRenderer;
use Icinga\Module\Icingadb\Widget\ServiceStateBadges;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use stdClass;

class ServiceStatusCubeRenderer extends CubeRenderer
{
    public function renderFacts($facts)
    {
        $parts = [];
        $partsObj = new stdClass();

        if ($facts->services_unhandled_critical > 0) {
            $parts['critical'] = $facts->services_unhandled_critical;
            $partsObj->services_critical_unhandled = $facts->services_unhandled_critical;
        }

        if ($facts->services_unhandled_unknown > 0) {
            $parts['unknown'] = $facts->services_unhandled_unknown;
            $partsObj->services_unknown_unhandled = $facts->services_unhandled_unknown;
        }

        if ($facts->services_unhandled_warning > 0) {
            $parts['warning'] = $facts->services_unhandled_warning;
            $partsObj->services_warning_unhandled = $facts->services_unhandled_warning;
        }

        if ($facts->services_critical > 0 && $facts->services_critical > $facts->services_unhandled_critical) {
            $criticalHandled = $facts->services_critical - $facts->services_unhandled_critical;

            $parts['critical handled'] = $criticalHandled;
            $partsObj->services_critical_handled = $criticalHandled;
        }

        if ($facts->services_unknown > 0 && $facts->services_unknown > $facts->services_unhandled_unknown) {
            $unknownHandled = $facts->services_unknown - $facts->services_unhandled_unknown;

            $parts['unknown handled'] = $unknownHandled;
            $partsObj->services_unknown_handled = $unknownHandled;
        }

        if ($facts->services_warning > 0 && $facts->services_warning > $facts->services_unhandled_warning) {
            $warningHandled = $facts->services_warning - $facts->services_unhandled_warning;

            $parts['warning handled'] = $warningHandled;
            $partsObj->services_warning_handled = $warningHandled;
        }

        if (
            $facts->services_cnt > $facts->services_critical
            && $facts->services_cnt > $facts->services_warning
            && $facts->services_cnt > $facts->services_unknown
        ) {
            $ok = $facts->services_cnt - $facts->services_critical - $facts->services_warning -
                $facts->services_unknown;

            $parts['ok'] = $ok;
            $partsObj->services_ok = $ok;
        }

        if ($this->cube::isUsingIcingaDb()) {
            return $this->renderIcingaDbCubeBadges($partsObj, $facts);
        }

        return $this->renderIdoCubeBadges($parts);
    }

    /**
     * @inheritdoc
     */
    protected function renderDimensionLabel($name, $row)
    {
        $htm = parent::renderDimensionLabel($name, $row);

        if (($next = $this->cube->getDimensionAfter($name)) && isset($this->summaries->{$next->getName()})) {
            $htm .= ' <span class="sum">(' . $this->summaries->{$next->getName()}->services_cnt . ')</span>';
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

        if ($sums->services_unhandled_critical > 0) {
            $severityClass[] = 'critical';
        } elseif ($sums->services_unhandled_unknown > 0) {
            $severityClass[] = 'unknown';
        } elseif ($sums->services_unhandled_warning > 0) {
            $severityClass[] = 'warning';
        }

        if (empty($severityClass)) {
            if ($sums->services_critical > 0) {
                $severityClass = ['critical', 'handled'];
            } elseif ($sums->services_unknown > 0) {
                $severityClass = ['unknown', 'handled'];
            } elseif ($sums->services_warning > 0) {
                $severityClass = ['warning', 'handled'];
            } else {
                $severityClass[] = 'ok';
            }
        }

        return array_merge($classes, $severityClass);
    }

    protected function getDetailsBaseUrl()
    {
        return 'cube/services/details';
    }

    protected function getSeveritySortColumns(): Generator
    {
        $columns = ['critical', 'unknown', 'warning'];
        foreach ($columns as $column) {
            yield "services_unhandled_$column";
        }

        foreach ($columns as $column) {
            yield "services_$column";
        }
    }

    protected function renderIcingaDbCubeBadges(stdClass $parts, object $facts): string
    {
        $filter = $this->getBadgeFilter($facts);
        $mainBadge = $this->getMainBadge($parts);

        $main = (new ServiceStateBadges($mainBadge))
            ->setBaseFilter($filter)
            ->addAttributes(new Attributes(['data-base-target' => '_next']));

        $others = new HtmlElement(
            'span',
            new Attributes(['class' => 'others']),
            (new ServiceStateBadges($parts))
                ->setBaseFilter($filter)
                ->addAttributes(new Attributes(['data-base-target' => '_next']))
        );

        return $main->render() . $others->render();
    }
}
