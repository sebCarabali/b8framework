<?php

/**
 * {@table.php_name} base model for table: {@name}
 */

namespace {@appNamespace}\Model\Base;

use b8\Model;

/**
 * {@table.php_name} Base Model
 */
class {@table.php_name}Base extends Model
{
    /**
    * @var array
    */
    public static $sleepable = array();

    /**
    * @var string
    */
    protected $tableName = '{@name}';

    /**
    * @var string
    */
    protected $modelName = '{@table.php_name}';

    /**
    * @var array
    */
    protected $data = array(
{loop table.columns}
        '{@item.name}' => null,

{/loop}     );

    /**
    * @var array
    */
    protected $getters = array(
{loop table.columns}
        '{@item.name}' => 'get{@item.php_name}',

{/loop}{loop table.relationships.toOne}
        '{@item.php_name}' => 'get{@item.php_name}',

{/loop}     );

    /**
    * @var array
    */
    protected $setters = array(
{loop table.columns}
        '{@item.name}' => 'set{@item.php_name}',

{/loop}{loop table.relationships.toOne}
        '{@item.php_name}' => 'set{@item.php_name}',

{/loop}     );

    /**
    * @var array
    */
    public $columns = array(
{loop table.columns}
        '{@item.name}' => array(
            'type' => '{@item.type}',
            'length' => '{@item.length}',
{if item.null}
            'nullable' => true,

{/if}
{if item.is_primary_key}
            'primary_key' => true,

{/if}
{if item.auto}
            'auto_increment' => true,

{/if}
{if item.default_is_null}
            'default' => null,

{/if}
{ifnot item.default_is_null}
            'default' => '{@item.default}',

{/ifnot}
            ),

{/loop}     );

    /**
    * @var array
    */
    public $indexes = array(
{loop table.indexes}
            '{@item.name}' => array({if item.unique}'unique' => true, {/if}'columns' => '{@item.columns}'),

{/loop}     );

    /**
    * @var array
    */
    public $foreignKeys = array(
{loop table.relationships.toOne}
            '{@item.fk_name}' => array(
                'local_col' => '{@item.from_col}',
                'update' => '{@item.fk_update}',
                'delete' => '{@item.fk_delete}',
                'table' => '{@item.table}',
                'col' => '{@item.col}'
                ),

{/loop}     );

{loop table.columns}

    /**
    * Get the value of {@item.php_name} / {@item.name}.
    *
{if item.validate_int}
    * @return int

{/if}{if item.validate_string}
    * @return string

{/if}{if item.validate_float}
    * @return float

{/if}{if item.validate_date}
    * @return \DateTime

{/if}
    */
    public function get{@item.php_name}()
    {
        $rtn    = $this->data['{@item.name}'];

        {if item.validate_date}

        if (!empty($rtn)) {
            $rtn    = new \DateTime($rtn);
        }

        {/if}

        return $rtn;
    }

{/loop}{loop table.columns}

    /**
    * Set the value of {@item.php_name} / {@item.name}.
    *
{if item.validate_null}
    * Must not be null.

{/if}{if item.validate_int}
    * @param $value int

{/if}{if item.validate_string}
    * @param $value string

{/if}{if item.validate_float}
    * @param $value float

{/if}{if item.validate_date}
    * @param $value \DateTime

{/if}
    */
    public function set{@item.php_name}($value)
    {
{if item.validate_null}
        $this->_validateNotNull('{@item.php_name}', $value);
{/if}

{if item.validate_int}
        $this->_validateInt('{@item.php_name}', $value);
{/if}{if item.validate_string}
        $this->_validateString('{@item.php_name}', $value);
{/if}{if item.validate_float}
        $this->_validateFloat('{@item.php_name}', $value);
{/if}{if item.validate_date}
        $this->_validateDate('{@item.php_name}', $value);
{/if}

        if ($this->data['{@item.name}'] === $value) {
            return;
        }

        $this->data['{@item.name}'] = $value;

        $this->_setModified('{@item.name}');
    }

{/loop}{loop table.relationships.toOne}

    /**
     * Get the {@item.table_php_name} model for this {@parent.table.php_name} by {@item.col_php}.
     *
     * @uses \{@parent.appNamespace}\Store\{@item.table_php_name}Store::getBy{@item.col_php}()
     * @uses \{@parent.appNamespace}\Model\{@item.table_php_name}
     * @return \{@parent.appNamespace}\Model\{@item.table_php_name}
     */
    public function get{@item.php_name}()
    {
        $key = $this->get{@item.from_col_php}();

        if (empty($key)) {
            return null;
        }

        $cacheKey   = 'Cache.{@item.table_php_name}.' . $key;
        $rtn        = $this->cache->get($cacheKey, null);

        if (empty($rtn)) {
            $rtn    = \b8\Store\Factory::getStore('{@item.table_php_name}')->getBy{@item.col_php}($key);
            $this->cache->set($cacheKey, $rtn);
        }

        return $rtn;
    }

    /**
    * Set {@item.php_name} - Accepts an ID, an array representing a {@item.table_php_name} or a {@item.table_php_name} model.
    *
    * @param $value mixed
    */
    public function set{@item.php_name}($value)
    {
        // Is this an instance of {@item.table_php_name}?
        if ($value instanceof \{@parent.appNamespace}\Model\{@item.table_php_name}) {
            return $this->set{@item.php_name}Object($value);
        }

        // Is this an array representing a {@item.table_php_name} item?
        if (is_array($value) && !empty($value['{@item.col}'])) {
            return $this->set{@item.from_col_php}($value['{@item.col}']);
        }

        // Is this a scalar value representing the ID of this foreign key?
        return $this->set{@item.from_col_php}($value);
    }

    /**
    * Set {@item.php_name} - Accepts a {@item.table_php_name} model.
    * 
    * @param $value \{@parent.appNamespace}\Model\{@item.table_php_name}
    */
    public function set{@item.php_name}Object(\{@parent.appNamespace}\Model\{@item.table_php_name} $value)
    {
        return $this->set{@item.from_col_php}($value->get{@item.col_php}());
    }

{/loop}{loop table.relationships.toMany}

    /**
     * Get {@item.table_php} models by {@item.from_col_php} for this {@parent.table.php_name}.
     *
     * @uses \{@parent.appNamespace}\Store\{@item.table_php}Store::getBy{@item.from_col_php}()
     * @uses \{@parent.appNamespace}\Model\{@item.table_php}
     * @return \{@parent.appNamespace}\Model\{@item.table_php}[]
     */
    public function get{@item.php_name}()
    {
        return \b8\Store\Factory::getStore('{@item.table_php}')->getBy{@item.from_col_php}($this->get{@item.col_php}());
    }

{/loop}}
