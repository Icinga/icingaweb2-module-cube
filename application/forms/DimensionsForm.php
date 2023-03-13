<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Forms;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;
use Icinga\Module\Cube\DimensionParams;
use Icinga\Web\Notification;
use ipl\Html\Form;
use ipl\Html\Html;
use ipl\I18n\Translation;
use ipl\Web\Common\FormUid;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;

class DimensionsForm extends Form
{
    use FormUid;
    use Translation;

    protected $defaultAttributes = [
        'class' => 'icinga-controls',
        'name'  => 'dimensions-form'
    ];

    /**
     * @var Cube
     */
    private $cube;

    /**
     * @var Url
     */
    private $url;

    /**
     * Get the url
     *
     * @return Url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the url
     *
     * @param mixed $url
     */
    public function setUrl($url): self
    {
        $this->url = $url;

        return $this;
    }

    public function setCube(Cube $cube)
    {
        $this->cube = $cube;
        return $this;
    }

    public function hasBeenSubmitted(): bool
    {
        // required to submit dimension controls and the selected dropdown option
        return $this->hasBeenSent() &&
            ($this->getPressedSubmitElement() !== null || $this->getPopulatedValue('addDimension'));
    }

    public function assemble()
    {
        $dimensions = $this->cube->listDimensions();
        $cnt = count($dimensions);

        if ($cnt < 3) {
            $allDimensions = $this->cube->listAdditionalDimensions();

            $this->addElement('select', 'addDimension', [
                'options' => [null => $this->translate('+ Add a dimension')] + $allDimensions,
                'class'   => 'autosubmit'
            ]);
        }

        $pos = 0;
        foreach ($dimensions as $dimension) {
            $this->addDimensionButtons($dimension, $pos++, $cnt);
        }

        foreach ($this->cube->getSlices() as $key => $value) {
            $this->addSlice($this->cube->getDimension($key), $value);
        }

        $this->addElement($this->createUidElement());
    }

    protected function addSlice(Dimension $dimension, $value)
    {
        $sliceId = sha1($this->cube::SLICE_PREFIX . $dimension->getName());

        $sliceFieldset = Html::tag('fieldset', ['class' => 'dimensions']);

        $btn = $this->createElement('submitButton', 'removeSlice_' . $sliceId, [
            'label' => new Icon('trash'),
            'class' => 'dimension-control'
        ]);

        $this->registerElement($btn);
        $sliceFieldset->addHtml($btn);

        $sliceFieldset->addHtml(Html::tag(
            'span',
            ['class' => 'dimension-name'],
            sprintf('%s: %s = %s', $this->translate('Slice/Filter'), $dimension->getLabel(), $value)
        ));

        $this->addHtml($sliceFieldset);
    }

    protected function addDimensionButtons(Dimension $dimension, $pos, $total)
    {
        $dimensionId = sha1($dimension->getName());

        $dimensionFieldset = Html::tag('fieldset', ['class' => 'dimensions']);

        $btn = $this->createElement('submitButton', 'removeDimension_' . $dimensionId, [
            'label' => new Icon('trash'),
            'title' => sprintf($this->translate('Remove dimension "%s"'), $dimension->getLabel()),
            'class' => 'dimension-control'
        ]);

        $this->registerElement($btn);
        $dimensionFieldset->addHtml($btn);

        if ($pos > 0) {
            $btn = $this->createElement('submitButton', 'moveDimensionUp_' . $dimensionId, [
                'label' => new Icon('angle-double-up'),
                'title' => sprintf($this->translate('Move dimension "%s" up'), $dimension->getLabel()),
                'class' => 'dimension-control',
            ]);

            $this->registerElement($btn);
            $dimensionFieldset->addHtml($btn);
        }

        if ($pos + 1 !== $total) {
            $btn = $this->createElement('submitButton', 'moveDimensionDown_' . $dimensionId, [
                'label' => new Icon('angle-double-down'),
                'title' => sprintf($this->translate('Move dimension "%s" down'), $dimension->getLabel()),
                'class' => 'dimension-control'
            ]);

            $this->registerElement($btn);
            $dimensionFieldset->addHtml($btn);
        }

        $dimensionFieldset->addHtml(Html::tag('span', ['class' => 'dimension-name'], $dimension->getLabel()));

        $this->addHtml($dimensionFieldset);
    }

    public function onSuccess()
    {
        $url = $this->getUrl();

        if ($dimension = $this->getValue('addDimension')) {
            $url->setParam('dimensions', DimensionParams::fromUrl($url)->add($dimension)->getParams());
            Notification::success($this->translate('New dimension has been added'));
        } else {
            $updateDimensions = false;
            $pressedButtonName = $this->getPressedSubmitElement()->getName();

            foreach ($this->cube->listDimensions() as $name => $_) {
                $dimensionId = sha1($name);

                switch (true) {
                    case ($pressedButtonName === 'removeDimension_' . $dimensionId):
                        $this->cube->removeDimension($name);
                        $updateDimensions = true;
                        break 2;
                    case ($pressedButtonName === 'moveDimensionUp_' . $dimensionId):
                        $this->cube->moveDimensionUp($name);
                        $updateDimensions = true;
                        break 2;
                    case ($pressedButtonName === 'moveDimensionDown_' . $dimensionId):
                        $this->cube->moveDimensionDown($name);
                        $updateDimensions = true;
                        break 2;
                }
            }

            if ($updateDimensions) {
                $dimensions = array_merge(array_keys($this->cube->listDimensions()), $this->cube->listSlices());
                $url->setParam('dimensions', DimensionParams::update($dimensions)->getParams());
            } else {
                foreach ($this->cube->listSlices() as $slice) {
                    $slice = $this->cube::SLICE_PREFIX . $slice;
                    $sliceId = sha1($slice);

                    if ($pressedButtonName === 'removeSlice_' . $sliceId) {
                        $url->getParams()->remove(rawurlencode($slice));
                    }
                }
            }
        }

        $this->setRedirectUrl($url);
    }
}
