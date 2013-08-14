<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Pi\Application\Installer\Resource;

use Pi;

/**
 * User meta setup
 *
 * Meta data registered to user module
 *
 * - Profile field registry
 * - Profile compound field registry
 * - Timeline registry
 * - Activity registry
 * - Quicklink registry
 *
 * <code>
 *  array(
 *      // Profile field
 *      'field' => array(
 *          // Field with simple text input
 *          <field-key> => array(
 *              // Field type, optional, default as 'custom'
 *              'type'          => 'custom',
 *              // Field name, optional, will be set as <module>_<field-key>
 *              // if not specified
 *              'name'          => <specified_field_name>,
 *              'title'         => __('Field Name A'),
 *              'description'   => __('Description of field A.'),
 *              'value'         => 'field value',
 *
 *              // Edit element specs
 *              'edit'          => 'text',
 *              // Filter for value processing for output
 *              'filter'        => <output-filter>
 *
 *              // Editable by user
 *              'is_edit'       => true,
 *              // Display on user profile page, default as true
 *              'is_display'    => true,
 *              // Search user by this field, default as true
 *              'is_search'     => false,
 *          ),
 *          // Field with specified edit with form element and filter
 *          <field-key> => array(
 *              'title'         => __('Field Name B'),
 *              'description'   => __('Description of field B.'),
 *              'value'         => 1,
 *              'edit'          => array(
 *                  'element'       => array(
 *                      'type'          => 'select'
 *                      'attributes'    => array(
 *                         'options'    => array(
 *                             0  => 'Option A',
 *                             1  => 'Option B',
 *                        ),
 *                   ),
 *                  'filters'       => array(
 *                  ),
 *                  'validators'    => array(
 *                  ),
 *              ),
 *              // Filter specs
 *              'filter'        => 'int',
 *          ),
 *          // Field with specified edit with simple element
 *          <field-key> => array(
 *              'title'         => __('Field Name C'),
 *              'description'   => __('Description of field C.'),
 *              'value'         => 1,
 *              'edit'          => array(
 *                  'element'       => 'text',
 *                  'validators'    => array(<...>),
 *              ),
 *              // Filter specs
 *              'filter'        => 'int',
 *          ),
 *
 *          // Field with no edit element, it will be handled as 'text'
 *          <field-key> => array(
 *              'title'         => __('Field Name D'),
 *              'description'   => __('Description of field D.'),
 *              'value'         => <field-value>,
 *          ),
 *
 *          <...>,
 *
 *          // Compound
 *          <compound-field-key> => array(
 *              // Field type, MUST be 'compound'
 *              'type'          => 'compound',
 *              // Field name, optional, will be set as <module>_<field-key>
 *              // if not specified
 *              'name'          => <specified_field_name>,
 *              'title'         => __('Compound Field'),
 *
 *              'field' => array(
 *                  <field-key> => array(
 *                      'title'         => __('Compound Field Item'),
 *
 *                      // Edit element specs
 *                      'edit'          => 'text',
 *                      // Filter for value processing for output
 *                      'filter'        => <output-filter>
 *                  ),
 *                  <...>,
 *              ),
 *          ),
 *      ),
 *
 *      // Timeline
 *      'timeline'      => array(
 *          <name>  => array(
 *              'title' => __('Timeline Title'),
 *              ['icon'  => <img-src>,]
 *          ),
 *          <...>
 *      ),
 *
 *      // Activity
 *      'activity'      => array(
 *          <name>  => array(
 *              'title'     => __('Activity Title'),
 *              'callback'  => <callback>
 *              'link'      => <link-to-full-list>,
 *
 *              ['icon'     => <img-src>,]
 *          ),
 *          <...>
 *      ),
 *
 *      // Quicklink
 *      'quicklink'     => array(
 *          <name>  => array(
 *              'title' => __('Link Title'),
 *              'link'  => <link-href>,
 *              ['icon'  => <img-src>]
 *          ),
 *          <...>
 *      ),
 *  );
 * </code>
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class User extends AbstractResource
{
    /**
     * Check if user spec is applicable
     *
     * @return bool
     */
    protected function isActive()
    {
        return Pi::service('module')->isActive('user') ? true : false;
    }

    /**
     * Canonize user specs for profile, timeline, activity meta and quicklinks
     *
     * Field name: if field `name` is not specified, `name` will be defined
     * as module name followed by field key and delimited by underscore `_`
     * as `<module-name>_<field_key>`
     *
     * @param array $config
     * @return array
     */
    protected function canonize($config)
    {
        $ret = array(
            'field'         => array(),
            'timeline'      => array(),
            'activity'      => array(),
            'quicklink'     => array(),
        );

        $module = $this->getModule();
        // Canonize fields
        if (isset($config['field'])) {
            $profile = $this->canonizeProfile($config['field']);
            $config['field'] = $profile['field'];
            if (isset($profile['compound'])) {
                $config['compound'] = $profile['compound'];
            }
            /*
            foreach ($config['field'] as $key => &$spec) {
                $spec = $this->canonizeField($spec);
            }
            */
        }

        foreach (array('field', 'timeline', 'activity', 'quicklink') as $op) {
            if (isset($config[$op])) {
                foreach ($config[$op] as $key => $spec) {
                    // Canonize field name
                    $name = !empty($spec['name'])
                        ? $spec['name']
                        : $module . '_' . $key;

                    $ret[$op][$name] = array_merge($spec, array(
                        'name'      => $name,
                        'module'    => $module,
                    ));
                }
            }
        }

        return $ret;
    }

    /**
     * Canonize a profile field specs
     *
     * Edit specs:
     * Transform
     * `'edit' => <type>` and `'edit' => array('element' => <type>)`
     * to
     * ```
     *  'edit' => array(
     *      'element'   => array(
     *          'type'  => <type>,
     *          <...>,
     *      ),
     *      <...>,
     *  ),
     * ```
     *
     * 3. Add edit specs if `is_edit` is `true` or not specified
     *
     * @param array $spec
     * @return array
     * @see Pi\Application\Service\User::canonizeField()
     */
    protected function canonizeField($spec)
    {
        if (isset($spec['field'])) {
            $spec['type'] = 'compound';
        }
        if (!isset($spec['type'])) {
            $spec['type'] = 'custom';
        }
        if ('compound' == $spec['type']) {
            $spec['is_edit'] = 0;
            $spec['is_display'] = 0;
            $spec['is_search'] = 0;
            
            return $spec;
        }

        // Canonize editable, display and searchable, default as true
        foreach (array('is_edit', 'is_display', 'is_search') as $key) {
            if (!isset($spec[$key])) {
                $spec[$key] = 1;
            } else {
                $spec[$key] = (int) $spec[$key];
            }
        }

        if (!isset($spec['edit']) && $spec['is_edit']) {
            $spec['edit'] = array(
                'element'   => array(
                    'type'  => 'text',
                ),
            );
        }

        if (isset($spec['edit'])) {
            if (is_string($spec['edit'])) {
                $spec['edit'] = array(
                    'element'   => array(
                        'type'  => $spec['edit'],
                    ),
                );
            } elseif (!$spec['edit']['element']) {
                $spec['edit']['element'] = array(
                    'type'  => 'text',
                );
            } elseif (is_string($spec['edit']['element'])) {
                $spec['edit']['element'] = array(
                    'type'  => $spec['edit']['element'],
                );
            }
        }

        return $spec;
    }

    /**
     * {@inheritDoc}
     */
    public function installAction()
    {
        if (!$this->isActive()) {
            return;
        }
        if (empty($this->config)) {
            return;
        }
        Pi::registry('profile')->clear();

        $config = $this->canonize($this->config);
        foreach (array('field', 'timeline', 'activity', 'quicklink')
            as $op
        ) {
            $model = Pi::model($op, 'user');
            foreach ($config[$op] as $key => $spec) {
                $row = $model->creatRow($spec);
                $status = $row->save();
                if (!$status) {
                    return array(
                        'status'    => false,
                        'message'   => sprintf(
                            '%s "%s" is not created.',
                            $op,
                            $key
                        ),
                    );
                }
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function updateAction()
    {
        if (!$this->isActive()) {
            return;
        }
        $module = $this->getModule();
        Pi::registry('profile')->clear();

        if ($this->skipUpgrade()) {
            return;
        }

        $itemsDeleted = array();
        $config = $this->canonize($this->config);
        foreach (array('field', 'timeline', 'activity', 'quicklink')
            as $op
        ) {
            $model = Pi::model($op, 'user');
            $rowset = $model->select(array('module' => $module));
            $items = $config[$op];
            $itemsDeleted[$op] = array();
            foreach ($rowset as $row) {
                // Update existent item
                if (isset($items[$row->name])) {
                    // Titles are editable by admin, don't overwrite
                    unset($items[$row->name]['name']);
                    unset($items[$row->name]['title']);
                    if (isset($items[$row->name]['value'])) {
                        unset($items[$row->name]['value']);
                    }

                    $row->assign($items[$row->name]);
                    $row->save();
                    unset($items[$row->name]);
                // Delete deprecated items
                } else {
                    $itemsDeleted[$op][] = $row->name;
                    $row->delete();
                }
            }
            // Add new items
            foreach ($items as $key => $spec) {
                $row = $model->creatRow($spec);
                $status = $row->save();
                if (!$status) {
                    return array(
                        'status'    => false,
                        'message'   => sprintf(
                            '%s "%s" is not created.',
                            $op,
                            $key
                        ),
                    );
                }
            }
        }

        // Delete deprecated user custom profile data
        if ($itemsDeleted['field']) {
            Pi::model('custom', 'user')->delete(array(
                'field' => $itemsDeleted['field'],
            ));
            Pi::model('compound_field', 'user')->delete(array(
                'compound' => $itemsDeleted['field'],
            ));
            Pi::model('compound', 'user')->delete(array(
                'compound' => $itemsDeleted['field'],
            ));
        }

        // Delete deprecated timeline log
        if ($itemsDeleted['timeline']) {
            Pi::model('timeline_log', 'user')->delete(array(
                'timeline' => $itemsDeleted['timeline'],
            ));
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function uninstallAction()
    {
        if (!$this->isActive()) {
            return;
        }
        $module = $this->getModule();
        Pi::registry('profile')->clear();

        $model = Pi::model('field', 'user');
        $fields = array();
        $rowset = $model->select(array('module' => $module));
        foreach ($rowset as $row) {
            $fields[] = $row->name;
        }
        // Remove module profile data
        if ($fields) {
            Pi::model('custom', 'user')->delete(array('field' => $fields));
        }

        $compounds = array();
        $rowset = $model->select(array(
            'module'    => $module,
            'type'      => 'compound',
        ));
        foreach ($rowset as $row) {
            $compounds[] = $row->name;
        }
        // Remove module profile data
        if ($compounds) {
            Pi::model('compound', 'user')->delete(array(
                'compound'  => $compounds,
            ));
        }

        foreach (array(
            'field',
            'compound_field',
            'timeline',
            'activity',
            'quicklink',
            'timeline_log'
        ) as $op) {
            $model = Pi::model($op, 'user');
            $model->delete(array('module' => $module));
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function activateAction()
    {
        if (!$this->isActive()) {
            return;
        }
        $module = $this->getModule();
        Pi::registry('profile')->clear();

        foreach (array('field', 'timeline', 'activity', 'quicklink')
            as $op
        ) {
            $model = Pi::model($op, 'user');
            $model->update(array('active' => 1), array('module' => $module));
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function deactivateAction()
    {
        if (!$this->isActive()) {
            return;
        }
        $module = $this->getModule();
        Pi::registry('profile')->clear();

        foreach (array('field', 'timeline', 'activity', 'quicklink')
            as $op
        ) {
            $model = Pi::model($op, 'user');
            $model->update(array('active' => 0), array('module' => $module));
        }

        return true;
    }
}
