<?php

namespace Icinga\Module\Cube\Web;

use Icinga\Exception\ConfigError;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Web\Controller as WebController;

class Controller extends WebController
{
    private $db;

    public function loadForm($name)
    {
        $form = FormLoader::load($name, $this->Module());
        return $form;
    }
}
