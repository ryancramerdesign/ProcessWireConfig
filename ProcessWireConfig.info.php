<?php

/**
 * ProcessWireConfig.info.php
 * 
 */

$info = array(
	'title' => 'ProcessWire Config', 
	'summary' => 'Enables you to customize most ProcessWire config settings from the admin, plus create your own.', 
	'version' => 2, 
	'author' => 'Ryan Cramer', 
	'icon' => 'gear', 
	'permission' => 'config-edit', 
	'permissions' => array(
		'config-edit' => 'Edit ProcessWire config settings (superuser recommended)'
	), 
	'page' => array(
		'name' => 'config',
		'parent' => 'setup', 
		'title' => 'Config'
	),
	'requires' => 'ProcessWire>=2.5.10'
);
