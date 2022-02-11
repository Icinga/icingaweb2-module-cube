<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Forms;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\DimensionParams;
use Icinga\Web\Form;
use Icinga\Web\Notification;

class DimensionsForm extends Form
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

    public function createElements(array $formData)
    {
        $cube = $this->cube;

        if (count($cube->listDimensions()) < 3) {
            $dimensions = array_diff(
                $cube->listAdditionalDimensions(),
                $cube->listDimensions()
            );

            if (! empty($dimensions)) {
                $dimensions = array_combine($dimensions, $dimensions);
            }

            $this->addElement('select', 'addDimension', [
                'multiOptions'  => [null => $this->translate('+ Add a dimension')] + $dimensions,
                'decorators'    => ['ViewHelper'],
                'class'         => 'autosubmit'
            ]);
        }

        $dimensions = $cube->listDimensions();
        $cnt = count($dimensions);
        foreach ($dimensions as $pos => $dimension) {
            $this->addDimensionButtons($dimension, $pos, $cnt);
        }

        foreach ($cube->getSlices() as $key => $value) {
            $this->addSlice($key, $value);
        }

        $this->addAttribs(['class' => 'icinga-controls']);
    }

    protected function addSlice($key, $value)
    {
        $view = $this->getView();

        $sliceId = sha1($key);
        $this->addElement('button', 'removeSlice_' . $sliceId, [
            'label' => $view->icon('cancel'),
            'decorators' => ['ViewHelper'],
            'value' => $key,
            'type' => 'submit',
            'escape' => false,
            'class' => 'dimension-control'
        ]);

        $label = $view->escape(
            sprintf(
                '%s: %s = %s',
                $view->translate('Slice/Filter'),
                $key,
                $value
            )
        );

        $this->addElement('note', 'slice_' . $sliceId, [
            'class' => 'dimension-name',
            'value' => '<span class="dimension-name">' . $label . '</span>',
            'decorators' => ['ViewHelper']
        ]);

        $this->addDisplayGroup(
            [
                'removeSlice_' . $sliceId,
                'slice_' . $sliceId,
            ],
            $key,
            [
                'class' => 'dimensions',
                'decorators'  => [
                    'FormElements',
                    'Fieldset'
                ]
            ]
        );
    }

    protected function addDimensionButtons($dimension, $pos, $total)
    {
        $view = $this->getView();
        $dimensionId = sha1($dimension);

        $this->addElement('note', 'dimension_' . $dimensionId, [
            'class' => 'dimension-name',
            'value' => '<span class="dimension-name">' . $view->escape($dimension) . '</span>',
            'decorators' => ['ViewHelper']
        ]);

        $this->addElement('button', 'removeDimension_' . $dimensionId, [
            'label' => $view->icon('cancel'),
            'decorators' => ['ViewHelper'],
            'title' => sprintf($this->translate('Remove dimension "%s"'), $dimension),
            'value' => $dimension,
            'type' => 'submit',
            'escape' => false,
            'class' => 'dimension-control'
        ]);

        if ($pos > 0) {
            $this->addElement('button', 'moveDimensionUp_' . $dimensionId, [
                'label' => $view->icon('angle-double-up'),
                'decorators' => ['ViewHelper'],
                'title' => sprintf($this->translate('Move dimension "%s" up'), $dimension),
                'value' => $dimension,
                'type' => 'submit',
                'escape' => false,
                'class' => 'dimension-control'
            ]);
        }

        if ($pos + 1 !== $total) {
            $this->addElement('button', 'moveDimensionDown_' . $dimensionId, [
                'label' => $view->icon('angle-double-down'),
                'decorators' => ['ViewHelper'],
                'title' => sprintf($this->translate('Move dimension "%s" down'), $dimension),
                'value' => $dimension,
                'type' => 'submit',
                'escape' => false,
                'class' => 'dimension-control'
            ]);
        }

        $this->addDisplayGroup(
            [
                'removeDimension_' . $dimensionId,
                'moveDimensionUp_' . $dimensionId,
                'moveDimensionDown_' . $dimensionId,
                'dimension_' . $dimensionId,
            ],
            $dimensionId,
            [
                'class' => 'dimensions',
                'decorators'  => [
                    'FormElements',
                    'Fieldset'
                ]
            ]
        );
    }

    public function onSuccess()
    {
        $url = $this->getRequest()->getUrl();

        if ($dimension = $this->getValue('addDimension')) {
            $url->setParam('dimensions', DimensionParams::fromUrl($url)->add($dimension)->getParams());
            Notification::success($this->translate('New dimension has been added'));
        } else {
            $updateDimensions = false;
            foreach ($this->cube->listDimensions() as $dimension) {
                $dimensionId = sha1($dimension);

                switch (true) {
                    case ($el = $this->getElement('removeDimension_' . $dimensionId)) && $el->isChecked():
                        $this->cube->removeDimension($dimension);
                        $updateDimensions = true;
                        break 2;
                    case ($el = $this->getElement('moveDimensionUp_' . $dimensionId)) && $el->isChecked():
                        $this->cube->moveDimensionUp($dimension);
                        $updateDimensions = true;
                        break 2;
                    case ($el = $this->getElement('moveDimensionDown_' . $dimensionId)) && $el->isChecked():
                        $this->cube->moveDimensionDown($dimension);
                        $updateDimensions = true;
                        break 2;
                }
            }

            if ($updateDimensions) {
                $dimensions = array_merge($this->cube->listDimensions(), $this->cube->listSlices());
                $url->setParam('dimensions', DimensionParams::update($dimensions)->getParams());
            } else {
                foreach ($this->cube->listSlices() as $slice) {
                    $sliceId = sha1($slice);

                    if (($el = $this->getElement('removeSlice_' . $sliceId)) && $el->isChecked()) {
                        $url->getParams()->remove(rawurlencode($slice));
                    }
                }
            }
        }

        $this->setRedirectUrl($url);
    }
}
