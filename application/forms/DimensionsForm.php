<?php

namespace Icinga\Module\Cube\Forms;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Web\Form;

class DimensionsForm extends Form
{
    private $cube;

    public function setCube(Cube $cube)
    {
        $this->cube = $cube;
        return $this;
    }

    public function setup()
    {
        $cube = $this->cube;

        $dimensions = array_diff(
            $cube->listAdditionalDimensions(),
            $cube->listDimensions()
        );

        if (! empty($dimensions)) {
            $dimensions = array_combine($dimensions, $dimensions);
        }

        $this->addElement('select', 'addDimension', array(
            'multiOptions' => array(
                null => $this->translate('+ Add a dimension')
            ) + $dimensions,
            'decorators'   => array('ViewHelper'),
            'class'        => 'autosubmit'
        ));

        $dimensions = $cube->listDimensions();
        $cnt = count($dimensions);
        foreach ($dimensions as $pos => $dimension) {
            $this->addDimensionButtons($dimension, $pos, $cnt);
        }

        foreach ($cube->getSlices() as $key => $value) {
            $this->addSlice($key, $value);
        }

        $this->setSubmitLabel(false);
    }

    protected function addSlice($key, $value)
    {
        $this->addHtml(
            '<span>' . $this->getView()->escape(sprintf('%s = %s', $key, $value)) . '</span>',
            array('name' => 'slice_' . $key)
        );

        $this->addElement('submit', 'removeSlice_' . $key, array(
            'label' => $this->translate('x'),
            'decorators' => array('ViewHelper')
        ));

        $this->addSimpleDisplayGroup(
            array(
                'slice_' . $key,
                'removeSlice_' . $key,
            ),
            $key,
            array('class' => 'dimensions')
        );
    }

    protected function addDimensionButtons($dimension, $pos, $total)
    {
        $this->addHtml(
            '<span>' . $this->getView()->escape($dimension) . '</span>',
            array('name' => 'dimension_' . $dimension)
        );

        $this->addElement('submit', 'removeDimension_' . $dimension, array(
            'label' => $this->translate('x'),
            'decorators' => array('ViewHelper')
        ));

        $this->addElement('submit', 'moveDimensionUp_' . $dimension, array(
            'label' => sprintf($this->translate('^'), $dimension),
            'decorators' => array('ViewHelper'),
        ));

        $this->addElement('submit', 'moveDimensionDown_' . $dimension, array(
            'label' => sprintf($this->translate('^'), $dimension),
            'decorators' => array('ViewHelper')
        ));

        if ($pos === 0) {
            $this->getElement('moveDimensionUp_' . $dimension)->disabled = 'disabled';
        }

        if ($pos + 1 === $total) {
            $this->getElement('moveDimensionDown_' . $dimension)->disabled = 'disabled';
        }

        $this->addSimpleDisplayGroup(
            array(
                'dimension_' . $dimension,
                'removeDimension_' . $dimension,
                'moveDimensionUp_' . $dimension,
                'moveDimensionDown_' . $dimension,
            ),
            $dimension,
            array('class' => 'dimensions')
        );
    }

    public function onRequest()
    {
        parent::onRequest();
        if (! $this->hasBeenSent()) {
            return;
        }

        $url = $this->getSuccessUrl();
        $post = $this->getRequest()->getPost();
        $this->populate($post);
        $cube = $this->cube;
        $dimension = null;

        foreach ($this->getElements() as $el) {
            if (! $el->getValue()) {
                // Skip unpressed buttons
                continue;
            }
            $name = $el->getName();
            $pos = strpos($name, '_');

            if ($pos === false || $pos === 0) {
                continue;
            }

            $action = substr($name, 0, $pos);
            $name = substr($name, $pos + 1);

            switch ($action) {
                case 'removeSlice':
                    $dimension = $name;
                    $url->getParams()->remove($dimension);
                    break 2;

                case 'removeDimension':
                    $dimension = $name;
                    $cube->removeDimension($dimension);
                    break 2;

                case 'moveDimensionUp':
                    $dimension = $name;
                    $cube->moveDimensionUp($dimension);
                    break 2;

                case 'moveDimensionDown':
                    $dimension = $name;
                    $cube->moveDimensionDown($dimension);
                    break 2;
                default:
            }
        }

        if ($dimension) {
            $dimensions = array_merge($cube->listDimensions(), $cube->listSlices());
            if ($action !== 'removeSlice') {
                $url->setParam('dimensions', implode(',', $dimensions));
            }
            $this->redirectAndExit($url);
        }

        if ($dimension = $this->getSentValue('addDimension')) {
            $dimensions = $url->getParam('dimensions');
            if (empty($dimensions)) {
                $dimensions = $dimension;
            } else {
                $dimensions .= ',' . $dimension;
            }
            $url->setParam('dimensions', $dimensions);

            $this->setSuccessUrl($url->without('addDimension'));
            $this->redirectOnSuccess($this->translate('New dimension has been added'));
        }
    }
}
