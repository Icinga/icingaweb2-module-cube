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
    /** @var Link[] */
    private $actionLinks = [];

    /**
     * Create additional action links for the given cube
     *
     * @param IcingaDbCube $cube
     * @return void
     */
    abstract public function createActionLinks(IcingaDbCube $cube);

    /**
     * Return the action links for the cube
     *
     * @return Link[]
     */
    final protected function getActionLinks(): array
    {
        return $this->actionLinks;
    }

    /**
     * Helper method to populate action links array
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

        $this->actionLinks[] = new Link($linkContent, $url);

        return $this;
    }

    /**
     * Helper method instantiating an Url object
     *
     * @param string $path
     * @param ?array $params
     * @return Url
     */
    final protected function makeUrl(string $path, ?array $params = null): Url
    {
        $url = Url::fromPath($path);
        if ($params !== null) {
            $url->getParams()->mergeValues($params);
        }

        return $url;
    }

    /**
     * Render all links for all Hook implementations
     *
     * This is what the Cube calls when rendering details
     *
     * @param IcingaDbCube $cube
     *
     * @return string
     */
    public static function renderAll(Cube $cube)
    {
        $html = new HtmlDocument();

        /** @var IcingaDbActionsHook $hook */
        foreach (Hook::all('Cube/IcingaDbActions') as $hook) {
            try {
                $hook->createActionLinks($cube);
            } catch (Exception $e) {
                $html->addHtml(HtmlElement::create('li', ['class' => 'error'], $e->getMessage()));
            }

            foreach ($hook->getActionLinks() as $link) {
                $html->addHtml(HtmlElement::create('li', null, $link));
            }
        }

        if ($html->isEmpty()) {
            $html->addHtml(
                HtmlElement::create(
                    'li',
                    ['class' => 'error'],
                    t('No action links have been provided for this cube')
                )
            );
        }

        return $html->render();
    }
}
