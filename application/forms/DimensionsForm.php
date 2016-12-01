<?php

namespace Icinga\Module\Cube\Forms;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Web\Form\QuickForm;
use Icinga\Module\Cube\Web\IconHelper;

class DimensionsForm extends QuickForm
{
    /**
     * @var Cube
     */
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
        $view = $this->getView();

        $this->addElement('submit', 'removeSlice_' . $key, array(
            'label'      => IconHelper::instance()->iconCharacter('cancel'),
            'decorators' => array('ViewHelper')
        ));

        $label = $view->escape(
            sprintf(
                '%s: %s = %s',
                $view->translate('Slice/Filter'),
                $key,
                $value
            )
        );

        $this->addHtml(
            '<span class="dimension-name">' . $label . '</span>',
            array('name' => 'slice_' . $key)
        );

        $this->addSimpleDisplayGroup(
            array(
                'removeSlice_' . $key,
                'slice_' . $key,
            ),
            $key,
            array('class' => 'dimensions')
        );
    }

    protected function addDimensionButtons($dimension, $pos, $total)
    {
        $this->addHtml(
            '<span class="dimension-name">' . $this->getView()->escape($dimension) . '</span>',
            array('name' => 'dimension_' . $dimension)
        );
        $icons = IconHelper::instance();
        $this->addElement('submit', 'removeDimension_' . $dimension, array(
            'label' => $icons->iconCharacter('cancel'),
            'decorators' => array('ViewHelper'),
            'title' => sprintf($this->translate('Remove dimension "%s"'), $dimension),
        ));

        $this->addElement('submit', 'moveDimensionUp_' . $dimension, array(
            'label' => $icons->iconCharacter('angle-double-left'),
            'decorators' => array('ViewHelper'),
            'title' => sprintf($this->translate('Move dimension "%s" up'), $dimension),
        ));

        $this->addElement('submit', 'moveDimensionDown_' . $dimension, array(
            'label' => $icons->iconCharacter('angle-double-right'),
            'decorators' => array('ViewHelper'),
            'title' => sprintf($this->translate('Move dimension "%s" down'), $dimension),
        ));

        if ($pos === 0) {
            $this->getElement('moveDimensionUp_' . $dimension)->disabled = 'disabled';
        }

        if ($pos + 1 === $total) {
            $this->getElement('moveDimensionDown_' . $dimension)->disabled = 'disabled';
        }

        $this->addSimpleDisplayGroup(
            array(
                'removeDimension_' . $dimension,
                'moveDimensionUp_' . $dimension,
                'moveDimensionDown_' . $dimension,
                'dimension_' . $dimension,
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
