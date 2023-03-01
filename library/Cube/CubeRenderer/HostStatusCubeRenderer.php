<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\CubeRenderer;

use Generator;
use Icinga\Module\Cube\CubeRenderer;

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
        } elseif ($sums->hosts_unhandled_unreachable > 0) {
            $severityClass[] = 'unreachable';
        }

        if (empty($severityClass)) {
            if ($sums->hosts_down > 0) {
                $severityClass = ['critical', 'handled'];
            } elseif ($sums->hosts_unreachable > 0) {
                $severityClass = ['unreachable', 'handled'];
            } else {
                $severityClass[] = 'ok';
            }
        }

        return array_merge($classes, $severityClass);
    }

    public function renderFacts($facts)
    {
        $indent = str_repeat('    ', 3);
        $parts = array();

        if ($facts->hosts_unhandled_down > 0) {
            $parts['critical'] = $facts->hosts_unhandled_down;
        }

        if ($facts->hosts_unhandled_unreachable > 0) {
            $parts['unreachable'] = $facts->hosts_unhandled_unreachable;
        }

        if ($facts->hosts_down > 0 && $facts->hosts_down > $facts->hosts_unhandled_down) {
            $parts['critical handled'] = $facts->hosts_down - $facts->hosts_unhandled_down;
        }

        if ($facts->hosts_unreachable > 0 && $facts->hosts_unreachable > $facts->hosts_unhandled_unreachable) {
            $parts['unreachable handled'] = $facts->hosts_unreachable - $facts->hosts_unhandled_unreachable;
        }

        if ($facts->hosts_cnt > $facts->hosts_down && $facts->hosts_cnt > $facts->hosts_unreachable) {
            $parts['ok'] = $facts->hosts_cnt - $facts->hosts_down - $facts->hosts_unreachable;
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

    protected function makeBadgeHtml($class, $count)
    {
        $indent = str_repeat('    ', 3);
        return sprintf(
            '%s<span class="%s">%s</span>',
            $indent,
            $class,
            $count
        ) . "\n";
    }

    protected function getDetailsBaseUrl()
    {
        return 'cube/hosts/details';
    }

    protected function getSeveritySortColumns(): Generator
    {
        $columns = ['down', 'unreachable'];
        foreach ($columns as $column) {
            yield "hosts_unhandled_$column";
        }

        foreach ($columns as $column) {
            yield "hosts_$column";
        }
    }
}
