<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Application\Installer;

use Pi;

/**
 * SQL schema query class
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class SqlSchema
{
    /**
     * Schema file
     * @var string
     */
    protected $file;

    /**
     * Table types, core or specified module
     * @var string
     */
    protected static $type;

    /**
     * Constructor
     *
     * @param string|null $file
     */
    public function __construct($file = null)
    {
        if ($file) {
            $this->setFile($file);
        }
    }

    /**
     * Set schema file
     *
     * @param string $file
     * @return $this
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * Set schema type
     *
     * @param string $type
     * @return void
     */
    public static function setType($type)
    {
        static::$type = $type;
    }

    /**
     * Parse and canonize schema definition content
     *
     * @param string $content
     * @param string $type
     *
     * @return string
     */
    public function parseContent($content, $type = '')
    {
        // Remove comments to prevent from invalid syntax
        $content = preg_replace('/(#.*|-- .*)/', '', $content);

        $type           = $type ?: static::$type;
        $canonizePrefix = function ($matches) use ($type) {
            $name = $matches[1];
            // Core tables: {core.<table_name>}
            if (substr($name, 0, 6) == '{core.') {
                $tableName = substr($name, 6, -1);
                $tableName = Pi::db()->prefix($tableName, 'core');
                // Module tables: {<module_table>}
            } else {
                $tableName = substr($name, 1, -1);
                $tableName = Pi::db()->prefix($tableName, $type);
            }
            return $tableName;
        };

        $result = preg_replace_callback(
            '|(\{[^\}]+\})|',
            $canonizePrefix,
            $content
        );

        return $result;
    }

    /**
     * Performe query on content
     *
     * @param string $content
     * @param string $type
     *
     * @return bool
     */
    public function queryContent($content = null, $type = '')
    {
        $sql = $this->parseContent($content, $type);
        Pi::db()->adapter()->query($sql, 'execute');

        return true;
    }

    /**
     * Query content from a file
     *
     * @param string $file
     * @param string $type
     *
     * @return bool
     */
    public function queryFile($file = null, $type = '')
    {
        $content = file_get_contents($file ?: $this->file);

        return $this->queryContent($content, $type);
    }

    /**
     * Query a file with specified type
     *
     * @param string $file
     * @param string $type
     *
     * @return bool
     */
    public static function query($file, $type = 'core')
    {
        $schema = new self;
        //static::setType($type);

        return $schema->queryFile($file, $type);
    }

    /**
     * Fetch schema from a file
     *
     * @param string $file
     * @param bool $isCore Fetch core schema, default as false
     * @return array
     */
    public static function fetchSchema($file, $isCore = false)
    {
        $content = file_get_contents($file);

        return static::parseSchema($content, $isCore);
    }

    /**
     * Parse schema names and types
     *
     * @param string $content
     * @param bool $isCore Fetch core schema, default as false
     * @return array
     */
    public static function parseSchema($content, $isCore = false)
    {
        $result = [];
        // Remove comments to prevent from invalid syntax
        $content = preg_replace('/(#.*|-- .*)/', '', $content);

        $pattern = '/create\s+(table|view|trigger)\s+\`\{'
            . ($isCore ? 'core\.' : '')
            . '([a-z0-9_]+)\}\`/i';
        $matches = [];
        preg_match_all($pattern, $content, $matches);
        if ($matches) {
            foreach ($matches[2] as $key => $schema) {
                $result[$schema] = $matches[1][$key];
            }
        }

        return $result;
    }
}
