<?php

namespace Icinga\Module\Cube\Forms;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Web\Form;

class AddDimensionForm extends Form
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

        foreach ($cube->listDimensions() as $dimension) {
            $this->addDimensionButtons($dimension);
        }

        $this->addElement('select', 'addDimension', array(
            'label'        => $this->translate('Add a dimension'),
            'multiOptions' => $this->optionalEnum($dimensions),
            'class'        => 'autosubmit'
        ));
    }

    protected function addDimensionButtons($dimension)
    {
        $this->addElement('submit', 'removeDimension_' . $dimension, array(
            'label' => sprintf($this->translate('Remove %s'), $dimension)
        ));
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

        foreach ($this->getElements() as $el) {
            $name = $el->getName();
            if (substr($name, 0, 16) === 'removeDimension_' && $el->getValue()) {
                $dimension = substr($name, 16);
                $cube->removeDimension($dimension);
                $url->setParam('dimensions', implode(',', $cube->listDimensions()));
                $this->setSuccessUrl($url);
                $this->redirectOnSuccess($this->translate('Dimension has been removed'));
            }
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
