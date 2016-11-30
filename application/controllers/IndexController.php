<?php

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Cube\Web\ActionLinks;
use Icinga\Module\Cube\Web\Controller;
use Icinga\Web\UrlParams;
use Icinga\Web\Widget\Tabextension\DashboardAction;

class IndexController extends Controller
{
    /** @var UrlParams */
    protected $params;

    public function indexAction()
    {
        $this->getTabs()->add('cube', array(
            'label' => $this->translate('Cube'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('cube')->extend(new DashboardAction());

        // Hint: order matters, we are shifting!
        $showSettings = $this->params->shift('showSettings');
        $cube = $this->cubeFromParams($this->params);

        $this->view->title = sprintf(
            $this->translate('Cube: %s'),
            $cube->getPathLabel()
        );

        if (count($cube->listDimensions()) > 0) {
            $this->view->cube = $cube;
        } else {
            $showSettings = true;
        }

        if ($showSettings) {
            $this->view->form = $this->loadForm('Dimensions')
                 ->setCube($cube)
                ->handleRequest();
        } else {
            $this->setAutorefreshInterval(15);
        }
    }

    public function detailsAction()
    {
        $this->getTabs()->add('details', array(
            'label' => $this->translate('Cube details'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('details');
        $cube = $this->cubeFromParams($this->params);

        $this->view->title = $cube->getSlicesLabel();
        $this->view->links = ActionLinks::renderAll($cube, $this->view);
    }

    /**
     * @param  UrlParams $params
     * @return IdoHostStatusCube
     */
    protected function cubeFromParams($params)
    {
        $cube = new IdoHostStatusCube();

        $cube->chooseFacts(array('hosts_cnt', 'hosts_nok', 'hosts_unhandled_nok'));
        $dimensions = $params->shift('dimensions');
        $wantNull = $params->shift('wantNull');
        $vars = preg_split('/,/', $dimensions, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($vars as $var) {
            $cube->addDimensionByName($var);
            if ($wantNull) {
                $cube->getDimension($var)->wantNull();
            }
        }

        foreach (array('renderLayout', 'showFullscreen', 'showCompact', 'view') as $p) {
            $params->shift($p);
        }

        foreach ($this->params->toArray() as $param) {
            $cube->slice($param[0], $param[1]);
        }

        return $cube;
    }
}
