<?php
// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\CubeRenderer;

class IdoStatusCubeRenderer extends CubeRenderer
{
    /** @var string Prefix of the facts */
    protected $factsPrefix;

    public function __construct(Cube $cube)
    {
        parent::__construct($cube);

        if ($cube instanceof IdoServiceStatusCube) {
            $this->factsPrefix = 'services';
        } else {
            $this->factsPrefix = 'hosts';
        }
    }

    public function renderFacts($facts)
    {
        $indent = str_repeat('    ', 3);
        $parts = [];

        if ($facts->{$this->factsPrefix . '_unhandled_critical'} > 0) {
            $parts['critical'] = $facts->{$this->factsPrefix . '_unhandled_critical'};
        }

        if ($facts->{$this->factsPrefix . '_critical'} > 0 && $facts->{$this->factsPrefix . '_critical'} > $facts->{$this->factsPrefix . '_unhandled_critical'}) {
            $parts['critical handled'] = $facts->{$this->factsPrefix . '_critical'} - $facts->{$this->factsPrefix . '_unhandled_critical'};
        }

        if ($facts->{$this->factsPrefix . '_unhandled_warning'} > 0) {
            $parts['warning'] = $facts->{$this->factsPrefix . '_unhandled_warning'};
        }

        if ($facts->{$this->factsPrefix . '_warning'} > 0 && $facts->{$this->factsPrefix . '_warning'} > $facts->{$this->factsPrefix . '_unhandled_warning'}) {
            $parts['warning handled'] = $facts->{$this->factsPrefix . '_warning'} - $facts->{$this->factsPrefix . '_unhandled_warning'};
        }

        if ($facts->{$this->factsPrefix . '_unhandled_unknown'} > 0) {
            $parts['unknown'] = $facts->{$this->factsPrefix . '_unhandled_unknown'};
        }

        if ($facts->{$this->factsPrefix . '_unknown'} > 0 && $facts->{$this->factsPrefix . '_unknown'} > $facts->{$this->factsPrefix . '_unhandled_unknown'}) {
            $parts['unknown handled'] = $facts->{$this->factsPrefix . '_unknown'} - $facts->{$this->factsPrefix . '_unhandled_unknown'};
        }

        if ($facts->{$this->factsPrefix . '_cnt'} > $facts->{$this->factsPrefix . '_critical'}) {
            $parts['ok'] = $facts->{$this->factsPrefix . '_cnt'} - $facts->{$this->factsPrefix . '_critical'};
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

    /**
     * @inheritdoc
     */
    protected function renderDimensionLabel($name, $row)
    {
        $htm = parent::renderDimensionLabel($name, $row);

        if ($next = $this->cube->getDimensionAfter($name)) {
            $htm .= ' <span class="sum">(' . $this->summaries->$next->{$this->factsPrefix . '_cnt'} . ')</span>';
        }

        return $htm;
    }

    protected function getDimensionClasses($name, $row)
    {
        $classes = parent::getDimensionClasses($name, $row);

        $sums = $row;
        if ($sums->{$this->factsPrefix . '_critical'} > 0) {
            $classes[] = 'critical';
            if ((int) $sums->{$this->factsPrefix . '_unhandled_critical'} === 2) {
                $classes[] = 'handled';
            }
        }

        if ($sums->{$this->factsPrefix . '_critical'} < 1) {
            $classes[] = 'warning';
            if ((int) $sums->{$this->factsPrefix . '_unhandled_warning'} === 1) {
                $classes[] = 'handled';
            }
        }

        if ($sums->{$this->factsPrefix . '_critical'} < 1 && $sums->{$this->factsPrefix . '_warning'} < 1) {
            $classes[] = 'unknown';
            if ((int) $sums->{$this->factsPrefix . '_unhandled_unknown'} === 3) {
                $classes[] = 'handled';
            }
        }

        if ($sums->{$this->factsPrefix . '_critical'} < 1 && $sums->{$this->factsPrefix . '_warning'} < 1) {
            $classes[] = 'ok';
        }

        return $classes;
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
