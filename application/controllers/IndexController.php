<?php

namespace Icinga\Module\Cube\Controllers;

use Icinga\Web\Controller;
use Icinga\Module\Cube\Ido\CustomVarDimension;
use Icinga\Module\Cube\Ido\IdoCube;
use Icinga\Module\Cube\CubeRenderer;


class IndexController extends Controller
{
    public function indexAction()
    {
        $this->setAutoRefreshInterval(15);

        $this->getTabs()->add('cube', array(
            'label' => $this->translate('Cube'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('cube');

        if ($this->params->shift('showSettings')) {
            $this->view->form = 'as';
        }

        $cube = new IdoCube();
        $cube->chooseFacts(array('hosts_cnt', 'hosts_nok', 'hosts_unhandled_nok'));
        $vars = preg_split('/,/', $this->params->shift('dimensions'), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($vars as $var) {
            $cube->addDimension(new CustomVarDimension($var));
        }

        foreach ($this->params->toArray() as $param) {
            $cube->slice($param[0], $param[1]);
        }

        $this->view->title = sprintf(
            $this->translate('Cube: %s'),
            implode(' -> ', $cube->listDimensions())
        );

        $this->view->cube = new CubeRenderer($cube);
    }
}
