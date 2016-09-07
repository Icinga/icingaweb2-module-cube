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
        $dimensions = array_diff(
            $this->cube->listAdditionalDimensions(),
            $this->cube->listDimensions()
        );

        if (! empty($dimensions)) {
            $dimensions = array_combine($dimensions, $dimensions);
        }

        $this->addElement('select', 'addDimension', array(
            'label'        => $this->translate('Add a dimension'),
            'multiOptions' => $this->optionalEnum($dimensions),
            'class'        => 'autosubmit'
        ));
    }

    public function onRequest()
    {
        parent::onRequest();

        if ($dimension = $this->getSentValue('addDimension')) {
            $url = $this->getSuccessUrl();
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
