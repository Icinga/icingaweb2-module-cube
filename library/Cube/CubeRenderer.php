<?php

namespace Icinga\Module\Cube;
/*
DimensionRenderer
FactRenderer

SummaryHelper
*/

class CubeRenderer
{
    protected $view;

    protected $cube;

    protected $dimensions;

    protected $reversedDimensions;

    protected $dimensionLevels;

    protected $facts;

    protected $lastRow;

    protected $summaries;

    protected $started;

    public function __construct($cube)
    {
        $this->cube = $cube;
    }

    protected function initialize()
    {
        $this->started = false;
        $this->initializeDimensions()
            ->initializeFacts()
            ->initializeLastRow()
            ->initializeSummaries();
    }

    protected function initializeLastRow()
    {
        $object = (object) array();
        foreach ($this->dimensions as $key) {
            $object->$key = null;
        }

        $this->lastRow = $object;

        return $this;
    }

    protected function initializeDimensions()
    {
        $this->dimensions = $this->cube->listDimensions();
        $this->reversedDimensions = array_reverse($this->dimensions);
        $this->dimensionLevels = array_flip($this->dimensions);
        return $this;
    }

    protected function initializeFacts()
    {
        $this->facts = $this->cube->listFacts();
        return $this;
    }

    protected function initializeSummaries()
    {
        $this->summaries = (object) array();
        return $this;
    }

    protected function startsDimension($row)
    {
        foreach ($this->dimensions as $name) {
            if ($row->$name === null) {
                $this->summaries->{$name} = $this->extractFacts($row);
                return true;
            }
        }

        return false;
    }

    protected function extractFacts($row)
    {
        $res = (object) array();

        foreach ($this->facts as $fact) {
            $res->$fact = $row->$fact;
        }

        return $res;
    }

    public function render($view)
    {
        $this->view = $view;
        $this->initialize();
        $htm = $this->beginContainer();

        foreach ($this->cube->fetchAll() as $row) {
            $htm .= $this->renderRow($row);
        }

        return $htm . $this->closeDimensions() . $this->endContainer();
    }

    protected function renderRow($row)
    {
        $htm = '';
        if ($dimension = $this->startsDimension($row)) {
            return $htm;
        }

        $htm .= $this->closeDimensionsForRow($row);
        $htm .= $this->beginDimensionsForRow($row);
        $htm .= $this->renderFacts($row);
        $this->lastRow = $row;
        return $htm;
    }

    protected function beginDimensionsForRow($row)
    {
        $last = $this->lastRow;
        foreach ($this->dimensions as $name) {
            if ($last->$name !== $row->$name) {
                return $this->beginDimensionsUpFrom($name, $row);
            }
        }

        return '';
    }

    protected function beginDimensionsUpFrom($dimension, $row)
    {
        $htm = '';
        $found = false;

        foreach ($this->dimensions as $name) {
            if ($name === $dimension) {
                $found = true;
            }

            if ($found) {
                $htm .= $this->beginDimension($name, $row);
            }
        }

        return $htm;
    }

    protected function closeDimensionsForRow($row)
    {
        $last = $this->lastRow;
        foreach ($this->dimensions as $name) {
            if ($last->$name !== $row->$name) {
                return $this->closeDimensionsDownTo($name);
            }
        }

        return '';
    }

    protected function closeDimensionsDownTo($name)
    {
        $htm = '';

        foreach ($this->reversedDimensions as $dimension) {
            $htm .= $this->closeDimension($dimension);

            if ($name === $dimension) {
                break;
            }
        }

        return $htm;
    }

    protected function closeDimensions()
    {
        $htm = '';
        foreach ($this->reversedDimensions as $name) {
            $htm .= $this->closeDimension($name);
        }

        return $htm;
    }

    protected function closeDimension($name)
    {
        if (! $this->started) {
            return '';
        }

        $indent = $this->getIndent($name);
        return $indent . '  </div>' . "\n" . $indent . "</div><!-- $name -->\n";
    }

    protected function getIndent($name)
    {
        return str_repeat('    ', $this->getLevel($name));
    }

    protected function beginDimension($name, $row)
    {
        $indent = $this->getIndent($name);
        if (! $this->started) {
            $this->started = true;
        }

        if ($this->isOuterDimension($name)) {
            $sum = ' <span class="sum">(' . $this->summaries->$name->hosts_cnt . ')</span>';
        } else {
            $sum = '';
        }

        return
            $indent . '<div class="' . $this->getDimensionClasses($name, $row) . '">' . "\n"
            . $indent . '  <div class="header">'
            . $this->view->escape($row->$name)
            . $sum
            . '</div>' . "\n"
            . $indent . '  <div class="body">' . "\n";
    }

    protected function isOuterDimension($name)
    {
        return $this->reversedDimensions[0] !== $name;
    }

    protected function getDimensionClasses($name, $row, $stringify = true)
    {
        $classes = array(
            'cube-dimension' . $this->getLevel($name)
        );

        $sums = $this->summaries->$name;
        $sums = $row;
        if ($sums->hosts_nok > 0) {
            $classes[] = 'critical';
            if ((int) $sums->hosts_unhandled_nok === 0) {
                $classes[] = 'handled';
            }
        }

        if ($stringify) {
            return implode(' ', $classes);
        } else {
            return $classes;
        }
    }

    protected function getLevel($name)
    {
        return $this->dimensionLevels[$name];
    }

    protected function renderFacts($object)
    {
        $indent = str_repeat('    ', 3);
        $htm = '';
        if ($object->hosts_nok > 0 && $object->hosts_unhandled_nok !== $object->hosts_nok) {
            $htm .= $indent . '<span class="critical handled">'
                  . ($object->hosts_nok - $object->hosts_unhandled_nok)
                  . "</span>\n";
        }

        if ($object->hosts_unhandled_nok > 0) {
            $htm .= $indent . '<span class="critical">'
                  . $object->hosts_unhandled_nok
                  . "</span>\n";
        }

        $htm .= $indent . '<span class="sum">' . $object->hosts_cnt . "</span>\n";

        return $htm;
    }

    protected function beginContainer()
    {
        return '<div class="cube">' . "\n";
    }

    protected function endContainer()
    {
        return '</div>' . "\n";
    }
}
