<?php

/**
 * Generate a new eloquent model, including
 * relationships.
 *
 * @package 	bob
 * @author 		Dayle Rees
 * @copyright 	Dayle Rees 2012
 * @license 	MIT License <http://www.opensource.org/licenses/mit>
 */
class Generators_Model extends Generator
{
	/**
	 * Enable the timestamps string in models?
	 *
	 * @var string
	 */
	private $_timestamps = '';
	
	private $_table = '';

	/**
	 * Start the generation process.
	 *
	 * @return void
	 */
	public function __construct($args)
	{
		parent::__construct($args);

		// we need a controller name
		if ($this->class == null)
			Common::error('You must specify a model name.');

		// load any command line switches
		$this->_settings();

		// start the generation
		$this->_model_generation();

		// write filesystem changes
		$this->writer->write();
	}

	/**
	 * This method is responsible for generation all
	 * source from the templates, and populating the
	 * files array.
	 *
	 * @return void
	 */
	private function _model_generation()
	{
		$prefix = ($this->bundle == DEFAULT_BUNDLE) ? '' : Str::classify($this->bundle).'_';

		// set up the markers for replacement within source
		$markers = array(
			'#CLASS#'		=> $prefix.$this->class_prefix.$this->class,
			'#LOWER#'		=> $this->lower,
			'#TIMESTAMPS#'	=> $this->_timestamps
		);
		
		if ( $this->_table !== '' ){
			$pdo = DB::connection(Config::get('database.default'))->pdo;
			$all_columns = $pdo->query('DESCRIBE ' . $this->_table )->fetchAll( PDO::FETCH_COLUMN );
			
			$primary_keys = $pdo->query("SHOW INDEX FROM {$this->_table} WHERE Key_name = 'primary'")->fetchAll( PDO::FETCH_ASSOC );
			
			if ( count( $primary_keys ) > 0){
				
				if (count( $primary_keys ) > 1){
					$primary_keys_temp = array();
					
					foreach($primary_keys as $primary){
						$primary_keys_temp[] = $primary['column_name'];
					}
					
					$primary_keys = $primary_keys_temp;
				} else {
					$primary_keys = array( $primary_keys[0]['column_name'] );
				}
				
			} else {
				$primary_keys = '';
			}
			
			$columns = array();
			$foreign_keys = array();
			
			foreach($all_columns as $column_name){
				switch(true){
					case in_array($column_name, $primary_keys):
					case $column_name == 'created_at':
					case $column_name == 'updated_at':
					break;
					case strrpos($column_name, '_id') === strlen($column_name) - 3 && $column_name !== $this->_table . '_id':
						$foreign_keys[] = $column_name;
					default:
						$columns[] = $column_name;
					break;
				}
			}
			
			$table = $this->_table;
			
			$markers['#TABLE_PHP#'] = function($marker, $template) use ($columns, $foreign_keys, $primary_keys, $table){
				
				$matches = array();
				preg_match_all( '/#TABLE_PHP#(.*?)#END_TABLE_PHP#/s', $template, $matches );
				if ( count( $matches ) > 0 ){
					foreach($matches as $match){
						if (count($match) > 0){
							
							foreach($match as $matched_item){
								$code_to_parse = str_replace('#TABLE_PHP#', '', str_replace('#END_TABLE_PHP#', '', $matched_item) );

								ob_start();
								eval("?>".$code_to_parse."<?php ");
								$parsed_code = ob_get_clean();

								$template = str_replace($matched_item, $parsed_code, $template);
							}
						}
					}
				}
				
				return $template;
			};
			
			
		} else {
			//if the user has not specified a table but the TABLE_PHP directive is found, we need to fire a closure with dummy data
			//set up dummies
			$columns = array();
			$foreign_keys = array();
			$primary_keys = array();
			$table = '';
			$markers['#TABLE_PHP#'] = function($marker, $template) use ($columns, $foreign_keys, $primary_keys, $table){
				
				$matches = array();
				preg_match_all( '/#TABLE_PHP#(.*?)#END_TABLE_PHP#/s', $template, $matches );
				if ( count( $matches ) > 0 ){
					foreach($matches as $match){
						if (count($match) > 0){
							
							foreach($match as $matched_item){
								$code_to_parse = str_replace('#TABLE_PHP#', '', str_replace('#END_TABLE_PHP#', '', $matched_item) );

								ob_start();
								eval("?>".$code_to_parse."<?php ");
								$parsed_code = ob_get_clean();

								$template = str_replace($matched_item, $parsed_code, $template);
							}
						}
					}
				}
				
				return $template;
			};
		}
		
		// loud our model template
		$template = Common::load_template('model/model.tpl');

		// holder for relationships source
		$relationships_source = '';

		// loop through our relationships
		foreach ($this->arguments as $relation)
		{
			// if we have a valid relation
			if(! strstr($relation, ':')) continue;

			// split
			$relation_parts = explode(':', Str::lower($relation));

			// we need two parts
			if(! count($relation_parts) == 2) continue;

			// markers for relationships
			$rel_markers = array(
				'#SINGULAR#'		=> Str::lower(Str::singular($relation_parts[1])),
				'#PLURAL#'			=> Str::lower(Str::plural($relation_parts[1])),
				'#WORD#'			=> Str::classify(Str::singular($relation_parts[1])),
				'#WORDS#'			=> Str::classify(Str::plural($relation_parts[1]))
			);

			// start with blank
			$relationship_template = '';

			// use switch to decide which template
			switch ($relation_parts[0])
			{
				case "has_many":
				case "hm":
					$relationship_template = Common::load_template('model/has_many.tpl');
					break;
				case "belongs_to":
				case "bt":
					$relationship_template = Common::load_template('model/belongs_to.tpl');
					break;
				case "has_one":
				case "ho":
					$relationship_template = Common::load_template('model/has_one.tpl');
					break;
				case "has_and_belongs_to_many":
				case "hbm":
					$relationship_template = Common::load_template('model/has_and_belongs_to_many.tpl');
					break;
			}

			// add it to the source
			$relationships_source .= Common::replace_markers($rel_markers, $relationship_template);
		}

		// add a marker to replace the relationships stub
		// in the model template
		$markers['#RELATIONS#'] = $relationships_source;

		// add the generated model to the writer
		$this->writer->create_file(
			'Model',
			$prefix.$this->class_prefix.$this->class,
			$this->bundle_path.'models/'.$this->class_path.$this->lower.EXT,
			Common::replace_markers($markers, $template)
		);
	}

	/**
	 * Alter generation settings from artisan
	 * switches.
	 *
	 * @return void
	 */
	private function _settings()
	{
		if(Common::config('timestamps') or Common::config('t'))
			$this->_timestamps = "\tpublic static \$timestamps = true;\n\n";
		if(Common::config('table')) $this->_table = Common::config('table');
	}
}
