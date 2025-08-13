<?php

// Icinga Web 2 Cube Module | (c) 2025 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web\Widget;

use Icinga\Module\Cube\Dimension;

class ServiceDimensionWidget extends DimensionWidget
{
    /**
     * If the cube is using Icinga DB, the URL leads to the Icinga DB service list.
     * If not, the URL leads to the details action of the IdoServicesController.
     *
     * @return string
     */
    protected function getDetailsBaseUrl(): string
    {
        if ($this->cube::isUsingIcingaDb()) {
            return 'icingadb/services';
        }

        return 'cube/services/details';
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

        if ($sums->services_unhandled_critical > 0) {
            $severityClass = ['critical'];
        } elseif ($sums->services_unhandled_unknown > 0) {
            $severityClass = ['unknown'];
        } elseif ($sums->services_unhandled_warning > 0) {
            $severityClass = ['warning'];
        } elseif ($sums->services_critical > 0) {
            $severityClass = ['critical', 'handled'];
        } elseif ($sums->services_unknown > 0) {
            $severityClass = ['unknown', 'handled'];
        } elseif ($sums->services_warning > 0) {
            $severityClass = ['warning', 'handled'];
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
        return $this->dimension['summaries']->{$next->getName()}->services_cnt;
    }
}
