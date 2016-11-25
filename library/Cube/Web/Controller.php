<?php

namespace Icinga\Module\Cube\Web;

use Icinga\Application\Icinga;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Web\Controller as WebController;
use Icinga\Web\View;

class Controller extends WebController
{
    /** @var View This helps IDEs to understand that this is not ZF view */
    public $view;

    /**
     * Load a form with a specific name
     *
     * @param $name
     * @return mixed
     */
    public function loadForm($name)
    {
        $form = FormLoader::load($name, $this->Module());
        $director = Icinga::app()->getModuleManager()->getModule('director');
        $basedir = sprintf(
            '%s/Director/Web/Form',
            $director->getLibDir()
        );

        $form->addPrefixPaths(array(
            array(
                'prefix'    => 'Icinga\\Module\\Director\\Web\\Form\\Element\\',
                'path'      => $basedir . '/Element',
                'type'      => $form::ELEMENT
            )
        ));

        return $form;
    }
}
