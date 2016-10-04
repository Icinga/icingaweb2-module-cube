<?php

namespace Icinga\Module\Cube\Web;

use Icinga\Application\Icinga;
use Icinga\Exception\ConfigError;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Web\Controller as WebController;

class Controller extends WebController
{
    private $db;

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
