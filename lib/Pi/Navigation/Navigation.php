<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Navigation;

use Traversable;
use Zend\Navigation\Exception;
use Zend\Navigation\Navigation as ZendNavigation;

/**
 * Navigation class
 *
 * {@inheritDoc}
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Navigation extends ZendNavigation
{
    /**
     * Add a page
     *
     * {@inheritDoc}
     * @see Pi\Navigation\Page\Mvc::addPage()
     * @see Pi\Navigation\Page\Uri::addPage()
     */
    public function addPage($page)
    {
        if ($page === $this) {
            throw new Exception\InvalidArgumentException(
                'A page cannot have itself as a parent'
            );
        }

        if (!$page instanceof Page\AbstractPage) {
            if (!is_array($page) && !$page instanceof Traversable) {
                throw new Exception\InvalidArgumentException(
                    'Invalid argument: $page must be an instance of '
                    . 'Zend\Navigation\Page\AbstractPage or Traversable,'
                    . ' or an array'
                );
            }
            $page = Page\AbstractPage::factory($page);
        }

        $hash = $page->hashCode();

        if (array_key_exists($hash, $this->index)) {
            // page is already in container
            return $this;
        }

        // adds page to container and sets dirty flag
        $this->pages[$hash] = $page;
        $this->index[$hash] = $page->getOrder();
        $this->dirtyIndex   = true;

        // inject self as page parent
        $page->setParent($this);

        return $this;
    }
}
