<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Hook;

use Exception;
use Icinga\Application\Hook;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use ipl\Web\Url;
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
     * @return void
     */
    abstract public function createActionLinks(IcingaDbCube $cube);

    /**
     * Lazy access to an ActionLinks object
     *
     * @return IcingaDbActionLinks
     */
    final protected function getActionLinks(): array
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
    final protected function addActionLink(Url $url, string $title, string $description, string $icon): self
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
    final protected function makeUrl(string $path, array $params = null): Url
    {
        $url = Url::fromPath($path);
        if ($params !== null) {
            $url->getParams()->mergeValues($params);
        }

        return $url;
    }
}
