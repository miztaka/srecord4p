<?php
/**
 * sRecord - PHP5 Library for Salesforce SObject like active record.
 *
 * @package     sRecord
 * @author      Mitsutaka Sato <miztaka@gmail.com>
 * @license     Apache License, Version 2.0 http://www.apache.org/licenses/LICENSE-2.0
 */
class Srecord_ActiveRecord
{
    const STATE_SUCCESS = 1;
    const STATE_FAIL    = 2;
    const STATE_NOTEXECUTED = 3;
    
    /**
     * name of sobject
     * must be overrided by subclass
     * @var string
     */
    public static $_SONAME = '';
    protected $__soname;

    /**
     * parent-child relationships definition
     * @var array
     */
    public static $_childRelationships = array();
    protected $__childRelationships = array();
    
    /**
     * child-parent relationships definition
     * @var unknown_type
     */
    public static $_parentRelationships = array();
    protected $__parentRelationships = array();
    
    protected $_children = array();
    protected $_parents = array();
    protected $_criteria = array();
    protected $_bindvalue = array();
    protected $_afterwhere = array();
    protected $_selectColumns = array();
    protected $_fieldsToNull = array();
    
    /**
     * constructor
     */
    public function __construct()
    {
        $this->__soname = $this->_getEntityConfig(get_class($this), '_SONAME');
        $this->__childRelationships = $this->_getEntityConfig(get_class($this), '_childRelationships');
        $this->__parentRelationships = $this->_getEntityConfig(get_class($this), '_parentRelationships');
        return;
    }

    public function getState() {
        return $this->_state;
    }
    public function setState($state) {
        $this->_state = $state;
    }
    protected $_state = self::STATE_NOTEXECUTED;
    
    /**
     * get errors object of last insert, update..
     */
    public function getErrors() {
        return $this->_errors;
    }
    public function setErrors($errors) {
        $this->_errors = $errors;
    }
    protected $_errors;
    
    /**
     * get metadata of SObject
     */
    public function getMeta()
    {
        $meta = $this->_getEntityConfig(get_class($this), '__meta');
        if (! is_object($meta)) {
            $metaObj = unserialize(base64_decode($meta));
            $this->_setEntityConfig(get_class($this), '__meta', $metaObj);
            return $metaObj;
        }
        return $meta;
    }

    /**
     * set/get dryrun flag.
     * @param boolean $bool
     * @return boolean
     */
    public function dryrun($bool=NULL)
    {
        if ($bool != NULL) {
            $this->_dryrun = $bool;
        }
        return $this->_dryrun;
    }
    protected $_dryrun = FALSE;
    
    /**
     * check if the field is updateable
     * @param string $f
     * @return bool
     */
    public  function isUpdateableField($f)
    {
        return $this->getMeta()->fields[$f]->updateable == 1;
    }
    
    /**
     * check if the field is createable
     * @param string $f
     * @return bool
     */
    protected function isCreateableField($f)
    {
        return $this->getMeta()->fields[$f]->createable == 1;
    }

    /**
     * set child-parent relationship
     * @param string $name relationship name
     * @param string $columns columns to select.(if null, all columns is selected.)   
     * @return Srecord_ActiveRecord
     */
    public function join($name, $columns=NULL)
    {
        $config = $this->__parentRelationships;
        $nest = explode('.', $name);
        $lastname = array_pop($nest);
        while ($n = array_shift($nest)) {
            $obj = $config[$n];
            $config = $this->_getEntityConfig("Sobjectdef_{$obj}", "_parentRelationships");
            if (! is_array($config)) {
                throw new Srecord_ActiveRecordException("parentRelationships not found.");
            }
        }
        if (! isset($config[$lastname])) {
            throw new Srecord_ActiveRecordException("parentRelationships not found.");
        }
        $this->_parents[$name] = $config[$lastname];
        
        if ($columns != NULL) {
            $this->_selectColumns[$name] = $columns;
        }
        return $this;
    }
    
    /**
     * set parent-child relationship
     * @param string $name relationship name
     * @param string $columns columns to select.(if null, all columns is selected.)
     * @param string $filter child filter clause. can use place holder '?'.
     * @param mixed $bindvlaue bindvalue of filter.
     * @return Srecord_ActiveRecord
     */
    public function child()
    {
        // manipulate args
        $name = NULL;
        $columns = NULL;
        $filter = NULL;
        $bindvalue = NULL;
        
        $args_num = func_num_args();
        if ($args_num < 1) {
            throw new SRecord_ActiveRecordException("args too short.");
        }
        $args_ar = func_get_args();
        $name = array_shift($args_ar);
        if ($args_num >= 2) {
            $columns = array_shift($args_ar);
        }
        if ($args_num >= 3) {
            $filter = array_shift($args_ar);
        }
        if ($args_num >= 4) {
            $bindvalue = $args_ar;
        }
        
        // filter
        if ($filter != NULL) {
            $filter = $this->_placeValue($filter, $bindvalue);
        }

        // set child relationship
        $config = $this->__childRelationships;
        if (! is_array($config) || ! isset($config[$name])) {
            throw new Srecord_ActiveRecordException("childRelationships not found.");
        }
        $this->_children[$name] = array(
            'name' => $config[$name],
            'filter' => $filter
        );

        if ($columns != NULL) {
            $this->_selectColumns[$name] = $columns;
        }
        return $this;
    }

    /**
     * set where clause
     * 
     * @param string where clause. use ? as a placeholder.  
     * @param mixed values to set to placeholders.
     * @return Sobject_ActiveRecord
     */
    public function where()
    {
        $args_num = func_num_args();
        if ($args_num < 1) {
            throw new SRecord_ActiveRecordException("args too short.");
        }
        
        $args_ar = func_get_args();
        $where_clause = array_shift($args_ar);
        if (! strlen($where_clause)) {
            return $this;
        }
        
        // check num of params.
        if (@is_array($args_ar[0])) {
            $args_ar = $args_ar[0];
        }
        
        $this->_checkPlaceHolder($where_clause, $args_ar);
        $this->_criteria[] = array(
            'str' => $where_clause,
            'val' => $args_ar
        );
        
        return $this;
    }
    
    /**
     * set where clause. Equals
     * <pre>
     * if $notnullonly is true, this clause are set only if $value is not null.
     * if $notnullonly is false and $value is empty, `property = null` is set. 
     * </pre>
     *
     * @param string $property property name
     * @param mixed $value value of property
     * @param boolean $notnullonly flag of treat null
     * @return Srecord_ActiveRecord
     */
    public function eq($property, $value, $notnullonly=true)
    {
        if ($value === NULL || $value === "") {
            if (! $notnullonly) {
                $this->where("{$property} = null");
            }
        } elseif (is_bool($value)) {
            $this->where($value ? "{$property} = TRUE" : "{$property} = FALSE");
        } else {
            $this->where("{$property} = ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Not equals.
     * <pre>
     * if $notnullonly is true, this clause are set only if $value is not null.
     * if $notnullonly is false and $value is empty, `property != null` is set. 
     * </pre>
     *
     * @param string $property property name
     * @param mixed $value value of property
     * @param boolean $notnullonly flag of treat null
     * @return Srecord_ActiveRecord
     */
    public function ne($property, $value, $notnullonly=true)
    {
        if ($value === NULL || $value === "") {
            if (! $notnullonly) {
                $this->where("{$property} != null");
            }
        } elseif (is_bool($value)) {
            $this->where($value ? "{$property} != TRUE" : "{$property} != FALSE");
        } else {
            $this->where("{$property} != ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Less than.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function lt($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} < ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Greater than.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function gt($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} > ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Less equal.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function le($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} <= ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Greater equal.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function ge($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} >= ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. IN.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function in($property, $value)
    {
        if (! is_array($value) || count($value) == 0) {
            // do nothing
        } else {
            $num = count($value);
            $placeholder = "";
            for($i=0; $i<$num; $i++) {
                $placeholder .= "?,";
            }
            $placeholder = substr($placeholder, 0, -1);
            
            $this->where("{$property} IN ({$placeholder})", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. NOT IN.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function notin($property, $value)
    {
        if (! is_array($value) || count($value) == 0) {
            // do nothing
        } else {
            $num = count($value);
            $placeholder = "";
            for($i=0; $i<$num; $i++) {
                $placeholder .= "?,";
            }
            $placeholder = substr($placeholder, 0, -1);
            
            $this->where("{$property} NOT IN ({$placeholder})", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Like.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function like($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} LIKE ?", $value);
        }
        return $this;
    }
    
    /**
     * set where clause. Like 'foo%'.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function starts($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} LIKE ?", $value.'%');
        }
        return $this;
    }

    /**
     * set where clause. Like '%foo'.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function ends($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} LIKE ?", '%'.$value);
        }
        return $this;
    }
    
    /**
     * set where clause. Like '%foo%'.
     * <pre>
     * This clause is set only if $value is not empty.
     * </pre>
     * 
     * @param string $property property name
     * @param mixed $value value of property
     * @return Srecord_ActiveRecord
     */
    public function contains($property, $value)
    {
        if ($value === NULL || $value === "") {
            // do nothing
        } else {
            $this->where("{$property} LIKE ?", '%'.$value.'%');
        }
        return $this;
    }
    
    /**
     * set includes clause for multiple select.
     * ex.) "$sobject->includes('cst__c', 'AAA;BBB', 'CCC')" for "cst__c includes ('AAA;BBB', 'CCC')" 
     */
    public function includes()
    {
        $args_ar = func_get_args();
        return $this->_includesExcludes('includes', $args_ar);
    }
    
    /**
     * set excludes clause for multiple select.
     * ex.) "$sobject->excludes('cst__c', 'AAA;BBB', 'CCC')" for "cst__c excludes ('AAA;BBB', 'CCC')" 
     */
    public function excludes()
    {
        $args_ar = func_get_args();
        return $this->_includesExcludes('excludes', $args_ar);
    }

    /**
     * execute includes / excludes
     * @param string $str
     * @param array $args_ar
     */
    protected function _includesExcludes($str, $args_ar)
    {
        if (! is_array($args_ar) || count($args_ar) < 2) {
            throw new SRecord_ActiveRecordException("args too short.");
        }
        $property = array_shift($args_ar);
        if (! strlen($property)) {
            return $this;
        }
        
        // check num of params.
        if (@is_array($args_ar[0])) {
            $args_ar = $args_ar[0];
        }
        array_walk($args_ar, create_function('&$arr','$arr=addslashes($arr);'));
        $param = "('".implode("','", $args_ar)."')";
        $where = "{$property} {$str}".$param;
            
        $this->_criteria[] = array('str' => $where, 'val' => array());
        return $this;
    }
    
    /**
     * set order by clause.
     *
     * @param string $clause order by caluse
     * @return Srecord_ActiveRecord
     */
    public function order($clause)
    {
        $this->_afterwhere['order'] = $clause;
        return $this;
    }
    
    /**
     * set limit clause.
     *
     * @param int $num limit
     * @return Srecord_ActiveRecord
     */
    public function limit($num)
    {
        if (is_numeric($num)) {
            $this->_afterwhere['limit'] = $num;
        }
        return $this;
    }
    
    /**
     * set offset clause.
     *
     * @param int $num offset
     * @return Srecord_ActiveRecord
     */
    public function offset($num)
    {
        if (is_numeric($num)) {
            $this->_afterwhere['offset'] = $num;
        }
        return $this;
    }
    
    /**
     * execute select query.
     * 
     * @param $columns string columns to select. concats with comma (ex. 'Name,Age,Address')
     * @return array of SObject
     */
    public function select($columns=NULL)
    {
        if ($columns != NULL) {
            $this->_selectColumns['__this'] = $columns;
        }
        $this->_bindvalue = array();
        
        $sql = $this->_buildSelectSql();
        if ($this->dryrun()) {
            $this->resetInstance();
            return $sql;
        }
        
        // connect to salesforce
        $client = Srecord_Schema::getClient();
        $res = $client->query($sql);
        $item_list = array();
        foreach ($res->records as $row) {
            $item = clone($this);
            $item->_buildResultSet($row);
            $item->resetInstance();
            $item_list[] = $item;
        }
        
        $this->resetInstance();
        return $item_list;
    }
    
    /**
     * get one object.
     * if too many rows are selected, throw exception.
     *
     * @param $id string Id value to select. set null if you don't use Id for query.
     * @param $columns string columns to select. concats with comma (ex. 'Name,Age,Address')
     * @return Srecord_ActiveRecord
     */
    public function find($id=NULL, $columns=NULL)
    {
        if ($id != NULL) {
            $this->eq('Id', $id, FALSE);
        }
        $result = $this->select($columns);
        if ($this->dryrun()) {
            return $result;
        }
        if (count($result) > 1) {
            throw new Srecord_ActiveRecordException('too many rows.');
        }
        if (count($result) == 0) {
            return NULL;
        }
        return $result[0];
    }
    
    /**
     * get record count with results of count() query.
     * 
     * @return int record count
     */
    public function count()
    {
        $this->_bindvalue = array();
        $select_str = "SELECT count()";
        $from_str = $this->_buildFromClause();
        $where_str = $this->_buildWhereClause();
        $other_str = $this->_buildAfterWhereClause();
        
        $sql = implode(" ", array($select_str,$from_str,$where_str,$other_str));
        if ($this->dryrun()) {
            return $sql;
        }
        
        // connect to salesforce
        $client = Srecord_Schema::getClient();
        $res = @$client->query($sql); // E_Notice
        return $res->size;
    }

    /**
     * insert one record.
     * if you want to insert multiple records, use Srecord_Schema::createAll() instead.
     * Id is set if success.
     * you can get errors with getError() when error occurs.
     * 
     * @return bool or returns SObject if dryrun
     */
    public function insert()
    {
        if (function_exists('srecord_activerecord_before_insert')) {
            srecord_activerecord_before_insert($this);
        }

        $so = $this->_convert2SObject();
        foreach ($so->fields as $n => $v) {
            if (! $this->isCreateableField($n)) {
                unset($so->fields[$n]);
            }
        }
        if ($this->dryrun()) {
            return $so;
        }
        
        // connect to salesforce.
        $client = Srecord_Schema::getClient();
        $res = $client->create(array($so));
        if ($res->success == 1) {
            $this->Id = $res->id;
            $this->_state = self::STATE_SUCCESS;
            $this->_errors = NULL;
            return TRUE;
        }
        
        // error.
        $this->_errors = $res->errors;
        $this->_state = self::STATE_FAIL;
        return FALSE;
    }
    
    /**
     * set fieldsToNull
     * @param mixed $fields array or field names separated by comma 
     */
    public function fieldnull($fields)
    {
        if (! is_array($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ($fields as $f) {
            $f = trim($f);
            if ($this->isUpdateableField($f)) {
                $this->_fieldsToNull[] = $f;
            }
        }
        return $this;
    }
    
    /**
     * update record by id.
     * if you want to update multiple records, use Srecord_Schema::updateAll() instead.
     * empty fields are ignored.
     * set $fieldsToNull if you want set NULL to some fields.
     *
     * @param array $fieldsToNull fields names to set NULL.
     * @return bool
     */
    public function update($fieldsToNull=NULL)
    {
        if (function_exists('srecord_activerecord_before_update')) {
            srecord_activerecord_before_update($this);
        }

        if ($this->Id === NULL || ! strlen($this->Id)) {
            throw new Srecord_ActiveRecordException("id not set.");
        }
        
        if (is_array($fieldsToNull)) {
            foreach ($fieldsToNull as $one) {
                if ($this->isUpdateableField($one)) {
                    $this->_fieldsToNull[] = $one;
                }
            }
        }
        
        $so = $this->_convert2SObject();
        if (count($this->_fieldsToNull) > 0) {
            $so->fieldsToNull = array_unique($this->_fieldsToNull);
        }
        foreach ($so->fields as $n => $v) {
            if (! $this->isUpdateableField($n)) {
                unset($so->fields[$n]);
            }
        }
        if ($this->dryrun()) {
            return $so;
        }
        
        // connect to salesforce.
        $client = Srecord_Schema::getClient();
        $res = $client->update(array($so));
        if ($res->success == 1) {
            $this->_state = self::STATE_SUCCESS;
            $this->_errors = NULL;
            return TRUE;
        }
        
        // error.
        $this->_errors = $res->errors;
        $this->_state = self::STATE_FAIL;
        return FALSE;
    }
    
    /**
     * update record by id.
     * empty fields are set to NULL.
     *
     * @param array $fieldsToNull fields names to set NULL.
     * @return bool
     */
    public function updateEntity()
    {
        foreach ($this->_getColumns(get_class($this)) as $col) {
            if ($this->_null($this->$col) === NULL && $this->isUpdateableField($col)) {
                $this->_fieldsToNull[] = $col;
            }
        }
        return $this->update();
    }
    
    /**
     * update or insert one record.
     * if you want to upsert multiple records, use Srecord_Schema::upsertAll() instead.
     * Id is set if success.
     * you can get errors with getError() when error occurs.
     * 
     * @param string $externalIDFieldName
     * @return bool
     */    
    public function upsert($externalIDFieldName)
    {
        if (function_exists('srecord_activerecord_before_upsert')) {
            srecord_activerecord_before_upsert($this);
        }
        
        $so = $this->_convert2SObject();
        if (count($this->_fieldsToNull) > 0) {
            $so->fieldsToNull = array_unique($this->_fieldsToNull);
        }
        if ($this->dryrun()) {
            return $so;
        }
        
        // connect to salesforce.
        $client = Srecord_Schema::getClient();
        $res = $client->upsert($externalIDFieldName, array($so));
        if ($res->success == 1) {
            if (isset($res->id)) {
                $this->Id = $res->id;
            }
            $this->_state = self::STATE_SUCCESS;
            $this->_errors = NULL;
            return TRUE;
        }
        
        // error.
        $this->_errors = $res->errors;
        $this->_state = self::STATE_FAIL;
        return FALSE;
    }
    
    /**
     * delete record of specified id.
     * Id must be set as a parameter or Id field.
     * if you want to delete multiple records, use Srecord_Schema::deleteAll() instead.
     * 
     * @param string $id
     * @return bool
     */
    public function delete($id = NULL)
    {
        if ($id == NULL) {
            $id = $this->Id;
        }
        if ($this->_null($id) == NULL) {
            throw new SRecord_ActiveRecordException('Id is not specified.');
        }
        
        // connect to salesforce.
        $client = Srecord_Schema::getClient();
        $res = $client->delete(array($id));
        if ($res->success == 1) {
            $this->_state = self::STATE_SUCCESS;
            $this->_errors = NULL;
            return TRUE;
        }
        
        // error.
        $this->_state = self::STATE_FAIL;
        $this->_errors = $res->errors;
        return FALSE;
    }
    
    /**
     * reset metadata of this instance.
     */
    public function resetInstance()
    {
        $this->_parents = array();
        $this->_children = array();
        $this->_criteria = array();
        $this->_bindvalue = array();
        $this->_afterwhere = array();
        $this->_selectColumns = array();
        $this->_fieldsToNull = array();
        $this->_errors = NULL;
        $this->_state = self::STATE_NOTEXECUTED;
        return;
    }
    
    /**
     * copy object property value to this instance.
     *
     * @param mixed $obj object or array (fieldName => value)
     * @param array $colmap mapping of ('field name of this instance' => 'field name of $obj')
     */
    public function copyToThis($obj, $colmap=null)
    {
        if ($colmap == null) {
            $colmap = array();
        }
        if (is_array($obj)) {
            $obj = (object)$obj;
        }
        
        $columns = $this->_getColumns(get_class($this));
        foreach($columns as $column) {
            $prop = array_key_exists($column, $colmap) ? $colmap[$column] : $column;
            if (isset($obj->$prop)) {
                $this->$column = $obj->$prop;
            }
        }
        return;
    }
    
    /**
     * copy object property value of this instance to obj.
     *
     * @param Object $obj
     * @param array $colmap mapping of ('field name of this instance' => 'field name of $obj')
     */
    public function copyFromThis($obj, $colmap=null)
    {
        if ($colmap == null) {
            $colmap = array();
        }
        
        $columns = $this->_getColumns(get_class($this));
        foreach($columns as $column) {
            if (@isset($this->$column)) {
                $prop = array_key_exists($column, $colmap) ? $colmap[$column] : $column;
                $obj->$prop = $this->$column;
            }
        }
        return;
    }
    
    /**
     * get xsd:datetime expression for now
     * @return string 
     */
    public function now()
    {
        return date("c");
    }
    
    /**
     * build select query
     *
     * @return String
     */
    protected function _buildSelectSql()
    {
        $select_str = $this->_buildSelectClause();
        $from_str = $this->_buildFromClause();
        $where_str = $this->_buildWhereClause();
        $other_str = $this->_buildAfterWhereClause();
        return implode(" ", array($select_str, $from_str, $where_str, $other_str));
    }
    
    /**
     * build select clause.
     *
     * @return String SELECT clause
     */
    protected function _buildSelectClause()
    {
        $buff = array();
        $base = $this->__soname;
        
        // columns of this object
        $columns = $this->getSelectColumnsAsArray('__this');
        if ($columns == NULL) {
            $columns = $this->_getColumns(get_class($this));
        }
        foreach($columns as $col) {
            $buff[] = "{$base}.{$col}";
        }
        
        // columns of parents
        if (count($this->_parents)) {
            foreach ($this->_parents as $name => $objname) {
                $columns = $this->getSelectColumnsAsArray($name);
                if ($columns == NULL) {
                    $clsname = 'Sobjectdef_'.$objname;
                    $columns = $this->_getColumns($clsname);
                }
                foreach($columns as $col) {
                    $buff[] = "{$base}.{$name}.{$col}";
                }
            }
        }
        
        // columns of child
        if (count($this->_children)) {
            foreach ($this->_children as $name => $def) {
                $childClause = $this->_buildChildSelectClause($name, $def);
                $buff[] = $childClause;
            }
        }
        
        return "SELECT ". implode(', ', $buff);
    }

    /**
     * build from clause.
     * @return string
     */
    protected function _buildFromClause()
    {
        $base = $this->__soname;
        return "FROM {$base}";
    }
    
    /**
     * build where clause.
     * @return string
     */
    protected function _buildWhereClause()
    {
        $buff = array();
        // criteria
        if (count($this->_criteria)) {
            foreach($this->_criteria as $cri) {
                $str = $cri['str'];
                $val = $cri['val'];
                $buff[] = $str;
                if ($val != null) {
                    if (is_array($val)) {
                        foreach($val as $item) {
                            array_push($this->_bindvalue, $item);
                        }
                    }
                }
            }
        }
        
        if (count($buff)) {
            return $this->_placeValue("WHERE (". implode(") AND (", $buff) .")", $this->_bindvalue);
        }
        return "";
    }

    /**
     * build order by, limit, offset clause
     * @return string
     */
    protected function _buildAfterWhereClause()
    {
        $buff = array();
        if (count($this->_afterwhere)) {
            if (isset($this->_afterwhere['order'])) {
                $buff[] = "ORDER BY {$this->_afterwhere['order']}";
            }
            if (isset($this->_afterwhere['limit'])) {
                $buff[] = "LIMIT {$this->_afterwhere['limit']}";
            }
            if (isset($this->_afterwhere['offset'])) {
                $buff[] = "OFFSET {$this->_afterwhere['offset']}";
            }
        }
        
        if (count($buff)) {
            return implode(' ', $buff);
        }
        return "";
    }
    
    /**
     * set record value.
     * @param SObject $row
     */
    protected function _buildResultSet($row)
    {
        $parentNames = array();
        foreach ($this->_parents as $name => $val) {
            if (strpos($name, '.') === FALSE) {
                $parentNames[] = $name;
            }
        }
        
        // fields
        if (isset($row->Id)) {
            $this->Id = $row->Id;
        }
        foreach (get_object_vars($row->fields) as $n => $v) {
            if (is_int($n) && is_object($v)) {
                // parent
                $col = $parentNames[$n-1];
                $this->$col = $this->getParentInstance($col);
                $this->$col->_buildResultSet($v);
                continue;
            }
            // fields
            switch ($this->getMeta()->fields[$n]->type) {
                case 'boolean':
                    if ($v == 'true') {
                        $this->$n = TRUE;
                    } elseif ($v == 'false') {
                        $this->$n = FALSE;
                    } else {
                        $this->$n = NULL;
                    }
                    break;
                default:
                    $this->$n = $v;
            }
        }
        
        // children
        if (isset($row->queryResult)) {
            $childNames = array_keys($this->_children);
            foreach ($row->queryResult as $n => $result) {
                $relname = $childNames[$n];
                $this->$relname = array();
                foreach ($result->records as $sobj) {
                    $obj = $this->getChildInstance($relname);
                    $obj->_buildResultSet($sobj);
                    array_push($this->$relname, $obj);
                }
            }
        }
        return;
    }
    
    /**
     * check occurence of placeholder.
     * 
     * @param string $condition
     * @param array $params
     */
    protected function _checkPlaceHolder($condition, $params)
    {
        $param_num = count($params);
        $holder_num = substr_count($condition, '?');
        if ($param_num != $holder_num) {
            throw new Srecord_ActiveRecordException("The num of placeholder is wrong.");
        }
        return;
    }
    
    /**
     * get static property value
     * @param string $clsname
     * @param string $property
     * @return mixed
     */
    protected function _getEntityConfig($clsname, $property)
    {
        $ref = new ReflectionClass($clsname);
        return $ref->getStaticPropertyValue($property);
    }
    
    /**
     * set value to static property. 
     * @param string $clsname
     * @param string $property
     * @param mixed $value
     */
    protected function _setEntityConfig($clsname, $property, $value)
    {
        $ref = new ReflectionClass($clsname);
        $ref->setStaticPropertyValue($property, $value);
    }

    /**
     * get field value map.
     *
     * @param Srecord_ActiveRecord $obj
     * @param bool $excludeNull
     * @return array
     */
    protected function _convertObject2Array($obj, $excludeNull=false)
    {
        $columns = $this->_getColumns(get_class($obj));
        $result = array();
        foreach ($columns as $name) {
            $val = $obj->$name;
            if (@is_array($val)) {
                $result[$name] = serialize($val);
            } else if (! $excludeNull || $this->_null($val) !== NULL) {
                $result[$name] = $this->_xsdvalue($val);
            }
        }
        return $result;
    }

    /**
     * convert Srecord_ActiveRecord to SObject
     * @param bool $excludeNull
     * @param Srecord_ActiveRecord $obj
     */
    protected function _convert2SObject($excludeNull=TRUE, $obj=NULL)
    {
        if ($obj == NULL) {
            $obj = $this;
        }
        $fields = $this->_convertObject2Array($obj, $excludeNull);
        $so = new SObject();
        $so->type = $obj->__soname;
        if (isset($fields['Id'])) {
            $so->Id = $fields['Id'];
            unset($fields['Id']);
        }
        $so->fields = $fields;
        return $so;
    }
    
    /**
     * get list of column names.
     *
     * @param string $clsname
     * @return array
     */ 
    protected function _getColumns($clsname)
    {
        $parentConfig = $this->_getEntityConfig($clsname, "_parentRelationships");
        $parentNames = is_array($parentConfig) ? array_keys($parentConfig) : array();
        $childConfig = $this->_getEntityConfig($clsname, "_childRelationships");
        $childNames = is_array($childConfig) ? array_keys($childConfig) : array();
        
        $result = array();
        $vars = get_class_vars($clsname);
        foreach($vars as $name => $value) {
            // excludes property which name starts with '_'
            if (substr($name, 0, 1) === '_') {
                continue;
            }
            // excludes child and parent relationship property
            if (in_array($name, $parentNames) || in_array($name, $childNames)) {
                continue;
            }
            array_push($result, $name);
        }
        return $result;
    }

    /**
     * if $str is empty, returns NULL.
     * @param mixed $str
     * @return mixed
     */
    protected function _null($str)
    {
        if (is_bool($str)) {
            return $str;
        }
        return $str !== NULL && strlen($str) > 0 ? $str : NULL;
    }

    /**
     * @param string $name
     * @param array $def
     */
    protected function _buildChildSelectClause($name, $def)
    {
        $objname = $def['name'];
        $filter = $def['filter'];
        $columns = $this->getSelectColumnsAsArray($name);
        if ($columns == NULL) {
            $columns = $this->_getColumns("Sobjectdef_{$objname}");
        }
        $q = "SELECT ". implode(", ", $columns);
        $q .= " FROM {$name}";
        if ($filter) {
            $q .= " WHERE {$filter}";
        }
        return "({$q})";
    }
    
    /**
     * set value to placeholder
     * @param string $query
     * @param array $values
     * @return string
     */
    protected function _placeValue($query, $values)
    {
        foreach ($values as $v) {
            $escaped = addslashes($v);
            $part = explode('?',$query, 2);
            $query = $part[0]."'".$escaped."'".$part[1];
        }
        return $query;
    }

    /**
     * get select columns
     * @param string $name RelationshipName
     */
    protected function getSelectColumnsAsArray($name)
    {
        if (isset($this->_selectColumns[$name])) {
            $columns = explode(',', $this->_selectColumns[$name]);
            array_walk($columns, create_function('&$arr','$arr=trim($arr);'));
            return $columns;
        }
        return NULL;
    }

    /**
     * get parent instance.
     * @param $relname
     */
    protected function getParentInstance($relname)
    {
        $objname = $this->__parentRelationships[$relname];
        if (! $objname) {
            throw new SRecord_ActiveRecordException("cannot find parent relationship for $relname");
        }
        $objname = "Sobject_{$objname}";
        $obj = new $objname();
        
        foreach ($this->_parents as $name => $value) {
            $len = strlen($relname);
            if ($name != $relname && substr($name, 0, $len) == $relname) {
                $cname = substr($name, $len+1);
                $obj->join($cname, 'Id');
            }
        }
        return $obj; 
    }
    
    /**
     * get instance of child from relationship name.
     * @param string $relname
     */
    protected function getChildInstance($relname)
    {
        $objname = $this->__childRelationships[$relname];
        $objname = "Sobject_{$objname}";
        $obj = new $objname();
        return $obj;
    }
    
    protected function _xsdvalue($val)
    {
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }
        return $val;
    }
    
}

/**
 * exception class for Srecord_ActiveRecord.
 * 
 * @package srecord
 * 
 */
class SRecord_ActiveRecordException extends Exception
{
    /**
     * constructor
     * @param string $message
     */
    function __construct($message)
    {
        return parent::__construct($message);
    }
}

?>