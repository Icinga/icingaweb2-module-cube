<?php

namespace Icinga\Module\Cube\Web;

use Icinga\Module\Cube\Web\Form\FormLoader;
use Icinga\Module\Cube\Web\Form\QuickForm;
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
     * @return QuickForm
     */
    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }
}
