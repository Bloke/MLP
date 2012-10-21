<?php

$plugin['name'] = 'gbp_admin_library';
$plugin['version'] = '0.4.2';
$plugin['author'] = 'Graeme Porteous';
$plugin['author_uri'] = 'http://rgbp.co.uk/projects/textpattern/gbp_admin_library/';
$plugin['description'] = 'GBP\'s Admin-Side Library';
$plugin['type'] = '2';

@include_once('../zem_tpl.php');

if (0) {
?>
<!-- CSS SECTION
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
div#adminlib_help td { vertical-align:top; }
div#adminlib_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
div#adminlib_help code.code_tag { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
div#adminlib_help a:link, div#adminlib_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
div#adminlib_help a:hover, div#adminlib_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
div#adminlib_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
div#adminlib_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
div#adminlib_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
</style>
# --- END PLUGIN CSS ---
-->
<!-- HELP SECTION
# --- BEGIN PLUGIN HELP ---

<div id="adminlib_help">

h1(#top). Graeme Porteous' Admin Library.

Provides basic classes for building the admin side of your own, derived, plugins.

</div>

# --- END PLUGIN HELP ---
-->
<?php
}
# --- BEGIN PLUGIN CODE ---

// Constants
define('gbp_tab', 'tab');
define('gbp_id', 'id');

class GBPPlugin {
	// Internal variables
	var $plugin_name;
	var $title;
	var $event;
	var $message = '';
	var $tabs = array();
	var $active_tab = 0;
	var $use_tabs = false;
	var $gp = array();
	var $preferences = array();
	var $permissions = '1,2,3,4,5,6';
	var $wizard_key;
	var $wizard_installed = false;

	// Constructor
	function GBPPlugin ($title = '', $event = '', $parent_tab = '') {

		global $txp_current_plugin;

		// Store a reference to this class so we can get PHP 4 to work
		if (version_compare(phpversion(), '5.0.0', '<'))
			global $gbp_admin_lib_refs; $gbp_admin_lib_refs[$txp_current_plugin] = &$this;

		// Get the plugin_name from the global txp_current_plugin variable
		$this->plugin_name = $txp_current_plugin;

		// When making a GBPAdminView there must be event attributes
		$this->event = $event;

		// Add privs for this event
		global $txp_permissions;
		$perms = @$txp_permissions[$this->permissions];
		add_privs($this->event, ($perms ? $perms : $this->permissions));

		if (@txpinterface == 'admin') {
			// We are admin-side.

			// There must be title and event attributes
			$this->title = $title;

			// The parent_tab can only be one of four things, make sure it is
			if ($event AND $title AND $parent_tab AND array_search($parent_tab, array('content', 'presentation', 'admin', 'extensions')) === false)
				$parent_tab = 'extensions';

			// Set up the get-post array
			$this->gp = array_merge(array('event', gbp_tab), $this->gp);

			// Check if our event is active, if so call preload()
			if (gps('event') == $event) {

				$this->load_preferences();

				$this->preload();

				// Tabs should be loaded by now
				if ($parent_tab && $this->use_tabs) {

					foreach (array_keys($this->tabs) as $key) {
						$tab = &$this->tabs[$key];
						$tab->php_4_fix();
						if (is_a($tab, 'GBPWizardTabView')) {
							$this->wizard_key = $key;
							$this->wizard_installed = $tab->installed();
						}
					}

					if (!$this->wizard_installed && $this->wizard_key)
						$this->active_tab = $this->wizard_key;

					// Let the active_tab know it's active and call it's preload()
					$tab = &$this->tabs[$this->active_tab];
					$tab->is_active = 1;
					$tab->preload();
				}
			}

			// Call txp functions to register this plugin
			if ($parent_tab) {
				register_tab($parent_tab, $event, $title);
				register_callback(array(&$this, 'render'), $event, null, 0);
			}
		}
		if (@txpinterface == 'public')
			$this->load_preferences();
	}

	function load_preferences () {
		/*
		Grab and store all preferences with event matching this plugin, combine gbp_partial
		rows and decode the value if it's of custom type.
		*/
		global $prefs;

		// Override the default values if the prefs have been stored in the preferences table.
		$preferences = safe_rows("name, html as type",
		'txp_prefs', "event = '{$this->event}' AND html <> 'gbp_partial'");

		// Add the default preferences which aren't saved in the db but defined in the plugin's source.
		foreach ($this->preferences as $key => $pref) {
			$db_pref = array('name' => $this->plugin_name.'_'.$key, 'type' => $pref['type']);
			if (array_search($db_pref, $preferences) === false)
				$preferences[] = $db_pref + array('default_value' => $pref['value']);
		}

		foreach ($preferences as $name => $pref) {
			// Extract the name and type.
			extract($pref);

			// The base name which gbp_partial preferences could share.
			$base_name = $name;

			// Combine the extended preferences, which go over two rows into one preference.
			$i = 0; $value = '';
			while (array_key_exists($name, $prefs)) {
				$value .= $prefs[$name];
				unset($prefs[$name]);
				// Update name for the next array_key_exists check.
				$name = $base_name.'_'.++$i;
			}

			// If there is no value then revert to the default value if it exists.
			if ((!$value || (@!$value[0] && count($value) <= 1)) && isset($default_value))
				$value = $default_value;

			// Else if this a custom type (E.g. gbp_serialized OR gbp_array_text)
			// call it's db_get method to decode it's value.
			else if (is_callable(array(&$this, $type)))
				$value = call_user_func(array(&$this, $type), 'db_get', $value);

			// Re-set the combined and decoded value to the global prefs array.
			$prefs[$base_name] = $value;

			// If the preference exists in our preference array set the new value and correct type.
			$base_name = substr($base_name, strlen($this->plugin_name.'_'));
			if (array_key_exists($base_name, $this->preferences))
				$this->preferences[$base_name] = array('value' => $value, 'type' => $type);
		}
	}

	function set_preference ($key, $value, $type = '') {
		global $prefs, $txp_current_plugin;

		// If the plugin_name or event isn't set is it safe to assume
		// $txp_current_plugin and gps('event') are correct?
		$plugin = ($this->plugin_name) ? $this->plugin_name : $txp_current_plugin;
		$event = ($this->event) ? $this->event : gps('event');

		// Set some standard db fields
		$base_name = $plugin.'_'.$key;
		$name = $base_name;

		// If a type hasn't been specified then look the key up in our preferences.
		// Else assume it's type is 'text_input'.
		if (empty($type) && array_key_exists($key, $this->preferences))
			$type = $this->preferences[$key]['type'];
		else if (empty($type))
			$type = 'text_input';

		// Set the new value to the global prefs array and if the preference exists
		// to our own preference array.
		$prefs[$name] = $value;
		if (array_key_exists($key, $this->preferences))
			$this->preferences[$key] = array('value' => $value, 'type' => $type);

		// If this preference has a custom type (E.g. gbp_serialized OR gbp_array_text)
		// call it's db_set method to encode the value.
		if (is_callable(array(&$this, $type)))
			$value = call_user_func(array(&$this, $type), 'db_set', $value);

		// It is possible to leave old 'gbp_partial' perferences when reducing the
		// lenght of a preference. Remove them all.
		$this->remove_preference($name);

		// Make sure preferences which equal NULL are saved
		if (empty($value))
			set_pref($name, '', $event, 2, $type);

		$i = 0; $value = doSlash($value);
		// Limit preference to approximatly 4Kb of data. I hope this will be enough
		while (strlen($value) && $i < 16) {
			// Grab the first 255 chars from the value and strip any backward slashes which
			// cause the SQL to break.
			$value_segment = rtrim(substr($value, 0, 255), '\\');

			// Set the preference and update name for the next array_key_exists check.
			set_pref($name, $value_segment, $event, 2, ($i ? 'gbp_partial' : $type));
			$name = $base_name.'_'.++$i;

			// Remove the segment of the value which has been saved.
			$value = substr_replace($value, '', 0, strlen($value_segment));
		}
	}

	function remove_preference ($key) {
		$event = $this->event;
		safe_delete('txp_prefs', "event = '$event' AND ((name LIKE '$key') OR (name LIKE '{$key}_%' AND html = 'gbp_partial'))");
	}

	function gbp_serialized ($step, $value, $item = '') {
		switch (strtolower($step)) {
			default:
			case 'ui_in':
				if (!is_array($value)) $value = array($value);
				return text_input($item, implode(',', $value), 50);
			break;
			case 'ui_out':
				return explode(',', $value);
			break;
			case 'db_set':
				return serialize($value);
			break;
			case 'db_get':
				return unserialize($value);
			break;
		}
		return '';
	}

	function gbp_array_text ($step, $value, $item = '') {
		switch (strtolower($step)) {
			default:
			case 'ui_in':
				if (!is_array($value)) $value = array($value);
				return text_input($item, implode(',', $value), 50);
			break;
			case 'ui_out':
				return explode(',', $value);
			break;
			case 'db_set':
				return implode(',', $value);
			break;
			case 'db_get':
				return explode(',', $value);
			break;
		}
		return '';
	}

	function &add_tab ($tab, $is_default = NULL) {

		// Check to see if the tab is active
		if (($is_default && !gps(gbp_tab)) || (gps(gbp_tab) == $tab->event))
			$this->active_tab = count($this->tabs);

		if (is_a($tab, 'GBPWizardTabView')) {
			$tab->parent = &$this;

			// Wizard routines
			$step = gps('step');
			if (in_array($step, array('setup', 'cleanup'))) {
				$installation_steps = ($step == 'setup')
					? array_keys($tab->installation_steps)
					: array_reverse(array_keys($tab->installation_steps));
				foreach ($installation_steps as $key) {
					$function = array(&$tab, $step.'_'.$key);
					if (is_callable($function)) {
						$optional = @$tab->installation_steps[$key]['optional'];
						if (($optional && gps('optional_'.$key)) || !$optional)
							call_user_func($function);
						else
							$tab->add_report_item($tab->installation_steps[$key][$step], 'skipped');
					}
				}
			}
		}

		// Store the tab
		$this->tabs[] = $tab;

		// We've got a tab, lets assume we want to use it
		$this->use_tabs = true;

		return $this;
	}

	function preload () {
		// Override this function if you require sub tabs.
	}

	function render () {

		// render() gets called because it is specified in txp's register_callback()

		// After a callback we lose track of the current plugin in PHP 4
		global $txp_current_plugin;
		$txp_current_plugin = $this->plugin_name;

		$this->render_header();
		$this->main();

		if ($this->use_tabs) {

			$this->render_tabs();
			$this->render_tab_main();
		}

		$this->render_footer();
		$this->end();
	}

	function render_header () {

		// Render the pagetop, a txp function
		pagetop($this->title, $this->message);

		// Once a message has been used we discard it
		$this->message = '';
	}

	function render_tabs () {
		// This table, which contains the tags, will have to be changed if any improvements
		// happen to the admin interface
		$out[] = '<div id="'.$this->plugin_name.'_control" class="txp-control-panel">';
		$out[] = '<p class="txp-buttons">';
		$style = '';

		// Force the wizard to be the only tab if the plugin isn't installed
		if ($this->wizard_installed || !$this->wizard_key)
			foreach (array_keys($this->tabs) as $key) {
				// Render each tab but keep a reference to the tab so any changes made are stored
				$tab = &$this->tabs[$key];
				$out[] = $tab->render_tab();
				$fn = array(&$tab , 'get_canvas_style');
				if (is_callable($fn)) {
					$res = call_user_func($fn);
					$style = (false!==$res) ? $res : $style ;
				}
			}
		else {
			$tab = &$this->tabs[$this->wizard_key];
			$out[] = $tab->render_tab();
			$fn = array(&$tab , 'get_canvas_style');
			if (is_callable($fn)) {
				$res = call_user_func($fn);
				$style = (false!==$res) ? $res : $style ;
			}
		}

		$out[] = '</p>';
		$out[] = '</div><div>';

		echo join('', $out);
	}

	function main () {
		// Override this function
	}

	function render_tab_main () {

		// Call main() for the active_tab
		$tab = &$this->tabs[$this->active_tab];
		if (($this->wizard_installed || !$this->wizard_key || $tab->event == 'wizard') && has_privs($this->event.'.'.$tab->event))
			$tab->main();
		else
			echo '<p>'.gTxt('restricted_area').'</p>';
	}

	function render_footer () {

		// A simple footer
		global $plugins_ver;
		$out[] = '</div>';
		$out[] = '<div style="padding-top: 3em; text-align: center; clear: both;">';
		$out[] = $this->plugin_name;
		if (@$plugins_ver[$this->plugin_name])
		 	$out[] = ' &#183; ' . $plugins_ver[$this->plugin_name];
		$out[] = '</div>';

		echo join('', $out);
	}

	function end () {
		// Override this function
	}

	function form_inputs () {

		$out[] = eInput($this->event);

		if ($this->use_tabs) {

			$tab = $this->tabs[$this->active_tab];
			$out[] = hInput(gbp_tab, $tab->event);
		}

		return join('', $out);
	}

	function url ($vars = array(), $gp = false) {
		/*
		Expands $vars into a get style url and redirects to that location. These can be
		overriden with the current get, post, session variables defined in $this->gp
		by setting $gp = true
		NOTE: If $vars is not an array or is empty then we assume $gp = true.
		*/

		if (!is_array($vars))
			$vars = gpsa($this->gp);
		else if ($gp || !count($vars))
			$vars = array_merge(gpsa($this->gp), $vars);

		foreach ($vars as $key => $value) {
			if (!empty($value))
				$out[] = $key.'='.urlencode($value);
		}

		$script = hu.basename(txpath).'/index.php';
		return $script . (isset($out)
			? '?'.join('&', $out)
			: '');
	}

	function redirect ($url = '', $status = 303) {
		/*
		If $vars is an array, use url() to expand as an GET style url and redirect to
		that location using the HTTP status code definition defined by $status.
		*/

		static $status_definitions = array (
			301 => "Moved Permanently",
			302 => "Found",
			303 => "See Other",
			307 => "Temporary Redirect"
		);

		if (!in_array($status, array_keys($status_definitions)))
			$status = 303;

		if (is_array($url))
			$url = $this->url($url);
		else {
			$url_details = parse_url($url);
			if (!@$url_details['scheme'])
				$url = 'http://'.$url;
		}

		if (empty($_SERVER['FCGI_ROLE']) and empty($_ENV['FCGI_ROLE'])) {
			header('HTTP/1.1 '.$status.' '.$status_definitions[$status]);
			header('Status: '.$status);
			header('Location: '.$url);
			header('Connection: close');
			header('Content-Length: 0');
			exit(0);
			} else {
			global $sitename;
			$url = htmlspecialchars($url, ENT_COMPAT, 'UTF-8');
			echo <<<END
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
			<head>
				<title>$sitename</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<meta http-equiv="refresh" content="0;url=$url" />
			</head>
			<body>
			<a href="$url">{$status_definitions[$status]}</a>
			</body>
			</html>
END;
		}
	}

	function pref ($key) {
		global $prefs, $txp_current_plugin;

		$plugin = ($this->plugin_name) ? $this->plugin_name : $txp_current_plugin;
		$key = $plugin.'_'.$key;

		if (@$this->preferences[$key])
			return $this->preferences[$key]['value'];
		if (@$prefs[$key])
			return $prefs[$key];
		return NULL;
	}
}

class GBPAdminTabView {
	//	Internal variables
	var $title;
	var $event;
	var $is_active;
	var $parent;
	var $permissions = '1,2,3,4,5,6';

	//	Constructor
	function GBPAdminTabView ($title, $event, &$parent, $is_default = NULL) {

		$this->title = (function_exists('mb_convert_case'))
			? mb_convert_case($title, MB_CASE_TITLE, "UTF-8")
			: ucwords($title);

		$this->event = $event;

		// Note: $this->parent only gets set correctly for PHP 5
		$this->parent =& $parent->add_tab($this, $is_default);

		// Add privs for this tab
		global $txp_permissions;
		$perms = @$txp_permissions[$this->permissions];
		add_privs($this->parent->event.'.'.$this->event, ($perms ? $perms : $this->permissions));
	}

	function php_4_fix () {

		// Fix references in PHP 4 so sub tabs can access their parent tab
		if (version_compare(phpversion(), '5.0.0', '<')) {
			global $txp_current_plugin, $gbp_admin_lib_refs;
			$this->parent =& $gbp_admin_lib_refs[$txp_current_plugin];
		}
	}

	function preload () {
		// Override this function
	}

	function render_tab () {

		// Grab the url to this tab
		$url = $this->parent->url(array(gbp_tab => $this->event), true);

		// Will need updating if any improvements happen to the admin interface
		$out[] = '<a class="navlink' . ($this->is_active ? ' active' : '') . '" href="' .$url. '">' .$this->title. '</a>'.n;

		return join('', $out);
	}

	function main () {
		// Override this function
	}

	function pref ($key) {
		return @$this->parent->pref($key);
	}

	function redirect ($vars = '') {
		$this->parent->redirect($vars);
	}

	function set_preference ($key, $value, $type = '') {
		return $this->parent->set_preference($key, $value, $type);
	}

	function remove_preference ($key) {
		return $this->parent->remove_preference($key);
	}

	function url ($vars, $gp = false) {
		return $this->parent->url($vars, $gp);
	}

	function form_inputs () {
		return $this->parent->form_inputs();
	}
}

class GBPPreferenceTabView extends GBPAdminTabView {

	var $permissions = 'prefs';

	function GBPPreferenceTabView (&$parent, $is_default = NULL) {
		// Call the parent constructor
		GBPAdminTabView::GBPAdminTabView(gTxt('tab_preferences'), 'preference', $parent, $is_default);
	}

	function preload () {
		if (ps('step') == 'prefs_save') {
			foreach ($this->parent->preferences as $key => $pref) {
				extract($pref);
				$value = ps($key);
				if (is_callable(array(&$this->parent, $type)))
					$value = call_user_func(array(&$this->parent, $type), 'ui_out', $value);
				$this->parent->set_preference($key, $value);
			}
		}
	}

	function main () {
		// Make txp_prefs.php happy :)
		global $event;
		$event = $this->parent->event;

		include_once txpath.'/include/txp_prefs.php';

		echo
			'<form action="index.php" method="post">',
			startTable('list');

		foreach ($this->parent->preferences as $key => $pref) {
			extract($pref);

			$out = tda(gTxt($key), ' style="text-align:right;vertical-align:middle"');

			switch ($type) {
				case 'text_input':
					$out .= td(pref_func('text_input', $key, $value, 20));
				break;
				default:
					if (is_callable(array(&$this->parent, $type)))
						$out .= td(call_user_func(array(&$this->parent, $type), 'ui_in', $value, $key));
					else
						$out .= td(pref_func($type, $key, $value, 50));
				break;
			}

			$out .= tda($this->popHelp($key), ' style="vertical-align:middle"');
			echo tr($out);
		}

		echo
			tr(tda(fInput('submit', 'Submit', gTxt('save_button'), 'publish'), ' colspan="3" class="noline"')),
			endTable(),
			$this->parent->form_inputs(),
			sInput('prefs_save'),
			'</form>';
	}

	function popHelp ($helpvar) {
		$script = hu.basename(txpath).'/index.php';
		return '<a href="'.$script.'?event=plugin'.a.'step=plugin_help'.a.'name='.$this->parent->plugin_name.'#'.$helpvar.'" class="pophelp">?</a>';
	}
}

class GBPWizardTabView extends GBPAdminTabView {

	var $installation_steps = array();
	var $wiz_report = array();
	var $permissions = 'admin.edit';

	function GBPWizardTabView (&$parent, $is_default = NULL, $title = 'Wizards') {
		global $textarray;

		#
		#	Get the strings and merge into the textarray before we get the steps...
		#
		$strings = $this->get_strings();
		$textarray = array_merge($strings , $textarray);

		#
		#	Now get the steps...
		#
		$this->installation_steps = $this->get_steps();

		// Call the parent constructor
		GBPAdminTabView::GBPAdminTabView($title, 'wizard', $parent, $is_default);
	}

	function get_steps () {
		#
		#	Override this method in derived classes to return the appropriate setup/cleanup steps.
		#
		$steps = array(
			'basic' 		=> array('setup' => 'Basic setup step', 'cleanup' => 'Basic cleanup step'),
			'optional'		=> array('setup' => 'Optional setup step', 'cleanup' => 'Optional cleanup step', 'optional' => true , 'checked' => 0),
			'has_options'	=> array('setup' => 'Setup step with a option', 'cleanup' => 'Cleanup step with a option', 'has_options' => true),
		);
		return $steps;
	}

	function get_strings ($language = '') {
		#
		#	Override this function in derived classes to define/change the set of strings to
		# inject into $textarray to localise the wizard.
		#
		$strings = array(
			'gbp_adlib_wiz-version_errors'		=> 'Version Errors',
			'gbp_adlib_wiz-version_reason'		=> 'This plugin cannot operate in this installation because&#8230;',
			'gbp_adlib_wiz-version_item'		=> 'It requires <strong class="failure">{name} {min}</strong> or above, current install is {current}.',
			'gbp_adlib_wiz-setup' 				=> 'Setup',
			'gbp_adlib_wiz-setup_steps' 		=> 'The following setup steps will be taken&#8230;',
			'gbp_adlib_wiz-setup_report'		=> 'Setup Report&#8230;',
			'gbp_adlib_wiz-cleanup' 			=> 'Cleanup',
			'gbp_adlib_wiz-cleanup_steps' 		=> 'The following cleanup steps will be taken&#8230;',
			'gbp_adlib_wiz-cleanup_report'		=> 'Cleanup Report&#8230;',
			'gbp_adlib_wiz-cleanup_next' 		=> 'The plugin can now be disabled and/or uninstalled.',
			'gbp_adlib_wiz-done' 				=> 'Done',
			'gbp_adlib_wiz-skipped'				=> 'Skipped',
			'gbp_adlib_wiz-failed'				=> 'Failure',
			'gbp_adlib_wiz-step_basic'			=> 'Basic Step',
			'gbp_adlib_wiz-step_optional'		=> 'Optional step',
			'gbp_adlib_wiz-step_complex'		=> 'Step with option(s)',
			'gbp_adlib_wiz-step_complex_txt'	=> 'This {step} step has an option/options.',
		);
		return $strings;
	}

	function versions_ok () {
		$msg = '';

		#
		#	Check the plugin can run in this environment.
		#
		#	Return: TRUE -> Yes.
		#	HTML formatted string -> No, and explain why.
		#
		$tests = $this->get_required_versions();
		if (count($tests))
			foreach ($tests as $name => $versions) {
				if( array_key_exists( 'custom_handler' , $versions ) && is_callable( $versions['custom_handler'] ) ) {
					#
					# Allow derived classes to define their own checking routines...
					#
					$fn = $versions['custom_handler'];
					$res = call_user_func( $fn , $name , $versions );
					if( $res )
						$msg[] = tag($res , 'li', ' style="text-align: left; padding-top: 0.75em;"');
				}
				else {
					if (version_compare($versions['current'], $versions['min'] , '<')) {
						$res = gTxt('gbp_adlib_wiz-version_item' , array('{name}' => $name , '{min}' => $versions['min'] , '{current}' => $versions['current']));
						$msg[] = tag($res , 'li', ' style="text-align: left; padding-top: 0.75em;"');
					}
				}
			}

		if (!empty($msg))
			return tag(join('' , $msg) , 'ol');

		return true;
	}

	function get_required_versions () {
		global $prefs;

		#
		#	Override this function to return an array of tests to be carried out.
		#
		$tests = array('TxP' => array(
			'current'	=> $prefs['version'],
			'min'		=> '4.0.3',
		));
		return $tests;
	}

	function main () {
		$out[] = '<style type="text/css"> .success { color: #009900; } .failure { color: #FF0000; } .skipped { color: #0000FF; } </style>';
		$out[] = '<div style="border: 1px solid gray; width: 50em; text-align: center; margin: 1em auto; padding: 1em; clear: both;">';

		$fieldset_style = ' style="text-align: left; padding: 1em 0"';

		$step = gps('step');
		if (empty($step)) {
			$result = $this->versions_ok();
			if (is_string($result))
				$step = 'version_error';
			else
				$step = ($this->installed()) ? 'cleanup-verify' : 'setup-verify';

			$_POST['step'] = $step;
		}

		switch ($step) {
			case 'version_error':
				$out[] = hed(gTxt('gbp_adlib_wiz-version_errors') , 1);
				$out[] = graf(gTxt('gbp_adlib_wiz-version_reason'));
				$out[] = $result;
			break;

			case 'setup-verify':
			// Render the setup wizard initial step...
				$out[] = hed(gTxt('gbp_adlib_wiz-setup') , 1);
				$out[] = graf(gTxt('gbp_adlib_wiz-setup_steps'));
				$out[] = tag(tag($this->wizard_steps('setup') , 'ol') , 'fieldset', $fieldset_style);
				$out[] = fInput('submit', '', gTxt('gbp_adlib_wiz-setup'), '');
				$out[] = $this->form_inputs();
				$out[] = sInput('setup');
			break;

			case 'setup':
			// Render the post-setup screen...
				$out[] = hed(gTxt('gbp_adlib_wiz-setup_report') , 1);
				$out[] = tag($this->wizard_report() , 'fieldset', $fieldset_style);
				$out[] = fInput('submit', '' , gTxt('next') , '');
				$out[] = eInput($this->parent->event);
				$out[] = hInput(gbp_tab, 'preference');
			break;

			case 'cleanup-verify':
			// Render the cleanup wizard initial step...
				$out[] = hed(gTxt('gbp_adlib_wiz-cleanup') , 1);
				$out[] = graf(gTxt('gbp_adlib_wiz-cleanup_steps'));
				$out[] = tag(tag($this->wizard_steps('cleanup') , 'ol') , 'fieldset', $fieldset_style);
				$out[] = fInput('submit', '', gTxt('gbp_adlib_wiz-cleanup'), '');
				$out[] = $this->form_inputs();
				$out[] = sInput('cleanup');
			break;

			case 'cleanup':
			// Render the post-cleanup screen...
				$out[] = hed(gTxt('gbp_adlib_wiz-cleanup_report') , 1);
				$out[] = tag($this->wizard_report() , 'fieldset', $fieldset_style);
				$out[] = graf(gTxt('gbp_adlib_wiz-cleanup_next'));
				$out[] = fInput('submit', '' , gTxt('next') , '');
				$out[] = eInput('plugin');
			break;
		}

		$out[] = '</div>';

		$verify = (in_array($step, array('setup-verify', 'cleanup-verify')))
			? "verify('".doSlash(gTxt('are_you_sure'))."')"
			: '';

		echo form(join(n, $out), '', $verify);
	}

	function installed () {
		return false;
	}

	function wizard_steps ($step) {
		$step_details = '';

		foreach ($this->installation_steps as $key => $detail) {
			if (@$detail[$step]) {
				$options = '';
				if (@$detail['has_options']) {
					$function = array(&$this, 'option_'.$key);
					if (is_callable($function))
						$options = n.tag(call_user_func($function, $step), 'span', ' id="wizard_'.$key.'" style="display: block; margin-right: 1em; padding: 0.5em; background-color: #eee;"');
				}

				$checkbox = '';
				if (@$detail['optional']) {
					$checked = (isset($detail['checked'])) ? $detail['checked'] : 0 ;
					$checkbox = checkbox2('optional_'.$key, $checked, ($options ? '" onclick="toggleDisplay(\'wizard_'.$key.'\');' : ''));
				}

				$step_details .= n.tag(graf(
					tag($detail[$step].$checkbox, 'label').$options
				), 'li');
			}
		}

		return $step_details.n;
	}

	function wizard_report () {
		// Render the wizard report as an ordered list. There maybe
		// 'sub' reports which we need to also render as ordered lists
		$out = array();
		foreach ($this->wiz_report as $report) {
			$out_sub = array();

			// Skip the first element as it is in fact the parent report
			next($report);

			// Lets generate a sub report - if there are more elements
			while (list($key, $report_sub) = each($report))
				$out_sub[] = tag($report_sub , 'li');

			// Check to see if we actually have a sub report - tag it as necessary
			$out_sub = (count($out_sub) > 0)
				? tag(join(n , $out_sub), 'ol')
				: '';

			$out[] = tag($report[0] . $out_sub , 'li');
		}
		return tag(join(n , $out) , 'ol');
	}

	function add_report_item ($string , $ok = NULL, $sub = false) {
		if (isset($ok)) {
			switch ($ok) {
				case '1' :
					$class = 'success';
					$okfail = gTxt('gbp_adlib_wiz-done');
				break;

				default :
				case '0' :
					$class = 'failure';
					$okfail = gTxt('gbp_adlib_wiz-failed');
				break;

				case 'skipped' :
					$class = 'skipped';
					$okfail = gTxt('gbp_adlib_wiz-skipped');
				break;
			}
			$okfail = ' : <span class="'.$class.'">'.tag($okfail, 'strong').'</span>';
		}

		$line = graf($string . (isset($okfail) ? $okfail : ''));

		if ($sub && count($this->wiz_report) > 0)
			$this->wiz_report[count($this->wiz_report) - 1][] = $line;
		else
			$this->wiz_report[] = array($line);
	}

	function setup_basic () {
		$this->add_report_item(gTxt('gbp_adlib_wiz-step_basic'), true);
	}

	function cleanup_basic () {
		$this->add_report_item(gTxt('gbp_adlib_wiz-step_basic'), false);
	}

	function setup_optional () {
		$this->add_report_item(gTxt('gbp_adlib_wiz-step_optional'), true);
	}

	function cleanup_optional () {
		$this->add_report_item(gTxt('gbp_adlib_wiz-step_optional'), false);
	}

	function setup_has_options () {
		$this->add_report_item(gTxt('gbp_adlib_wiz-step_complex'), true);
	}

	function cleanup_has_options () {
		$this->add_report_item(gTxt('gbp_adlib_wiz-step_complex'), false);
	}

	function option_has_options ($step) {
		return graf(gTxt('gbp_adlib_wiz-step_complex_txt' , array('{step}' => $step))).yesnoRadio('wizard_has_options_test', 1);
	}
}

# --- END PLUGIN CODE ---

?>
