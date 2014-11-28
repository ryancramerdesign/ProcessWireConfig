<?php

class ProcessWireConfigConfig extends ModuleConfig {

	public function __construct() {

		$this->add(array(
			array(
				'name' => 'customConfig', // name of field
				'type' => 'textarea', // type of field (any Inputfield module name)
				'label' => $this->_('Custom field definitions'), // field label
				'description' => $this->_('Specify one custom field definition per line, using one of the indicated formats.'), 
				'notes' => 
					$this->_('**Format option 1:** Name = Type') . "\n" . 
					$this->_('**Format option 2:** Name = Type = Label') . "\n" . 
					$this->_('**Format option 3:** Name = Type = Label = Description') . "\n\n" . 
					$this->_('**Name:** must be any combination of _a-zA-Z0-9 (no hyphens).') . "\n" . 
					$this->_('**Type:** can be "text", "textarea", or "checkbox".') . "\n" . 
					$this->_('**Label:** is optional and can be any text except newlines or equals signs.') . "\n" . 
					$this->_('**Description:** is optional and can be any text except newlines.') . "\n\n" . 
					$this->_('**Example 1:** site_title = text') . "\n" . 
					$this->_('**Example 2:** site_title = text = Site Title') . "\n" . 
					$this->_('**Example 3:** site_title = text = Site Title = You can access this property with $config->site_title'),
				'value' => 'site_title = text = Site Title = You can access this property with $config->site_title'
			),
			array(
				'name' => 'chmodConfig',
				'type' => 'text', 
				'label' => $this->_('File access mode for config file'), 
				'description' => $this->_('For security ensure that the file is readable and writable to the copy of PHP powering your site, but not writable to any other users on the system. The best setting will depend on your hosting environment.'), 
				'value' => $this->wire('config')->chmodFile, 
				'notes' => 'Example: 0600 (always start with a 0). Please verify the specified mode is safe in your server environment. If modified, you will want to re-save the config file under Setup > Config to commit the change. See [chmod man page](http://ss64.com/bash/chmod.html) for more details on file modes. Mode 0666 is not recommended.',
				'pattern' => '^0[0-9][0-9][0-9]$'
			),
		)); 
	}
}

