<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\CubeRenderer;

/**
 * Class IdoHostStatusCubeRenderer
 * @package Icinga\Module\Cube\Ido
 */
class IdoHostStatusCubeRenderer extends CubeRenderer
{
    /**
     * @inheritdoc
     */
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
        $parts = array();

        if ($facts->hosts_unhandled_nok > 0) {
            $parts['critical'] = $facts->hosts_unhandled_nok;
        }

        if ($facts->hosts_nok > 0 && $facts->hosts_nok > $facts->hosts_unhandled_nok) {
            $parts['critical handled'] = $facts->hosts_nok - $facts->hosts_unhandled_nok;
        }

        if ($facts->hosts_cnt > $facts->hosts_nok) {
            $parts['ok'] = $facts->hosts_cnt - $facts->hosts_nok;
        }

        $main = '';
        $sub = '';
        foreach ($parts as $class => $count) {
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
}
