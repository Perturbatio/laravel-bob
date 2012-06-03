<?php

/**
 * Generate a controller, its actions and associated views.
 *
 * @package 	bob
 * @author 		Dayle Rees
 * @copyright 	Dayle Rees 2012
 * @license 	MIT License <http://www.opensource.org/licenses/mit>
 */
class Generators_Controller extends Generator
{
	/**
	 * The view file extension, can also be blade.php
	 *
	 * @var string
	 */
	private $_view_extension = EXT;
	
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
			Common::error('You must specify a controller name.');

		// set switches
		$this->_settings();

		// start the generation
		$this->_controller_generation();

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
	private function _controller_generation()
	{
		$prefix = ($this->bundle == DEFAULT_BUNDLE) ? '' : Str::classify($this->bundle).'_';
		$view_prefix = ($this->bundle == DEFAULT_BUNDLE) ? '' : $this->bundle.'::';

		// set up the markers for replacement within source
		$markers = array(
			'#CLASS#'		=> $prefix.$this->class_prefix.$this->class,
			'#LOWER#'		=> $this->lower,
			'#LOWERFULL#'	=> $view_prefix.Str::lower(str_replace('/','.', $this->class_path).$this->lower)
		);
		
		if ( $this->_table !== '' ){
			$pdo = DB::connection(Config::get('database.default'))->pdo;
			
			$all_columns = $pdo->query('DESCRIBE ' . $this->_table )->fetchAll( PDO::FETCH_COLUMN );
			$table_primary = $pdo->query("SHOW INDEX FROM tasks WHERE Key_name = 'primary'")->fetchAll( PDO::FETCH_ASSOC );
			
			if ( count( $table_primary ) > 0){
				
				if (count( $table_primary ) > 1){
					$table_primary_temp = array();
					foreach($table_primary as $primary){
						$table_primary_temp[] = $primary['column_name'];
					}
					$table_primary = $table_primary_temp;
				} else {
					$table_primary = array( $table_primary[0]['column_name'] );
				}
				
			} else {
				$table_primary = '';
			}
			
			
			$columns = array();
			$foreign_keys = array();
			
			foreach($all_columns as $column_name){
				switch(true){
					case in_array($column_name, $table_primary):
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
			
			$markers['#TABLE_PHP#'] = function($marker, $template) use ($columns, $foreign_keys, $table_primary){
				
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
			$markers['#TABLE_PHP#'] = function($marker, $template) {
				
				$result = $template;
				$matches = array();
				preg_match_all( '/#TABLE_PHP#(.*?)#END_TABLE_PHP#/s', $result, $matches );

				if ( count( $matches ) > 0 ){
					foreach($matches as $match){
						if (count($match) > 0){
							$template = str_replace($match[0], '/* no table specified in bob command line but TABLE_PHP found in template */', $template);
							Common::log('no table specified in bob command line but TABLE_PHP found in template');
							
						}
					}
				}
				
				return $template;
			};
		}
		
		
		// loud our controller template
		$template = Common::load_template('controller/controller.tpl');

		// holder for actions source, and base templates for actions and views
		$actions_source 	= '';
		$action_js_source	= '';
		
		$main_action_template 	= Common::load_template('controller/action.tpl');
		$main_view_template 		= Common::load_template('controller/view.tpl');

		$restful = (strstr(implode(' ', $this->arguments), ':')) ? true : false;

		array_unshift($this->arguments, 'index');

		// loop through our actions
		foreach ($this->arguments as $action)
		{
			$verb = ($restful) ? 'get' :'action';

			if(strstr($action, ':'))
			{
				$parts = explode(':', $action);

				if (count($parts) == 2)
				{
					$verb = Str::lower($parts[0]);
					$action = Str::lower($parts[1]);
				}
			}

			// add the current action to the markers
			$markers['#ACTION#'] = Str::lower($action);
			$markers['#VERB#'] = $verb;
			
			$custom_action_template_name = 'controller/action.' . Str::lower($action) . '.tpl';
			if ( Common::template_exists($custom_action_template_name) ){
				$action_template = Common::load_template($custom_action_template_name);
			} else {
				$action_template 	= $main_action_template;
			}
			
			// append the replaces source
			$actions_source .= Common::replace_markers($markers, $action_template);

			$file_prefix = ($restful) ? $verb.'_' :'';
			
			$custom_view_template_name = 'controller/view.' . Str::lower($action) . '.tpl';
			if ( Common::template_exists($custom_view_template_name) ){
				$view_template = Common::load_template($custom_view_template_name);
			} else {
				$view_template 	= $main_view_template; 
			}
			$viewdir = Common::config('viewdir');

			if ( !$viewdir || $viewdir === true ){
				$viewdir = '';
			} else {
				Common::log('{c}[ {y}Writing Views to ' . $viewdir . '{c}]');
			}

			// add the file to be created
			$this->writer->create_file(
				'View',
				$this->class_path.$this->lower.'/'.$file_prefix.Str::lower($action).$this->_view_extension,
				$this->bundle_path.'views/' . $viewdir . '/' .$this->class_path.$this->lower.'/'.$file_prefix.Str::lower($action).$this->_view_extension,
				Common::replace_markers($markers, $view_template)
			);
			
			//custom javascript
			$custom_action_js_name = 'controller/view.' . Str::lower($action) . '.js.tpl';
			if ( Common::template_exists($custom_action_js_name) ){
				Common::log('{c}[ {y}Creating JS{c}]');
				$action_js_template = Common::load_template($custom_action_js_name);
				
				$action_js_source .= Common::replace_markers($markers, $action_js_template);
				Common::log( '{c}[ {y}Writing: ' . path('public').'js/' . $this->class_path.$this->lower.'-'.$file_prefix.Str::lower($action). '.js{c}' );
				//Common::log( '/'.$this->lower.'/'.$this->lower.'-'.$file_prefix.Str::lower($action).$this->_view_extension );
				
				// add the file to be created
				$this->writer->create_file(
					'asset_js',
					$this->class_path.$this->lower.'-'.$file_prefix.Str::lower($action). '.js',
					path('public').'js/' .$this->lower. '/' . $this->lower.'-'.$file_prefix.Str::lower($action). '.js' ,
					Common::replace_markers($markers, $action_js_template)
				);
			}
			
			
		}

		// add a marker to replace the actions stub in the controller
		// template
		$markers['#ACTIONS#'] = $actions_source;
		$markers['#RESTFUL#'] = ($restful) ? "\n\tpublic \$restful = true;\n" : '';

		// added the file to be created
		$this->writer->create_file(
			'Controller',
			$markers['#CLASS#'].'_Controller',
			$this->bundle_path.'controllers/'.$this->class_path.$this->lower.EXT,
			Common::replace_markers($markers, $template)
		);

		$this->writer->append_to_file(
			$this->bundle_path.'routes.php',
			"\n\n// Route for {$markers['#CLASS#']}_Controller\nRoute::controller('{$markers['#LOWERFULL#']}');"
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
		if(Common::config('blade')) $this->_view_extension = BLADE_EXT;
		if(Common::config('table')) $this->_table = Common::config('table');
	}
}
