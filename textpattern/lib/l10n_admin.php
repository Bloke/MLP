<?php

if( !defined( 'txpinterface' ) ) exit;

global $l10n_vars, $l10n_painters, $l10n_release_version;
$l10n_vars = array();
$l10n_mappings = null;
$l10n_painters = array();

if( $l10n_view->installed() )
	{
	#
	#	Detect the dirty-flag and re-build tables...
	#
	global $prefs;

	if( @$prefs[L10N_DIRTY_FLAG_VARNAME] === 'DIRTY' )
		{
		# Ensure new indexes (indices) are present in case of upgrade...
		_l10n_check_index();
		safe_optimize('textpattern');

		# Iterate over the site languages, rebuilding the tables...
		$langs = MLPLanguageHandler::get_site_langs();
		foreach( $langs as $lang )
			{
			_l10n_generate_lang_table( $lang );
			_l10n_generate_localise_table_fields( $lang );
			}

		# Clear the dirty flag...
		_l10n_update_dirty_flag( '' );

		# Update the installed version number
		set_pref( 'l10n_version', $l10n_release_version , 'l10n', PREF_HIDDEN );
		}

	#
	#	Observers...
	#
	register_callback( '_l10n_observe_glz_custom_fields', 'glz_custom_fields' );

	#
	#	Article handlers...
	#
	register_callback( '_l10n_setup_article_buffer_processor'	, 'article' , '' , 1 );
	register_callback( '_l10n_add_rendition_to_article_cb' 	, 'article' );

	#
	#	Article list handlers...
	#
	register_callback( '_l10n_pre_multi_edit_cb'				, 'list' , 'list_multi_edit' , 1 );
	register_callback( '_l10n_post_multi_edit_cb'				, 'list' , 'list_multi_edit' );
	register_callback( '_l10n_list_filter'						, 'list' , '' , 1 );

	#
	#	Comment handlers...
	#
	register_callback( '_l10n_pre_discuss_multi_edit' 			, 'discuss' , 'discuss_multi_edit' , 1 );
	register_callback( '_l10n_post_discuss_multi_edit' 		, 'discuss' , 'discuss_multi_edit' );

	#
	#	Section handlers...
	#
	register_callback( '_l10n_post_sectionsave'				, 'section' , 'section_save' );

	#
	#	Language management handlers (to stop language strings from being deleted) ...
	#
	register_callback( '_l10n_language_handler_callback_pre'  , 'prefs' , 'get_language' , 1 );
	register_callback( '_l10n_language_handler_callback_post' , 'prefs' , 'get_language' );

	#
	#	Insert the handlers for extending DB fields...
	#
	global $l10n_mappings;
	$l10n_mappings = _l10n_remap_fields( '' , '' , true );

	foreach( $l10n_mappings as $table=>$field_map )
		{
		//echo br , 'Processing $table';
		foreach( $field_map as $field=>$attributes )
			{
			$sql 	= '';
			$e 	= '';
			$s 	= '';
			$paint 	= '';
			$save 	= '';
			$save_pre	= 0;
			extract( $attributes );

			if( $e !== '' )
				{
				if( $paint != '' )
					{
					if( is_array( $paint_steps ) )
						foreach( $paint_steps as $st )
							_l10n_register_painter( $paint , $e , $st );
					else
						_l10n_register_painter( $paint , $e , $paint_steps );
					}
				if( $save != '' )
					{
					//echo br , "Setting saver for event($event), step($step), -> $save";
					if( is_array( $save_steps ) )
						foreach( $save_steps as $st )
							register_callback( $save , $e , $st, $save_pre );
					else
						register_callback( $save , $e , $save_steps );
					}
				}
			}
		}

	register_callback('_l10n_category_extend', 'category_ui', 'extend_detail_form');
	register_callback('_l10n_image_extend',    'image_ui',    'extend_detail_form');
	register_callback('_l10n_link_extend',     'link_ui',     'extend_detail_form');

	ob_start('_l10n_process_admin_page');
	}

function _l10n_register_painter( $fn , $e, $s )
	{
	global $l10n_painters;

	if( !is_callable($fn) )
		return;

	$l10n_painters[$e][$s][] = $fn;
	}

#
#	The following two routines were added to stop the TxP language update/intsall
# from a file from trampling all over any other strings in that language.
#
function _l10n_language_handler_callback_pre( $event , $step )
	{
	global $l10n_file_import_details;

	$force = gps( 'force' );
	if( 'file' !== $force )
		return;

	$lang = gps('lang_code');
	$lang_file = txpath.'/lang/'.$lang.'.txt';
	if (is_file($lang_file) && is_readable($lang_file))
		{
		$lang_file = txpath.'/lang/'.$lang.'.txt';
		if (!is_file($lang_file) || !is_readable($lang_file))
			return;

		$lastmod = filemtime($lang_file);
		$lastmod = date('YmdHis',$lastmod);

		#
		#	Set the timestamp of all lines that will be deleted by the file import to a safe value.
		# The 'post' routine will restore the timestamp.
		#
		$new_time = '19990101000000';
		$ok = safe_update( 'txp_lang' , "`lastmod`='$new_time'" , "`lang`='$lang' and `lastmod` > $lastmod" );
		}
	}
function _l10n_language_handler_callback_post( $event , $step )
	{
	$force = gps( 'force' );
	if( 'file' !== $force )
		return;

	#
	#	Restore the timestamp of all the lins that would have been deleted...
	#
	$lang = gps('lang_code');
	$new_time = date('YmdHis');
	$old_time = '19990101000000';
	$ok = safe_update( 'txp_lang' , "`lastmod`='$new_time'" , "`lang`='$lang' and `lastmod` = '$old_time'" );
	}

function _l10n_get_user_languages( $user_id = null )
	{
	#
	#	Returns an array of the languages that the given TxP user can create/edit
	# If the input user id is null (default) then the current txp_user is used...
	#
	if( null === $user_id )
		{
		global $txp_user;
		$user_id = $txp_user;
		}

	$langs = array();

	#
	#	Certain user groups get full rights...
	#
	$power_users = array( '1', '2' );
	$privs = safe_field('privs', 'txp_users', "user_id='$user_id'");
	if( in_array( $privs , $power_users ) )
		$langs = MLPLanguageHandler::get_site_langs();

	#
	#	Stub... replace with lookup of the user's languages....
	#
	$langs = MLPLanguageHandler::get_site_langs();

	return $langs;
	}

function _l10n_get_indexes()
	{
	return '(PRIMARY KEY  (`ID`), KEY `categories_idx` (`Category1`(10),`Category2`(10)), KEY `Posted` (`Posted`), FULLTEXT KEY `searching` (`Title`,`Body`))';
	}
function _l10n_create_temp_textpattern( $languages )
	{
	$indexes = _l10n_get_indexes();
	$sql = 'create temporary table `'.PFX.'textpattern` '.$indexes.' ENGINE=MyISAM select * from `'.PFX.'textpattern` where `'.L10N_COL_LANG.'` IN ('.$languages.')';
	@safe_query( $sql );
	}
function _l10n_check_index()
	{
	$debug = false;
	if($debug) dmp('Entered _l10n_check_index()');

	$sql = array();
	$has_lang_index = $has_group_index = false;
	
	$rs = getRows('show index from `'.PFX.'textpattern`');
	foreach ($rs as $row)
		{
		if (!$has_lang_index)
			$has_lang_index = ($row['Key_name'] == L10N_COL_LANG);
		if (!$has_group_index)
			$has_group_index = ($row['Key_name'] == L10N_COL_GROUP);
		}

	if( !$has_lang_index )
		$sql[] = 'ADD INDEX(`'.L10N_COL_LANG.'`)';
	if( !$has_group_index )
		$sql[] = 'ADD INDEX(`'.L10N_COL_GROUP.'`)';

	if($debug) dmp($sql);
	
	if( !empty( $sql ) )
		{
		$ok = @safe_alter( 'textpattern' , join(',', $sql) , $debug );
		}
	else
		{
		if($debug) dmp('No need to add new indexes');
		}

	if($debug) dmp('Exiting _l10n_check_index()');
	}
function _l10n_post_sectionsave( $event , $step )
	{
	//echo br , "_l10n_post_sectionsave( $event , $step )";

	$old_name = doSlash( ps('old_name') );
	$name     = doSlash( sanitizeForUrl( ps('name') ) );

	if( $name !== $old_name )
		{
		$langs = MLPLanguageHandler::get_site_langs();
		foreach( $langs as $lang )
			{
			$table = _l10n_make_textpattern_name(array('long'=>$lang));
			@safe_update( $table , "Section = '$name'", "Section = '$old_name'" );
			}
		}
	}
function _l10n_list_filter( $event, $step )
	{
	if( $event !== 'list' )
		return;

	switch( $step )
		{
		case '':
		case 'list':
			$langs = MLPLanguageHandler::get_site_langs();
			$selected = array();
			$use_cookies = (gps( 'l10n_filter_method' ) !== 'post');
			foreach( $langs as $lang )
				{
				if( $use_cookies )
					{
					if( cs($lang) )
						$selected[] = "'$lang'";
					}
				else
					{
					if( gps($lang) )
						{
						$selected[] = "'$lang'";
						$time = time() + (3600 * 24 * 365);
						}
					else
						$time = time() - 3600;

					$ok = setcookie( $lang , $lang , $time );
					}
				}
			$languages = join( ',' , $selected );
			_l10n_create_temp_textpattern( $languages );
			break;
		default:
			break;
		}
	ob_start( '_l10n_list_buffer_processor' );
	}
function _l10n_match_cb( $matches )
	{
	#
	#	$matches[0] is the entire pattern...
	#	$matches[1] is the article ID...
	#
	$id 		= $matches[1];
	$rs 		= safe_row(	'*', 'textpattern', 'ID='.$id );
	//$rs = array( L10N_COL_LANG => 'test' );
	if( empty( $rs ) )
		return $matches[0] . br . '<span class="articles_detail">' . "ID: $id" . gTxt( 'l10n-missing' ) .'</span>';

	if( !isset($rs[L10N_COL_LANG]) or !isset($rs[L10N_COL_GROUP]) )
		return $matches[0] . br . '<span class="articles_detail">' . "ID: $id - " . L10N_COL_GROUP . ' || '. L10N_COL_LANG . ' ' . gTxt( 'l10n-missing' ) .'</span>';

	$code		= $rs[L10N_COL_LANG];
	$article	= $rs[L10N_COL_GROUP];
	$lang 		= MLPLanguageHandler::get_native_name_of_lang( $code );
	return $matches[0] . br . '<span class="articles_detail">' . $lang . ' [' . gTxt('article'). ' :' .$article . ']</span>';
	}
function _l10n_chooser( $permitted_langs )
	{
	$count = 0;
	$langs = MLPLanguageHandler::get_site_langs();
	$o[] = '<div class="l10n_extensions"><fieldset><legend>' . gTxt('l10n-show_langs') . '</legend>' . n;
	$use_cookies = (gps( 'l10n_filter_method' ) !== 'post');

	#
	#	See if there are any languages selected. If not, select them all -- to give the user something to look at!
	#
	$showlangs = array();
	$rendition_count = 0;
	$count = 0;
	foreach( $langs as $lang )
		{
		$table = _l10n_make_textpattern_name(array('long'=>$lang));
		$lang_rendition_count = safe_count( $table , L10N_COL_LANG."='$lang'" );
		$lang_has_renditions = ($lang_rendition_count > 0);

		$rw = '';
		if( $use_cookies )
			$checked = cs( $lang ) ? 'checked' : '' ;
		else
			$checked = gps( $lang ) ? 'checked' : '' ;

		$lang_name = MLPLanguageHandler::get_native_name_of_lang( $lang );

		if( !in_array( $lang , $permitted_langs ) )
			{
			$rw = 'disabled="disabled"';
			$checked = '';
			}
		elseif( !$lang_has_renditions )
			{
			$rw = 'disabled="disabled"';
			$checked = 'checked';
			}

		$showlangs[$lang]['lang_name']	= $lang_name;
		$showlangs[$lang]['rw'] 	= $rw;
		$showlangs[$lang]['checked']	= $checked;
		if( !empty($checked) )
			$rendition_count += $lang_rendition_count;
		}

	$override_check = false;
	if( $rendition_count === 0 )
		{
		$override_check = true;
		}

	foreach( $showlangs as $lang=>$record )
		{
		$dir = MLPLanguageHandler::get_lang_direction( $lang );
		$rtl = ( $dir == 'rtl' );

		extract( $record );
		$checked = ($override_check) ? 'checked' : $checked;
		if( $rtl )
			$o[] = t . '<span dir="rtl">';
		$o[] = t . '<input type="checkbox" class="checkbox" '.$rw.' '.$checked.' value="'.$lang.'" name="'.$lang.'" id="'.$lang.'"/>' . n;
		$o[] = t . '<label for="'.$lang.'">'.$lang_name.'</label>' . n;
		if( $rtl )
			$o[] = t . '</span>';
		}
	$o[] = hInput( 'l10n_filter_method' , 'post' );
	$o[] = t.'<input type="submit" value="'.gTxt('go').'" />' . n;
	$o[] = '</fieldset></div>' . n;

	$o = join( '' , $o );
	return $o;
	}
function _l10n_list_buffer_processor( $buffer )
	{
	global $DB; // NEEDED to fix the mark-up elements injected into the renditions (list) page.

	//	Fix for php5 behaviour change: the global object has been decostructed by the time this
	// routine is called from the output buffer processor.
	if( !isset( $DB) )
		$DB = new DB;

	//$count = 0;
	if( version_compare( $GLOBALS['prefs']['version'], '4.3' , '>=') )
    $pattern = '/<td class="title"><a href="\?event=article&#38;step=edit&#38;ID=(\d+)">.*<\/a>/';
	else
    $pattern = '/<\/td>'.n.t.'<td><a href="\?event=article&#38;step=edit&#38;ID=(\d+)">.*<\/a>/';

	#	Inject the language chooser...
	$chooser = _l10n_chooser( MLPLanguageHandler::get_site_langs() );
	$f = '<p><label for="list-search">';
	$buffer = str_replace( $f , $chooser.br.n.$f , $buffer );

	#	Inject the language markers...
	$result = _l10n_preg_replace_callback( $pattern , '_l10n_match_cb' , $buffer /*, -1 , $count*/ );
	if( !empty( $result ) )
		return $result;

	return $buffer;
	}
function _l10n_setup_vars( $event , $step )
	{
	#
	#	Read the variables we need and stash them away for use in the buffer
	# processor...
	#
	global $l10n_vars;

	if(!empty($GLOBALS['ID']))
		{
		// newly-saved article
		$ID = intval($GLOBALS['ID']);
		}
	else
		{
		$ID = gps('ID');
		}

	if( $ID )
		{
		$rs = safe_row(	'*, unix_timestamp(Posted) as sPosted, unix_timestamp(LastMod) as sLastMod', 'textpattern', 'ID='.$ID );
		$l10n_vars['article_id'] 	= $ID;
		$l10n_vars['article_lang']	= $rs[L10N_COL_LANG];
		$l10n_vars['article_group']	= $rs[L10N_COL_GROUP];
		$l10n_vars['article_author_id'] = $rs['AuthorID'];
		}
	else
		{
		$l10n_vars['article_lang']	= MLPLanguageHandler::get_site_default_lang();
		}

	$l10n_vars['step']			= $step;
	}
function _l10n_setup_article_buffer_processor( $event , $step )
	{
	#	Setup the buffer process routine. It will inject new page elements
	# into the article edit page...
	#
	if( version_compare( $GLOBALS['prefs']['version'], '4.5' , '>=') )
	{
		// Delay article_ui callbacks - this fixes issues with glz_custom_fields and potentially other plugins too
		global $plugin_callback;
		foreach( $plugin_callback as $index => $callback )
			{
			if ( $callback['event'] == 'article_ui' && in_array( $callback['step'], array('title', 'body', 'excerpt') ) )
				$plugin_callback[$index]['event'] = '_l10n_article_ui';
			}

		register_callback('_l10n_write_tab_title',   'article_ui', 'title');
		register_callback('_l10n_write_tab_excerpt', 'article_ui', 'excerpt');
		register_callback('_l10n_write_tab_body',    'article_ui', 'body');
		register_callback('_l10n_write_tab_view',    'article_ui', 'partials_meta');
	}
	else
	ob_start( '_l10n_article_buffer_processor' );

	_l10n_setup_vars( $event , $step );

	#
	#	If we are posting a new article from an existing one, force some simple
	# values into the article...
	#
	global $l10n_vars;
	$publish = gps('publish');
	if( $publish and @$l10n_vars['article_id'] )
		{
		$_POST['Status'] = '1';			#	All cloned articles are DRAFTS, pending translation.
		$_POST['publish_now'] = '1';	#	Force update of publish time to NOW.
		unset($_POST['reset_time']);
		$_POST['url_title'] = '';		#	Force the url_title to be rebuilt.
		$_POST[L10N_COL_LANG] = $_POST['CloneLang'];		#	The article language and group comes
		$_POST[L10N_COL_GROUP] = $_POST['CloneGroup'];	# from the clone selector elements.
		}
	}

function _l10n_inject_switcher_form()
	{
	global $event, $l10n_language;
	$langs = MLPLanguageHandler::get_installation_langs();
	$langs = MLPLanguageHandler::do_fleshout_names( $langs );

	$tab = gps('tab');
	$tab = ( !empty($tab) ) ? hInput( 'tab' , $tab ) : '';
	$subtab=gps('subtab');
	$subtab = ( !empty($subtab) ) ? hInput( 'subtab' , $subtab ) : '';

	$sel = selectInput( 'adminlang' , $langs , $l10n_language['long'] , '' , 1 );
	$ret =  '<form method="get" action="index.php" style="clear:both;float:right;">' . n .
			$sel . n .
			$tab . $subtab . eInput( $event ) .
			'</form>' . n;
	return $ret;
	}
function _l10n_rename_articles_tab($page)
	{
	#
	#	Dynamically replace the 'tab_list' label and header...
	#
	$rend_label = txpspecialchars(gTxt('l10n-renditions'));
	$f = array('href="?event=list">'.gTxt('tab_list').'</a>', '<h1 class="txp-heading">'.gTxt('tab_list').'</h1>');
	$r = array('href="?event=list">'.$rend_label.'</a>', '<h1 class="txp-heading">'.$rend_label.'</h1>');
	$tmp = str_replace( $f , $r , $page);
	if( null !== $tmp ) $page = $tmp;
	return $page;
	}
function _l10n_inject_stuff(&$page, $stuff, &$matchpoints, $sep, $revorder=false)
	{
	global $plugins, $prefs;

	// wet_native sets language per user so having overridable controls makes no sense
	if (is_array($plugins) && in_array('wet_native', $plugins)) return;

	$ver = $prefs['version'];
	$keys = array_keys($matchpoints);
	$count = count($keys);
	if( $count > 0 )
		{
		$lastkey = $keys[$count - 1];
		$matchpoint = array_key_exists( $ver , $matchpoints ) ? $matchpoints[$ver] : $matchpoints[$lastkey];
		$replacement = $stuff.$sep.$matchpoint;
		if( $revorder ) $replacement = $matchpoint.$sep.$stuff;
		$page = str_replace( $matchpoint , $replacement , $page);
		}
	}
function _l10n_process_admin_page($page)
	{
	global $event , $step , $l10n_painters , $DB , $prefs;

	//	NEEDED to populate the language switcher on admin tabs & change the text of the 'articles' tab.
	//	Fix for php5 behaviour change: the global object has been decostructed by the time this
	// routine is called from the output buffer processor.
	if( !isset( $DB ) )
	    $DB = new DB;

	$mlp_js_events = array( 'l10n' , 'article' );
	if( in_array( $event , $mlp_js_events )  )
		{
		#
		#	Inject the MLP JavaScript into the head area...
		#
		$f = '<script type="text/javascript" src="textpattern.js"></script>';
		$r = t.'<script type="text/javascript" src="'. hu . 'textpattern/index.php?event=l10n&amp;l10nfile=mlp.js" language="javascript" charset="utf-8"></script>';
		$page = str_replace( $f , $f.n.$r , $page );
		}

	#
	#	Add the language switcher to the admin head area...
	#
	$ls = _l10n_inject_switcher_form();
	$fs = array	(
				'4.0.4' => '<form method="get" action="index.php" style="display: inline;">',
				'4.0.6' => '<form method="get" action="index.php" class="navpop" style="display: inline;">',
				'4.5.0' => '<div id="messagepane">',
				);
	_l10n_inject_stuff($page, $ls, $fs, sp);

	$page = _l10n_rename_articles_tab($page);

	#
	#	Pass the page through any matching event processors...
	#
	if( empty( $l10n_painters ) )
		return $page;

	foreach( $l10n_painters as $e=>$spec )
		{
		if( $e !== $event )
			continue;

		foreach( $spec as $s=>$painters )
			{
			if( empty( $s ) || $s === $step )
				{
				foreach( $painters as $painter )
					$page = $painter( $page );
				}
			}
		}

	return $page;
	}
function _l10n_write_tab_excerpt($event, $step, $data, $rs)
	{
	$lang = $GLOBALS['l10n_vars']['article_lang'];
	$r = MLPLanguageHandler::get_lang_direction_markup( $lang );
	$f = 'class="excerpt"';
	$data = str_replace( $f , $f.$r , $data );

	return pluggable_ui( '_l10n_article_ui', $step, $data, $rs );
	}
function _l10n_write_tab_body($event, $step, $data, $rs)
	{
	$lang = $GLOBALS['l10n_vars']['article_lang'];
	$r = MLPLanguageHandler::get_lang_direction_markup( $lang );
	$f = 'class="body"';
	$data = str_replace( $f , $f.$r , $data );

	return pluggable_ui( '_l10n_article_ui', $step, $data, $rs );
	}
function _l10n_write_tab_title($event, $step, $data, $rs)
	{
	$lang = $GLOBALS['l10n_vars']['article_lang'];
	$r = MLPLanguageHandler::get_lang_direction_markup( $lang );
	$f = 'class="title"';
	$data = str_replace( $f , $f.$r , $data );
	$data = _l10n_make_writeselector().$data;

	return pluggable_ui( '_l10n_article_ui', $step, $data, $rs );
	}
function _l10n_write_tab_view($event, $step, &$rs, &$partials)
{
	$partials['article_view'] = array(
		'mode'     => PARTIAL_VOLATILE,
		'selector' => '#article_partial_article_view',
		'cb'       => '_l10n_write_tab_partial_view',
	);
}
function _l10n_write_tab_partial_view($rs)
{
	extract($rs);
	$lc = isset($rs[L10N_COL_LANG]) ? $rs[L10N_COL_LANG] : LANG;

	if ($Status != STATUS_LIVE and $Status != STATUS_STICKY)
	{
		$url = '?txpreview='.intval($ID).'.'.time(); // article ID plus cachebuster
	}
	else
	{
		include_once txpath.'/publish/taghandlers.php';
		$url = permlinkurl_id($ID);
		$url = _l10n_inject_language_marker_url($url, $lc);
	}
	return '<span id="article_partial_article_view"><a href="'.$url.'" class="article-view">'.gTxt('view').'</a></span>';
}

// TODO: consider using $prefs['custom_url_func'] instead, so permlinkurl() and pagelinkurl() are always intercepted site-wide?
function _l10n_inject_language_marker_url($url, $lang) {
	$lang = MLPLanguageHandler::compact_code( $lang );
	$lc = $lang['short'];

	return ( $lc ) ? preg_replace('@'.hu."(?!$lc/)@", hu.$lc.'/', $url) : $url;
}

function _l10n_make_writeselector()
	{
	global $l10n_vars, $l10n_article_message, $l10n_view;

	$view		= gps( 'view' );
	$preview	= ($view === 'preview');
	$html		= ($view === 'html');
	$lang 		= $l10n_vars['article_lang'];
	$user_sel_lang = cs( 'rendition_lang_selection' );
	$user_langs = MLPLanguageHandler::do_fleshout_names( _l10n_get_user_languages() );
	$r = '';

	if( !isset( $l10n_view ) )
		$l10n_view = new MLPPlugin( 'l10n-localisation' , L10N_NAME, 'content' );	// <<<<

	$reassigning_permitted = '1' === $l10n_view->pref('l10n-allow_writetab_changes');
	$has_reassign_privs = has_privs( 'l10n.reassign' );

	$id_no		= '-';
	if( isset($l10n_vars['article_id']) )
		$id_no = $l10n_vars['article_id'];

	$group_id 	= '-';
	if( isset($l10n_vars['article_group']) )
		$group_id = $l10n_vars['article_group'];

	if( isset($l10n_article_message) )
		{
		$r = strong( txpspecialchars($l10n_article_message) ) . n . br;
		unset( $l10n_article_message );
		}
	$r.= 'ID: ' . strong( $id_no ) . ' / ';

	if( $group_id == '-' )	#	New article , don't setup a L10N_COL_GROUP element in the page!...
		{
		if( !empty( $user_sel_lang ) )
			$lang = $user_sel_lang;

		$r .=	gTxt('language') . ': ' . selectInput( L10N_COL_LANG , $user_langs , $lang , '', ' onchange="on_lang_selection_change()"', 'l10n_lang_selector' ) . ' / ';
		$r .= 	gTxt('article')  . ': ' . strong( $group_id );
		}
	else	# Existing article, either being cloned/edited with re-assignment language rights or not...
		{
		if( $reassigning_permitted and $has_reassign_privs )
			{
			$r .=	gTxt('language') . ': ' . selectInput( L10N_COL_LANG , $user_langs , $lang , '', ' onchange="on_lang_selection_change()"', 'l10n_lang_selector' ) . ' / ';
			$r .=	gTxt('article')  . ': ' . fInput('edit' , L10N_COL_GROUP , $group_id , '', '', '', '4');
			}
		else
			{
			$r .= 	hInput( L10N_COL_LANG  , $lang )     . gTxt('language') . ': ' . strong( MLPLanguageHandler::get_native_name_of_lang($lang) ) . ' / ';
			$r .= 	hInput( L10N_COL_GROUP , $group_id ) . gTxt('article')  . ': ' . strong( $group_id );
			}
		}

	if( !$preview and !$html )
		{
		#
		#	Inject direction hyper-link...
		#
		$r .= ' / <a href="#" onClick="toggleTextElements()" id="title-toggle">'.gTxt('l10n-toggle').'</a>';
		}

	$r = graf( $r );

	return $r;
  }
function _l10n_article_buffer_processor( $buffer )
	{
	global $l10n_vars, $l10n_view, $l10n_article_message;

	#
	#	The buffer processing routine injects page elements when editing an article.
	#
	$view		= gps( 'view' );
	$preview	= ($view === 'preview');
	$html		= ($view === 'html');

	$lang 		= $l10n_vars['article_lang'];
	//$from_view	= gps( 'from_view' );
/*
	$user_sel_lang = cs( 'rendition_lang_selection' );
	$user_langs = MLPLanguageHandler::do_fleshout_names( _l10n_get_user_languages() );


	//	Needed to prevent a blank content > write tab.
	//	Fix for php5 behaviour change: the global object has been deconstructed by the time this
	// routine is called from the output buffer processor.
	if( !isset( $l10n_view ) )
		$l10n_view = new MLPPlugin( 'l10n-localisation' , L10N_NAME, 'content' );	// <<<<

	$reassigning_permitted = '1' === $l10n_view->pref('l10n-allow_writetab_changes');
	$has_reassign_privs = has_privs( 'l10n.reassign' );

	$id_no		= '-';
	if( isset($l10n_vars['article_id']) )
		$id_no = $l10n_vars['article_id'];

	$group_id 	= '-';
	if( isset($l10n_vars['article_group']) )
		$group_id = $l10n_vars['article_group'];
*/

	#
	#	Insert the ID/Language/Group display elements...
	#
	
	#	Find strings for different versions...
	$find[] = '<p><input type="text" id="title"';
	$find[] = '<p><label for="title"';
	
	$f = $find[0];
	foreach( $find as $v )
		{
		if( false === strpos( $buffer , $v ) )
			continue;
		
		$f = $v;
		}

  $r = _l10n_make_writeselector();
  /*
	$r = '';
	if( isset($l10n_article_message) )
		{
		$r = strong( txpspecialchars($l10n_article_message) ) . n . br;
		unset( $l10n_article_message );
		}
	$r.= 'ID: ' . strong( $id_no ) . ' / ';

	if( $group_id == '-' )	#	New article , don't setup a L10N_COL_GROUP element in the page!...
		{
		if( !empty( $user_sel_lang ) )
			$lang = $user_sel_lang;

		$r .=	gTxt('language') . ': ' . selectInput( L10N_COL_LANG , $user_langs , $lang , '', ' onchange="on_lang_selection_change()"', 'l10n_lang_selector' ) . ' / ';
		$r .= 	gTxt('article')  . ': ' . strong( $group_id );
		}
	else	# Existing article, either being cloned/edited with re-assignment language rights or not...
		{
		if( $reassigning_permitted and $has_reassign_privs )
			{
			if( !empty( $user_sel_lang ) )
				$lang = $user_sel_lang;

			$r .=	gTxt('language') . ': ' . selectInput( L10N_COL_LANG , $user_langs , $lang , '', ' onchange="on_lang_selection_change()"', 'l10n_lang_selector' ) . ' / ';
			$r .=	gTxt('article')  . ': ' . fInput('edit' , L10N_COL_GROUP , $group_id , '', '', '', '4');
			}
		else
			{
			$r .= 	hInput( L10N_COL_LANG  , $lang )     . gTxt('language') . ': ' . strong( MLPLanguageHandler::get_native_name_of_lang($lang) ) . ' / ';
			$r .= 	hInput( L10N_COL_GROUP , $group_id ) . gTxt('article')  . ': ' . strong( $group_id );
			}
		}

	if( !$preview and !$html )
		{
		#
		#	Inject direction hyper-link...
		#
		$r .= ' / <a href="#" onClick="toggleTextElements()" id="title-toggle">'.gTxt('l10n-toggle').'</a>';
		}

	$r = graf( $r );
	*/
	$buffer = str_replace( $f , $r.n.$f , $buffer );

	if( !$preview and !$html )
		{
		#
		#	Inject direction markup...
		#
		$r = MLPLanguageHandler::get_lang_direction_markup( $lang );
		$buffer = str_replace( $f , $f.$r , $buffer );
		$f = 'id="body"';
		$buffer = str_replace( $f , $f.$r , $buffer );
		$f = 'id="excerpt"';
		$buffer = str_replace( $f , $f.$r , $buffer );
		}
	if( $preview )
		{
		#
		#	Inject direction markup...
		#
		$f = '<td id="article-main"';
		$r = MLPLanguageHandler::get_lang_direction_markup( $lang );
		$buffer = str_replace( $f , $f.$r , $buffer );

		#
		#	Inject direction hyper-link...
		#
		$r = '<td><a href="#" onClick="togglePreview()" id="article-main-toggle">'.gTxt('l10n-toggle').'</a></td>';
		$buffer = str_replace( $f , $r.n.$f , $buffer );
		}

	return $buffer;
	}

function _l10n_replace_rendition( $lang , $replace=false , $id='' )
	{
	$op = 'INSERT';
	if( $replace )
		$op = 'REPLACE';

	if( empty($id) )
		{
		if(!empty($GLOBALS['ID']))
			$id = intval($GLOBALS['ID']);
		else
			$id = gps('ID');
		}

	if( !MLPLanguageHandler::is_valid_code($lang) )
		{
		echo br , "Invalid language code '$lang' calculated in _l10n_add_rendition()";
		return;
		}
	$table_name = safe_pfx( _l10n_make_textpattern_name($lang) );
	$safe_txp = safe_pfx( 'textpattern' );

	$sql = $op." INTO $table_name SELECT * FROM $safe_txp WHERE $safe_txp.ID='$id' LIMIT 1";
	safe_query( $sql );
	}
function _l10n_remove_rendition( $lang , $id )
	{
	if( !MLPLanguageHandler::is_valid_code($lang) )
		{
		echo br , "Invalid language code '$lang' calculated in _l10n_add_rendition()";
		return;
		}
	$table_name = safe_pfx( _l10n_make_textpattern_name($lang) );
	safe_delete( $table_name , "`ID`='$id'" );
	}
function _l10n_add_rendition_to_article_cb( $event , $step )
	{
	require_privs('article');

	global $vars;
	$new_vars = array_merge( $vars , array( L10N_COL_LANG , L10N_COL_GROUP , 'original_ID' ) );

	$save = gps('save');
	if ($save) $step = 'save';

	$publish = gps('publish');
	if ($publish) $step = 'publish';

	$incoming = gpsa($new_vars);
	$default = MLPLanguageHandler::get_site_default_lang();
	$new_lang	= (@$incoming[L10N_COL_LANG]) ? $incoming[L10N_COL_LANG] : $default;

	switch(strtolower($step))
		{
		case 'publish':
			#
			#	Create a group for this article
			#
			MLPArticles::create_article_and_add( $incoming );

			#
			#	Update the language table for the target language...
			#
			_l10n_replace_rendition( $new_lang );
			#
			#	Read the variables to continue the edit...
			#
			_l10n_setup_vars( $event , $step );
			break;
		case 'save':
			#
			#	Record the old and new languages, if there are any changes we need to update
			# both the old and new tables after moving the group/lang over...
			#
			$rendition_id	= $incoming['ID'];

			$info = safe_row( '*' , 'textpattern' , "`ID`='$rendition_id'" );
			if( $info !== false )
				$current_lang	= $info[L10N_COL_LANG];

			#
			#	Check for changes to the article language and groups ...
			#
			MLPArticles::move_to_article( $incoming );

			#
			#	Now we can setup the tables again...
			#
			_l10n_replace_rendition( $new_lang , true , $rendition_id );
			if( $new_lang != $current_lang )
				_l10n_remove_rendition( $current_lang , $rendition_id );

			#
			#	If this rendition is in the default language then update the article
			# title...
			#
			$default_lang = MLPLanguageHandler::get_site_default_lang();
			if( isset( $info['Title'] ) and isset( $info[L10N_COL_GROUP] ) and $current_lang === $default_lang )
				{
				MLPArticles::retitle_article( $info[L10N_COL_GROUP] , $info['Title'] );
				}

			#
			#	Read the variables to continue the edit...
			#
			_l10n_setup_vars( $event , $step );
			break;
		}
	}

function _l10n_changeauthor_notify_routine()
	{
	global $l10n_view;

	#	Permissions for email...
	$send_notifications	= ( '1' == $l10n_view->pref('l10n-send_notifications') ) ? true : false;
	$on_changeauthor	= ( '1' == $l10n_view->pref('l10n-send_notice_on_changeauthor') ) ? true : false;
	$notify_self 		= ( '1' == $l10n_view->pref('l10n-send_notice_to_self') ) ? true : false;

	if( !$send_notifications or !$on_changeauthor )
		return false;

	global $statuses, $sitename, $siteurl, $txp_user;
	$new_user = gps('AuthorID');
	$selected = gps('selected');
	$links    = array();
	$same	  = ($new_user == $txp_user);

	if( empty( $new_user ) )
		return;

	if( !$same or $notify_self )
		{
		if( $selected and !empty($selected) )
			{
			foreach( $selected as $id )
				{
				#
				#	Make a link to the article...
				#
				$row = safe_row('Title , '.L10N_COL_LANG.' , `'.L10N_COL_GROUP.'` , Status' , 'textpattern' , "`ID`='$id'");
				extract( $row );
				$lang   = MLPLanguageHandler::get_native_name_of_lang( $row[L10N_COL_LANG] );
				$status = $statuses[$Status];
				$msg = 	gTxt('title')  . ": \"$Title\"\r\n" .
						gTxt('status') . ": $status , " . gTxt('language') . ": $lang [".$row[L10N_COL_LANG].'] , ' . gTxt( 'group' ) . ': '.$row[L10N_COL_GROUP].".\r\n";
				$msg.= "http://$siteurl/textpattern/index.php?event=article&step=edit&ID=$id\r\n";
				$links[] = $msg;
				}
			}

		extract(safe_row('RealName AS txp_username,email AS replyto','txp_users',"name='$txp_user'"));
		extract(safe_row('RealName AS new_user,email','txp_users',"name='$new_user'"));

		$count = count( $links );
		$s = (($count===1) ? '' : 's');

		$subs = array(	'{sitename}' => $sitename ,
						'{count}' => $count ,
						'{s}' => $s ,
						'{txp_username}' => $txp_username,
						);

		if( $same )
			$body = gTxt( 'l10n-email_body_self' , $subs );
		else
			$body = gTxt( 'l10n-email_body_other' , $subs );
		$body.= join( "\r\n" , $links ) . "\r\n\r\n" . gTxt( 'thanks' ) . "\r\n--\r\n$txp_username.";
		$subject = gTxt( 'l10n-email_xfer_subject' , $subs );

		$ok = @txpMail($email, $subject, $body, $replyto);
		}
	}
function _l10n_post_multi_edit_cb( $event , $step )
	{
	global $l10n_vars;
	global $l10n_view;

	$method   		= gps('edit_method');
	$redirect 		= true;	#	Redirect to the 'list' event, forcing a re-draw with the correct language filters applied.
	$update   		= true;

	#
	#	Special cases...
	#
	switch( $method )
		{
		case 'changeauthor':
			_l10n_changeauthor_notify_routine();
			break;
		}

	if( isset( $l10n_vars['update_work'] ) )
		{
		$work = $l10n_vars['update_work'];
		unset( $l10n_vars['update_work'] );
		if( $work AND !empty( $work ) )
			{
			foreach( $work as $id=>$lang )
				{
				if( $method === 'delete' )
					_l10n_remove_rendition( $lang , $id );
				else
					_l10n_replace_rendition( $lang , true , $id );
				}
			}
		}

	if( $redirect )
		{
		while (@ob_end_clean());

		$search = gpsa( array( 'search_method' , 'crit' , 'event' , 'step' ) );
		$search['event'] = 'list';
		$search['step'] = '';

		$l10n_view->redirect( $search );
		}
	}
function _l10n_pre_multi_edit_cb( $event , $step )
	{
	global $l10n_vars;
	$method = gps('edit_method');
	$things = gps('selected');
	$work 	= array();

	#
	#	Scan the selected items, building a table of languages touched by the edit.
	# Also delete any group info on the delete method calls.
	#
	if( $things )
		{
		foreach( $things as $id )
			{
			$id = intval($id);
			$info = safe_row( '*' , 'textpattern' , "`ID`='$id'" );
			if( $info !== false )
				{
				$article	= $info[L10N_COL_GROUP];
				$lang  		= $info[L10N_COL_LANG];
				$work[$id]=$lang;
				if( 'delete' === $method )
					MLPArticles::remove_rendition( $article , $id , $lang );
				}
			}
		}

	#
	#	Pass the languages array to the post-process routine to reconstruct the
	# per-language tables that were changed by the edit...
	#
	$l10n_vars['update_work'] = $work;
	}

function _l10n_observe_glz_custom_fields( $event , $step )
	{
	# Observer for glz_custom_field events that change the structure of the textpattern table...
	if( gps('delete') || gps('custom_field_number') )
		{
		_l10n_update_dirty_flag( 'DIRTY' );
		}
	}

function _l10n_update_dirty_flag( $v )
	{
	global $prefs;
	set_pref( L10N_DIRTY_FLAG_VARNAME, $v , 'l10n', 2 );
	$prefs[L10N_DIRTY_FLAG_VARNAME] = $v;
	}

function _l10n_check_lang_code( $lang )
	{
	if( !is_string( $lang ) )
		{
		echo 'Non-string language passed to _l10n_check_lang_code() ... ' , var_dump($lang) , br;
		return false;
		}

	if( empty( $lang ) )
		{
		echo 'Blank language passed to _l10n_check_lang_code()' , br;
		return false;
		}

	if( strlen( $lang ) > 2 )
		{
		$code = MLPLanguageHandler::compact_code( $lang );
		if( isset( $code['long'] ) )
			$code = $code['long'];
		else
			$code = $code['short'];
		}
	else
		$code = $lang;

	if( empty( $code ) )
		{
		echo 'Blank language code calculated in _l10n_check_lang_code()' , br;
		return false;
		}

	if( !MLPLanguageHandler::is_valid_code($code) )
		{
		echo 'Invalid language code ['.$code.'] calculated in _l10n_check_lang_code()' , br;
		return false;
		}

	return $code;
	}

function _l10n_check_lang_table( $lang )
	{
	$result = _l10n_check_lang_code( $lang );
	if( !is_string($result) ) return $result;

	$code = $result;
	$table_name = _l10n_make_textpattern_name( $code );
	if( @safe_query( "SHOW COLUMNS FROM `$table_name`" ) )
		{
		return true;
		}
	return array($code, $table_name);
	}
function _l10n_generate_lang_table( $lang )
	{
	$result = _l10n_check_lang_table( $lang );
	if( !is_array($result) ) return $result;

	list($code, $table_name) = $result;
	$where = ' WHERE `'.L10N_COL_LANG."`='$lang'";

	@safe_query( 'LOCK TABLES `'.PFX.$table_name.'` WRITE' );
	@safe_query( 'CREATE TABLE `'.PFX.$table_name.'` LIKE `'.PFX.'textpattern`' );
	@safe_query( 'INSERT INTO `'.PFX.$table_name.'` SELECT * FROM `'.PFX.'textpattern`'.$where );
	@safe_query( 'OPTIMIZE TABLE `'.PFX.$table_name.'`' );
	@safe_query( 'UNLOCK TABLES' );
	}

function _l10n_check_localise_table( $lang )
	{
	$result = _l10n_check_lang_code( $lang );
	if( !is_string($result) ) return $result;

	global $l10n_mappings;
	if( !is_array( $l10n_mappings ) )
		$l10n_mappings = _l10n_remap_fields( '' , '' , true );

	$missing_mappings = array();
	foreach( $l10n_mappings as $table=>$fields )
		{
		$safe_table = safe_pfx( $table );
		foreach( $fields as $field=>$attributes )
			{
			$f = _l10n_make_field_name( $field , $lang );
			$exists = getThing( "SHOW COLUMNS FROM $safe_table LIKE '$f'" );
			if( !$exists )
				{
				if( !isset($missing_mappings[$table]) ) $missing_mappings[$table] = array();
				$missing_mappings[$table][$field] = $attributes['sql'];
				}
			}
		}

	if( count($missing_mappings) > 0 )
		return $missing_mappings;
	else
		return true;
	}
function _l10n_generate_localise_table_fields( $lang )
	{
	$result = _l10n_check_localise_table( $lang );
	if( !is_array($result) ) return $result;

	$default    = MLPLanguageHandler::get_site_default_lang();
	$extend_all = array( 'txp_category' , 'txp_section' );

	foreach( $result as $table=>$fields )
		{
		foreach( $fields as $field=>$sql )
			{
			$do_all = in_array( $table , $extend_all );

			$safe_table = safe_pfx( $table );
			$f = _l10n_make_field_name( $field , $lang );
			$sql = "ADD `$f` ".$sql;
			$ok = @safe_alter( $table , $sql );

			if( $ok and ($do_all or $lang===$default) )
				{
				$sql = "UPDATE $safe_table SET `$f`=`$field` WHERE `$f`=''";
				$ok = @safe_query( $sql );
				}
			}
		}
	}

function _l10n_pre_discuss_multi_edit( $event , $step )
	{
	global $l10n_vars;
	//$languages = array();
	$work = array();

	$things = gps('selected');
	$method = gps('edit_method');

	if( $things )
		{
		foreach( $things as $id )
			{
			$id = intval($id);
			$comment = safe_row( 'parentid as id,visible as current_visibility' , 'txp_discuss' , "`discussid`='$id'" );
			if( $comment !== false )
				{
				$mark_lang = false;
				extract( $comment );

				#
				# It's only going from non_visible->visible or visible->non_visible that
				# needs an update.
				#
				if( 'visible' == $method )
					$mark_lang = (VISIBLE != $current_visibility);
				else
					$mark_lang = (VISIBLE == $current_visibility);

				if( $mark_lang )
					{
					$info = safe_row( L10N_COL_LANG , 'textpattern' , "`ID`='$id'" );
					if( $info !== false )
						{
						if( array_key_exists( L10N_COL_LANG , $info ) )
							{
							$lang = $info[L10N_COL_LANG];
							//$languages[$lang] = $lang;
							$work[$id] = $lang;
							}
						}
					}
				}
			}
		}

	#
	#	Pass the languages array to the post-process routine to reconstruct the
	# per-language tables that were changed by the edit...
	#
	//if( !empty( $languages ) )
		//$l10n_vars['update_tables'] = $languages;
	$l10n_vars['update_work'] = $work;
	}
function _l10n_post_discuss_multi_edit( $event , $step )
	{
	global $l10n_vars;
	$method   = gps('edit_method');
	if( isset( $l10n_vars['update_work'] ) )
		{
		$work = $l10n_vars['update_work'];
		unset( $l10n_vars['update_work'] );
		if( $work AND !empty( $work ) )
			{
			foreach( $work as $id=>$lang )
				{
				_l10n_replace_rendition( $lang , true , $id );
				}
			}
		}
	}

function _l10n_build_sql_set( $table )
	{
	global $l10n_mappings;
	$langs = MLPLanguageHandler::get_site_langs();
	$default = MLPLanguageHandler::get_site_default_lang();
	$set = '';

	if( !isset($l10n_mappings[$table]) )
		return $set;

	$fields = $l10n_mappings[$table];
	foreach( $fields as $field => $attributes )
		{
		foreach( $langs as $lang )
			{
			$f_name = _l10n_make_field_name( $field , $lang );

			if( $lang === $default )
				$f_value = gps( $field );
			else
				$f_value = gps( $f_name );

			$f_name = doSlash( $f_name );
			$f_value = doSlash( $f_value );

			$set[] = "`$f_name`='$f_value'";
			}
		}
	return join( ', ', $set );
	}

function _l10n_category_extend ($evt, $stp, $data, $rs) {
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the remaining language fields...
	#
	global $l10n_mappings;
	$langs = MLPLanguageHandler::get_site_langs();
 	$fields = $l10n_mappings['txp_category'];
	$r = '';
	$id = $rs['id'];

	foreach( $fields as $field => $attributes )
		{
		foreach( $langs as $lang )
			{
			$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );
			$dir = MLPLanguageHandler::get_lang_direction_markup( $lang );
 			if( $lang !== $default )
				{
				$field_name = _l10n_make_field_name( $field , $lang );
				$r .= '<p class="edit-category-title"><span class="edit-label"><label for="category_title_'.$lang.'">['.$full_name.']</label></span>';
				$r .= '<span class="edit-value"><input id="category_title_'.$lang.'" name="' . $field_name . '" value="'.$rs[$field_name].'" size="'.INPUT_REGULAR.'" type="text" '.$dir.' />';
				$r .= '</span></p>'.n;
				}
			}
		}
	return $r;
}

function _l10n_category_paint( $page )
	{
	$default = MLPLanguageHandler::get_site_default_lang();

	$id = gps( 'id' );
	assert_int($id);
	$row = safe_row( '*' , 'txp_category' , "`id`='$id'" );

	#
	#	Insert the default title field's language's direction...
	#
	$dir = MLPLanguageHandler::get_lang_direction_markup( $default ) . ' ';
	$f = '<input type="text" name="title"';
	$page = str_replace( $f , $f.$dir , $page );

	#
	#	Insert the default title field's language name...
	#
	$f = '<label for="category_title">'.gTxt($row['type'].'_category_title');
	$r = ' ['.MLPLanguageHandler::get_native_name_of_lang( $default ).']';
	$page = str_replace( $f , $f.sp.$r , $page );

	return $page;
	}

function _l10n_category_save( $event , $step )
	{
	if (strpos($step, '_create') !== false) {
		$id_name = 'title';
		$id = strtolower(sanitizeForUrl(gps( $id_name )));
	} else {
		$id_name = 'id';
		$id = gps( $id_name );
		assert_int($id);
	}

	$table   = 'txp_category';
	$where = "`$id_name`='$id'";
	$set = _l10n_build_sql_set( $table );
	@safe_update( $table , $set , $where );
	}

function _l10n_section_paint( $page )
	{
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the remaining language title fields...
	#
	global $l10n_mappings, $prefs;
	$langs = MLPLanguageHandler::get_site_langs();
 	$fields = $l10n_mappings['txp_section'];
	$editing = gps( 'name' );
	$row = safe_row( '*' , 'txp_section' , "`name`='".doSlash($editing)."'" );
	if( $row )
		{
		$ver = $prefs['version'];

		$name  = txpspecialchars($row['name']);
		$title = txpspecialchars($row['title']);
		$f = 'id="section_title" /></span></p>';

		foreach( $fields as $field => $attributes )
			{
			$r = '';
			foreach( $langs as $lang )
				{
				$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );
				$dir = MLPLanguageHandler::get_lang_direction_markup( $lang );
				if( $lang !== $default )
					{
					$field_name = _l10n_make_field_name( $field , $lang );
					$field_value = $row[$field_name];
					$r .= '<p class="edit-section-title"><span class="edit-label"><label for="section_title_'.$lang.'">['. $full_name .']</label></span>';
					$r .= '<span class="edit-value"><input id="section_title_'.$lang.'" type="text" size="'.INPUT_REGULAR.'" name="'. $field_name .'" value="'. $row[$field_name] .'"'.$dir.' /></span></p>';
					}
				}
				$page = str_replace( $f , $f.n.$r , $page );
			}
		}

	#
	#	Insert the default title field's language's direction...
	#
	$dir = MLPLanguageHandler::get_lang_direction_markup( $default ) . ' ';
	$f = 'id="section_title"';
	$page = str_replace( $f , $f.$dir , $page );

	#
	#	Insert the default title field's language name...
	#
	$f = '">'.gTxt('section_longtitle');
	$r = '['.MLPLanguageHandler::get_native_name_of_lang( $default ) . ']';
	$page = str_replace( $f , $f.n.$r , $page );

	return $page;
	}
function _l10n_section_save( $event , $step )
	{
	$id_name = 'name';
	$table   = 'txp_section';
	$id = gps( $id_name );
	$where = "`$id_name`='$id'";
	$set = _l10n_build_sql_set( $table );
	@safe_update( $table , $set , $where );
	}

function _l10n_file_paint( $page )
	{
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the remaining language fields...
	#
	global $l10n_mappings;
	$langs = MLPLanguageHandler::get_site_langs();
 	$fields = $l10n_mappings['txp_file'];
	$id = gps( 'id' );
	assert_int($id);
	$row = safe_row( '*' , 'txp_file' , "`id`='$id'" );
	$dir = MLPLanguageHandler::get_lang_direction_markup( $default ) . ' ';
	foreach( $fields as $field => $attributes )
		{
		$r = '';

		foreach( $langs as $lang )
			{
			$field_name = _l10n_make_field_name( $field , $lang );

			if( $lang !== $default )
				{
				$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );
				$dir = MLPLanguageHandler::get_lang_direction_markup( $lang );

				if( $field === 'title' )
					{
					$r .= '<p class="edit-file-title"><span class="edit-label"><label for="file_title_'.$lang.'">['.$full_name.']</label></span>';
					$r .= '<span class="edit-value"><input type="text" id="file_title_'.$lang.'" name="'.$field_name.'" '.$dir.' value="'.$row[$field_name].'" size="'.INPUT_REGULAR.'" /></span></p>'.n;
					$f = '<p class="edit-file-category">';
					}
				else
					{
					$r .= '<p class="edit-file-description"><span class="edit-label"><label for="file_description_'.$lang.'">['.$full_name.']</label></span>';
					$r .= '<textarea id="file_description_'.$lang.'" name="'.$field_name .'" cols="'.INPUT_LARGE.'" rows="'.INPUT_XSMALL.'"'.$dir.'>';
					$r .= $row[$field_name].'</textarea></p>'.n;
					$f = '<fieldset class="file-created">';
					}
				}
			}
			$page = str_replace( $f , $r.n.$f , $page );
		}

	#
	#	Insert the default description field's language name...
	#
	$f = '<label for="file_description">'.gTxt('description');
	$r = ' ['.MLPLanguageHandler::get_native_name_of_lang( $default ) . ']';
	$page = str_replace( $f , $f.sp.$r , $page );

	#
	#	Insert the default title field's language name...
	#
	$f = '<label for="file_title">'.gTxt('title');
	$r = ' ['.MLPLanguageHandler::get_native_name_of_lang( $default ) . ']';
	$page = str_replace( $f , $f.sp.$r , $page );

	return $page;
	}

function _l10n_file_save( $event , $step )
	{
	$id_name = 'id';
	$table   = 'txp_file';
	$id = gps( $id_name );
	assert_int($id);
	$where = "`$id_name`='$id'";
	$set = _l10n_build_sql_set( $table );
	@safe_update( $table , $set , $where );
	}

function _l10n_link_extend ($evt, $stp, $data, $rs) {
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the remaining language fields...
	#
	global $l10n_mappings;
	$langs = MLPLanguageHandler::get_site_langs();
 	$fields = $l10n_mappings['txp_link'];
	$r = '';
	$id = $rs['id'];

	foreach( $fields as $field => $attributes )
		{
		foreach( $langs as $lang )
			{
			$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );
			$dir = MLPLanguageHandler::get_lang_direction_markup( $lang );
 			if( $lang !== $default )
				{
				$field_name = _l10n_make_field_name( $field , $lang );
				$r .= '<p class="edit-link-description"><span class="edit-label">';
				$r .= '<label for="link_description_'.$lang.'">['.$full_name.']</label></span>';
				$r .= '<textarea id="link_description_'.$lang.'" name="'.$field_name .'" cols="'.INPUT_LARGE.'" rows="'.INPUT_SMALL.'"'.$dir.'>';
				$r .= $rs[$field_name];
				$r .= '</textarea></p>'.n;
				}
			}
		}
	return $r;
}

function _l10n_link_paint( $page )
	{
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the default title field's language's direction...
	#
	$dir = MLPLanguageHandler::get_lang_direction_markup( $default ) . ' ';
	$f = 'id="link-description"';
	$page = str_replace( $f , $f.$dir , $page );

	#
	#	Insert the default title field's language name...
	#
	$f = 'for="link_description">'.gTxt('description');
	$r = '['.MLPLanguageHandler::get_native_name_of_lang( $default ) . ']';
	$page = str_replace( $f , $f.n.$r , $page );

	return $page;
	}
function _l10n_link_save( $event , $step )
	{
	$id_name = 'id';
	$table   = 'txp_link';
	$id = gps( $id_name );
	assert_int($id);
	$where = "`$id_name`='$id'";
	$set = _l10n_build_sql_set( $table );
	@safe_update( $table , $set , $where );
	}

function _l10n_image_extend ($evt, $stp, $data, $rs) {
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the remaining language fields...
	#
	global $l10n_mappings;
	$langs = MLPLanguageHandler::get_site_langs();
 	$fields = $l10n_mappings['txp_image'];
	$id = $rs['id'];
	$r = '';
	foreach( $langs as $lang )
		{
		if( $lang !== $default )
			{
			$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );
			$dir = MLPLanguageHandler::get_lang_direction_markup( $lang );
			foreach( $fields as $field => $attributes )
				{
				$field_name = _l10n_make_field_name( $field , $lang );

				if( $field === 'alt' )
					{
					$r .= '<p class="edit-image-alt-text"><span class="edit-label"><label for="image_alt_text_'.$lang.'">'.gTxt('alt_text').' ['.$full_name.']</label></span>';
					$r .= '<span class="edit-value"><input type="text" name="'.$field_name.'" '.$dir.' value="'.$rs[$field_name].'" size="'.INPUT_REGULAR.'" id="image_alt_text_'.$lang.'" /></span></p>'.n;
					}
				else
					{
					$r .= '<p class="edit-image-caption"><span class="edit-label"><label for="image_caption_'.$lang.'">'.gTxt('caption').' ['.$full_name.']</label></span>';
					$r .= '<textarea id="image_caption_'.$lang.'" name="'.$field_name .'" cols="'.INPUT_LARGE.'" rows="'.INPUT_XSMALL.'"'.$dir.'>';
					$r .= $rs[$field_name].'</textarea></p>'.n;
					}
				}
			}
		}

	return $r;
}

function _l10n_image_paint( $page )
	{
	$default = MLPLanguageHandler::get_site_default_lang();

	#
	#	Insert the default title field's language's direction...
	#
	$dir = MLPLanguageHandler::get_lang_direction_markup( $default ) . ' ';
	$f = 'id="image_alt_text"';
	$page = str_replace( $f , $f.$dir , $page );
	$f = 'id="image_caption"';
	$page = str_replace( $f , $f.$dir , $page );

	#
	#	Insert the default title field's language name...
	#
	$f = 'for="image_alt_text">'.gTxt('alt_text');
	$r = '['.MLPLanguageHandler::get_native_name_of_lang( $default ).']';
	$page = str_replace( $f , $f.n.$r , $page );
	$f = 'for="image_caption">'.gTxt('caption');
	$page = str_replace( $f , $f.n.$r , $page );

	return $page;
	}
function _l10n_image_save( $event , $step )
	{
	$id_name = 'id';
	$table   = 'txp_image';
	$id = gps( $id_name );
	$where = "`$id_name`='$id'";
	$set = _l10n_build_sql_set( $table );
	@safe_update( $table , $set , $where );
	}

function _l10n_php2js_array($name, $array)
	{
	# From PHP.net (thanks Graeme!)
	if (is_array($array))
		{
		$result = $name.' = new Array();'.n;
		foreach ($array as $key => $value)
			$result .= _l10n_php2js_array($name.'[\''.$key.'\']',$value,'').n;
		}
	else
		{
		$result = $name.' = \''.$array.'\';';
		}
	return $result;
	}

function _l10n_inject_js()
	{
	$ltr = doSlash( gTxt( 'l10n-ltr' ) );
	$rtl = doSlash( gTxt( 'l10n-rtl' ) );
	$toggle_title = doSlash( gTxt('l10n-toggle') );

	$langs = MLPLanguageHandler::get_installation_langs();
	$langs = MLPLanguageHandler::do_fleshout_dirs( $langs );
	$langs = _l10n_php2js_array( 'langs' , $langs );

	$fn = <<<end_js
var {$langs}
	var search_box   = null;
	var search_term  = null;
	var result_div   = null;
	var result_list  = null;
	var cresult_div  = null;
	var result_num   = null;
	var csearch_box  = null;
	var csearch_lang = null;
	var str_edit_div = null;
	var sbn_lang_sel = null;
	var last_req     = "";

	var	xml_manager = false;
	if( window.XMLHttpRequest )
		{
		xml_manager = new XMLHttpRequest();
		}

function addLoadEvent(func)
	{
	var oldonload = window.onload;
	if (typeof window.onload != 'function')
		{
		window.onload = func;
		}
	else
		{
		window.onload = function()
			{
			oldonload();
			func();
			}
		}
	}

function l10nSetCookie(name, value, days)
	{
	if (days)
		{
		var date = new Date();
		date.setTime(date.getTime() + (days*24*60*60*1000));
		var expires = '; expires=' + date.toGMTString();
		}
	else
		{
		var expires = '';
		}

	document.cookie = name + '=' + value + expires + '; path=/';
	}

function l10nGetCookie(name)
	{
	var nameEQ = name + '=';

	var ca = document.cookie.split(';');

	for (var i = 0; i < ca.length; i++)
		{
		var c = ca[i];

		while (c.charAt(0)==' ')
			{
			c = c.substring(1, c.length);
			}

		if (c.indexOf(nameEQ) == 0)
			{
			return c.substring(nameEQ.length, c.length);
			}
		}

	return null;
	}

addLoadEvent( function(){l10n_js_init();} );
function l10n_js_init()
	{
	if (!document.getElementById)
		{
		return false;
		}

	search_box   = document.getElementById('l10n_search_by_name');
	result_div   = document.getElementById('l10n_div_sbn_result_list');
	result_num   = document.getElementById('l10n_result_count');
	csearch_box  = document.getElementById('l10n_search_by_content');
	cresult_div  = document.getElementById('l10n_sbc_result_list');
	csearch_lang = document.getElementById('sbc_lang_selection');
	str_edit_div = document.getElementById('l10n_div_string_edit');
	sbn_lang_sel = document.getElementById('sbn_lang_selection');

	if( search_box == null )
		return;

	addEvent( search_box , 'keyup' , l10nRefineResultsEventHandler , false );

	var search_type = getCookie( 'l10n_string_search_by' );
	if( search_type == null || search_type == '' || search_type == 'name' )
		{
		var selection = getCookie( 'l10n_string_search_by_subtype' );
		if( selection == null || selection == 'all' )
			{
			sbn_lang_sel.disabled = true;
			selection = '';
			}
		else
			{
			sbn_lang_sel.disabled = false;
			selection = sbn_lang_sel.value;
			}
		do_name_search( selection );
		}
	else
		{
		result_div.className="l10n_hidden";
		cresult_div.className="l10n_visible";
		do_content_search();
		}
	}

function l10nRefineResultsEventHandler(event)
	{
	l10nRefineResults();
	}

function l10nRefineResults()
	{
	var result_list  = document.getElementById('l10n_sbn_result_list');
	var target = trim( search_box.value );
	target = target.toLowerCase()
	var t_len = target.length;

	//
	// Iterate over all strings showing those that match, hiding those that don't...
	//
	var item = result_list.firstChild;
	var visible = 0;
	while( item != null )
		{
		var match_text = item.id;
		var tmp = match_text.substring(0,t_len)
		var match = (tmp == target);

		if( match )
			{
			item.className = 'l10n_visible';
			++visible;
			}
		else
			{
			item.className = 'l10n_hidden';
			}

		item = item.nextSibling;
		}
	var result_num   = document.getElementById('l10n_result_count');
	result_num.innerHTML = visible;
	l10nSetCookie( 'search_string_name_live' , target , 365 );
	}

function trim(term)
	{
	var len = term.length;
	var lenm1 = len - 1;

	while (term.substring(0,1) == ' ')
		{
		term = term.substring(1, term.length);
		}
	while (term.substring(term.length-1, term.length) == ' ')
		{
		term = term.substring(0,term.length-1);
		}
	return term;
	}

function make_xml_req(req,req_receiver)
	{
	if( !xml_manager || (req_receiver == null) )
		return false;

	if( (last_req != req) && (req != '') )
		{
		if( xml_manager && xml_manager.readyState < 4 )
			{
			xml_manager.abort();
			}
		if( window.ActiveXObject )
			{
			xml_manager = new ActiveXObject("Microsoft.XMLHTTP");
			}

		xml_manager.onreadystatechange = req_receiver;
		xml_manager.open("GET", req);
		xml_manager.send(null);
		last_req = req;
		}
	}

function do_name_search( lang )
	{
	var req = "?event=l10n&tab=snippets&step=l10n_search_for_names&l10n-sfn=" + lang;
	make_xml_req( req , ns_result_handler );
	}
function ns_result_handler()
	{
	if (xml_manager.readyState == 4)
		{
		var results = xml_manager.responseText;
		result_div.innerHTML = results;
		l10nRefineResults();
		}
	}

function do_content_search()
	{
	var search_term = encodeURI(csearch_box.value);
	var search_lang = csearch_lang.value;
	var query       = search_term + search_lang;

	if( search_term != '' )
		{
		var req = "?event=l10n&tab=snippets&step=l10_search_for_content&l10n-sfc=" + search_term + "&l10n-lang=" + search_lang;
		make_xml_req( req , cs_result_handler );
		l10nSetCookie( 'search_string_content' , search_term , 365 );
		l10nSetCookie( 'search_string_lang' , search_lang , 365 );
		}
	}

function cs_result_handler()
	{
	if (xml_manager.readyState == 4)
		{
		var results = xml_manager.responseText;
		cresult_div.innerHTML = results;
		}
	}

function do_string_edit(id)
	{
	var req = "?event=l10n&tab=snippets&XMLHTTP=true&id=" + id;
	make_xml_req( req , string_edit_handler );
	}


function string_edit_handler()
	{
	if (xml_manager.readyState == 4)
		{
		var results = xml_manager.responseText;
		str_edit_div.innerHTML = results;
        window.scrollTo(0,128);
		}
	}

function update_search( id )
	{
	var by_name = document.getElementById('l10n_div_s_by_n');
	var by_cont = document.getElementById('l10n_div_s_by_c');
	if( id == 'sbn_radio_button' )
		{
		by_name.className="l10n_visible";
		by_cont.className="l10n_hidden";
		result_div.className="l10n_visible";
		cresult_div.className="l10n_hidden";
		l10nSetCookie( 'l10n_string_search_by' , 'name' , 365 );
		var selection = l10nGetCookie( 'l10n_string_search_by_subtype' );
		if( selection == null || selection == 'all' )
			selection = '';
		else
			selection = sbn_lang_sel.value;
		do_name_search( selection );
		}
	else if ( id == 'sbc_radio_button' )
		{
		by_name.className="l10n_hidden";
		by_cont.className="l10n_visible";
		result_div.className="l10n_hidden";
		cresult_div.className="l10n_visible";
		l10nSetCookie( 'l10n_string_search_by' , 'cont' , 365 );
		do_content_search();
		}
	else if( id == 'sbn_missing_radio_button' )
		{
		sbn_lang_sel.disabled = false;
		var selection = sbn_lang_sel.value;
		l10nSetCookie( 'l10n_string_search_by_subtype' , 'missing' , 365 );
		do_name_search( selection );
		}
	else if( id == 'sbn_all_radio_button' )
		{
		sbn_lang_sel.disabled = true;
		l10nSetCookie( 'l10n_string_search_by_subtype' , 'all' , 365 );
		do_name_search( '' );
		}
	}
function on_sbn_lang_change()
	{
	var selection = sbn_lang_sel.value;
	l10nSetCookie( 'search_string_name_lang' , selection , 365 );
	do_name_search( selection );
	}

function toggleTextElements()
	{
	toggleDirection('title');
	toggleDirection('body');
	toggleDirection('excerpt');
	}

function togglePreview()
	{
	toggleDirection('article-main');
	}

function toggleDirection(id)
	{
	if (!document.getElementById)
		{
		return false;
		}

	var textarea = document.getElementById(id + '-data');
	if( textarea == null )
		textarea = document.getElementById(id);
	var toggler  = document.getElementById(id + '-toggle');

	if (textarea.style.direction == 'ltr')
		{
		textarea.style.direction = 'rtl';
		if( toggler != null )
			toggler.innerHTML = '$rtl';
		}
	else
		{
		textarea.style.direction = 'ltr';
		if( toggler != null )
			toggler.innerHTML = '$ltr';
		}
	}
function resetToggleDir( id , dir )
	{
	var e = document.getElementById(id);
	if( e == null )
		return;

	e.style.direction = dir;
	}
function on_lang_selection_change()
	{
	var selection = document.getElementById('l10n_lang_selector').value;
	var dir = langs[selection];

	l10nSetCookie( 'rendition_lang_selection' , selection , 365 );

	resetToggleDir( 'title', dir );
	resetToggleDir( 'body', dir );
	resetToggleDir( 'excerpt', dir );
	resetToggleDir( 'article-main', dir );

	var toggler = document.getElementById('title-toggle');
	if( toggler != null )
		toggler.innerHTML = '$toggle_title';

	var toggler  = document.getElementById('article-main-toggle');
	if( toggler != null )
		toggler.innerHTML = '$toggle_title';
	}

end_js;
	return $fn;
	}

?>
