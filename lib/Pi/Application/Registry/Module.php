<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 * @package         Registry
 */

namespace Pi\Application\Registry;

use Pi;

/**
 * Module list
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Module extends AbstractRegistry
{
    /**
     * {@inheritDoc}
     */
    protected function loadDynamic($options = [])
    {
        $modules = [];
        $model   = Pi::model('module');
        $select  = $model->select();
        $select->order('title')->where([]);
        $rowset = $model->selectWith($select);
        foreach ($rowset as $module) {
            $modules[$module->name] = [
                'id'        => $module->id,
                'name'      => $module->name,
                'title'     => $module->title,
                'active'    => $module->active,
                'directory' => $module->directory,
            ];
        }

        return $modules;
    }

    /**
     * {@inheritDoc}
     * @param string $module
     */
    public function read($module = '')
    {
        $data = $this->loadData();
        $ret  = empty($module)
            ? $data
            : (isset($data[$module])
                ? $data[$module]
                : false);

        return $ret;
    }

    /**
     * {@inheritDoc}
     * @param string $module
     */
    public function create($module = '')
    {
        $this->clear($module);
        $this->read($module);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setNamespace($meta = '')
    {
        return parent::setNamespace('');
    }

    /**
     * {@inheritDoc}
     */
    public function clear($namespace = '')
    {
        parent::clear('');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function flush()
    {
        return $this->clear('');
    }
}
