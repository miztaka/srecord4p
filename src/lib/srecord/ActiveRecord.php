<?php
/**
 * Teeple2 - PHP5 Web Application Framework inspired by Seasar2
 *
 * PHP versions 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @package     teeple
 * @author      Mitsutaka Sato <miztaka@gmail.com>
 * @license     http://www.php.net/license/3_0.txt  PHP License 3.0
 */

/**
 * ActiveRecordクラスです。
 * 
 * <pre>
 * [Entityクラスの作成]
 * このクラスを継承して各テーブルのレコードを表すクラスを作成します。
 * ファイルの配置場所は、ENTITY_DIR で定義されたディレクトリです。
 * ファイル名は、`table名`.class.php, クラス名は Entity_`table名` とします。
 * 
 * 各テーブルの以下のプロパティを定義する必要があります。
 *   // 使用するデータソース名(Teepleフレームワークを使用しない場合は不要。)
 * 　public static $_DATASOURCE = "";
 *   // テーブル名称
 *   public static $_TABLENAME = "";
 *   // プライマリキーのカラム名(配列)
 *   public static $_PK = array();
 *   // プライマリキー以外のカラム名(配列)
 *   public static $_COLUMNS = array();
 *   // PKが単一で、AUTOINCREMENT等の場合にTRUEをセット
 *   // ※シーケンスには対応できていません。
 *   public static $_AUTO = TRUE;
 *   // joinするテーブルの定義
 *   public static $_JOINCONFIG = array();
 * </pre>
 * 
 * @package teeple
 * 
 */
class Srecord_ActiveRecord
{
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
    
    /**
     * set dryrun flag.
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
     * return new Instance of this SObjects
     * @return Srecord_ActiveRecord
     */
    public function newInstance()
    {
        $class_name = get_class($this);
        $obj = new $class_name();
        return $obj;
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
     * TODO child filter
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
        $ltem_list = array();
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
     * @param $columns string columns to select. concats with comma (ex. 'Name,Age,Address')
     * @return Srecord_ActiveRecord
     */
    public function find($columns=NULL) {
        
        $result = $this->select($columns);
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
     * レコードを登録します。
     * 
     * <pre>
     * constraintとrowに設定されている値で、レコードを１つ作成します。
     * PKが単一カラムで、$_auto がTRUEに設定されている場合で、INSERT値にPKが設定されていなかった場合は、
     * INSERT後、インスタンスにPK値をセットします。
     * </pre>
     * 
     * @return bool 登録できたかどうか。
     */
    public function insert()
    {
        if (function_exists('teeple_activerecord_before_insert')) {
            teeple_activerecord_before_insert($this);
        }

        $this->_bindvalue = array();
        $row = $this->_convertObject2Array($this, true);
        $this->_log->info("insert ". $this->_tablename. ": \n".@var_export($row,TRUE));
        
        $binding_params = $this->_makeBindingParams($row);
        $sql = "INSERT INTO `". $this->_tablename ."` (" .
            implode(', ', array_keys($row)) . ') VALUES(' .
            implode(', ', array_keys($binding_params)) . ');';

        $this->_log->debug("sql: $sql");
        $sth = $this->_pdo->prepare($sql);
        if(! $sth) { 
            $err = $this->_pdo->errorInfo();
            throw new TeepleActiveRecordException("pdo prepare failed: {$err[2]}:{$sql}");
        }

        if(! $sth->execute($binding_params)) {
            $err = $sth->errorInfo();
            throw new TeepleActiveRecordException("pdo execute failed: {$err[2]}:{$sql}");
        }

        if (count($this->_pk) == 1 && $this->_auto && (! isset($this->{$this->_pk[0]}) || $this->{$this->_pk[0]} == "")) {
            $this->{$this->_pk[0]} = $this->_pdo->lastInsertId();
            $this->_log->info("AUTO: ". $this->_pk[0] ." = {$this->{$this->_pk[0]}}");
        }
        
        $this->_log->info("insert ". $this->_tablename .": result=(".$sth->rowCount().")");
        
        $this->resetInstance();
        return $sth->rowCount() > 0;
    }
    
    /**
     * レコードの更新を実行します。
     * 
     * <pre>
     * rowにセットされているPKで更新を行ないます。
     * </pre>
     * 
     * @return int 変更のあったレコード数
     */
    public function update()
    {
        if (function_exists('teeple_activerecord_before_update')) {
            teeple_activerecord_before_update($this);
        }
        $this->_bindvalue = array();
        if (! $this->isSetPk()) {
            throw new TeepleActiveRecordException("primary key not set.");
        }
        
        $values = $this->_convertObject2Array($this, false);
        $this->_log->info("update ". $this->_tablename .": \n".@var_export($values,TRUE));
        $pks = array();
        // primary key は 更新しない。
        foreach ($this->_pk as $pk) {
            unset($values[$pk]);
            $this->setConstraint($pk, $this->$pk);
        }
        if (! count($values)) {
            throw new TeepleActiveRecordException("no columns to update.");
        }

        $sql = "UPDATE `". $this->_tablename ."` ".
            $this->_buildSetClause($values).
            " ". $this->_buildConstraintClause(false);
        
        $this->_log->debug("update ". $this->_tablename .": {$sql}");
        $this->_log->debug(@var_export($this->_bindvalue,TRUE));
        
        $sth = $this->_pdo->prepare($sql);
        if (! $sth) {
            $err = $this->_pdo->errorInfo();
            throw new TeepleActiveRecordException("pdo prepare failed: {$err[2]}:{$sql}");
        }
        if (! $sth->execute($this->_bindvalue)) {
            $err = $sth->errorInfo();
            throw new TeepleActiveRecordException("pdo execute failed: {$err[2]}:{$sql}");
        }
        
        $this->_log->info("update ". $this->_tablename .": result=(".$sth->rowCount().")");
        
        if ($sth->rowCount() != 1) {
            throw new TeepleActiveRecordException('更新に失敗しました。他の処理と重なった可能性があります。');
        }
        
        $this->resetInstance();
        return $sth->rowCount();
    }
    
    /**
     * 条件に該当するレコードを全て更新します。
     * 
     * <pre>
     * セットされているconstraints及びcriteriaに
     * 該当するレコードを全て更新します。
     * </pre>
     * 
     * @return int 更新件数
     */
    public function updateAll()
    {
        if (function_exists('teeple_activerecord_before_updateAll')) {
            teeple_activerecord_before_updateAll($this);
        }
         
        $this->_bindvalue = array();
        
        $row = $this->_convertObject2Array($this, true);
        $sql = "UPDATE `". $this->_tablename ."` ".
            $this->_buildSetClause($row).
            " ". $this->_buildWhereClause(false);
        
        $this->_log->info("updateAll ". $this->_tablename .": $sql");
        $this->_log->info("param is: \n". @var_export($this->_bindvalue, TRUE));
        $sth = $this->_pdo->prepare($sql);
        
        if (! $sth) {
            $err = $this->_pdo->errorInfo();
            throw new TeepleActiveRecordException("pdo prepare failed: {$err[2]}:{$sql}");
        }
        if (! $sth->execute($this->_bindvalue)) {
            $err = $sth->errorInfo();
            throw new TeepleActiveRecordException("pdo execute failed: {$err[2]}:{$sql}");
        }
        
        $this->_log->info("updateAll ". $this->_tablename .": result=(".$sth->rowCount().")");
        
        $this->resetInstance();
        return $sth->rowCount();        
    }
    
    /**
     * 指定されたレコードを削除します。 
     * 
     * <pre>
     * constraintまたは $idパラメータで指定されたPKに該当するレコードを削除します。
     * $id がハッシュでないときは、id列の値とみなしてDELETEします。
     * $id がハッシュのときは、key値をPKのカラム名とみなしてDELETEします。
     * </pre>
     * 
     * @param mixed $id PKの値
     * @return bool 実行結果
     */
    public function delete($id = null)
    {
        $this->_bindvalue = array();
        
        // rowにセットされているPKがあればconstraintに
        foreach ($this->_pk as $pk) {
            if (isset($this->$pk) && $this->getConstraint($pk) == "") {
                $this->setConstraint($pk, $this->$pk);
            }
        }
        
        if ($id != null) {
            if (! is_array($id)) {
                if (count($this->_pk) != 1) {
                    throw new TeepleActiveRecordException("pk is not single.");
                }
                $this->setConstraint($this->_pk[0], $id);
            } else {
                foreach($id as $col => $val) {
                    $this->setConstraint($col, $val);
                }
            }
        }
        
        $sql = "DELETE FROM `". $this->_tablename ."` ".
            $this->_buildConstraintClause(false);

        $this->_log->info("delete ". $this->_tablename .": $sql");
        $this->_log->debug("param is: \n". @var_export($this->_bindvalue, TRUE));
        
        $sth = $this->_pdo->prepare($sql);
        if (! $sth) {
            $err = $this->_pdo->errorInfo();
            throw new TeepleActiveRecordException("pdo prepare failed: {$err[2]}:{$sql}");
        }
        if (! $sth->execute($this->_bindvalue)) {
            $err = $sth->errorInfo();
            throw new TeepleActiveRecordException("pdo execute failed: {$err[2]}:{$sql}");
        }
        
        $this->_log->info("delete ". $this->_tablename .": result=(".$sth->rowCount().")");

        $props = array_keys(get_class_vars(get_class($this)));
        foreach ($props as $key) {
            $this->$key = NULL;
        }
        
        $this->resetInstance();
        return $sth->rowCount() > 0;
    }
    
    /**
     * 条件に該当するレコードを全て削除します。
     * 
     * <pre>
     * セットされているconstraints及びcriteriaに
     * 該当するレコードを全て削除します。
     * </pre>
     * 
     * @return int 削除件数
     */
    public function deleteAll()
    {
        $this->_bindvalue = array();
        
        $sql = "DELETE FROM `". $this->_tablename ."` ".
            $this->_buildWhereClause(false);
        
        $this->_log->info("deleteAll ". $this->_tablename .": $sql");
        $this->_log->info("param is: \n". @var_export($this->_bindvalue, TRUE));
        $sth = $this->_pdo->prepare($sql);
        
        if (! $sth) {
            $err = $this->_pdo->errorInfo();
            throw new TeepleActiveRecordException("pdo prepare failed: {$err[2]}:{$sql}");
        }
        if (! $sth->execute($this->_bindvalue)) {
            $err = $sth->errorInfo();
            throw new TeepleActiveRecordException("pdo execute failed: {$err[2]}:{$sql}");
        }
        
        $this->_log->info("deleteAll ". $this->_tablename .": result=(".$sth->rowCount().")");
        
        $this->resetInstance();
        return $sth->rowCount();
    }
    
    /**
     * 指定されたSELECT文を実行します。
     * 結果はstdClassの配列になります。
     * 結果が0行の場合は空の配列が返ります。
     *
     * @param string $query
     * @param array $bindvalues
     * @return array
     */
    public function selectQuery($query, $bindvalues) {
        
        // prepare
        $sth = $this->getPDO()->prepare($query); 
        
        // bind
        if (is_array($bindvalues) && count($bindvalues) > 0) {
            foreach($bindvalues as $i => $value) {
                $sth->bindValue($i+1, $value);
            }
        }
        
        // 実行
        $sth->execute();
        
        // 結果を取得
        $result = array();
        while ($row = $sth->fetch()) {
            $obj = new stdClass;
            foreach($row as $col => $value) {
                $obj->$col = $value;
            }
            array_push($result, $obj);
        }
        return $result;
    }
    
    /**
     * 指定されたSELECT文を実行します。(単一行)
     * 結果はstdClassになります。
     * 結果が0行の場合はNULLが返ります。
     *
     * @param string $query
     * @param array $bindvalues
     * @return stdClass
     */
    public function findQuery($query, $bindValues) {
        
        $result = $this->selectQuery($query, $bindValues);
        if (count($result) > 0) {
            return $result[0];
        }
        return NULL;
    }    
    
    /**
     * reset metadata of this instance.
     */
    public function resetInstance() {
        $this->_parents = array();
        $this->_children = array();
        $this->_criteria = array();
        $this->_bindvalue = array();
        $this->_afterwhere = array();
        $this->_selectColumns = array();
        return;
    }
    
    /**
     * ActionクラスのプロパティからEntityのプロパティを生成します。
     *
     * @param Object $obj Actionクラスのインスタンス
     * @param array $colmap 'entityのカラム名' => 'Actionのプロパティ名' の配列
     */
    public function convert2Entity($obj, $colmap=null) {
        
        if ($colmap == null) {
            $colmap = array();
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
     * EntityのプロパティからActionクラスのプロパティを生成します。
     *
     * @param Object $obj Actionクラスのインスタンス
     * @param array $colmap 'entityのカラム名' => 'Actionのプロパティ名' の配列
     */
    public function convert2Page($obj, $colmap=null) {

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
     * 現在の時刻を返します。
     * @return string 
     */
    public function now() {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * SELECT文を構築します。
     *
     * @return String SELECT文
     */
    protected function _buildSelectSql() {
        
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
    protected function _buildSelectClause() {
        
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
    protected function _buildFromClause() {
        
        $base = $this->__soname;
        return "FROM {$base}";
    }
    
    /**
     * build where clause.
     * @return string
     */
    protected function _buildWhereClause() {
        
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
     * WHERE clause を構築します。
     *
     * @return unknown
     */
    protected function _buildConstraintClause($usebase=true) {
        
        $buff = array();
        
        // constraints
        if (count($this->_constraints)) {
            foreach($this->_constraints as $col => $val) {
                if ($val != null) {
                    $buff[] = $usebase ? "base.{$col} = ?" : "{$col} = ?";
                    array_push($this->_bindvalue, $val);
                } else {
                    $buff[] = $usebase ? "base.{$col} IS NULL" : "{$col} IS NULL";
                }
            }
        }
        if (count($buff)) {
            return "WHERE ". implode(' AND ', $buff);
        }
        return "";
    }    
    
    /**
     * build order by, limit, offset clause
     * @return string
     */
    protected function _buildAfterWhereClause() {
        
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
     * UPDATE文のVALUES部分を作成します。
     *
     * @param array $array アップデートする値の配列 
     * @return string SQL句の文字列
     */
    protected function _buildSetClause($array) {
        foreach($array as $key => $value) {
            $expressions[] ="{$key} = ?";
            array_push($this->_bindvalue, $value);
        }
        return "SET ". implode(', ', $expressions);
    }
    
    /**
     * set record value.
     * @param SObject $row
     */
    protected function _buildResultSet($row) {
        
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
            $this->$n = $v;
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
    
    protected function _checkPlaceHolder($condition, $params) {
        
        $param_num = count($params);
        $holder_num = substr_count($condition, '?');
        if ($param_num != $holder_num) {
            throw new TeepleActiveRecordException("The num of placeholder is wrong.");
        }
    }
    
    protected function _getEntityConfig($clsname, $property) {
        
        $ref = new ReflectionClass($clsname);
        return $ref->getStaticPropertyValue($property);
    }

    /**
     * 設定された制約でWHERE句を作成します。
     *
     * @param array $array 制約値
     * @return string SQL句の文字列
     */
    protected function _makeUpdateConstraints($array) {
        foreach($array as $key => $value) {
            if(is_null($value)) {
                $expressions[] = "{$key} IS NULL";
            } else {
                $expressions[] = "{$key}=:{$key}";
            }
        }
        return implode(' AND ', $expressions);
    }

    /**
     * バインドするパラメータの配列を作成します。
     *
     * @param array $array バインドする値の配列
     * @return array バインドパラメータを名前にした配列
     */
    protected function _makeBindingParams( $array )
    {
        $params = array();
        foreach( $array as $key=>$value )
        {
            $params[":{$key}"] = $value;
        }
        return $params;
    }

    /**
     * IN句に設定するIDのリストを作成します。
     * 
     * @param array $array IDの配列
     * @return string IN句に設定する文字列
     */
    protected function _makeIDList( $array )
    {
        $expressions = array();
        foreach ($array as $id) {
            $expressions[] = "`". $this->_tablename ."`.id=".
                $this->_pdo->quote($id, isset($this->has_string_id) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        return '('.implode(' OR ', $expressions).')';
    }
    
    /**
     * PKがセットされているかどうかをチェックします。
     * 
     * @return PKがセットされている場合はTRUE
     */
    protected function isSetPk()
    {
        if (! isset($this->_pk)) {
            return isset($this->id);
        }
        
        foreach ($this->_pk as $one) {
            if (! isset($this->$one)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Entityのカラム値をArrayとして取り出す
     *
     * @param Teeple_ActiveRecord $obj
     * @param boolean $excludeNull
     * @return array
     */
    protected function _convertObject2Array($obj, $excludeNull=false) {
        
        $columns = $this->_getColumns(get_class($obj));
        $result = array();
        foreach ($columns as $name) {
            $val = $obj->$name;
            if (@is_array($val)) {
                $result[$name] = serialize($val);
            } else if (! $excludeNull || ($val !== NULL && strlen($val) > 0)) {
                $result[$name] = $this->_null($val);
            }
        }

        return $result;
    }
    
    /**
     * get list of column names.
     *
     * @param string $clsname
     * @return array
     */ 
    protected function _getColumns($clsname) {
        
        $parentConfig = $this->_getEntityConfig($clsname, "_parentRelationships");
        $parentNames = array_keys($parentConfig);
        $childConfig = $this->_getEntityConfig($clsname, "_childRelationships");
        $childNames = array_keys($childConfig);
        
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

    protected function _null($str) {
        return $str !== NULL && strlen($str) > 0 ? $str : NULL;
    }

    /**
     * @param string $name
     * @param array $def
     */
    protected function _buildChildSelectClause($name, $def) {
        
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
     * TODO escape special character.
     * @param unknown_type $query
     * @param unknown_type $values
     */
    protected function _placeValue($query, $values) {
        
        foreach ($values as $v) {
            $query = preg_replace('/\?/', "'{$v}'", $query, 1);
        }
        return $query;
    }
    
    protected function getSelectColumnsAsArray($name) {
        
        if (isset($this->_selectColumns[$name])) {
            $columns = explode(',', $this->_selectColumns[$name]);
            array_walk($columns, create_function('&$arr','$arr=trim($arr);'));
            return $columns;
        }
        return NULL;
    }
    
    protected function getParentInstance($relname) {
        
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
    
    protected function getChildInstance($relname) {
        
        $objname = $this->__childRelationships[$relname];
        $objname = "Sobject_{$objname}";
        $obj = new $objname();
        return $obj;
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
     * コンストラクタです。
     * @param string $message
     */
    function __construct($message)
    {
        return parent::__construct($message);
    }
}

?>