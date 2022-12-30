<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\CubeRenderer;

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
        return 'cube/services/details';
    }

    protected function sortBySeverity($a, $b): int
    {
        if (is_array($a)) {
            $a = $a[0];
            $b = $b[0];
        }

        if ($this->isSortDirDesc) {
            $y = $a;
            $a = $b;
            $b = $y;
        }

        switch (true) {
            case $a->services_unhandled_critical > $b->services_unhandled_critical:
                return 1;
            case $a->services_unhandled_critical < $b->services_unhandled_critical:
                return -1;
            case $a->services_unhandled_unknown > $b->services_unhandled_unknown:
                return 1;
            case $a->services_unhandled_unknown < $b->services_unhandled_unknown:
                return -1;
            case $a->services_unhandled_warning > $b->services_unhandled_warning:
                return 1;
            case $a->services_unhandled_warning < $b->services_unhandled_warning:
                return -1;
            case $a->services_critical - $a->services_unhandled_critical > $b->services_critical - $b->services_unhandled_critical:
                return 1;
            case $a->services_critical - $a->services_unhandled_critical < $b->services_critical - $b->services_unhandled_critical:
                return -1;
            case $a->services_unknown - $a->services_unhandled_unknown > $b->services_unknown - $b->services_unhandled_unknown:
                return 1;
            case $a->services_unknown - $a->services_unhandled_unknown < $b->services_unknown - $b->services_unhandled_unknown:
                return -1;
            case $a->services_warning - $a->services_unhandled_warning > $b->services_warning - $b->services_unhandled_warning:
                return 1;
            case $a->services_warning - $a->services_unhandled_warning < $b->services_warning - $b->services_unhandled_warning:
                return -1;
            default:
                return 0;
        }
    }
}
