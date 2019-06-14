<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Hook;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Web\ActionLink;
use Icinga\Module\Cube\Web\ActionLinks;
use Icinga\Web\Url;
use Icinga\Web\View;

/**
 * ActionsHook
 *
 * Implement this hook in case your module wants to add links to the detail
 * page shown for a slice.
 *
 * @package Icinga\Module\Cube\Hook
 */
abstract class ActionsHook
{
    /** @var ActionLinks */
    private $actionLinks;

    /**
     * Your implementation should extend this method
     *
     * Then use the addActionLink() method, eventually combined with the
     * createUrl() helper like this:
     *
     * <code>
     * $this->addActionLink(
     *     $this->makeUrl('mymodule/controller/action', array('some' => 'param')),
     *     'A shown title',
     *     'A longer description text, should fit into the available square field',
     *     'icon-name'
     * );
     * </code>
     *
     * For a list of available icon names please enable the Icinga Web 2 'doc'
     * module and go to "Documentation" -> "Developer - Style" -> "Icons"
     *
     * @param Cube $cube
     * @param View $view
     *
     * @return void
     */
    abstract public function prepareActionLinks(Cube $cube, View $view);

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
