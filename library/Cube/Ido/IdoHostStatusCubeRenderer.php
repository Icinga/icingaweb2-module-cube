<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\CubeRenderer;

/**
 * Class IdoHostStatusCubeRenderer
 * @package Icinga\Module\Cube\Ido
 */
class IdoHostStatusCubeRenderer extends CubeRenderer
{

    public function __construct(Cube $cube)
    {
        parent::__construct($cube);
    }

    /**
     * @inheritdoc
     */
    protected function renderDimensionLabel($name, $row)
    {
        $htm = parent::renderDimensionLabel($name, $row);

        if (($next = $this->cube->getDimensionAfter($name)) && isset($this->summaries->$next)) {
            $htm .= ' <span class="sum">(' . $this->summaries->$next->hosts_cnt . ')</span>';
        }

        return $htm;
    }

    protected function getDimensionClasses($name, $row)
    {
        $classes = parent::getDimensionClasses($name, $row);

        $sums = $row;
        if ($sums->hosts_down > 0) {
            $classes[] = 'critical';
            if ((int)$sums->hosts_unhandled_down === 1) {
                $classes[] = 'handled';
            }
        }

        if ($sums->hosts_down < 1) {
            $classes[] = 'unreachable';
            if ((int)$sums->hosts_unhandled_unreachable === 2) {
                $classes[] = 'handled';
            }
        }

        if ($sums->hosts_down < 1 && $sums->hosts_unreachable < 1) {
            $classes[] = 'ok';
        }

        return $classes;
    }

    public function renderFacts($facts)
    {
        $indent = str_repeat('    ', 3);
        $parts = array();

        if ($facts->hosts_unhandled_down > 0) {
            $parts['critical'] = $facts->hosts_unhandled_down;
        }

        if ($facts->hosts_down > 0 && $facts->hosts_down > $facts->hosts_unhandled_down) {
            $parts['critical handled'] = $facts->hosts_down - $facts->hosts_unhandled_down;
        }

        if ($facts->hosts_unhandled_unreachable > 0) {
            $parts['unreachable'] = $facts->hosts_unhandled_unreachable;
        }

        if ($facts->hosts_unreachable > 0 && $facts->hosts_unreachable > $facts->hosts_unhandled_unreachable) {
            $parts['unreachable handled'] = $facts->hosts_unreachable - $facts->hosts_unhandled_unreachable;
        }

        if ($facts->hosts_cnt > $facts->hosts_down) {
            $parts['ok'] = $facts->hosts_cnt - $facts->hosts_down;
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

    protected function getDetailsBaseUrl()
    {
        return 'cube/hosts/details';
    }
}
