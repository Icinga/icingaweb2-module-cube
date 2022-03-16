<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Exception;
use Icinga\Application\Hook;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Hook\IcingaDbActionsHook;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Web\Widget\Link;

/**
 * ActionLink
 *
 * ActionsHook implementations return instances of this class
 *
 * @package Icinga\Module\Cube\Web
 */
class IcingaDbActionLinks
{
    /** @var Link[] */
    protected $links = [];

    /**
     * Get all links for all Hook implementations
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
                $html->addHtml(static::addErrorItem($e));
            }

            foreach ($hook->getActionLinks()->getLinks() as $link) {
                $html->addHtml(HtmlElement::create('li', null, $link));
            }
        }

        if ($html->count() === 0) {
            $html->addHtml(static::addErrorItem(t('No action links have been provided for this cube')));
        }

        return $html->render();
    }

    /**
     * @param Exception|string $error
     * @return HtmlElement
     */
    private static function addErrorItem($error)
    {
        if ($error instanceof Exception) {
            $error = $error->getMessage();
        }

        return HtmlElement::create('li', ['class' => 'error'], $error);
    }

    /**
     * Add an ActionLink to this set of actions
     *
     * @param Link $link
     * @return $this
     */
    public function add(Link $link)
    {
        $this->links[] = $link;
        return $this;
    }

    /**
     * @return Link[]
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     *
     * @return string
     */
    public function render()
    {
        $links = $this->getLinks();
        if (empty($links)) {
            return '';
        }

        $html = HtmlElement::create('ul', ['class' => 'action-links']);
        foreach ($links as $link) {
            $html->addHtml($link);
        }

        return (new HtmlDocument())->addHtml($html)->render();
    }
}
