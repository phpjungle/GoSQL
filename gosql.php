<?php
define('PJ_ENV_ISCLIMODE',isset($_SERVER['argv'])); //  定义脚本执行环境,true:cli,false:web
define('EOL', PJ_ENV_ISCLIMODE?PHP_EOL:'<br>');
require_once 'SQLWhereMaker.php';
/**
 * PJ框架DB操作类
 * 
 * @author PHPJungle
 * @since 2015/05/27 周三
 * @abstract 1.获取表格
 * 	SQL种类：	
 * 			1.1 DQL-Q-select(query)
 * 			1.2 DML-M-insert+delete+update
 * 			1.3 TCL-T-begin+commit+rollback;
 * 			1.4 DDL-D-create table/view/index/syn/cluster
 * 			1.5 DCL-C-grant+revoke
 * 
 * 	提交数据类型:
 * 			1.1 显式提交(COMMIT)
 * 			1.2 隐式提交(ALTER，AUDIT，COMMENT，CONNECT，CREATE，DISCONNECT，DROP， EXIT，GRANT，NOAUDIT，QUIT，REVOKE，RENAME)
 * 			1.3 自动提交(SET AUTOCOMMIT ON;)
 */
class GoSQL{
	private $config = array (
			'host'=>'', // 服务器
			'port'=>'', // 端口
			'user'=>'', // 用户名
			'pwd'=>'', // 密码
			'db'=>''
	);
	private $setNames = 'SET NAMES UTF8';
	
	private $link;
	private $sqli;
	
	private $resultmode = MYSQLI_STORE_RESULT; // For using buffered resultsets 
	private $result = null; // 最后一次查询时返回的结果集对象；
	private $fieldsinfo = null; // 结果集的字段信息；
	
	private $tables; // selected table[一维数组]
	private $dbs; // 所有的数据库名称[一维数组]
	
	public $cache_sel_fields = array(); // 待选择的字段
	
	public $stack_where_slice = array(); // where_slice
	
	function __construct($host, $port, $user, $pwd, $db) {
		$this->config ['host'] = $host;
		$this->config ['port'] = $port; // PHP 3.0.0 对 server 添加 ":port" 支持。
		$this->config ['user'] = $user;
		$this->config ['pwd'] = $pwd;
		$this->config ['db'] = $db;
		
		// init
		$this->init();
	}
	function __desstruct() {
		$this->sqli->close(); // close the connection
	}
	
	private function init(){
		$this->connect();
		
		// set names
		$this->sqli->query($this->setNames);
		
		// get all dbs
		$this->__dbs();
		
		// get all tables
		$this->__tables();
	}
	
	private function connect(){ 
		$this->sqli = $mysqli =  new mysqli($this->config ['host'], $this->config ['user'], $this->config ['pwd'], $this->config ['db'],$this->config ['port']);
		if ($mysqli->connect_error) {
			die ( 'Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error );
		}
		return;
	}
	
	public function tables($show = false){
		if ($show) {
			if ($this->tables) {
				array_map ( function ($tb) {
					echo EOL, $tb;
				}, $this->tables );
			} else {
				echo 'table-is-empty';
			}
		}
		return $this->dbs;
	}
	
	/**
	 * get dblist
	 * 
	 * @since 2015/05/29 周五
	 * @param bool $show
	 */
	public function dbs($show = false){
		if ($show) {
			if ($this->dbs) {
				array_map ( function ($db) {
					echo EOL, $db;
				}, $this->dbs );
			} else {
				echo 'database-is-empty';
			}
		}
		return $this->dbs;
	}
	
	public function showFieldsInfo(){
		var_dump($this->fieldsinfo);
		return $this->fieldsinfo;
	}
	
	/**
	 * get all Tables of current db
	 */
	private function __tables() {
		$sql = "SHOW TABLES";
		/**
		 * mixed false on failure. 
		 * For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries mysqli_query will return a result object. 
		 * For other successful queries mysqli_query will return true. 
		 */
		$resobj = $this->sqli->query($sql,$this->resultmode); // mysqli_result implements Traversable
// 		$ele = iterator_to_array($resobj);// Copy the iterator into an array
		if($resobj){
			foreach($resobj as $row){
				$this->tables[] = $row['Tables_in_'.$this->config['db']];
			}
			$resobj->free();
		}
		return ;
	}
	
	/**
	 * get all dbs
	 */
	private function __dbs() {
		$sql = "SHOW DATABASES";
		$resobj = $this->sqli->query($sql,$this->resultmode); // mysqli_result implements Traversable
// 		$ele = iterator_to_array($resobj);// Copy the iterator into an array
		if($resobj){
			foreach($resobj as $row){
				$this->dbs[] = $row['Database'];
			}
			$resobj->free();
		}
		return ;
	}
	
	/**
	 * select db
	 * 
	 * @since 2015/05/28 周四
	 * @param string $dbname
	 */
	public function select_db($dbname){
		// check_db_exist
		false !== array_search($dbname, $this->dbs) or die("database $dbname not exists");
		
		// select db
		$this->config['db'] = $dbname ; 
		$this->sqli->select_db($dbname);
		
		// set names
		$this->sqli->query($this->setNames);
		
		// get all tables
		$this->__tables();
	}
	
	/**
	 * select fields
	 * 
	 * @since 2015/05/29 周五
	 * @param mixed $fields
	 * @param string $_ [optional]
	 * @abstract <pre>参数很灵活
	 * 			1.1 if array(one dimensional array),then push every element into cache_fields
	 * 			1.2 if string(contains ','),then explode to array and push every element into cache_fields
	 * 			1.3 if string ,then ...
	 * 			1.4 if empty string ,then not push
	 * 			1.5 ignore other case
	 * 		</pre>
	 * @return $this
	 * @abstract return the availale number of fields
	 * @access this function will empty old fields before push
	 */
	public function Q($fields = null,$_=null){
		
		$this->cache_sel_fields = array();
		
		$vars = func_get_args ();
		$available_fields = 0;
		
		if (empty ( $vars ))
			return $available_fields;
		
		foreach ( $vars as $r ) {
			if (is_array ( $r )) {
				foreach ( $r as $tail ) {
					if (! is_array ( $tail ) && '' !== trim ( $tail )) {
						$this->cache_sel_fields [] = trim ( $tail );
						$available_fields++;
					}
				}
			} else if (is_string ( $r ) && false !== strpos ( $r, ',' )) {
				$ar_fd = explode ( ',', $r );
				if ($ar_fd) {
					foreach ( $ar_fd as $tail ) {
						if (! is_array ( $tail ) && '' !== trim ( $tail )) {
							$this->cache_sel_fields [] = trim ( $tail );
							$available_fields++;
						}
					}
				}
			} else if (is_string ( $r ) && '' !== trim ( $r )) {
				$this->cache_sel_fields [] = trim ( $r );
				$available_fields++;
			}
		}
		return $this;
	}
	
	public function S($mix,$_){
	}
	
	
	/**
	 * go
	 * 
	 * @since 2015/05/29 周五
	 * @param mix $mix
	 * @param mix $_
	 */
	public function Go($mix= null,$_ = null){
		
	}
	
	public function where($mix = null ,$_ = null){
		// init where slice
		$this->stack_where_slice = array();
		
		$params = func_get_args();
		$paramN = func_num_args();
		if(0 === $paramN){
			return ;
		}
		
		$sqlwhere = new SQLWhereMaker;
		foreach($params as $p){
			$sqlwhere->get($p);
		}
		
		$slices = $sqlwhere->get_slice();
		return implode('', $slices);
	}
	
	private function dump_where($mix = null){
		
	}
	
	
}
$host = 'localhost';
$port = '3306';
$user = 'root';
$pwd = '';
$db = 'test';

$gosql = new GoSQL($host, $port, $user, $pwd, $db);

// $dbs = $pb->dbs(true); // method1

// echo EOL;

// $pb->tables(true); // method2

// $pb->select_db($dbs['3']); method3

// $pb->tables(true); //


// $n = $gosql->Q(null,false,array(),'age','name','SEX,gender, age as AGE');

// var_dump($gosql);

/**
 * where f1='b' and f2='c' and (f3>10 or f4>100) and f4 like 'v%' 
 * 
 * 1.1 =  array('f1'='b','f2'=>'d')
 * 1.2 >
 * 1.3 or
 * 
 * 
 */ 
// date-2:大致发散了一下可能的where形式...
/* $input = array (
		array (
				'a=' => 1,
				'b=' => 2,
				'c>' => 1,
				'c<=' => '100',
				'd like' => 'zhang%' 
		),
		'(',
		array ('a in'=>array(1,2,3,4)),
		array ('b not in'=>array(1,2,3,4)),
		'or',
		'name = 1',
// 		array('or'=>array('a'=>'1','a'=>2,'c'=>2,'a in'=> array(''))),
		')'
); */

$sql = $gosql->where(array (
				'a=' => 1,
				'b=' => 2,
				'c>' => 1,
				'c<=' => '100',
				'd like' => 'zhang%' 
		),
		'(',
		array ('a in'=>array(1,2,3,4),'f not in'=>array('43243','fhfdhduh',3,4)),
		array ('b not in'=>array(1,2,3,4)),
		array ('d not in'=>array(1,2,3,4)),
		'or',
		'name = 1',
		')'
);

echo $sql;
