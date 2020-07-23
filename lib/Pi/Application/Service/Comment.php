<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 * @package         Service
 */

namespace Pi\Application\Service;

use Module\Comment\Form\PostForm;
use Pi;
use Pi\Db\Sql\Where;

/**
 * Comment service
 *
 * - addPost(array $data)
 * - getPost($id)
 * - getRoot(array $condition|$id)
 * - getTarget($root)
 * - getList(array $condition|$root, $limit, $offset, $order)
 * - getCount(array $condition|$root)
 * - getForm(array $data)
 * - getUrl($type, array $options)
 * - updatePost($id, array $data)
 * - deletePost($id)
 * - approve($id, $flag)
 * - enable($root, $flag)
 * - delete($root, $flag)
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Comment extends AbstractService
{
    /** {@inheritDoc} */
    protected $fileIdentifier = 'comment';

    /** @var array TTL and CacheAdapter */
    protected $cache;

    /**
     * Is comment service available
     *
     * @return bool
     */
    public function active()
    {
        return Pi::service('module')->isActive('comment');
    }

    /**
     * Load leading comment posts for targets
     *
     * Load comment data
     * - root[id, module, category, item, active]
     * - count
     * - posts[id, uid, IP, content, time, active]
     * - users[uid, name, avatar, url]
     * - url_list
     * - url_submit
     * - url_ajax
     *
     * @param array|string $params
     *
     * @return string
     */
    public function load($params = null)
    {
        if (!$this->active()) {
            return;
        }
        if (is_array($params) && isset($params['options']['type'])) {
            $type = $params['options']['type'];
            unset($params['options']['type']);
        } else {
            $type = '';
        }

        $review = false;
        if (is_array($params) && isset($params['review'])) {
            $review = $params['review'];
        }

        $ajaxLoad = Pi::config('ajax_load', 'comment');

        if ($ajaxLoad) {
            
            // Determine language for datepicker
            $language = !empty($params['language']) ? $params['language'] : Pi::service('i18n')->getLocale();
            $segs     = explode(' ', str_replace(['-', '_'], ' ', $language));
            $language = array_shift($segs);
            if ($segs) {
                $language .= '-' . strtoupper(implode('-', $segs));
            }
            
            // Add datepicker js
            $bsLoad = array(
                'datepicker/bootstrap-datepicker.min.css',
                'datepicker/bootstrap-datepicker.min.js'
            );  
             if ('en' != $language) {
                $bsLoad[] = sprintf('datepicker/locales/bootstrap-datepicker.%s.min.js', $language);
            }
            Pi::Service('View')->getViewManager()->getHelperManager()->get('bootstrap')->__invoke($bsLoad, [], null, false);
            //
            
            $callback = Pi::service('url')->assemble('comment', array(
                'module'        => 'comment',
                'controller'    => 'index',
                'action'        => 'load',
                'review'        => $review,
                'caller'        => Pi::service('module')->current(),
                'owner'         => isset($params['owner']) ? $params['owner'] : null 
            ));
            $rand = rand();
            $content =<<<EOT
            <a id="pi-comment-lead-anchor-$rand" style="display: block;
    position: relative;
    top: -100px;
    visibility: hidden;"></a>
<div class="hidden pi-comment-lead-$rand"></div>
<script>
    $( document ).ready(function() {

        $.get("{$callback}", {
            uri: $(location).attr('href'),
            time: new Date().getTime()
        })
        .done(function (data) {

            if (data) {
                var el = $('.pi-comment-lead-$rand');
                el.attr('class','show pi-comment-lead').html(data);
            }
        });
    });
    
</script>
EOT;
        } else {
            $params['uri'] = $_SERVER['REQUEST_URI'];
            $params['caller'] = Pi::service('module')->current();
            $content = $this->loadContent($params);
            $content = '<div class="pi-comment-lead">' . $content . '</div>';
        }

        Pi::Service('View')->getViewManager()->getHelperManager()->get('jQuery')->__invoke('extension/jquery.magnific-popup.min.js', [], null, false);
        Pi::Service('View')->getViewManager()->getHelperManager()->get('jQuery')->__invoke('extension/magnific-popup.min.css', [], null, false);
        Pi::Service('View')->getViewManager()->getHelperManager()->get('js')->__invoke(Pi::service('asset')->getModuleAsset('script/system-msg.js', 'system'), [], null, false);
        Pi::Service('View')->getViewManager()->getHelperManager()->get('css')->__invoke(Pi::service('asset')->getModuleAsset('css/front.css', 'comment'), [], null, false);
            
        return $content;
    }

    /**
     * Load leading comment content
     *
     * @param array|string $params
     *
     * @return string
     */
    public function loadContent($params = null)
    {
        $options = [];
        if (is_string($params)) {
            $uri        = $params;
            $routeMatch = Pi::service('url')->match($uri);
            $params     = ['uri' => $uri];
        } else {
            $params     = (array)$params;
            $uri        = $params['uri'];
            $routeMatch = Pi::service('url')->match($uri);
        }
                $params = array_replace($params, $routeMatch->getParams());
        
        $review = isset($params['review']) ? $params['review'] : false;
        $options = [
            'review' => $review,
            'page'   => isset($params['page']) ? $params['page'] : 1,

        ];
        $data    = Pi::api('api', 'comment')->load($params, $options);
        if (!$data) {
            return;
        }
        $data['uri']    = isset($params['uri'])
            ? $params['uri']
            : Pi::service('url')->getRequestUri();
        $data['uid'] = Pi::user()->getId();
        
        $data['review'] = $review;        
        $data['owner'] = isset($params['owner']) ? $params['owner'] : false;
        $data['admin'] =  Pi::service('permission')->isAdmin('comment', $data['uid']);
        $data['page'] = isset($params['page']) ? $params['page'] : 1;
        $data['caller'] = isset($params['caller']) ? $params['caller'] : null;
        $template = 'comment:front/comment-lead';
        $result = Pi::service('view')->render($template, $data);

        return $result;
    }

    public function loadComments($params = null)
    {

        $options = [];
        if (is_string($params)) {
            $uri        = $params;
            $routeMatch = Pi::service('url')->match($uri);
            $params     = ['uri' => $uri];
        } else {
            $params     = (array)$params;
            $uri        = $params['uri'];
            $routeMatch = Pi::service('url')->match($uri);
            $review     = $params['review'];
        }

        $params  = array_replace($params, $routeMatch->getParams());
        $options = [
            'review' => $review,
            'page'   => $params['page'],
        ];

        $data = Pi::api('api', 'comment')->load($params, $options);
        if (!$data) {
            return;
        }

        $data['uri']    = isset($params['uri'])
            ? $params['uri']
            : Pi::service('url')->getRequestUri();
        $data['uid']    = Pi::user()->getId();
        $data['review'] = $params['review'];
        $data['page']   = isset($params['page']) ? $params['page'] : 1;

        $template = 'comment:front/partial/paginate-comments';
        $result   = Pi::service('view')->render($template, $data);
        return $result;
    }

    /**
     * Get URLs
     *
     * For AJAX request, set `$options['return'] = 1;`
     *
     * @param string $type
     * @param array $options
     *
     * @return string
     */
    public function getUrl($type, array $options = [])
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getUrl($type, $options);
    }

    /**
     * Get comment post edit form
     *
     * @param array $data
     *
     * @return bool|PostForm
     */
    public function getForm(array $data = [], array $options = [])
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getForm($data, $options);
    }

    /**
     * Get comment post edit form
     *
     * @param array $data
     *
     * @return bool|PostForm
     */
    public function getFormReply(array $data = array(), array $options = array())
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getFormReply($data, $options);
    }
    /**
     * Render post content
     *
     * @param array|RowGateway|string $post
     *
     * @return string
     */
    public function renderPost($post)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->renderPost($post);
    }

    /**
     * Render list of posts
     *
     * @param array $posts
     * @param bool $isAdmin
     *
     * @return array
     */
    public function renderList(array $posts, $isAdmin = false)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->renderList($posts, $isAdmin);
    }

    /**
     * Add comment of an item
     *
     * @param array $data Data of uid, content, module, item, category, time
     *
     * @return int|bool
     */
    public function addPost(array $data)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->add($data);
    }

    /**
     * Get a comment
     *
     * @param int $id
     *
     * @return array|bool   uid, content, time, active, IP
     */
    public function getPost($id)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getPost($id);
    }

    /**
     * Get root
     *
     * @param int|array $condition
     *
     * @return array|bool    module, category, item, callback, active
     */
    public function getRoot($condition)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getRoot($condition);
    }

    /**
     * Get target content
     *
     * @param int $root
     *
     * @return array|bool    Title, url, uid, time
     */
    public function getTarget($root)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getTarget($root);
    }

    /**
     * Get multiple comments
     *
     * @param int|array|Where $condition Root id or conditions
     * @param int $limit
     * @param int $offset
     * @param string $order
     *
     * @return array|bool
     */
    public function getList($type, $condition, $limit, $offset = 0, $order = '')
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getList($type, $condition, $limit, $offset, $order);
    }

    /**
     * Get comment count
     *
     * @param int|array|Where $condition Root id or conditions
     *
     * @return int|bool
     */
    public function getCount($condition)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->getCount($condition);
    }

    /**
     * Update a comment
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function updatePost($id, array $data)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->update($id, $data);
    }

    /**
     * Delete a comment
     *
     * @param int $id
     *
     * @return bool
     */
    public function deletePost($id)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->delete($id);
    }

    /**
     * Approve/Disapprove a comment
     *
     * @param int $id
     * @param bool $flag
     *
     * @return bool
     */
    public function approve($id, $flag = true)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->approve($id, $flag);
    }

    /**
     * Enable/Disable comments for a target
     *
     * @param array|int $root
     * @param bool $flag
     *
     * @return bool
     */
    public function enable($root, $flag = true)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->enable($root, $flag);
    }

    /**
     * Delete comment root and its comments
     *
     * @param int $root
     *
     * @return bool
     */
    public function delete($root)
    {
        if (!$this->active()) {
            return false;
        }

        return Pi::api('api', 'comment')->deleteRoot($root);
    }

    /**
     * Get cache specs
     *
     * @param int $id
     *
     * @return array
     */
    public function cache($id = null)
    {
        if (null === $this->cache) {
            $ttl     = $this->getOption('cache', 'ttl');
            $storage = $this->getOption('cache', 'storage');
            if ($ttl) {
                if ($storage) {
                    $storage = Pi::service('cache')->loadStorage($storage);
                } else {
                    $storage = null;
                }

                $this->cache = [
                    'namespace' => 'comment',
                    'ttl'       => $ttl,
                    'storage'   => $storage,
                ];
            } else {
                $this->cache = [];
            }
        }
        $spec = (array)$this->cache;
        if ($id && $spec) {
            $spec['key'] = md5((string)$id);
        }

        return $spec;
    }

    /**
     * Load comments on leading page from cache
     *
     * @param int $root
     *
     * @return array
     */
    public function loadCache($root)
    {
        $result = [];
        $cache  = $this->cache($root);
        if ($root && $cache) {
            $data = Pi::service('cache')->getItem(
                $cache['key'],
                $cache,
                $cache['storage']
            );
            if (null !== $data) {
                $result = json_decode($data, true);
            }
        }

        return $result;
    }

    /**
     * Save comments on leading page to cache
     *
     * @param int $root
     * @param array $data
     *
     * @return bool
     */
    public function saveCache($root, array $data)
    {
        $result = false;
        $cache  = $this->cache($root);
        if ($root && $cache) {
            Pi::service('cache')->setItem(
                $cache['key'],
                json_encode($data),
                $cache,
                $cache['storage']
            );
            $result = true;
        }

        return $result;
    }

    /**
     * Flush cache for a root or all comments
     *
     * @param int|int[] $id
     * @param bool $isRoot
     *
     * @return bool
     */
    public function clearCache($id = null, $isRoot = false)
    {
        $result = false;

        if (!$id) {
            $cache = $this->cache();
            if ($cache) {
                Pi::service('cache')->clearByNamespace(
                    $cache['namespace'],
                    $cache['storage']
                );
                $result = true;
            }
        } else {
            $ids = (array)$id;
            foreach ($ids as $id) {
                if (!$isRoot) {
                    $post = $this->getPost($id);
                    if ($post) {
                        $id = $post['root'];
                    } else {
                        $id = 0;
                    }
                }
                if ($id) {
                    $cache = $this->cache($id);
                    // Remove an item
                    if ($cache) {
                        Pi::service('cache')->removeItem(
                            $cache['key'],
                            $cache,
                            $cache['storage']
                        );
                        $result = true;
                    }
                }
            }
        }

        return $result;
    }

    public function clearPagination($id = null, $isRoot = false)
    {
        $ids = (array)$id;
        foreach ($ids as $id) {
            if (!$isRoot) {
                $post = $this->getPost($id);
                if ($post) {
                    $id = $post['root'];
                } else {
                    $id = 0;
                }
            }
            if ($id) {
                $limit = Pi::config('leading_limit', 'comment') ?: 5;

                $offset = 0;
                $key    = $id . '-' . \Module\Comment\Model\Post::TYPE_REVIEW . '-' . $limit . $offset;
                while ($this->loadCache($key) != []) {
                    // Clear cache for leading comments
                    $this->clearCache($key, true);

                    $offset += $limit;
                    $key    = $id . '-' . $limit . $offset;
                }

                $offset = 0;
                $key    = $id . '-' . \Module\Comment\Model\Post::TYPE_COMMENT . '-' . $limit . $offset;
                while ($this->loadCache($key) != []) {
                    // Clear cache for leading comments
                    $this->clearCache($key, true);

                    $offset += $limit;
                    $key    = $id . '-' . $limit . $offset;
                }
            }
        }

    }


    /**
     * Insert user timeline for a new comment
     *
     * @param int $id
     * @param int $uid
     *
     * @return bool
     */
    public function timeline($id, $uid = null)
    {
        $result = true;
        $uid    = $uid ?: Pi::service('user')->getId();

        $message = __('Posted a new comment.');

        $post   = Pi::api('api', 'comment')->getPost($id);
        $link   = Pi::url(Pi::api('api', 'comment')->getUrl('post', [
            'post' => $id,
        ]), true);
        $params = [
            'uid'      => $uid,
            'message'  => $message,
            'timeline' => 'new_comment',
            'time'     => time(),
            'module'   => 'comment',
            'link'     => $link,
            'data'     => json_encode(['comment' => $id]),
        ];
        Pi::service('user')->timeline()->add($params);

        return $result;
    }

    /**
     * Delete user timeline for a comment
     *
     * @param int $id
     *
     */
    public function timelineDelete($id)
    {
        $params = [
            'module' => 'comment',
            'data'   => $id,
        ];
        Pi::service('user')->timeline()->delete($params);


    }
}
