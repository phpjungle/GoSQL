<?php
/**
 * 
 * 构造SQL-WHERE的字符串处理类
 * 
 * @author PHPJunlge
 * @since 2015/05/31 周日
 *
 */
class SQLWhereMaker {
	var $stack_where_slice = array ();
	var $slices = array ();
	var $next_ele = null; // 下一个元素:用于判断or()的情况
	var $params = array ();
	public function __construct($params = null) {
		$this->params = $params;
		$exp=self::end_with_circle_start('AND (');
// 		var_dump($exp);exit;
	}
	/**
	 * is_string "("
	 *
	 * @since 2015/05/31 周日
	 * @param string $str        	
	 * @return boolean
	 */
	static function is_bracket_start($str) {
		return '(' === trim ( $str ) ? true : false;
	}
	
	/**
	 * is_string ")"
	 *
	 * @since 2015/05/31 周日
	 * @param string $str        	
	 * @return boolean
	 */
	static function is_bracket_end($str) {
		return ')' === trim ( $str ) ? true : false;
	}
	
	/**
	 * 判断当前数组元素是不是可以直接构造的数组
	 *
	 * @access input is array
	 * @param array $ar        	
	 * @return bool
	 */
	static function is_buildable_array($ar) {
		if (is_array ( $ar ) && $ar) {
			foreach ( $ar as $k => $v ) {
				$pure_k = strtolower ( trim ( $k ) ); // or (,)
			}
			return true;
		}
		return false;
	}
	
	/**
	 * is_in_array
	 *
	 * @since 15.06.13 周六
	 * @param string $k        	
	 * @param array $v        	
	 * @return boolean
	 */
	static private function is_in_array($k, $v) {
		$illegal = ($k = strtoupper ( trim ( $k ) ) and strstr ( $k, ' IN' ) and is_array ( $v ) and $v);
		return $illegal;
	}
	
	/**
	 * is_not_in_array
	 *
	 * @since 15.06.13 周六
	 * @param string $k        	
	 * @param array $v        	
	 * @return boolean
	 */
	static private function is_not_in_array($k, $v) {
		$k = strtoupper ( trim ( $k ) );
		$inc_not_in = preg_match ( '/ NOT\s.*IN/', $k );
		
		$illegal = $inc_not_in and is_array ( $v ) and $v;
		return $illegal;
	}
	
	/**
	 * is_or_array
	 *
	 * @since 2015/05/31 周日
	 * @access input is array
	 * @param string $k
	 * @param array $v        	
	 * @return bool
	 */
	static private function is_or_array($k,$v) {
		if ('or' === strpos ( strtolower ( trim ( $k ) ) ) AND is_array($v) AND $v) {
			return true;
		}
		return false;
	}
	
	/**
	 * is_or
	 * 
	 * @since 15.06.14 周日
	 * @param string $k
	 * @return boolean
	 */
	static private function is_or($k){
		return 'OR' === strtoupper ( trim ( $k ) );
	}
	
	/**
	 * index=>or
	 * 
	 * @since 2015/05/31 周日
	 * @param array $ar
	 */
	private function or_arrays($ar){
		if(is_array($ar) AND $ar){
			$new = array('(');
			$n = count($ar);
			if(1 === $n){
				
			}else{
				
			}
		}
	}
	
	private function in_arrays($fields,$ds){
		
	}
	
	/**
	 * 判断字符串是不是以(开始
	 * 
	 * @since 15.06.14 周日
	 * @param array $ar
	 */
	static private function end_with_circle_start($k){
		if(!is_string($k))
			return false;
		
		return  '(' === substr(trim($k), -1);
	}
	
	/**
	 * get
	 *
	 * @since 2015/05/31 周日
	 * @param mix $vars      
	 * @abstract 细节问题:还要判断索引类别	
	 * 		KEY_TYPE:
	 * 				1.连接符类:(,),OR;
	 * 				2.关键字:IN,NOT IN,2
	 * 				3.完整表达式:(a > 1)
	 * 				4.非完整表达式: a>1（） 一般情况下,标准的变量命名中间不带有空格=>@2015/06/10 周三
	 * 				5.如何判断一个字符串是否是表达式，还是一个标准的变量命名?（同时还要结合key对应的value,记住，任何一个数组都是键值对）
	 * 				6.字符串中可能包含换行符
	 */
	public function get($vars) {
		if (empty ( $vars )) {
			return;
		}
		
		$legal = false;
		$slice_num = count($this->stack_where_slice);
		$last_slice = isset($this->stack_where_slice[$slice_num-1])?$this->stack_where_slice[$slice_num-1]:null;
		$not_need_and_prefix = (0 === $slice_num or self::end_with_circle_start ( $last_slice ) or self::is_or($last_slice)); # 潜在的Bug带有orand的bool表达式在复制给其他变量的时候记得加括号或者使用&&||
		
		#case-1:type-string
		if (is_string ( $vars ) and ($vars = trim ( $vars ))) {
			# 判断上一个是不是 (结尾
			if (self::is_bracket_start ( $vars )) { // or {
				$this->stack_where_slice [] = $not_need_and_prefix ? ' ( ' : ' AND ( ';
			} elseif (self::is_bracket_end ( $vars )) {
				$this->stack_where_slice [] = ')';
			} elseif (self::is_or ( $vars )) {
				$this->stack_where_slice [] = ' OR ';
			} else {
				$this->stack_where_slice [] = $vars;
			}
		#case-2:type-array
		} elseif (is_array ( $vars ) and $vars) {
			# 判断上一个是不是 (结尾
			$this->stack_where_slice [] = '<hr>';
			$this->stack_where_slice [] = $not_need_and_prefix ? ' ( ' : ' AND ( ';
			
			$key_type = array('(',')','or','%s in','%s not in');
			$tick = 0;
			foreach($vars as $type =>$v){
				0 === $tick++ or $this->stack_where_slice []  = ' AND '; # 单个AND有空格
				
				#case-1:is in array
				if(self::is_in_array($type, $v)){
					$this->stack_where_slice [] = sprintf('%s (%s)',trim($type),implode("','", $v));
					continue;
				}
				
				#case-2:is not in array
				if(self::is_not_in_array($type, $v)){
					$this->stack_where_slice [] = sprintf('%s (\'%s\')',trim($type),implode("','", $v));
					continue;
				}
				
				#case-3:is or array
				#case-default:
				$this->stack_where_slice [] = sprintf(' %s %s ',$type,$v);
			}
			$this->stack_where_slice [] = ')'; // )
		}
		return;
	}
	
	function get_slice(){
		return $this->stack_where_slice;
	}
}

?>
