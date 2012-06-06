<?php
/**
 * Model #CLASS# is used for ...
 *
 ****************/
class #CLASS# extends Eloquent {
	#TABLE_PHP#<?php
	echo "static::\$table = \"{$table}\";";
	?>#END_TABLE_PHP#
#TIMESTAMPS##RELATIONS#
	
	/**
	 *
	 * @param type $input
	 * @param type $exclude
	 * @return true|Validator
	 */
	static public function validate($input, $exclude_fields = array(), $ignore_id = 0){
		$rules = array(//enter validation rules here
		#TABLE_PHP#<?php
		foreach( $columns as $column_name ){
			if ($column_name == 'name'){
			echo "
			'$column_name' => 'required|unique:'.static::\$table.','.$column_name.','.\$ignore_id,";
			} else {
			echo "
			'$column_name' => 'required',";
			}
		}
		?>
		#END_TABLE_PHP#
		);
		
		$messages = array(//enter validation messages here
		#TABLE_PHP#<?php
		foreach( $columns as $column_name ){
		echo "
			'$column_name' => '',";
		}
		?>
		#END_TABLE_PHP#
		);
		
		if (!is_array($exclude_fields)){
			$exclude_fields = array($exclude_fields);
		}
		foreach( $exclude_fields as $rule_name ){
			if ( isset( $rules[$rule_name] ) ){
				unset($rules[$rule_name]);
			}
		}
		
		$v = Validator::make( $input, $rules, $messages );

		if ($v->valid()){
			return true;
		}
		return $v;
		
	}
}
