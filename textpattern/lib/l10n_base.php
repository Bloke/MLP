<?php

if( !defined( 'txpinterface' ) ) exit;

#
#	This file contains functions/classes needed on both the admin and public side
# of the plugin.
#

if( !defined( 'L10N_COL_OWNER' ) )
	define( 'L10N_COL_OWNER' , L10N_NAME.'_owner' );
if( !defined( 'L10N_COL_LANG' ) )
	define( 'L10N_COL_LANG' , L10N_NAME.'_lang' );
if( !defined( 'L10N_COL_GROUP' ) )
	define( 'L10N_COL_GROUP' , L10N_NAME.'_group' );

global $event;
include_once txpath.'/lib/l10n_langs.php';

if( !is_callable('preg_last_error') )
	{
	function preg_last_error()
		{
		}
	}
function _l10n_preg_replace_err()
	{
	$err = preg_last_error();
	}
function _l10n_preg_replace_callback( $needle , $fn , $buffer , $limit = -1 )
	{
	$tmp = preg_replace_callback( $needle, $fn , $buffer );
	if (NULL !== $tmp)
		{
		$buffer = $tmp;
		// put email-sending or a log-message here
		}
	else
		_l10n_preg_replace_err();

	unset( $tmp );
	return $buffer;	
	}

function _l10n_preg_replace( $needle , $new , $buffer , $limit = -1 )
	{
	$tmp = preg_replace( $needle , $new , $buffer , $limit );
	if (NULL !== $tmp)
		{
		$buffer = $tmp;
		$err = preg_last_error();
		// put email-sending or a log-message here
		}
	else
		_l10n_preg_replace_err();

	unset( $tmp );
	return $buffer;	
	}

function _l10n_load_localised_pref( $name )
 	{
	global $prefs,$pretext;
	$k = 'snip-'.$name;
	$r = gTxt( $k );
	if( $r !== $k )
		{
		$GLOBALS[$name] = $r;
		$GLOBALS['prefs'][$name] = $r;
		$prefs[$name] = $r;
		if( @txpinterface === 'public' )
			$pretext[$name] = $r;
		}
	}

function _l10n_replace_snippet( $m )
	{
	global $l10n_language, $textarray;
	static $l = false;
	
	if( !$l ) $l = 	$l10n_language['long'];

	#$s = strtolower( $m[1] ); # Allow case sensitive snippet names?
	$s = $m[1];
	$r = @$textarray[$s];
	if( !$r )
		return $s;
	return $r;	
	}

function _l10n_substitute_snippets( &$thing )
	{
	$out = _l10n_preg_replace_callback( L10N_SNIPPET_PATTERN , '_l10n_replace_snippet' , $thing );
	return $out;
	}

function _l10n_process_pageform_access( $thing , $table , $where , $results , $is_a_set )
	{
	global $event;
	if ('admin' === @txpinterface) 
		{
		if ('dashboard'!==$event)
			return $results;
		}
	
	switch( $table )
		{
		case 'txp_page' :
			if( $thing !== 'user_html' )
				break;

			if( is_array( $results ) )
				{
				$out = array();
				foreach( $results as $key => $result )
					{
					$out[$key] = _l10n_substitute_snippets( $result );
					}
				$results = $out;
				}
			else
				$results = _l10n_substitute_snippets( $results );
			break;
		case 'txp_form' :
			if( $thing !== 'Form' and $thing !== 'form' )
				break;

			if( is_array( $results ) )
				{
				$out = array();
				foreach( $results as $key => $result )
					{
					$out[$key] = _l10n_substitute_snippets( $result );
					}
				$results = $out;
				}
			else
				$results = _l10n_substitute_snippets( $results );
			break;
		}

	return $results;
	}
function _l10n_redirect_textpattern($table)
	{
	if( @txpinterface !== 'public' )
		return $table;

	if( 'textpattern' === $table )
		{
		global $l10n_language;

		$language_set 	= isset( $l10n_language );
		$language_ok	= true;
		if( $language_set and $language_ok )
			{
			$table = _l10n_make_textpattern_name( $l10n_language );
			}
		}
	elseif ( L10N_MASTER_TEXTPATTERN === $table )
		{
		$table = 'textpattern';
		}
	return $table;
	}

function _l10n_get_db_charsetcollation()
	{
	global $txpcfg;

	$result = 'CHARACTER SET '.$txpcfg['dbcharset'];
	if( $txpcfg['dbcharset'] === 'utf8')
		$result .= ' COLLATE utf8_general_ci';

	return $result;
	}
function _l10n_get_dbserver_type()
	{
	$version = mysql_get_server_info();
	//Use ENGINE if version of MySQL > (4.0.18 or 4.1.2)
	$type = ( intval($version[0]) >= 5 || preg_match('#^4\.(0\.[2-9]|(1[89]))|(1\.[2-9])#',$version))
					? 'ENGINE=MyISAM'
					: 'TYPE=MyISAM';

	return $type;
	}
function _l10n_rewrite_sql( $field, $lang, $sql )
	{
	$localised_field = _l10n_make_field_name( $field , $lang );
	$r = '`'.$localised_field.'` as `'.$field.'`';

	#
	#	Replace specific matches...
	#
	$newsql = 	' '.$sql.' ';	#inject padding to allow detection of matches at start/end of the string.

	$v = array(	'`'.$field.'`' =>     $r,	 # no need for extra backticks here -- the $r string has them.
				','.$field.',' => ','.$r.',',
				','.$field.' ' => ','.$r.' ',
				' '.$field.',' => ' '.$r.',',
				' '.$field.' ' => ' '.$r.' ', );
	$newsql = str_replace( array_keys($v) , array_values($v) , $newsql );

	#
	#	Don't forget to override any wildcard search with specific mappings,
	# but not in count ops...
	#
	if( false === stripos( $newsql, '(*)' ) )
		$newsql = str_replace( '*' , '*,'.$r , $newsql );
	return $newsql;
	}
function _l10n_admin_remap_fields( $thing , $table )
	{
	global $event , $step;
	static $mappings;

	$debug = 0;

	if( !isset( $mappings ) )
		{
		$mappings = array
			(
			'txp_category' => array( 	'field' 	=> 'title',
										'events' 	=> array('article'=>'all','category'=>'cat_category_list,cat_article_save,cat_link_save,cat_image_save,cat_file_save','list'=>'all','image'=>'image_edit','link'=>'all','file'=>'all'), ),
			'txp_section'  => array( 	'field' 	=> 'title',
										'events' 	=> array('article'=>'all','list'=>'all'), ),
			);
		}

	#	Return early if no matching mapping/event entries...
	if( !array_key_exists( $table , $mappings ) )
		return $thing;

	if( !array_key_exists( $event , $mappings[$table]['events'] ) )
		return $thing;

	if( $mappings[$table]['events'][$event] !== 'all' )
		{
		if( ($mappings[$table]['events'][$event] === 'none') && !empty($step) )
			return $thing;

		if( !in_array( $step , explode(',', $mappings[$table]['events'][$event]) ) )
			return $thing;
		}

	global $l10n_language;
	if( isset( $l10n_language['long'] ) )
		$lang = $l10n_language['long'];
	else
		$lang = LANG;

	# Ok, this is an event we have to map for this table...
	$field = $mappings[$table]['field'];
	$newthing = _l10n_rewrite_sql( $field , $lang , $thing );

	if( $debug && $thing !== $newthing )
		{
		echo br , '_l10n_admin_remap_fields ... table('.$table.')';
		echo br ,'   ... event/step('.$event.'/'.$step.') ... thing('.$thing.') ... newthing('.$newthing.')';
		}

	return $newthing;
	}

function _l10n_remap_fields( $thing , $table , $get_mappings=false )
	{
	//$charset_collation = _l10n_get_db_charsetcollation();
	static $interfaces = array( 'public' , 'admin' );
	static $mappings;

	if( !isset( $mappings ) )
		{
		$mappings = array	(
			'txp_category'	=> array(
				'title' 		=> array(
					'sql' 			=> 'varchar(255) NOT NULL DEFAULT \'\'',
					'e' 			=> 'category',
					'paint_steps'	=> array( 'cat_article_edit', 'cat_link_edit', 'cat_image_edit', 'cat_file_edit' ),
					'paint' 		=> '_l10n_category_paint',
					'save_steps'	=> array( 'cat_article_create', 'cat_article_save', 'cat_link_create', 'cat_link_save', 'cat_image_create', 'cat_image_save', 'cat_file_create', 'cat_file_save', ),
					'save'			=> '_l10n_category_save',
					'save_pre'		=> 1,
					),
				),
			'txp_file' 		=> array(
				'description'	=> array(
					'sql'			=> 'text NOT NULL' ,
					'e' 			=> 'file',
					'paint_steps'	=> array( 'file_edit', 'file_replace' ),
					'paint' 		=> '_l10n_file_paint',
					'save_steps'	=> array( 'file_save' ),
					'save'			=> '_l10n_file_save',
					),
				'title'		=> array(
					'sql'			=> 'varchar(255) NULL' ,
					'e' 			=> '',
					'paint_steps'	=> '',
					'paint' 		=> '',
					'save_steps'	=> '',
					'save'			=> '',
					),
				),
			'txp_image'		=> array(
				'alt'			=> array(
					'sql' 			=> 'varchar(255) NOT NULL DEFAULT \'\'',
					'e' 			=> 'image',
					'paint_steps'	=> array( '' ),
					'paint' 		=> '_l10n_image_paint',
					'save_steps'	=> array( 'image_save' ),
					'save'			=> '_l10n_image_save',
					),
				'caption' 	=> array(
					'sql'			=> 'text NOT NULL',
					'e' 			=> '',
					'paint_steps'	=> '',
					'paint' 		=> '',
					'save_steps'	=> '',
					'save'			=> '',
					),
				),
			'txp_link' 		=> array(
				'description'	=> array(
					'sql'			=> 'text NOT NULL',
					'e' 			=> 'link',
					'save_steps'	=> array( 'link_post', 'link_save' ),
					'save'			=> '_l10n_link_save',
					'paint_steps'	=> '',
					'paint' 		=> '_l10n_link_paint',
					),
				),
			'txp_section'	=> array(
				'title' 		=> array(
					'sql' 			=> 'varchar(255) NOT NULL DEFAULT \'\'',
					'e' 			=> 'section',
					'paint_steps'	=> array( 'section_edit' ),
					'paint' 		=> '_l10n_section_paint',
					'save_steps'	=> array( 'section_save', 'section_create' ),
					'save'			=> '_l10n_section_save',
					),
				),
			);
		}

	if( $get_mappings )
		{
		//echo br , dmp( $mappings );
		return $mappings;
		}

	if( !in_array( @txpinterface , $interfaces ) )
		return $thing;

	if( !isset( $mappings[$table] ) )
		return $thing;

	if( @txpinterface === 'admin' )
		$lang = MLPLanguageHandler::get_site_default_lang();
	else
		{
		global $l10n_language;
		if( isset( $l10n_language['long'] ) )
			$lang = $l10n_language['long'];
		else
			$lang = LANG;
		}

	foreach( $mappings[$table] as $field => $sql )
		{
		$thing = _l10n_rewrite_sql( $field , $lang , $thing );
		}

	return trim($thing);
	}
function _l10n_walk_mappings( $fn , $atts='' )
	{
	if( !is_callable( $fn ) )
		return false;

	global $l10n_mappings;
	if( !is_array( $l10n_mappings ) )
		$l10n_mappings = _l10n_remap_fields( '' , '' , true );

	foreach( $l10n_mappings as $table=>$fields )
		{
		foreach( $fields as $field=>$attributes )
			{
			#	The user function must create a safe table name by calling safe_pfx() on the table name
			call_user_func( $fn , $table , $field , $attributes , $atts );
			}
		}

	return true;
	}

function _l10n_make_field_name( $column , $lang )
	{
	$tmp = _l10n_clean_sql_name( L10N_NAME.'_'.$column.'_'.$lang );
	return $tmp;
	}

function _l10n_clean_sql_name( $name )
	{
	if( !is_string( $name ) )
		{
		$error = 'clean_table_name() given a non string input.';
		trigger_error( $error , E_USER_ERROR );
		}

	#Make sure the table name has no sql opeartors...
	$result = strtr( $name , array( '-' => '_' ) );
	return $result;
	}
function _l10n_make_textpattern_name( $full_code )
	{
	if( is_string( $full_code ) )
		{
		$code = $full_code;
		}
	else
		{
		if( @$full_code['long'] )
			$code = $full_code['long'];
		elseif( @$full_code['short'] )
			$code = $full_code['short'];
		else
			{
			$error = '_l10n_make_textpattern_name() given an invalid input value '.$full_code;
			trigger_error( $error , E_USER_ERROR );
			}
		}

	if( strlen( $code ) < 2 )
		{
		trigger_error( $code.' is too short!' , E_USER_ERROR );
		}

	$result = _l10n_clean_sql_name( L10N_RENDITION_TABLE_PREFIX . $code );

	return $result;
	}


class MLPLanguageHandler
	{
	#	class MLPLanguageHandler implements ISO-639-1 language support.
	function do_fleshout_names( &$langs , $suffix='' , $append_code = true , $append_default=false , $use_long=true )
		{
		$result = array();
		if( is_array($langs) and !empty($langs) )
			{
			foreach( $langs as $code )
				{
				$code = trim( $code );
				$tmp = MLPLanguageHandler::get_native_name_of_lang( $code );
				if( $append_code )
					$tmp .= ' [' . $code . ']';
				if( !$use_long )
					$code = substr( $code , 0 , 2 );
				if( !empty( $suffix ) )
					$tmp .= ' ' . $suffix;
				if( $append_default and ($code === MLPLanguageHandler::get_site_default_lang() ) )
					$tmp .= ' - ' . gTxt('default');
				$result[$code] = $tmp;
				}
			ksort( $result );
			}
		return $result;
		}
	function do_fleshout_dirs( &$langs )
		{
		$result = array();
		if( is_array($langs) and !empty($langs) )
			{
			foreach( $langs as $code )
				{
				$code = trim( $code );
				$tmp = MLPLanguageHandler::get_lang_direction( $code );
				$result[$code] = $tmp;
				}
			}
		return $result;
		}

	function compact_code( $long_code )
		{
		/*
		Pull apart a long form language code into components.
		Output = {short , COUNTRY , [long]}	So, en-gb=> {en , GB , en-gb}
		*/

		# Cache the results as they are probably going to get used many times per tab...
		static $code_mappings;
		if( !is_string( $long_code ) )
			{
			echo br , 'compact_code( ' , var_dump( $long_code ) , ').';
			trigger_error( 'Invalid type passed to MLPLanguageHandler::compact_code()', E_USER_ERROR);
			}

		$long_code = trim( $long_code );
		if( isset( $code_mappings[$long_code] ) )
			return $code_mappings[$long_code];

		$result = array();
		$result['short'] 	= @substr( $long_code , 0 , 2 );
		$result['country']  = @substr( $long_code , 3 , 2 );
		$result['long'] = '';

		if( isset( $result['country'] ) and (2 == strlen($result['country'])) )
			$result['long'] = $long_code;

		if( isset( $result['country'] ) )
			$result['country'] = strtoupper( $result['country'] );

		$code_mappings[$long_code] = $result;
		return $result;
		}

	function expand_code( $short_code )
		{
		$result = array();
		$short_code = trim( $short_code );

		if( empty( $short_code ) )
			{
			//echo br, 'expand_code( '.$short_code.' ) rejecting empty $short_code!';
			return null;
			}

		$langs = MLPLanguageHandler::get_site_langs();
		foreach( $langs as $code )
			{
			$code = trim( $code );
			$r = MLPLanguageHandler::compact_code( $code );
			if( $short_code === $r['short'] )
				$result[] = $code;
			}
		if( count( $result ) )
			return $result[0];
		return NULL;
		}

	function iso_639_langs ( $input, $to_return='lang' )
		{
		global $iso_639_langs;

		switch ( $to_return )
			{
			default:
			case 'lang':
				$r = MLPLanguageHandler::compact_code( $input );
				$short = $r['short'];
				if( isset($r['long']) ) $long = $r['long'];

				if( $short === false || !array_key_exists( $short , $iso_639_langs ))
					return NULL;

				$row = $iso_639_langs[$short];

				if( isset( $long ) )
					{
					#	Try getting the language name for the long code...
					if( array_key_exists( $long , $row ) )
						return $row[$long];
					}

				# Fall back to the default entry for the short code...
				return $row[$short];
			break;

			case 'valid_short':
				return array_key_exists( $input , $iso_639_langs );
			break;

			case 'valid_long':
				$short = substr( $input , 0 , 2 );
				if( !array_key_exists( $short , $iso_639_langs ) )
					return false;
				$row = $iso_639_langs[$short];
				return array_key_exists( $input , $row );
			break;

			case 'long2short':
				$r = MLPLanguageHandler::compact_code( $input );
				return $r['short'];
			break;

			case 'short2long':
				//return MLPLanguageHandler::expand_code( $input );

				if( array_key_exists( $input , $iso_639_langs ) )
					{
					$row = $iso_639_langs[$input];
					foreach( $row as $code => $name )
						{
						if( $code === 'dir' )
							continue;

						if( strlen($code) === 5 )
							return $code;
						}

					# If we get here we haven't found a matching long code so fallthrough to default return...
					}
				return $input.'-'.$input;
			break;

			case 'dir':
				extract( MLPLanguageHandler::compact_code( $input ) );
				return (array_key_exists( $short, $iso_639_langs ) and array_key_exists('dir', $iso_639_langs[$short]))
					?	$iso_639_langs[$short]['dir']
					:	NULL;
			break;

			case 'code':
				foreach( $iso_639_langs as $code => $data )
					{
					if( in_array( $input , $data ) )
						{
						return $code;
						}
					}
				return NULL;
			break;
			}
		}

	function is_valid_code($code)
		{
		/*
		Check the given string is a valid language code.
		*/
		$lang = MLPLanguageHandler::compact_code( $code );
		$short = $lang['short'];
		if( isset( $short ) )
			return MLPLanguageHandler::is_valid_short_code($short);

		return false;
		}

	function is_valid_short_code($code)
		{
		/*
		Check the given string is a valid 2-digit language code from the ISO-639-1 table.
		*/
		$result = false;
		$code = trim( $code );
		if( 2 == strlen( $code ) )
			{
			$result = ( MLPLanguageHandler::iso_639_langs( $code ) );
			}
		return $result;
		}

	function find_code_for_lang( $name )
		{
		/*
		Returns the ISO-639-1 code for the given native language.
		*/
		$out = '';

		if( $name and !empty( $name ) )
			{
			$out = MLPLanguageHandler::iso_639_langs( $name, 'code' );
			}

		if (empty($out))
			$out = gTxt( 'none' );

		return $out;
		}

	function get_lang_direction_markup( $lang )
		{
		/*
		Builds the xhtml direction markup needed based upon the directionality of the language requested.
		*/
		$dir = ' dir="ltr"';
		if( !empty($lang) and ('rtl' == MLPLanguageHandler::iso_639_langs( $lang, 'dir' ) ) )
			$dir = ' dir="rtl"';
		return $dir;
		}

	function get_lang_direction( $lang )
		{
		/*
		Builds the xhtml direction markup needed based upon the directionality of the language requested.
		*/
		$dir = 'ltr';
		if( !empty($lang) and ('rtl' == MLPLanguageHandler::iso_639_langs( $lang, 'dir' ) ) )
			$dir = 'rtl';
		return $dir;
		}

	function get_native_name_of_lang( $code )
		{
		/*
		Returns the native name of the given language code.
		*/
		return (MLPLanguageHandler::iso_639_langs( $code )) ? MLPLanguageHandler::iso_639_langs( $code ) : MLPLanguageHandler::iso_639_langs( 'en' ) ;
		}

	function get_site_langs( $set_if_empty = false )
		{
		/*
		Returns an array of the languages the public site supports.
		*/
		global $prefs;

		$exists = array_key_exists(L10N_PREFS_LANGUAGES, $prefs);
		if( $set_if_empty and !$exists )
			{
			$prefs[L10N_PREFS_LANGUAGES] = array( LANG );
			$exists = true;
			}

		if( $exists )
			{
			$lang_codes = $prefs[L10N_PREFS_LANGUAGES];
			if( !is_array( $lang_codes ) )
				{
				$lang_codes = explode( ',' , $lang_codes );
				}
			$lang_codes = doArray( $lang_codes , 'trim' );
			}
		else
			$lang_codes = NULL;

		return $lang_codes;
		}

	function get_site_default_lang()
		{
		/*
		Returns a string containing the ISO-639-1 language to be used as the site's default.
		*/
		$lang_codes = MLPLanguageHandler::get_site_langs();
		return $lang_codes[0];
		}
	function find_lang( $partial_key , $langs )
		{
		$result = $partial_key;
		$len = strlen( $partial_key );
		switch( $len )
			{
			case 5:
				break;
			default:
				if( !empty( $langs ) )
					{
					foreach( $langs as $lang )
						{
						if( substr( $lang , 0 , $len ) === $partial_key )
							{
							$result = $lang;
							break;
							}
						}
					}
				break;
			}

		return $result;
		}
	function get_installation_langs( $limit = 400 )
		{
		/*
		Returns an array of all the languages in this TXP installation with more
		than the limit number of strings in that lang...
		*/
		static $installation_langs;
		
		if( isset( $installation_langs ) )
 			return $installation_langs;

		$installation_langs = array( LANG );
		$langs = safe_column('lang','txp_lang','1=1 GROUP BY `lang`');
		if( count( $langs ) )
			{
			foreach( $langs as $lang )
				{
				$count = safe_count( 'txp_lang' , '`lang`=\''.$lang.'\'' );
				if( ($count >= $limit) && ($lang !== LANG) )
					$installation_langs[] = $lang;
				}
			}
		unset( $langs );

		return $installation_langs;
		}

	}

#
#	For non multi-byte compiled versions of php...
#
if( !defined( 'MB_CASE_UPPER' ) )
	define( 'MB_CASE_UPPER' , '0' );
if( !defined( 'MB_CASE_LOWER' ) )
	define( 'MB_CASE_LOWER' , '1' );
if( !defined( 'MB_CASE_TITLE' ) )
	define( 'MB_CASE_TITLE' , '2' );
