<?php

// Icinga Web 2 Cube Module | (c) 2025 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web\Widget;

use Icinga\Module\Cube\Dimension;

class HostDimensionWidget extends DimensionWidget
{
    /**
     * If the cube is using Icinga DB, the URL leads to the Icinga DB host list.
     * If not, the URL leads to the details action of the IdoHostsController.
     *
     * @return string
     */
    protected function getDetailsBaseUrl(): string
    {
        if ($this->cube::isUsingIcingaDb()) {
            return 'icingadb/hosts';
        }

        return 'cube/hosts/details';
    }

    /**
     * @inheritdoc
     */
    protected function getDimensionClasses(): array
    {
        $classes = [$this->getDimensionBaseClass()];
        $sums = $this->dimension['row'];

        $next = $this->cube->getDimensionAfter($this->dimension['name']);
        if ($next && isset($this->dimension['summaries']->{$next->getName()})) {
            $sums = $this->dimension['summaries']->{$next->getName()};
        }

        if ($sums->hosts_unhandled_down > 0) {
            $severityClass = ['critical'];
        } elseif (isset($sums->hosts_unhandled_unreachable) && $sums->hosts_unhandled_unreachable > 0) {
            $severityClass = ['unreachable'];
        } elseif (isset($sums->hosts_pending) && $sums->hosts_pending > 0) {
            $severityClass = ['pending'];
        } elseif ($sums->hosts_down > 0) {
            $severityClass = ['critical', 'handled'];
        } elseif (isset($sums->hosts_unreachable) && $sums->hosts_unreachable > 0) {
            $severityClass = ['unreachable', 'handled'];
        } else {
            $severityClass = ['ok'];
        }

        return array_merge($classes, $severityClass);
    }

    /**
     * @inheritdoc
     */
    protected function getDimensionSum(Dimension $next): int
    {
        return $this->dimension['summaries']->{$next->getName()}->hosts_cnt;
    }
}
