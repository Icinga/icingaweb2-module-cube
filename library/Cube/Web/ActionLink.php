<?php

namespace Icinga\Module\Cube\Web;

use Icinga\Web\Url;
use Icinga\Web\View;

/**
 * ActionLink
 *
 * ActionLinksHook implementations return instances of this class
 *
 * @package Icinga\Module\Cube\Web
 */
class ActionLink
{
    /** @var Url */
    protected $url;

    /** @var string */
    protected $title;

    /** @var string */
    protected $description;

    /** @var string */
    protected $icon;

    /**
     * ActionLink constructor.
     * @param Url $url
     * @param string $title
     * @param string $description
     * @param string $icon
     */
    public function __construct(Url $url, $title, $description, $icon)
    {
        $this->url         = $url;
        $this->title       = $title;
        $this->description = $description;
        $this->icon        = $icon;
    }

    /**
     * @return Url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Render our icon
     *
     * @param View $view
     * @return string
     */
    protected function renderIcon(View $view)
    {
        return $view->icon($this->getIcon());
    }

    /**
     * @param View $view
     * @return string
     */
    public function render(View $view)
    {
        return sprintf(
            '<a href="%s">%s<span class="title">%s</span><p>%s</p></a>',
            $this->getUrl(),
            $this->renderIcon($view),
            $view->escape($this->getTitle()),
            $view->escape($this->getDescription())
        );
    }
}
