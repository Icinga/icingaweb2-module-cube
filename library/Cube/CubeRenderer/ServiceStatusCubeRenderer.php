<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\CubeRenderer;

use Generator;
use Icinga\Module\Cube\CubeRenderer;

class ServiceStatusCubeRenderer extends CubeRenderer
{
    public function renderFacts($facts)
    {
        $indent = str_repeat('    ', 3);
        $parts = [];

        if ($facts->services_unhandled_critical > 0) {
            $parts['critical'] = $facts->services_unhandled_critical;
        }

        if ($facts->services_unhandled_unknown > 0) {
            $parts['unknown'] = $facts->services_unhandled_unknown;
        }

        if ($facts->services_unhandled_warning > 0) {
            $parts['warning'] = $facts->services_unhandled_warning;
        }

        if ($facts->services_critical > 0 && $facts->services_critical > $facts->services_unhandled_critical) {
            $parts['critical handled'] = $facts->services_critical - $facts->services_unhandled_critical;
        }

        if ($facts->services_unknown > 0 && $facts->services_unknown > $facts->services_unhandled_unknown) {
            $parts['unknown handled'] = $facts->services_unknown - $facts->services_unhandled_unknown;
        }

        if ($facts->services_warning > 0 && $facts->services_warning > $facts->services_unhandled_warning) {
            $parts['warning handled'] = $facts->services_warning - $facts->services_unhandled_warning;
        }

        if (
            $facts->services_cnt > $facts->services_critical && $facts->services_cnt > $facts->services_warning
            && $facts->services_cnt > $facts->services_unknown
        ) {
            $parts['ok'] = $facts->services_cnt - $facts->services_critical - $facts->services_warning -
                $facts->services_unknown;
        }

        $main = '';
        $sub = '';
        foreach ($parts as $class => $count) {
            if ($count === 0) {
                continue;
            }

            if ($main === '') {
                $main = $this->makeBadgeHtml($class, $count);
            } else {
                $sub .= $this->makeBadgeHtml($class, $count);
            }
        }
        if ($sub !== '') {
            $sub = $indent
                . '<span class="others">'
                . "\n    "
                . $sub
                . $indent
                . "</span>\n";
        }

        return $main . $sub;
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
}
