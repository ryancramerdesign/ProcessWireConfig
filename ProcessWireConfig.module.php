<?php

/**
 * ProcessWireConfig module
 *
 * Edit many ProcessWire $config settings from the admin. 
 * 
 * ProcessWire 2.x 
 * Copyright (C) 2015 by Ryan Cramer 
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 * 
 * http://processwire.com
 *
 */

class ProcessWireConfig extends Process {

	/**
	 * Contains the names of fields that are representing associative arrays
	 * 
	 * @var array
	 *
	 */
	protected $hasAssociativeArray = array();

	/**
	 * Extract the #xtra properties from phpdoc comment text and return them
	 *
	 * Possible properties include: pattern, input, notes, property
	 *
	 * @param string $comment
	 * @return array
	 *
	 */
	protected function extractCommentXtraProperties(&$comment) {

		if(strpos($comment, '#') === false) return array(); 
		if(!preg_match_all('{^ *#([a-zA-Z]+)\s+(.+)$}m', $comment, $matches)) return array();
		$xtra = array();

		foreach($matches[0] as $key => $line) {

			$comment = str_replace($line, '', $comment); // remove this line from comment
			$propertyName = $matches[1][$key];		
			$propertyVal = trim($matches[2][$key]);

			if($propertyName == 'pattern') { 
				// i.e. #pattern /^[a-z]+$/
				$xtra['pattern'] = $propertyVal; 

			} else if($propertyName == 'input') { 
				// i.e. #input text|textarea|checkbox
				$xtra['input'] = $propertyVal;

			} else if($propertyName == 'notes') {
				// i.e #notes These are my notes
				$xtra['notes'] = $propertyVal;

			} else if($propertyName == 'property') {
				if(!isset($xtra['property'])) $xtra['property'] = array();
				// i.e. #property string|int|bool Label
				if(preg_match('{^([a-z]+)\s+([_a-zA-Z0-9]+)\s?(.*)$}m', $propertyVal, $dm)) {
					$propertyType = $dm[1];
					$propertyName = $dm[2];
					$propertyLabel = $dm[3];
					$xtra['property'][$propertyName] = array(
						'name' => $propertyName, 
						'type' => $propertyType, 
						'label' => $propertyLabel
						);
				}
			}
		}

		return $xtra;
	}

	/**
	 * Extract config options from $data string consistent with that in /wire/config.php section
	 *
	 * @param string $data
	 * @return array
	 *
	 */
	protected function getConfigOptions($data) {

		static $used = array();
		$config = $this->wire('config');
		$items = array();

		$parts = explode('/**', $data); 

		foreach($parts as $comment) {

			$varpos = strpos($comment, '@var');
			$cfgpos = strpos($comment, "\n" . '$config->'); 

			if(!$varpos || !$cfgpos) continue; 

			$name = substr($comment, $cfgpos+10, 100); 
			$name = trim(substr($name, 0, strpos($name, "=")));

			// if this field was already defined in a previous call to getConfigOptions, skip it
			if(isset($used[$name])) continue; 

			$type = substr($comment, $varpos+5, 50); 
			$type = trim(substr($type, 0, strpos($type, "\n")));

			$xtra = array();

			// reduce comment only include everything up to @var
			$comment = substr($comment, 0, $varpos); 

			// remove phpdoc asterisks
			$comment = trim(preg_replace('{^[\t\r\n ]*[*]+[\t ]*}m', '', $comment)); 

			// extract the #xtra properties from comment (this also updates $comment)
			$xtra = $this->extractCommentXtraProperties($comment); 

			// extract label from comment (label is at the beginning before first \n
			$pos = strpos($comment, "\n");
			$label = substr($comment, 0, $pos);

			// extract description from comment (all text after label)
			$description = trim(substr($comment, $pos));
			$description = str_replace(array(":\n", ";\n", ".\n", ";;"), array(":<br>", ";<br>", ".<br>", "<br>"), $description); 
			$description = str_replace(array("\r", "\n"), " ", $description);
			$description = str_replace("<br>", "\n", $description); 

			// the current value present in the $config API var
			$value = $config->$name; 

			$item = array(
				'name' => $name, 
				'type' => $type, 
				'value' => $value, 
				'label' => $label, 
				'description' => $description, 
				//'default' => trim($matches[4][$key], "'\""), // default value (string)
				);

			// merge in xtra data, if there was any
			if(count($xtra)) $item = array_merge($item, $xtra); 

			$items[$item['name']] = $item;
			$used[$name] = $name; 
		}

		return $items;
	}

	/**
	 * Extract each section containing one or more $config definitions 
	 *
	 * @param string $data
	 * @return array
	 * @todo cache data in sesssion
	 *
	 */
	protected function getConfigSections($data) {

		$config = $this->wire('config');
		$sections = array();

		// top holds the data present before any sections begin (uncategorized section)
		$top = null;
	
		foreach(explode('/*** ', $data) as $c => $data) {

			if(!$c) {
				$top = $data; 
				continue; 
			}

			$pos = strpos($data, ' **********'); 
			if(!$pos) continue; 

			$headline = substr($data, 0, $pos); 
			$headline = ucwords(strtolower(trim($headline))); 
			$items = $this->getConfigOptions($data); 
			if(count($items)) $sections[$headline] = $items;
		}

		if($top) { 
			$items = $this->getConfigOptions($top); 	
			static $n = 0;
			if(count($items)) {
				$n++;
				$sections["_x$n"] = $items;
			}
		}

		return $sections;
	}

	/**
	 * Get an Inputfield that can provide input for the given $item
	 *
	 * @param array $item
	 * @param bool $processInput Specify true to also process input for the item now
	 * @return Inputfield
	 *
	 */
	protected function getInputfield(array $item, $processInput = false) {

		$modules = $this->wire('modules');
		$skip = false;
		$input = $this->wire('input');
		$markAsChanged = false;

		// if $item specifies an 'input' property, use that, otherwise use it's 'type' property
		if(isset($item['input'])) {
			$inputType = $item['input']; 
		} else {
			$inputType = $item['type'];
		}

		if($inputType == 'bool' || $inputType == 'checkbox') {
			// booleans
			$in = $modules->get('InputfieldCheckbox'); 
			if($item['value']) $in->attr('checked', 'checked'); 

		} else if($inputType == 'array') {
			// arrays
			$in = $modules->get('InputfieldTextarea'); 
			$val = '';
			foreach($item['value'] as $k => $v) {
				if(is_array($v)) {
					// avoid multidimensional arrays: we don't support at present
					$skip = true; 
					break;
				}
				if(is_int($k)) {
					// is this is a regular array? or maybe an associative that got mixed up as one?
					// check to see if it has a key=value in the value
					if(strpos($v, '=') && preg_match('/^([_a-zA-Z0-9]+)\s*=\s*(.*)$/', $v, $matches)) {
						$this->message("Converting $item[name] from regular to associative array", Notice::debug); 
						$k = trim($matches[1]);
						$v = trim($matches[2]);
						$markAsChanged = true; // force convert
					}
				}
				if(is_int($k)) {
					// this is a regular array
					$val .= "\n$v";
				} else {
					// this is an associative key=value array
					$this->hasAssociativeArray[$item['name']] = $item['name'];
					if(is_bool($v)) $v = (int) $v; // represent bools as integers
					$val .= "\n$k = $v";
				}
			}
			$in->attr('rows', count($item['value'])+2);
			$item['value'] = trim($val);

		} else if($inputType == 'timezone') {
			// timezones
			$timezones = timezone_identifiers_list();
			$in = $modules->get('InputfieldSelect'); 
			foreach($timezones as $t) $in->addOption($t);

		} else if($inputType == 'int' || $inputType == 'integer') { 
			// integers
			$in = $modules->get('InputfieldInteger'); 

		} else if($inputType == 'float') { 
			// floats
			$in = $modules->get('InputfieldFloat'); 

		} else if($inputType == 'textarea') { 
			// textareas
			$in = $modules->get('InputfieldTextarea'); 

		} else if($inputType != 'string' && $modules->isInstalled('Inputfield' . ucfirst($inputType))) {
			// some other Inputfield name
			$in = $modules->get('Inputfield' . ucfirst($inputType)); 

		} else {
			// text (default)
			$in = $modules->get('InputfieldText'); 
		}

		if($skip) return null;

		// notes
		$notes = isset($item['notes']) ? $item['notes'] . "\n\n" : "";
		if(isset($item['property'])) foreach($item['property'] as $info) {
			// if there is info for array properties, append it to notes
			$notes .= "**$info[name]:** $info[label]\n";	
		}
		$usage = "**API:** \$$item[type] = \$config->$item[name];";
		if($notes) $in->notes = trim($notes) . "\n\n$usage";
			else $in->notes = $usage; 
		if($item['name'] == 'debugIf') $in->notes .= "\n**Your IP:** $_SERVER[REMOTE_ADDR]";

		$in->attr('name', $item['name']); 
		$in->label = $item['label'];
		$in->description = $item['description'];
		if($in->className() != 'InputfieldCheckbox') $in->attr('value', $item['value']); 
		$in->resetTrackChanges(true);

		if($processInput) $in->processInput($input->post); 
		if($markAsChanged) $in->trackChange('value');

		return $in;
	}

	/**
	 * Return the custom-defined config section that is defined in this module
	 *
	 * @return array
	 *
	 */
	protected function getCustomConfigSection() {
		$items = array();
		foreach(explode("\n", $this->customConfig) as $line) {
			$parts = explode('=', $line, 4); 
			if(count($parts) < 2) continue; 
			foreach($parts as $key => $part) $parts[$key] = trim($part); 
			$name = $this->wire('sanitizer')->fieldName($parts[0]); 
			$items[$name] = array(
				'name' => $name, 
				'type' => strtolower($parts[1]),
				'label' => isset($parts[2]) ? $parts[2] : $name, 
				'description' => isset($parts[3]) ? $parts[3] : '', 
				'value' => $this->wire('config')->get($name)
				);
		}
		return array('_custom' => $items); 
	}

	/**
	 * Return the tabs for the interface, each containing one or more sections of items
	 *
	 * @return array
	 *
	 */
	protected function getConfigTabs() {

		$tabs = array();
		$config = $this->wire('config');

		$data = file_get_contents($config->paths->wire . "config.php"); 
		$tabs['wire'] = array(
			'label' => $this->_('Core'), 
			'sections' => $this->getConfigSections($data),
			);

		$data = file_get_contents($config->paths->site . "config.php"); 
		$this->checkLoaderCode($data);
		$tabs['site'] = array(
			'label' => $this->_('Site'), 
			'sections' => $this->getConfigSections($data),
			);

		$tabs['custom'] = array(
			'label' => $this->_('Custom'), 
			'sections' => $this->getCustomConfigSection(),
			);

		return $tabs; 
	}

	/**
	 * Build the config form
	 *
	 * @return InputfieldForm
	 *
	 */
	protected function buildForm() {

		$config = $this->wire('config');
		$modules = $this->wire('modules');

		$itemsChanged = array(); // name => item, of items that changed
		$remove = array(); // names of items to remove values for
		$labels = array(); // labels for all inputs
		$types = array(); // types for all inputs
		$configFileData = $this->getConfigFileData();

		$form = $modules->get('InputfieldForm');
		$form->attr('id', 'ProcessWireConfigForm'); 

		$submit = $modules->get('InputfieldSubmit');
		$submit->attr('name', 'submit_save_config'); 
		$submit->addClass('head_button_clone');
		$process = $this->wire('input')->post($submit->attr('name'));
		$modules->get('JqueryWireTabs');

		foreach($this->getConfigTabs() as $name => $tabInfo) {

			if(!count($tabInfo['sections'])) continue; 

			$tab = new InputfieldWrapper();
			$tab->addClass('WireTab');
			$tab->attr('title', $tabInfo['label']);

			foreach($tabInfo['sections'] as $headline => $items) {

				if(strpos($headline, '_') === 0) {
					// uncategorized: use placeholder rather than visible fieldset
					$fieldset = new InputfieldWrapper();
				} else {
					// section with a headline, use visible fieldset
					$fieldset = $modules->get('InputfieldFieldset');
					$fieldset->label = $headline;
					$fieldset->collapsed = Inputfield::collapsedYes; 
				}

				// add Inputfields to the fieldset
				foreach($items as $name => $item) {

					$in = $this->getInputfield($item, $process); 
					if(is_null($in)) continue; 
					$fieldset->add($in);

					if($process) {
						// process Inputfield now
						if($in->isChanged()) {
							$item['value'] = $in->attr('value'); 
							$itemsChanged[$name] = $item;
						}
					} else {
						// just display Inputfield
						if(isset($configFileData[$name])) {
							// if this is defined in the json config, make it open automatically
							$in->collapsed = Inputfield::collapsedNo;
							$fieldset->collapsed = Inputfield::collapsedNo;
						} else {
							// does not hold a custom config value, can be collapsed
							$in->collapsed = Inputfield::collapsedYes;
						}
					}
					$labels[$name] = $item['label'];
					$types[$name] = $item['type'];
				}	

				$tab->add($fieldset);
			}

			$form->add($tab);
		}

		// add a 'remove' tab that lets you remove custom config values
		$tab = new InputfieldWrapper();
		$tab->attr('title', $this->_('Remove'));
		$tab->addClass('WireTab');

		$rm = $modules->get('InputfieldCheckboxes'); 
		$rm->attr('name', '_removeItems');
		$rm->label = $this->_('Remove Custom Config Items'); 
		$rm->description = $this->_('The following items have been configured by this tool. Check the box next to any of them to remove the configuration setting, which will remove it and [if defined] restore control of it back to /site/config.php or /wire/config.php.') . ' ';
		$rm->description .= $this->_('This does not remove the field from being configurable, it only removes the currently configured value from this tool.'); 
		$rm->addClass('WireTab');
		$rm->table = true; 
		foreach($configFileData as $name => $value) {
			$type = isset($types[$name]) ? $types[$name] : 'text';
			$label = (isset($labels[$name]) ? $labels[$name] : $name) . "|$type|\$config->$name"; 
			$rm->addOption($name, $label); 
		}
		$tab->add($rm);
		if(count($rm->getOptions())) $form->add($tab); 

		if($process) {
			$rm->processInput($this->wire('input')->post); 
			$this->saveConfigFile($itemsChanged, $rm->attr('value')); 
		}

		$form->add($submit);

		$debugIf = $form->getChildByName('debugIf'); 
		if($debugIf && strlen(trim($debugIf->attr('value')))) {
			$debug = $form->getChildByName('debug'); 
			$debug->removeAttr('checked'); 
			$debug->collapsed = Inputfield::collapsedYes;
			$debug->notes .= "\n" . $this->_('**Note:** This option is disabled since "debugIf" is in use below.');
		}

		return $form;
	}

	/**
	 * Get the full path+filename used by the config file 
	 *
	 * @return string
	 * 
	 */
	protected function getConfigFileName() {
		$dir = $this->wire('config')->paths->assets . 'config/';
		if(!is_dir($dir)) wireMkdir($dir); 
		$file = $dir . 'config.json'; 
		if(file_exists($file)) {
			$url = str_replace($this->wire('config')->paths->root, '/', $file); 	
			if(!is_readable($file)) $this->error("File exists but is not readable: $url", Notice::log); 
			if(!is_writable($file)) $this->error("File exists but is not writable: $url", Notice::log); 
		} else {
			if(!is_writable($dir)) $this->error("Directory is not writable: $dir", Notice::log); 
		}
		return $file;
	}

	/**
	 * Get the data (associative array) from the config file
	 *
	 * @return array
	 *
	 */
	protected function getConfigFileData() {
		static $data = null;
		if(!is_null($data)) return $data; 
		$file = $this->getConfigFileName();
		$data = is_readable($file) ? json_decode(file_get_contents($file), true) : array();
		if(!is_array($data)) $data = array();
		return $data;
	}

	/**
	 * Return the cleaned value property for an array item
	 *
	 * This method goes along with the cleanConfigData() method. 
	 *
	 * @param array $item
	 * @return array
	 *
	 */
	protected function cleanConfigDataArrayItem(&$item) {

		$value = array(); // replacement value
		$name = $item['name'];

		// iterate through each line in the string to convert to array
		foreach(explode("\n", $item['value']) as $line) {

			$line = trim($line);	
			if(empty($line)) continue; 

			if(isset($this->hasAssociativeArray[$name])) {
				// if it's a key=value type (associative) we convert to associative array
				$pos = strpos($line, '='); 

				if(!$pos) {
					$this->error("Error processing line for field: $name (line: $line)"); 
					continue; 
				}

				$k = trim(substr($line, 0, $pos)); 
				$v = trim(substr($line, $pos+1)); 

				if(isset($item['property'][$k])) {
					// sanitize according to what #xtra property requires
					// @todo add support for more types
					$info = $item['property'][$k];
					$type = isset($info['type']) ? $info['type'] : 'string';
				} else {
					$type = 'string';
				}
				
				if($type == 'bool') {
					// bools can be either 'true' or '1', and 'false' or '0'
					if(stripos($v, 'true') !== false) $v = 1; 
					if(stripos($v, 'false') !== false) $v = 0; 
					$v = (bool) ((int) $v); 
				} else if($type == 'int') {
					$v = (int) $v; 
				} else {
					// default is string, which is ok as is
				}

				if(strlen($k)) $value[$k] = $v;

			} else {
				// regular array: just move line to array
				$value[] = $line;	
			}
		}

		return $value; 
	}

	/**
	 * Given an $items array, prepare the values for storage in JSON config file
	 *
	 * @param array $items
	 * @return array
	 *
	 */
	protected function cleanConfigData(array $items) {

		$data = array();

		foreach($items as $name => $item) {

			$value = $item['value'];

			if($item['type'] == 'bool') {
				$value = (bool) $value; 

			} else if($item['type'] == 'array') {
				$value = $this->cleanConfigDataArrayItem($item);

			} else if($item['type'] == 'int') {
				$value = (int) $value; 
					
			} else if(!empty($item['pattern'])) {
				if(!preg_match($item['pattern'], $value)) {
					$this->error("Value '$value' for '$item[label]' does not match required pattern."); 
					continue; 
				}
			}

			$data[$name] = $value; 
		}

		return $data;
	}

	/**
	 * Save the given $items array to the JSON config file
	 *
	 * This method redirects back to same page after completing the save.
	 *
	 * @param array $items
	 * @param array $removeItems Optionally names of items to remove
	 *
	 */
	protected function saveConfigFile(array $items, array $removeItems = array()) {

		$file = $this->getConfigFileName();
		$data = $this->cleanConfigData($items); // data from this request
		$_data = $this->getConfigFileData(); // existing data present in config.json
		$data = array_merge($_data, $data); // both of the above merged, with new overwriting existing

		// remove items from $data before writing if requested to do so
		foreach($removeItems as $name) {
			if(!isset($data[$name])) continue; 
			unset($data[$name]); 
			$this->message(sprintf($this->_('Removed: %s'), $name)); 
		}

		// prepare and write JSON config file
		$flags = defined("JSON_PRETTY_PRINT") ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0; 
		file_put_contents($file, json_encode($data, $flags)); 
		$chmod = $this->chmodConfig; 
		if(!$chmod) $chmod = $this->wire('config')->chmodFile;
		wireChmod($file, false, $chmod); 

		// identify changes
		if(count($items)) $this->message($this->_('Changes:') . " " . implode(', ', array_keys($items))); 
		$this->message($this->_('Saved config:') . " " . str_replace($this->wire('config')->paths->root, '', $file) . " (mode: $chmod)"); 

		// redirect back to same page
		$this->wire('session')->redirect('./');

	}

	/**
	 * Build the form and return for output
	 *
	 * @return string
	 *
	 */
	public function ___execute() {
		$form = $this->buildForm();
		$out = $form->render();
		$out .= "<p class='notes'>" . $this->_('Note: Sometimes changing config settings can cause unexpected behavior. If you change a setting that causes something to break, you can always undo it by removing or editing the file /site/assets/config/config.json.'); 
		return $out; 
	}	

	/**
	 * Check that the config.json loader is present in /site/config.php
	 *
	 * If not present, it will attempt to add it. If it can't add it, it will ask the user to do so. 
	 *
	 * @param string $data Optional string to check for loader code. 
	 * @return bool
	 *
	 */
	public function checkLoaderCode($data = '') {

		static $result = null;
		if(!is_null($result)) return $result;

		$file = $this->wire('config')->paths->site . 'config.php'; 
		if(empty($data)) $data = file_get_contents($file);
		if(strpos($data, 'config.json"') !== false) {
			$result = true; 
			return true; 
		}

		$code = $this->getLoaderCode();
		$url = $this->wire('config')->urls->site . 'config.php';

		if(is_writable($file) && strpos($data, '?' . '>') === false) {
			// we append to file only if it doesn't have a closing PHP tag somewhere in it
			$fp = fopen($file, "a"); 
			fwrite($fp, "\n\n$code\n"); 
			fclose($fp);
			$this->message("Updated $url to load " . dirname($url) . "/assets/config/config.json"); 
			$result = true; 
			return true; 
		}

		$out = 	"To enable ProcessWireConfig: Please copy and paste the following code to the end of your <u>$url</u> file:" . 
			"<br /><pre style='background:#fff;color:#333;padding:0.5em;line-height:1.2em;'>$code</pre>" . 
			"<small>This message was shown because config.php is not writable.</small>";

		$this->warning($out, Notice::allowMarkup); 

		$result = false;
		return false;
		
	}

	/**
	 * Return the code used to load the config.json file
	 *
	 * @returns string
	 *
	 */
	protected function getLoaderCode() {
		$code = '// ProcessWireConfig v1' . "\n" . 
			'if(($f = $config->paths->assets . "config/config.json") && is_readable($f)) {' . "\n" . 
			'  if($a = json_decode(file_get_contents($f), true)) $config->setArray($a);' . "\n" . 
			'}';
		return $code; 
	}

	/**
	 * Uninstall this module
	 *
	 */
	public function ___uninstall() {
		parent::___uninstall();

		$file = $this->getConfigFileName();
		if(file_exists($file)) {
			$this->message("Removed $file"); 
			unlink($file); 
		}

		$code = $this->getLoaderCode();

		$out = 	"Please manually remove this code from your /site/config.php file to complete the uninstall:" . 
			"<br /><pre>$code</pre>"; 

		$this->warning($out); 
	}
}

