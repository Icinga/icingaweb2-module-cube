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
    protected $factsPrefix;

    public function __construct(Cube $cube)
    {
        parent::__construct($cube);

        if ($cube instanceof IdoHostStatusCube) {
            $this->factsPrefix = 'hosts';
        } else {
            $this->factsPrefix = 'services';
        }
    }

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
        if ($sums->{$this->factsPrefix . '_nok'} > 0) {
            $classes[] = 'critical';
            if ((int)$sums->{$this->factsPrefix . '_unhandled_nok'} === 1) {
                $classes[] = 'handled';
            }
        }

        if ($sums->{$this->factsPrefix . '_nok'} < 1) {
            $classes[] = 'unreachable';
            if ((int)$sums->{$this->factsPrefix . '_unhandled_unreachable'} === 2) {
                $classes[] = 'handled';
            }
        }

        if ($sums->{$this->factsPrefix . '_nok'} < 1 && $sums->{$this->factsPrefix . '_unreachable'} < 1) {
            $classes[] = 'ok';
        }

        return $classes;
    }

    public function renderFacts($facts)
    {
        $indent = str_repeat('    ', 3);
        $parts = array();

        if ($facts->{$this->factsPrefix . '_unhandled_nok'} > 0) {
            $parts['critical'] = $facts->{$this->factsPrefix . '_unhandled_nok'};
        }

        if ($facts->{$this->factsPrefix . '_nok'} > 0 && $facts->{$this->factsPrefix . '_nok'} > $facts->{$this->factsPrefix . '_unhandled_nok'}) {
            $parts['critical handled'] = $facts->{$this->factsPrefix . '_nok'} - $facts->{$this->factsPrefix . '_unhandled_nok'};
        }

        if ($facts->{$this->factsPrefix . '_unhandled_unreachable'} > 0) {
            $parts['unreachable'] = $facts->{$this->factsPrefix . '_unhandled_unreachable'};
        }

        if ($facts->{$this->factsPrefix . '_unreachable'} > 0 && $facts->{$this->factsPrefix . '_unreachable'} > $facts->{$this->factsPrefix . '_unhandled_unreachable'}) {
            $parts['unreachable handled'] = $facts->{$this->factsPrefix . '_unreachable'} - $facts->{$this->factsPrefix . '_unhandled_unreachable'};
        }

        if ($facts->{$this->factsPrefix . '_cnt'} > $facts->{$this->factsPrefix . '_nok'}) {
            $parts['ok'] = $facts->{$this->factsPrefix . '_cnt'} - $facts->{$this->factsPrefix . '_nok'};
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
        return 'cube/' . $this->factsPrefix . '/details';
    }
}
