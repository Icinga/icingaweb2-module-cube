<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Hook;

use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\Web\IcingaDbActionLinks;
use Icinga\Web\Url;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

/**
 * ActionsHook
 *
 * Implement this hook in case your module wants to add links to the detail
 * page shown for a slice.
 *
 * @package Icinga\Module\Cube\Hook
 */
abstract class IcingaDbActionsHook
{
    /** @var IcingaDbActionLinks */
    private $actionLinks;

    /**
     * Create additional action links for the given cube
     *
     * @param IcingaDbCube $cube
     *
     * @return Link[]
     */
    abstract public function createActionLinks(IcingaDbCube $cube);

    /**
     * Lazy access to an ActionLinks object
     *
     * @return IcingaDbActionLinks
     */
    public function getActionLinks()
    {
        if ($this->actionLinks === null) {
            $this->actionLinks = new IcingaDbActionLinks();
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
        $linkContent = (new HtmlDocument());
        $linkContent->addHtml(new Icon($icon));
        $linkContent->addHtml(HtmlElement::create('span', ['class' => 'title'], $title));
        $linkContent->addHtml(HtmlElement::create('p', null, $description));

        $this->getActionLinks()->add(
            new Link($linkContent, $url->getAbsoluteUrl())
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
