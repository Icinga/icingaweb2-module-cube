<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Hook;

use Icinga\Module\Cube\BaseCube;
use Icinga\Module\Cube\Web\ActionLink;
use Icinga\Module\Cube\Web\ActionLinks;
use Icinga\Web\Url;
use Icinga\Web\View;

abstract class IcingadbHook
{
    /** @var ActionLinks */
    private $actionLinks;

    /**
     * @param BaseCube $cube
     * @param View $view
     * @return mixed
     */
    abstract public function prepareActionLinks(BaseCube $cube, View $view);

    /**
     * Lazy access to an ActionLinks object
     *
     * @return ActionLinks
     */
    public function getActionLinks()
    {
        if ($this->actionLinks === null) {
            $this->actionLinks = new ActionLinks();
        }
        return $this->actionLinks;
    }

    /**
     * Helper method instantiating an ActionLink object
     *
     * @param Url $url
     * @param string $title
     * @param string $description
     * @param string $icon
     *
     * @return $this
     */
    public function addActionLink(Url $url, $title, $description, $icon)
    {
        $this->getActionLinks()->add(
            new ActionLink($url, $title, $description, $icon)
        );

        return $this;
    }

    /**
     * Helper method instantiating an Url object
     *
     * @param string $path
     * @param array $params
     * @return Url
     */
    public function makeUrl($path, $params = null)
    {
        $url = Url::fromPath($path);
        if ($params !== null) {
            $url->getParams()->mergeValues($params);
        }

        return $url;
    }
}
