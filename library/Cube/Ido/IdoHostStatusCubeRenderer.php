<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\CubeRenderer;

class IdoHostStatusCubeRenderer extends CubeRenderer
{
    protected function renderDimensionLabel($name, $row)
    {
        $htm = parent::renderDimensionLabel($name, $row);

        if ($next = $this->cube->getDimensionAfter($name)) {
            $htm .= ' <span class="sum">(' . $this->summaries->$next->hosts_cnt . ')</span>';
        }

        return $htm;
    }

    protected function getDimensionClasses($name, $row)
    {
        $classes = parent::getDimensionClasses($name, $row);

        $sums = $row;
        if ($sums->hosts_nok > 0) {
            $classes[] = 'critical';
            if ((int) $sums->hosts_unhandled_nok === 0) {
                $classes[] = 'handled';
            }
        }

        return $classes;
    }

    public function renderFacts($facts)
    {
        $indent = str_repeat('    ', 3);
        $htm = '';
        if ($facts->hosts_nok > 0 && $facts->hosts_unhandled_nok !== $facts->hosts_nok) {
            $htm .= $indent . '<span class="critical handled">'
                  . ($facts->hosts_nok - $facts->hosts_unhandled_nok)
                  . "</span>\n";
        }

        if ($facts->hosts_unhandled_nok > 0) {
            $htm .= $indent . '<span class="critical">'
                  . $facts->hosts_unhandled_nok
                  . "</span>\n";
        }

        $htm .= $indent . '<span class="sum">' . $facts->hosts_cnt . "</span>\n";

        return $htm;
    }
}
