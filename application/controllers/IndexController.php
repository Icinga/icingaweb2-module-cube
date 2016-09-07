<?php

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\CubeRenderer;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->getTabs()->add('cube', array(
            'label' => $this->translate('Cube'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('cube');

        // Hint: order matters, we are shifting!
        $cube = new IdoHostStatusCube();
        $showSettings = $this->params->shift('showSettings');

       $cube->chooseFacts(array('hosts_cnt', 'hosts_nok', 'hosts_unhandled_nok'));
        $vars = preg_split('/,/', $this->params->shift('dimensions'), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($vars as $var) {
            $cube->addDimensionByName($var);
        }

        foreach ($this->params->toArray() as $param) {
            $cube->slice($param[0], $param[1]);
        }

        $this->view->title = sprintf(
            $this->translate('Cube: %s'),
            implode(' -> ', $cube->listDimensions())
        );

        if (count($cube->listDimensions()) > 0) {
            $this->view->cube = new CubeRenderer($cube);
        } else {
            $showSettings = true;
        }

        if ($showSettings) {
            $this->view->form = $this->loadForm('Dimensions')
                 ->setCube($cube)
                ->handleRequest();
        } else {
            $this->setAutoRefreshInterval(15);
        }
    }
}
