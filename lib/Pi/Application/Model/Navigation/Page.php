<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Application\Model\Navigation;

use Pi\Application\Model\Nest;

/**
 * Navigation page model
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Page extends Nest
{
    /** @var string Navigation name */
    protected $navigation = '';

    /**
     * {@inheritDoc}
     */
    protected $encodeColumns
        = [
            'params'   => true,
            'resource' => true,
        ];

    /**
     * Set navigation name
     *
     * @param string $navigation
     * @return $this
     */
    public function setNavigation($navigation)
    {
        if (null !== $navigation) {
            $this->navigation = $navigation;
        }

        return $this;
    }

    /**
     * Get navigation
     *
     * @return string
     */
    public function getNavigation()
    {
        return $this->navigation;
    }

    /**
     * Remove pages of a navigation
     *
     * @param string $nav Navigation name
     * @return bool
     */
    public function removeByNavigation($nav)
    {
        $pageRoots = $this->getRoots(
            ['navigation' => $nav],
            [$this->column('left') . ' DESC']
        );
        foreach ($pageRoots as $root) {
            $this->remove($root);
        }

        return true;
    }
}
