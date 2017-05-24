<?php
/**
 *  SPDA - Simple PDO Database Abstraction
 *
 * @author		Author: Vincent Herbaut - Ikon-K 
 * @git 		https://github.com/baw0u/spda
 * @version     1.0
 *
 **/
namespace baw0u;
use \PDO;
use \Exception;

define('SPDA_PATH', str_replace('\\', '/', dirname(__file__)));
define('DS',DIRECTORY_SEPARATOR);
require_once(SPDA_PATH.DS.'spda.config.php'); // Configuration

class spda{
    
    private static $_instance = array();
    private $instanseName;
    private $db;
    private $magic_quotes_gpc;
    private $result;
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $dbEncoding;
    private $autoReset;
    private $debug;
    private $table;
    private $table_alias = array();
    private $was_set = false;
    private $unique = array();
    private $primary = array();
    private $autoincrement;
    private $fields = array();
    private $defaults = array();
    private $join_sql = array();
    private $order = array();
    private $group = array();
    private $random = false;
    private $sql_request = array();
    private $sql_fields = array();
    private $sql_where = array();
    private $sql_cond = array();
    private $cond_where = array();
    private $sql_fulltext = array();
    private $aliases = array();
    private $distinct = '';
    public $insert_id;
    
    public function __construct($table, $dbUser, $dbPass, $dbName, $dbHost, $dbencoding, $debug=false){
        $this->magic_quotes_gpc = get_magic_quotes_gpc();
        $this->debug = $debug;

        if(strpos($dbHost, ':') !== false):
            list($host, $port) = explode(':', $dbHost, 2);
            try{
                $this->db = new PDO("mysql:host=$dbHost;dbname=$dbName;port=$port;charset=$dbencoding;", $dbUser, $dbPass);
                $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }catch (PDOException $e) {
                self::error('Connection failed: '.$e->getMessage());
            }catch (Exception $e){
                 self::error('','Connection failed: '.$e->getMessage());
            }
        else:
            try{
                $this->db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=$dbencoding;", $dbUser, $dbPass);
                $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }catch (PDOException $e) {
                self::error('','Connection failed: '.$e->getMessage());
            }catch (Exception $e){
                 self::error('','Connection failed: '.$e->getMessage());
            }
        endif;
        if(isset($table) && !empty($table)):
            $this->table = $table;
        endif;
        $this->autoReset = spdaConfig::$autoReset;
    }
    
    private function __clone(){}
    
    public static function table($table='', $params=false){
        if(is_array($params)):
            $dbHost = isset($params['dbHost']) ? $params['dbHost'] : 'localhost';
            $dbUser = $params['dbUser'];
            $dbPass = $params['dbPass'];
            $dbname = $params['dbName'];
            $dbEncoding = isset($params['dbEncoding']) ? $params['dbEncoding'] : 'utf8';
            $debug = isset($params['debug']) ? $params['debug'] : false;
        else:
            $dbHost = spdaConfig::$dbHost;
            $dbUser = spdaConfig::$dbUser;
            $dbPass = spdaConfig::$dbPass;
            $dbname = spdaConfig::$dbNname;
            $dbEncoding = isset(spdaConfig::$dbEncoding) ? spdaConfig::$dbEncoding : 'utf8';
            $debug = spdaConfig::$debug;
        endif;
        $instanceName = sha1($table.$dbUser.$dbPass.$dbname.$dbHost);
        if(!isset(self::$_instance[$instanceName]) or null === self::$_instance[$instanceName]):
            self::$_instance[$instanceName] = new self($table, $dbUser, $dbPass, $dbname, $dbHost, $dbEncoding ,$debug);
            self::$_instance[$instanceName]->instanse_name = $instanceName;
        endif;
        return self::$_instance[$instanceName];
    }
    
    public static function bdd($params = false){
        return self::table(false, $params);
    }
    
    public function query($query = ''){
        $this->sql_request = $query;
        try{
            $this->result = $this->db->query($this->sql_request); 
        }catch (PDOException $pdoE){
            $this->error($this->sql_request, $pdoE->getMessage());
        }catch (Exception $e){
            $this->error($this->sql_request, $e->getMessage());
        }
        return $this;
    }
    public function result(){
        $output = array();
        if($this->result):
             $result = $this->result;
               while($row = $result->fetch()):
                foreach($row as $k=>$v):
                    if(is_int($k)):
                        unset($row[$k]);
                    endif;
                endforeach;
                $output[] = $row;
            endwhile;
            $this->result = false;
        endif;
        return $output;
    }
    
    public function get($limit=0, $start=0){
        $this->get_table_info($this->table,$this->table);
        $join = $this->create_join();
        $select = $this->create_select();
        $from = $this->create_from();
        $where = $this->create_where();
        $group = $this->create_group();
        $order = $this->create_order();
        $limit = $this->create_limit($limit, $start);
        $this->was_set = true;
        $output = array();
        $this->sql_request = "SELECT {$this->distinct}{$select} FROM {$from} {$join} {$where} {$group} {$order} {$limit}";
        try{
            $result = $this->db->prepare($this->sql_request); 
            if(isset($this->sql_cond) && !empty($this->sql_cond)):
                $result->execute(array_filter($this->sql_cond)); 
            else:
                $result->execute(); 
            endif;
        }catch (PDOException $pdoE){
            $this->error($this->sql_request, $pdoE->getMessage());
        }catch (Exception $e){
            $this->error($this->sql_request, $e->getMessage());
        }
        while ($row = $result->fetch()):
            foreach($row as $k=>$v):
                if(is_int($k)):
                    unset($row[$k]);
                endif;
            endforeach;
            $output[] = $row;
        endwhile;
        
        if($this->was_set && $this->autoReset): $this->reset(); endif;
        
        return $output;
    }
    
    public function get_one(){
        $result = $this->get(1);
        return $result[0];
    }
    
    public function total() {
        $join = $this->create_join();
        $from = $this->create_from();
        $where = $this->create_where();
        $group = $this->create_group();
        $this->was_set = true;
        $this->sql_request = "SELECT COUNT({$this->distinct}*) as `count` FROM {$from} {$join} {$where} {$group}";
        try{
            $result = $this->db->prepare($this->sql_request); 
            if(isset($this->sql_cond) && !empty($this->sql_cond)):
                $result->execute($this->sql_cond); 
            else:
                $result->execute(); 
            endif;
        }catch (PDOException $pdoE){
            $this->error($this->sql_request, $pdoE->getMessage());
        }catch (Exception $e){
            $this->error($this->sql_request, $e->getMessage());
        }
         
        if($this->autoReset): $this->reset(); endif;
         
        return $result->fetch()['count'];
    }
    
    public function insert($arr = array(), $table = false) {
        if(is_array($arr) && !empty($arr)):
            $exectCond = array();
            $table = $table ? $table : $this->table;
            $fieldvalues = array();
            $this->was_set = true;
            reset($arr);
            $first = $arr[key($arr)];
            if(is_array($first)):
                $fieldnames = array_keys($first);
                foreach($arr as $item):
                    $fieldvalues[] = array_values($item);
                endforeach;
            else:
                $fieldnames = array_keys($arr);
                $fieldvalues[0] = array_values($arr);
            endif;
            unset($first, $arr);

            foreach($fieldvalues as $key => $values):
                foreach($values as $fkey => $subitem):
                        $val = ":".sha1(rand().microtime());
                        $exectCond[$val] = $subitem;
                        $values[$fkey] = $val;
                endforeach;	
                $fieldvalues[$key] = '('.implode(",",$values).')';
            endforeach;

            $this->sql_request = "INSERT INTO `{$table}` (`".implode('`,`', $fieldnames)."`) VALUES ".implode(',', $fieldvalues);
            try{
                $result = $this->db->prepare($this->sql_request); 
                if(isset($exectCond) && !empty($exectCond)):
                    $result->execute($exectCond); 
                else:
                    $result->execute(); 
                endif;
            }catch (PDOException $pdoE){
                $this->error($this->sql_request, $pdoE->getMessage());
            }catch (Exception $e){
                $this->error($this->sql_request, $e->getMessage());
            }
            unset($fieldvalues);
            $this->insert_id = $this->db->lastInsertId();
        
            if($this->was_set && $this->autoReset): $this->reset(); endif;
            return $this->insert_id;
        else:
            $this->error("","Error insert. Array of rows value is empty.");
        endif;
    }
    
    public function insert_id(){
        return $this->insert_id;
    }
    
    public function update($arr = array(), $limit = 0, $start = 0, $table = false){
        if(is_array($arr) && !empty($arr)):
            $exectCond = array();
            $table = $table ? $table : $this->table;
            $where = $this->create_where();
            $order = $this->create_order();
            $limit = $this->create_limit($limit, $start);
            $fieldvalues = array();
            $this->was_set = true;
            foreach($arr as $key => $item):
                if(is_array($item)):
                    foreach($item as $subkey => $subitem):
                        $val = ":".sha1(rand().microtime());
                        $exectCond[$val] = $subitem;
                        $fieldvalues[$fkey] = "`{$table}`.`{$subkey}` = ".$val;
                    endforeach;
                else:
                    $val = ":".sha1(rand().microtime());
                    $exectCond[$val] = $item;
                    $fieldvalues[0][] = "`{$table}`.`{$key}` = ".$val;
                endif;
            endforeach;
             foreach($fieldvalues as $values):
                try{
                    $this->sql_request = "UPDATE `{$table}` SET ".implode(',', $values)." {$where} {$order} {$limit}";
                    $result = $this->db->prepare($this->sql_request); 
               
                    if(isset($this->sql_cond) && !empty($this->sql_cond)):
                        foreach($this->sql_cond as $k => $v):
                            $exectCond[$k] = $v;
                        endforeach;
                    endif;

                    if(isset($exectCond) && !empty($exectCond)):
                        $result->execute($exectCond); 
                    else:
                        $result->execute(); 
                    endif;
                }catch (PDOException $pdoE){
                    $this->error($this->sql_request, $pdoE->getMessage());
                }catch (Exception $e){
                    $this->error($this->sql_request, $e->getMessage());
                }
            endforeach;

            unset($fieldvalues);
            if($this->was_set && $this->autoReset): $this->reset(); endif;
         
            return $result->rowCount();
        else:
            $this->error("","Error Update. Array of rows value is empty.");
        endif;
    }
    
    public function set($arr = array(), $table = false){
        if(is_array($arr)):
            $exectCond =  array();
            $table = $table ? $table : $this->table;
            $fieldvalues = array();
            $this->was_set = true;
            foreach($arr as $key => $item):
                if(is_array($item)):
                    foreach($item as $subkey => $subitem):
                            $val = ":".sha1(rand().microtime());
                            $exectCond[$key][$val] = $subitem;
                            $fieldvalues[$key]['ins_key'][] = "`{$table}`.`{$subkey}`";
                            $fieldvalues[$key]['ins_val'][] = $val;
                    endforeach;
                    foreach($item as $subkey => $subitem):
                            $val = ":".sha1(rand().microtime());
                            $exectCond[$key][$val] = $subitem;
                            $fieldvalues[$key]['update'][] = "`{$table}`.`{$subkey}` = $val";
                    endforeach;
                else:
                    $val = ":".sha1(rand().microtime()); 
                    $exectCond[0][$val] = $item;
                    $fieldvalues[0]['ins_key'][] = "`{$table}`.`{$key}`";
                    $fieldvalues[0]['ins_val'][] = $val;
                    $fieldvalues[0]['update'][] = "`{$table}`.`{$key}` = $val";
                endif;
            endforeach;
            foreach($fieldvalues as $key => $values):
               $this->sql_request = "INSERT INTO `{$table}` (".implode(',', $values['ins_key']).") VALUES (".implode(',', $values['ins_val']).") ON DUPLICATE KEY UPDATE ".implode(',', $values['update']);
                try{
                    $result = $this->db->prepare($this->sql_request); 
                    if(isset($exectCond[$key]) && !empty($exectCond[$key])):
                        $result->execute($exectCond[$key]); 
                    else:
                        $result->execute(); 
                    endif;
                }catch (PDOException $pdoE){
                    $this->error($this->sql_request, $pdoE->getMessage());
                }catch (Exception $e){
                    $this->error($this->sql_request, $e->getMessage());
                }
            endforeach;
            unset($fieldvalues);
        
            if($this->was_set && $this->autoReset): $this->reset(); endif;
        
            return $result->rowCount();
        endif;
    }
    
    public function delete($limit = 0, $start = 0, $table = false){
        $this->get_table_info($this->table, $this->table);
        $join = $this->create_join();
        $from = $this->create_from();
        $where = $this->create_where();
        $order = $this->create_order();
        $limit = $this->create_limit($limit, $start);
        $this->was_set = true;

        $this->sql_request = "DELETE FROM {$from} {$join} {$where} {$order} {$limit}";
        try{
            $result = $this->db->prepare($this->sql_request); 
            if(isset($this->sql_cond) && !empty($this->sql_cond)):
                $result->execute($this->sql_cond); 
            else:
                $result->execute(); 
            endif;
        }catch (PDOException $pdoE){
            $this->error($this->sql_request, $pdoE->getMessage());
        }catch (Exception $e){
            $this->error($this->sql_request, $e->getMessage());
        }
        
        if($this->was_set && $this->autoReset): $this->reset(); endif;
        
        return $result->rowCount();
    }
    
    public function sum($field = '', $alias = false, $table = false) {
        $table = $table ? $table : $this->table;
        if($alias):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $this->sql_fields[] = "SUM(`{$table}`.`{$field}`) AS `{$alias}`";
            return $this;
       else:
            $join = $this->create_join();
            $where = $this->create_where();
            $group = $this->create_group();
            $this->was_set = true;
            $row = $this->query("SELECT SUM(`{$table}`.`{$field}`) AS `{$field}` FROM `{$table}` {$join} {$where} {$group}")->result();
            if(is_array($row)):
                return $row[$field];
            else:
                $array = get_object_vars($row);
                return $array[$field];
            endif;
        endif;
    }
    
    public function increment($field = '', $num = 1, $table = false){
        if($field):
            if(!is_int($num)):
                $num=1;
            endif;
            $table = $table ? $table : $this->table;
            $join = $this->_build_join();
            $where = $this->_build_where();
            $this->was_set = true;
            $this->sql_request = "UPDATE `$table` SET `{$field}` = `{$field}` + {$num} {$join} ";
            try{
                $result = $this->db->prepare($this->sql_request); 
                $result->execute(); 
            }catch (PDOException $pdoE){
                $this->error($this->sql_request, $pdoE->getMessage());
            }catch (Exception $e){
                $this->error($this->sql_request, $e->getMessage());
            }
            if($this->was_set && $this->autoReset): $this->reset(); endif;
        endif;
    }
    
    public function decrement($field = '', $num = 1, $table = false) {
        if($field):
            if(!is_int($num)):
                $num=1;
            endif;
            $table = $table ? $table : $this->table;
            $join = $this->_build_join();
            $where = $this->_build_where();
            $this->was_set = true;
            $this->sql_request = "UPDATE `$table` SET `{$field}` = `{$field}` - {$num} {$join} ";
            try{
                $result = $this->db->prepare($this->sql_request); 
                $result->execute(); 
            }catch (PDOException $pdoE){
                $this->error($this->sql_request, $pdoE->getMessage());
            }catch (Exception $e){
                $this->error($this->sql_request, $e->getMessage());
            }
            if($this->was_set && $this->autoReset): $this->reset(); endif;
        endif;
    }
    
    public function fields($fields = '', $table = false){
        if($fields):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $table = $table ? $table : $this->table;
            if(!isset($this->fields[$table])):
                $this->fields[$table] = array();
            endif;
            if(is_array($fields)):
                $this->fields[$table] = array_unique(array_merge($this->fields[$table], $fields));
            else:
                $this->fields[$table] = array_unique(array_merge($this->fields[$table], explode(',', str_replace(' ', '', $fields))));
            endif;
        endif;
        return $this;
    }

    public function alias($field = '', $alias = '', $table = false){
        if(($field && $alias) or (is_array($field))):
            $table = $table ? $table : $this->table;
            if(!isset($this->aliases[$table])):
                $this->aliases[$table] = array();
            endif;
            if(is_array($field)):
                $this->aliases[$table] = array_merge($this->aliases[$table], $field);
            else:
                $this->aliases[$table] = array_merge($this->aliases[$table], array($field => $alias));
            endif;
        endif;
        return $this;
    }
    
    public function distinct(){
        if($this->was_set && $this->autoReset):
            $this->reset();
        endif;
        $this->distinct = 'DISTINCT ';
    }

    public function where($field = '', $value = '', $table = false, $no_quotes = false, $join_with = 'AND', $operator = false){
        if($field):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $table = $table ? $table : $this->table;
            if(is_array($field)):
                foreach($field as $key => $value):
                    $this->sql_where[] = array(
                        'table'     => $table,
                        'field'     => $key,
                        'value'     => $value,
                        'join_with' => $join_with,
                        'no_quotes' => $no_quotes,
                        'operator'  => $operator,
                        'pdo_condition'  => ":".sha1(rand().microtime())
                    );
                endforeach;
            else:
                $this->sql_where[] = array(
                    'table'     => $table,
                    'field'     => $field,
                    'value'     => $value,
                    'join_with' => $join_with,
                    'no_quotes' => $no_quotes,
                    'operator'  => $operator,
                    'pdo_condition'  => ":".sha1(rand().microtime())
                );
            endif;
       endif;
       return $this;
    }
    
    public function and_where($field = '', $value = '', $table = false, $no_quotes = false) {
        return $this->where($field, $value, $table, $no_quotes, 'AND');
    }

    public function or_where($field = '', $value = '', $table = false, $no_quotes = false) {
        return $this->where($field, $value, $table, $no_quotes, 'OR');
    }

    public function like($field = '', $value = '', $pattern = array('%', '%'), $table = false, $join_with = 'AND'){
        return $this->where($field, $pattern[0].$value.$pattern[1], $table, false, $join_with, 'LIKE');
    }

    public function and_like($field = '', $value = '', $pattern = array('%', '%'), $table = false){
        return $this->where($field, $pattern[0].$value.$pattern[1], $table, false, 'AND', 'LIKE');
    }

    public function or_like($field = '', $value = '', $pattern = array('%', '%'), $table = false) {
        return $this->where($field, $pattern[0].$value.$pattern[1], $table, false, 'OR', 'LIKE');
    }

    public function not_like($field = '', $value = '', $pattern = array('%', '%'), $table = false, $join_with = 'AND'){
        return $this->where($field, $pattern[0].$value.$pattern[1], $table, false, $join_with, 'NOT LIKE');
    }

    public function and_not_like($field = '', $value = '', $pattern = array('%', '%'), $table = false) {
        return $this->where($field, $pattern[0].$value.$pattern[1], $table, false, 'AND', 'NOT LIKE');
    }

    public function or_not_like($field = '', $value = '', $pattern = array('%', '%'), $table = false){
        return $this->where($field, $pattern[0].$value.$pattern[1], $table, false, 'OR', 'NOT LIKE');
    }

    public function where_in($field = '', $values = '', $table = false) {
        if(is_array($values)):
            $values = explode(',', str_replace(' ', '', $values));
        endif;
        return $this->where($field, $values, $table, false, 'AND', 'IN');
    }

    public function where_not_in($field = '', $values = '', $table = false){
        if(is_array($values)):
            $values = explode(',', str_replace(' ', '', $values));
        endif;
        return $this->where($field, $values, $table, false, 'AND', 'NOT IN');
    }

    public function and_in($field = '', $values = '', $table = false){
        if(is_array($values)):
            $values = explode(',', str_replace(' ', '', $values));
        endif;
        return $this->where($field, $values, $table, false, 'AND', 'IN');
    }

    public function and_not_in($field = '', $values = '', $table = false){
        if(is_array($values)):
            $values = explode(',', str_replace(' ', '', $values));
        endif;
        return $this->where($field, $values, $table, false, 'AND', 'NOT IN');
    }

    public function or_in($field = '', $values = '', $table = false){
        if(is_array($values)):
            $values = explode(',', str_replace(' ', '', $values));
        endif;
        return $this->where($field, $values, $table, false, 'OR', 'IN');
    }

    public function or_not_in($field = '', $values = '', $table = false) {
        if(is_array($values)):
            $values = explode(',', str_replace(' ', '', $values));
        endif;
        return $this->where($field, $values, $table, false, 'OR', 'NOT IN');
    }
    
     public function open_sub($join_with = 'AND') {
        $this->sql_where[] = array(
            'table' => '',
            'field' => '',
            'value' => '',
            'join_with' => '(',
            'no_quotes' => '',
            'operator' => $join_with
        );
        return $this;
    }

    public function close_sub() {
        $this->sql_where[] = array(
            'table' => '',
            'field' => '',
            'value' => '',
            'join_with' => ')',
            'no_quotes' => '',
            'operator' => ''
        );
        return $this;
    }

    public function or_sub() {
        $this->open_sub('OR');
        return $this;
    }

    public function and_sub() {
        $this->open_sub('AND');
        return $this;
    }
		
    public function order_by($field = '', $direction = 'asc', $table = false){
        if($field):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $table = $table ? $table : $this->table;
            $this->order[] = array(
                'table' => $table,
                'field' => $field,
                'direction' => (strtolower($direction) == 'desc') ? 'DESC' : 'ASC');
        endif;
        return $this;
    }

    public function random(){	        
        if($this->was_set && $this->autoReset):
            $this->reset();
        endif;
        $this->random = true;
        return $this;
    }
    
    public function join($type = 'LEFT', $field = '', $table = '', $join_field = '', $left_table = false, $alias = false, $right_fields = false){
        if($type && $field && $join_field && $table):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $l_table = $left_table ? $left_table : $this->table;
            $alias = $alias ? $alias : $table;
            $condition = "`{$l_table}`.".$this->_prepare_field($field)."`{$alias}`.`{$join_field}`";
            $this->join_sql[] = array(
                'left_table' => $l_table,
                'right_table' => $table,
                'condition' => $condition,
                'alias' => $alias,
                'type' => strtoupper($type));
            if($right_fields):
                $this->fields($right_fields, $alias);
            endif;
        endif;
        return $this;
    }

    public function left_join($field = '', $table = '', $join_field = '', $left_table = false, $alias = false, $right_fields = false){
        return $this->join($type = 'LEFT', $field, $table, $join_field, $left_table, $alias = false, $right_fields);
    }

    public function right_join($field = '', $table = '', $join_field = '', $left_table = false, $alias = false, $right_fields = false){
        return $this->join($type = 'RIGHT', $field, $table, $join_field, $left_table, $alias = false, $right_fields);
    }

    public function inner_join($field = '', $table = '', $join_field = '', $left_table = false, $alias = false, $right_fields = false){
        return $this->join($type = 'INNER', $field, $table, $join_field, $left_table, $alias = false, $right_fields);
    }

    public function group_by($field = '', $table = false){
        if($field):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $table = $table ? $table : $this->table;
            $this->group[] = array('table' => $table, 'field' => $field);
        endif;
        return $this;
    }
    
    public function concat($fields = '', $alias = '', $separator = ',', $table = false){
        if($fields && $alias):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $table = $table ? $table : $this->table;
            if(!is_array($fields)):
                $fields = explode(',', preg_replace('/\s*\,\s*/u', ',', $fields));
            endif;
            foreach ($fields as $field):
                $concat_str[] = "`{$table}`.`{$field}`";
            endforeach;
            $concat_str = implode(',', $concat_str);
            $separator = $this->escape($separator);
            $this->sql_fields[] = "CONCAT_WS('{$separator}',{$concat_str}) AS `{$alias}`";
        endif;
        return $this;
    }

    public function group_concat($field = '', $alias = '', $separator = ',', $table = false, $distinct = true, $order_by = false, $direction = 'ASC'){
        if($field):
            if($this->was_set && $this->autoReset):
                $this->reset();
            endif;
            $alias = $alias ? $alias : $field;
            $table = $table ? $table : $this->table;
            $order = $order_by ? "ORDER BY `{$order_by}` {".strtoupper($direction)."}" : '';
            $this->sql_fields[] = 'GROUP_CONCAT('.($distinct ? 'DISTINCT ' : '')."`{$table}`.`{$field}` {$order} SEPARATOR '{$separator}') AS `{$alias}`";
        endif;
        return $this;
    }
    
    public function reset(){
        $this->sql_fields = array();
        $this->sql_fulltext = array();
        $this->sql_where = array();
        $this->sql_cond = array();
        $this->fields = array();
        $this->random = false;
        $this->group = array();
        $this->order = array();
        $this->join_sql = array();
        $this->was_set = false;
        return $this;
    }
    
    private function get_table_info($table, $alias){
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}`");
        $result->setFetchMode(PDO::FETCH_OBJ);
        
        while($row = $result->fetch()):
            if($row->Key == 'UNI'):
                $this->unique[$row->Field] = true;
            endif;
            if($row->Key == 'PRI'):
                $this->primary[$row->Field] = true;
            endif;
            if($row->Key == 'PRI' && $row->Extra == 'auto_increment'):
                $this->autoincrement[$row->Field] = true;
            endif;
            if(!isset($this->fields[$table]) || (isset($this->fields[$table]) && in_array($row->Field, $this->fields[$table])))
                $this->sql_fields["{$alias}.{$row->Field}"] = "`{$alias}`.`{$row->Field}`".(isset($this->aliases[$table][$row->Field]) ?
                    " AS `{$this->aliases[$table][$row->Field]}`" : '');
        endwhile;
    }
    
    public function show_request(){
        $html = "";
        if(isset($this->sql_request) && !empty($this->sql_request) && $this->debug == true):
            $html ='<pre>';
                $html.= $this->sql_request;
            $html.= '</pre>';
        endif;
        return $html;
    }

    private function create_join(){
        $join = array();
        if(isset($this->join_sql) && !empty($this->join_sql)):
            foreach($this->join_sql as $join_tbl):
                $this->table_alias[$join_tbl['right_table']] = $join_tbl['alias'];
                $this->get_table_info($join_tbl['right_table'], $join_tbl['alias']);
                $join[] = "{$join_tbl['type']} JOIN `{$join_tbl['right_table']}` AS `{$join_tbl['alias']}` ON {$join_tbl['condition']}";
            endforeach;
            return implode(' ', $join);
        endif;
        return '';
    }

    private function create_select(){
        return implode(', ', $this->sql_fields);
    }

    private function create_from(){
        return "`{$this->table}`";
    }

    private function create_where(){
        $where="";
        if($this->sql_where):
            $i = 1;
            foreach($this->sql_where as $k => $w):

                $value = $w['value'];
                $w['value'] = $w['pdo_condition'];
                $this->sql_cond[$w['value']] = $value; 

                if(isset($this->table_alias[$w['table']])):
                    $w['table'] = $this->table_alias[$w['table']];
                endif;
                if(($k > 0 or $where != '') && $w['field'] && $i > 0):
                    $where.= $w['join_with'];
                elseif($w['field'] && $i > 0):
                    $where.= 'WHERE';
                endif;
                if($w['join_with'] && $w['operator'] && !$w['field']):
                    $where.= $w['operator'].' '.$w['join_with'];
                    $i = 0;
                    continue;
                elseif($w['join_with'] && !$w['operator'] && !$w['field']):
                    $where.= $w['join_with'];
                    continue;
                endif;

                if(mb_stripos($w['operator'], 'IN') !== false):

                    /*foreach($w['value'] as $wkey => $wval):
                        $w['value'][$wkey] = $wval;
                        $w['value'][$wkey] = '\''.$this->escape($wval).'\'';
                    endforeach;*/

                    if(gettype($w['value']) == "string"):
                        $where.= " `{$w['table']}`.`{$w['field']}` {$w['operator']} (".$w['value'].') ';
                    else:
                        $where.= " `{$w['table']}`.`{$w['field']}` {$w['operator']} (".implode(',', $w['value']).') ';
                    endif;
                else:
                    if($w['operator']):
                        $where.= " `{$w['table']}`.`{$w['field']}` {$w['operator']} {$w['value']} ";
                    else:
                        $where.= " `{$w['table']}`.".$this->_prepare_field($w['field'])." {$w['value']} ";
                    endif;
                endif;
                ++$i;
            endforeach;
        endif;
        return $where;
    }

    private function create_group(){
        if($this->group):
            $group = array();
            foreach ($this->group as $param):
                $group[] = "`{$param['table']}`.`{$param['field']}`";
            endforeach;
            return 'GROUP BY '.implode(', ', $group);
        endif;
        return '';
    }

    private function create_order(){
        $order = array();
        if($this->random):
            $order[] = 'RAND()';
        endif;
        if($this->order):
            foreach($this->order as $param):
                if(isset($this->table_alias[$param['table']])):
                    $param['table'] = $this->table_alias[$param['table']];
                endif;
                $order[] = "`{$param['table']}`.`{$param['field']}` {$param['direction']}";
            endforeach;
        endif;
        if($order):
            return 'ORDER BY '.implode(', ', $order);
        endif;
        return '';

    }

    private function create_limit($limit, $start){
        if($limit != 0):
            return "LIMIT {$start},{$limit}";
        endif;
    }
    
    public function escape($val){
        if(is_int($val)):
            return $val;
        endif;
        if(!$this->magic_quotes_gpc):
            return $this->db->quote($val);
        endif;
        return $val;
    }

    private function _prepare_field($field){
        preg_match_all('/([^<>!=]+)/', $field, $matches);
        preg_match_all('/([<>!=]+)/', $field, $matches2);
        return '`'.trim($matches[0][0]).'`'.($matches2[0] ? implode('', $matches2[0]) : '=');
    }
    
    /*
     *  Show error's class
     *
     *  @param  string  $text
     *	@param  string  $query
     *	@return string
    */
    protected static function error($query = '', $text='Error!'){
        if(!spdaConfig::$debug): $query=""; endif;
        $style='padding: .75rem 1.25rem;margin-bottom: 1rem;border: 1px solid #ebcccc;border-radius: .25rem;background-color: #f2dede;color: #a94442;';
        echo '<div class="spda-error" style="'.$style.'"><strong>'.$text.'</strong><br> '.$query.'</div>';
        exit();
    }
    
}