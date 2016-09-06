<?php

namespace Icinga\Module\Cube\Controllers;

use Icinga\Web\Controller;
use Icinga\Module\Cube\Ido\IdoCube;
use Icinga\Module\Cube\CubeRenderer;
use Icinga\Module\Cube\CustomVarDimension;


class IndexController extends Controller
{
    public function indexAction()
    {
        $this->view->title = $this->translate('Cube');
        $this->getTabs()->add('cube', array(
            'label' => $this->view->title,
            'url'   => $this->getRequest()->getUrl()
        ))->activate('cube');

        $cube = new IdoCube();
        $cube->setDbName('ido2_snapshot');
        $cube->chooseFacts(array('hosts_cnt', 'hosts_nok', 'hosts_unhandled_nok'));
        $vars = preg_split('/,/', $this->params->get('dimensions'), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($vars as $var) {
            $cube->addDimension(new CustomVarDimension($var));
        }
        $this->view->cube = new CubeRenderer($cube);
    }
}
