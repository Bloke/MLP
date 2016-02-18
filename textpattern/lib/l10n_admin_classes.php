<?php

if( !defined( 'txpinterface' ) ) exit;

global $l10n_language , $l10n_default_string_lang;

#
#	Bring in the strings. Try getting the localised strings if possible, else
# bring in the defaults...
#
$l10n_langname = LANG;
$installed = Txp::get('\Netcarver\MLP\Kickstart')->l10n_installed();
if( $installed )
	$l10n_langname = $l10n_language['long'];

$file_name = txpath.DS.'lib'.DS.'l10n_'.$l10n_langname.'_strings.php';
if( is_readable($file_name) )
	{
	//echo br, "Reading $file_name";
	include_once $file_name;
	}
else
	{
	//echo br, "FAILED TO READ SPECIFIC STRINGS ... $file_name";
	include_once txpath.DS.'lib'.DS.'l10n_default_strings.php';
	$file_name = txpath.DS.'lib'.DS.'l10n_'.$l10n_default_strings_lang.'_strings.php';
	if( is_readable($file_name) )
		{
		//echo br, "Reading $file_name";
		include_once $file_name;
		}
	//else
		//echo br, "FAILED TO READ STRINGS ... \$l10n_language = " , var_dump($l10n_language);
	}

#
#	Classes for admin side operations...
#
class MLPTableManager
	{
	static function walk_table_return_array( $table , $fname , $fdata , $fn , $fn_data = null )
		{
		if( !is_callable( $fn ) )
			return;

		$results = array();

		$rs = safe_rows_start( "$fname as name, $fdata as data", $table, '1=1' ) ;
		if( $rs && mysqli_num_rows($rs) > 0 )
			{
			while ( $row = nextRow($rs) )
				{
				$r = call_user_func( $fn , $table , $row , $fn_data );
				if( is_array( $r ) )
					$results = array_merge( $results , $r );
				}
			}

		return $results;
		}
	static function walk_table_find( $table , $fname , $fdata , $string , $markup = false )
		{
		$fndata = array(
			'q'     => $string ,
			'fname' => $fname ,
			'fdata' => $fdata ,
			'markup'=> $markup
			);
		$fn = array( 'MLPTableManager' , 'find_cb' );
		$results = MLPTableManager::walk_table_return_array( $table , $fname , $fdata , $fn , $fndata );
		return $results;
		}
	static function walk_table_replace_simple( $table , $fname , $fdata , $fstrings , $rstrings )
		{
		$fndata = array(
			'fstrings'  => $fstrings ,
			'rstrings'  => $rstrings ,
			'fname'     => $fname ,
			'fdata'     => $fdata ,
			);
		$fn = array( 'MLPTableManager' , 'replace_cb' );
		$results = MLPTableManager::walk_table_return_array( $table , $fname , $fdata , $fn , $fndata );
		return $results;
		}
	static function replace_cb( $table , $row , $fndata )
		{
		extract( $fndata );
		extract( $row );

		$data  = str_replace( $fstrings , $rstrings , $data );

		$data  = doSlash( $data );
		$name  = doSlash( $name );
		$fdata = doSlash( $fdata );
		$fname = doSlash( $fname );
		$ok = safe_update( $table , " `$fdata`='$data'" , " `$fname`='$name'" );

		$r[$table.'.'.$name] = $ok;

		return $r;
		}
	static function find_cb( $table , $row , $data )
		{
		$markup    = false;
		extract( $data );								# override the default value of 'markup' variable
														# if set to true => not a regex find. Replace search term
														# with html highlight.
		$qq        = '/'.preg_quote( $q ).'/';			# processed search term.
		$qr        = '<span class="l10n_highlite">'.txpspecialchars( $q ).'</span>';	#search term html highlight
		$marker    = md5( $q );
		$col_name  = $row['name'];						# name of the row we are processing
		$input     = explode( "\n" , $row['data'] );	# array of lines obtained from the row data

		$r = preg_grep( $qq , $input );					# find all matching rows!

		$result = array();
		if( !empty( $r ) )
			foreach( $r as $line_num=>$match )
				{
				++$line_num;							# convert to 1 based indexing

				if( $markup )
					{
					$match = str_replace( $q , $marker , $match );
					$match = txpspecialchars( $match );
					$match = str_replace( $marker , $qr , $match );
					$result[$table.'.'.$col_name][$line_num] = $match;
					}
				else
					$result[$table.'.'.$col_name][$line_num] = trim( txpspecialchars($match) );
				}

		return $result;
		}
	}

class MLPArticles
	{
	static function create_table()
		{
		$db_charsetcollation = _l10n_get_db_charsetcollation();
		$db_type = _l10n_get_dbserver_type();

		$sql = array();
		$sql[] = 'CREATE TABLE IF NOT EXISTS `'.PFX.L10N_ARTICLES_TABLE.'` (';
		$sql[] = '`ID` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY , ';
		$sql[] = "`names` TEXT NULL , ";
		$sql[] = "`members` TEXT NULL";
		$sql[] = ") $db_type $db_charsetcollation";
		return safe_query( join('', $sql) );
		}
	static function destroy_table()
		{
		$sql = 'drop table `'.PFX.L10N_ARTICLES_TABLE.'`';
		return safe_query( $sql );
		}
	static function _get_article_info( $id )
		{
		$info = safe_row( '*' , L10N_ARTICLES_TABLE , "`ID`='$id'" );
		if( !empty($info) )
			$info['members'] = unserialize( $info['members'] );
		return $info;
		}
	static function rendition_exists( $article_id , $long_lang )
		{
		$info 	= MLPArticles::_get_article_info( $article_id );
		$result = array_key_exists( $long_lang , $info['members'] );
		return $result;
		}
	static function create_article( $title , $members , $article_id=0 )
		{
		$members = serialize( $members );
		if( 0 === $article_id )
			$article = safe_insert( L10N_ARTICLES_TABLE , "`names`='$title', `members`='$members'" );
		else
			$article = safe_insert( L10N_ARTICLES_TABLE , "`names`='$title', `members`='$members', `ID`=$article_id" );
		return $article;
		}
	static function destroy_article( $article_id )
		{
		return safe_delete( L10N_ARTICLES_TABLE , "`ID`=$article_id" );
		}
	static function _update_article( $article_id , $title , $members )
		{
		$members = serialize( $members );
		$title = doSlash( $title );
		$article = safe_update( L10N_ARTICLES_TABLE , "`names`='$title', `members`='$members'" , "`ID`=$article_id" );
		return $article;
		}
	static function change_rendition_language( $article_id , $rendition_id , $rendition_lang , $target_lang )
		{
		extract( MLPArticles::_get_article_info( $article_id ) );

		if( array_key_exists( $target_lang , $members ) )
			return "Article $article_id already has a rendition for $target_lang.";

		if( !array_key_exists( $rendition_lang , $members ) )
			return "Rendition $rendition_id in $rendition_lang does not belong to article $article_id.";
		unset( $members[$rendition_lang] );

		$rendition_id = (int)$rendition_id;
		$members[$target_lang] = $rendition_id;

		$ok = MLPArticles::_update_article( $article_id , $names , $members );
		return $ok;
		}
	static function add_rendition( $article_id , $rendition_id , $rendition_lang , $check_membership = true , $insert_group = false , $name = '' )
		{
		$info = MLPArticles::_get_article_info( $article_id );
		if( empty( $info ) )
			{
			if( $insert_group )
				{
				$title = '';
				$article_id = MLPArticles::create_article( $title , array() , $article_id );
				$info = MLPArticles::_get_article_info( $article_id );
				if( empty( $info ) )
					return "Article $article_id does not exist and could not be added";
				}
			else
				return "Article $article_id does not exist";
			}

		extract( $info );
		$rendition_id = (int)$rendition_id;

		if( array_key_exists( $rendition_lang , $members ) )
			return "A rendition in $rendition_lang is already present in article $article_id.";
		if( $check_membership and in_array( $rendition_id , $members ) )
			return "Rendition $rendition_id is already a member of article $article_id.";

		$members[$rendition_lang] = $rendition_id;
		$lang_match = ($rendition_lang === MLPLanguageHandler::get_site_default_lang());

		if( !empty( $name ) and $lang_match and $insert_group )
			$names = $name;
		$ok = MLPArticles::_update_article( $ID , $names , $members );
		if( !$ok )
			$ok = "Could not update article $article_id.";
		return $ok;
		}
	static function remove_rendition( $article_id , $rendition_id , $rendition_lang )
		{
		$g_info = MLPArticles::_get_article_info( $article_id );
		if( empty($g_info) )
			return "Article $article_id does not exist";

		extract( $g_info );

		$rendition_id = (int)$rendition_id;

		if( $members[$rendition_lang] != $rendition_id )	# Rendition is not in this article under this language!
			{
			return "No $rendition_lang rendition in article $article_id.";
			}

		unset( $members[$rendition_lang] );

		if( !empty( $members ) )
			{
			$result = MLPArticles::_update_article( $ID , $names , $members );
			if(!$result)
				$result = "Could not update article $article_id.";
			}
		else
			{
			$result = safe_delete( L10N_ARTICLES_TABLE , "`ID`=$ID" );
			if(!$result)
				$result = "Could not delete article $article_id.";
			}

		return $result;
		}
	static function _add_mapping( $article_id , $mapping )
		{
		$info = MLPArticles::_get_article_info( $article_id );
		if( empty( $info ) or (count($mapping)!==1) )
			return false;

		$mappings = $info['members'];

		foreach( $mapping as $lang=>$id )
			{
			if( in_array( $id , $mappings ) or array_key_exists( $lang, $mappings ) )
				return false;
			}

		$mappings[$lang] = $id;

		MLPArticles::_update_article( $article_id , $info['names'] , $mappings );
		return true;
		}
	static function create_article_and_add( $rendition )
		{
		$result = false;
		$name = doSlash($rendition['Title']);
		$lang = (@$rendition[L10N_COL_LANG] !== '-') ? $rendition[L10N_COL_LANG] : MLPLanguageHandler::get_site_default_lang();
		$id = @$GLOBALS['ID'];
		if( !isset( $id ) or empty( $id ) )
			$id = $rendition['ID'];
		$id = (int)$id;
		$mapping =  array( $lang=> $id );

		if( isset( $rendition[L10N_COL_GROUP] ) and !empty($rendition[L10N_COL_GROUP]) )
			{
			$article_id = $rendition[L10N_COL_GROUP];
			MLPArticles::_add_mapping( $article_id , $mapping );
			}
		else
			{
			$article_id = MLPArticles::create_article( $name , $mapping );
			}

		if( $article_id !== false and $article_id !== true )
			{
			//	echo br, "Added article '$name'[$article_id], updating rendition $id ... L10N_COL_LANG = '$lang' , L10N_COL_GROUP = '$article_id'";
			#	Update the rendition to point to its article and have a translation accounted to it...
			$result = safe_update( 'textpattern', "`".L10N_COL_LANG."` = '$lang',`".L10N_COL_GROUP."` = $article_id" , "ID=$id" );
			}
		return $result;
		}
	static function get_remaining_langs( $article_id )
		{
		#
		#	Returns an array of the site languages that do not have existing renditions in this article...
		#
		$langs 	= MLPLanguageHandler::get_site_langs();
		$info 	= MLPArticles::_get_article_info( $article_id );
		$to_do	= array();

		if( !empty( $info ) and !empty($langs) )
			{
			$mapped_langs = $info['members'];
			foreach( $langs as $lang )
				{
				if( !array_key_exists($lang , $mapped_langs) )
					$to_do[$lang] = MLPLanguageHandler::get_native_name_of_lang($lang);
				}
			}

		return $to_do;
		}
	static function move_to_article( $rendition )
		{
		global $l10n_article_message;

		#	Get the new entries...
		$new_article	= $rendition[L10N_COL_GROUP];
		$new_lang		= (@$rendition[L10N_COL_LANG]) ? $rendition[L10N_COL_LANG] : MLPLanguageHandler::get_site_default_lang();
		$rendition_id	= (int)$rendition['ID'];

		#	Read the existing rendition entries...
		$info = safe_row( '*' , 'textpattern' , "`ID`=$rendition_id" );
		if( $info === false )
			{
			$l10n_article_message = "Error: failed to read rendition $rendition_id data.";
			return false;
			}

		$current_article	= $info[L10N_COL_GROUP];
		$current_lang		= $info[L10N_COL_LANG];

		if( ($new_article == $current_article) and ($new_lang == $current_lang) )
			{
			return true;
			}

		#	Add rendition to new article...
		$result = MLPArticles::add_rendition( $new_article , $rendition_id , $new_lang , false );
		if( $result !== true )
			{
			$l10n_article_message = 'Error: ' . $result;
			return false;
			}

		#	Remove article from existing group...
		$result = MLPArticles::remove_rendition( $current_article , $rendition_id , $current_lang );
		if( $result !== true )
			{
			#	Attempt to remove from the article we just added to...
			MLPArticles::remove_rendition( $new_article , $rendition_id , $new_lang );
			$l10n_article_message = 'Error: ' . $result;
			return false;
			}

		# 	Update the entries in the article...
		$ok = safe_update( 'textpattern', "`".L10N_COL_GROUP."`='$new_article' , `".L10N_COL_LANG."`='$new_lang'" , "`ID`=$rendition_id" );
		if( $ok )
			$l10n_article_message = "Language: {$current_lang}->{$new_lang}, article:{$current_article}->{$new_article}";
		else
			$l10n_article_message = 'Warning: Failed to record changes to renditions table';

		return true;
		}
	static function get_articles( $criteria , $sort_sql='ID' , $offset='0' , $limit='' )
		{
		if( $offset == '0' and $limit == '' )
			$rs = safe_rows_start('*', L10N_ARTICLES_TABLE, "$criteria order by $sort_sql" );
		else
			$rs = safe_rows_start('*', L10N_ARTICLES_TABLE, "$criteria order by $sort_sql limit $offset, $limit" );
		return $rs;
		}
	static function check_groups()
		{
		#
		#	index => array( add|delete|skip , rendition-id , article-id , description );
		#
		$result = array();

		$members_count = 0;
		$langs = MLPLanguageHandler::get_site_langs();

		#
		#	Examing the groups table...
		#
		$articles = MLPArticles::get_articles( '1=1' );
		if( count( $articles ) )
			{
			while( $article = nextRow($articles) )
				{
				#
				#	Get the article's members...
				#
				extract( $article );
				$ID = (int)$ID;
				$members = unserialize( $members );
				$m_count = count( $members );
				$members_count += $m_count;

				#
				#	Find the members from the textpattern table too...
				#
				$renditions = safe_column( 'ID', 'textpattern' , "`".L10N_COL_GROUP."`=$ID" );
				$t_count = count( $renditions );

				#
				#	Take the diffs...
				#
				$diff_members_renditions = array_diff( $members , $renditions );
				$diff_renditions_members = array_diff( $renditions , $members );
				$count_m_r = count($diff_members_renditions);
				$count_r_m = count($diff_renditions_members);

				if( $count_m_r > 0 )
					{
					#
					#	Need to delete extra renditions from the articles table...
					#
					foreach( $diff_members_renditions as $lang=>$rendition )
						{
						unset( $members[$lang] );
						$result[] = array( 'delete' , $rendition , $ID , gTxt('l10n-del_phantom', array( '{rendition}'=>$rendition, '{ID}'=>$ID) ) );
						}
					MLPArticles::_update_article( $ID , $names , $members );
					}
				if( $count_r_m > 0 )
					{
					#
					#	Need to add missing renditions to the articles table...
					#
					foreach( $diff_renditions_members as $rendition )
						{
						$rendition = (int)$rendition;

						$details = safe_row( '*' , 'textpattern' , "`ID`=$rendition" );
						if( !empty( $details ) )
							$lang = $details[L10N_COL_LANG];
						else
							continue;

						#
						#	Check it's a valid site language...
						#
						if( !in_array( $lang , $langs ) )
							{
							$result[] = array( 'skip' , $rendition , $ID , gTxt('l10n-skip_rendition' , array('{rendition}'=>$rendition,'{ID}'=>$ID,'{lang}'=>$lang)) );
							continue;
							}
						$members[$lang] = $rendition;
						MLPArticles::_update_article( $ID , $names , $members );
						$result[] = array( 'add' , $rendition , $ID , gTxt('l10n-add_missing_rend',array('{rendition}'=>$rendition, '{ID}'=>$ID)) );
						}
					}
				}
			}
		return $result;
		}
	static function get_total()
		{
		return safe_count(L10N_ARTICLES_TABLE, "1" );
		}
	static function retitle_article( $article_id , $new_title )
		{
		$new_title = doSlash( $new_title );
		$info = MLPArticles::_get_article_info( $article_id );
		MLPArticles::_update_article( $article_id , $new_title , $info['members'] );
		}
	static function force_integer_ids()
		{
		#echo br , "Entering MLPArticles::force_integer_ids()";
		$articles = MLPArticles::get_articles( '1=1' );
		if( count( $articles ) )
			{
			while( $article = nextRow($articles) )
				{
				#
				#	Get the article's members...
				#
				extract( $article );
				$members = unserialize( $members );

				#echo br , "Processing article $ID:$names " , dmp($members);

				if( !empty( $members ) )
					{
					$new_members = array();

					foreach( $members as $lang => $rendition_id )
						$new_members[$lang] = (int)$rendition_id;

					#echo "New members => " , dmp($new_members);

					MLPArticles::_update_article( $ID , $names , $new_members );
					}
				}
			}
		#echo br , "Leaving MLPArticles::force_integer_ids()";
		}
	}

class MLPSnips
	{
	/*
	class MLPSnips implements localised "snippets" within page and
	form templates. Uses the services of the string_handler to localise the
	strings therein.
	*/

	static function get_special_snippets()
		{
		global $prefs;
		$specials=array('snip-site_slogan');
		$custom_fields = preg_grep("(^custom_\d+_set$)", array_keys($prefs));
		if (NULL !== $custom_fields)
			{
			$langs = MLPLanguageHandler::get_site_langs();
			foreach( $custom_fields as $name )
				{
				$translation = $prefs[$name];
				if( $translation )	#only store this entry if there is a name for it...
					{
					$specials[] = $name = 'snip-'.$name;
					$stats = array();
					$strings = MLPStrings::get_string_set( $name , $stats );
					#dmp( $strings );
					foreach( $langs as $lang )
						{
						if( !@$strings[$lang] && $translation )
							MLPStrings::store_translation_of_string( $name , 'admin' , $lang , $translation );
						}
					}
				}
			}
		return $specials;
		}
	static function get_pattern( $name )
		{
		# Use the first snippet detection pattern for a simple snippet format that is visible when the substitution fails.
		# Use the second snippet detection pattern if you want unmatched snippets as xhtml comments.
		//static $snippet_pattern = "/##([\w|\.|\-]+)##/";

		# The following pattern is used to match any l10n_snippet tags in pages and forms.
		static $snippet_tag_pattern = "/\<txp:text item=\"([\w|\.|\-]+)\"\s*\/\>/";

		# The following are the localise tag pattern(s)...
		static $tag_pattern = '/\<\/*txp:l10n_localise(\w|\=|\"|\-|\_|\'|\s)*\>/';

		switch( $name )
			{
			case 'snippet' :
				return L10N_SNIPPET_PATTERN;
			break;
			default :
			case 'snippet_tag' :
				return $snippet_tag_pattern;
			break;
			}
		}

	static function find_snippets_in_block( &$thing , &$raw_snippet_count )
		{
		/*
		Scans the given block ($thing) for snippets and returns their names as the values of an array.
		If merge is true then these values are expanded with txp_lang data
		*/
		$out = array();
		$tags = array();

		# Match all directly included snippets...
		preg_match_all( MLPSnips::get_pattern('snippet') , $thing , $out );

		# Match all snippets included as txp tags...
		preg_match_all( MLPSnips::get_pattern('snippet_tag') , $thing , $tags );

		#	cleanup and merge the snippets, removing duplicates...
		$out = doArray( $out[1] , 'strtolower' );
		$tags = doArray( $tags[1] , 'strtolower' );
		$out = array_unique( array_merge( $out , $tags ) );
		$raw_snippet_count = count( $out );
		unset($tags);
		return $out;
		}

	static function get_snippet_strings( $names , &$stats )
		{
		$result = array();

		if( !is_array( $names ) )
			$names = array( $names );

		$name_set = '';
		foreach( $names as $name )
			{
			$name_set .= "'$name', ";
			$result[$name] = '';
			}
		$name_set = rtrim( $name_set , ', ' );
		if ( empty( $name_set ) )
			$name_set = "''";

		$where = " `name` IN ($name_set)";
		$rs = safe_rows_start( 'lang, name, '.L10N_COL_OWNER, 'txp_lang', $where );
		//$rs = safe_rows_start( 'lang, name, owner', 'txp_lang', $where );

		$result = array_merge( $result , MLPStrings::get_strings( $rs , $stats ) );
		ksort( $result );
		return $result;
		}
	}

class MLPStrings
	{
	static function convert_case( $string , $convert = MB_CASE_TITLE )
		{
		static $exists;

		$exists = function_exists('mb_convert_case');

		$result = $string;

		if( $exists )
			$result = mb_convert_case( $result, $convert, "UTF-8" );
		else
			{
			switch( $convert )
				{
				case MB_CASE_TITLE:
					$result = ucwords( $result );
					break;
				case MB_CASE_UPPER:
					$result = strtoupper( $result );
					break;
				case MB_CASE_LOWER:
					$result = strtolower( $result );
					break;
				}
			}

		return $result;
		}
	static function make_legend( $title , $args = null )
		{
		$title = gTxt( $title , $args );
		$title = MLPStrings::convert_case( $title , MB_CASE_TITLE );
		$title = tag( $title.'&#8230;', 'legend' );
		return $title;
		}
	static function strip_leading_section( $string , $delim='.' )
		{
		/*
		Simply removes anything that prefixes a string up to the delimiting character.
		So 'hello.world' -> 'world'
		*/
		if( empty( $string ))
			return '';

		$i = strstr( $string , $delim );
		if( false === $i )
			return $string;
		$i = ltrim( $i , $delim );
		return $i;
		}

	static function do_prefs_name( $plugin , $add = true )
		{
		static $pfx;
		static $pfx_len;

		if( !isset( $pfx ) )
			{
			$pfx = 'l10n_registered_plugin'.L10N_SEP;
			$pfx_len = strlen( $pfx );
			}

		if( $add )
			return  $pfx.$plugin;
		else
			return substr( $plugin , $pfx_len );
		}

	static function if_plugin_registered( $plugin , $lang , $count = 0 , $strings = null )
		{
		//echo br , "Checking [$plugin] ";
		global $prefs;
		static $cache = array();

		if( empty($plugin) )
			{
			//echo " ... not a plugin name, returning.";
			return false;
			}

		$name = MLPStrings::do_prefs_name( $plugin );
		if( empty($name) )
			{
			//echo " ... Invalid name, returning.";
			return false;
			}

		if( !isset( $cache[$name] ) )
			{
			$details = @$prefs[$name];
			if( !isset( $details ) )
				{
				//echo " ... not registered.";
				return false;	#	Not registered.
				}

			//echo " ... caching details";
			$cache[$name] = unserialize( $details );	#	Cache registered values.
			}

		//echo " ... reading cache";
		$details = $cache[$name];

		if( empty( $details ) )
			{
			//echo " ... not registered.";
			return false;
			}

		if( null === $strings )
			{
			//echo " ... registered!";
			return $details;
			}

		#
		#	Registered, but changed?
		#
		$md5 = @$details['md5'];

		#
		#	Do an md5 check vs the strings keys...
		#
		$keys = serialize( array_keys( $strings ) );
		//echo br , var_dump( $keys );
		$keys = md5(  $keys );
		//echo " ... comparing hashes original($md5), new($keys)";
		if( $keys !== $md5 )
			{
			//echo " ... !!!CHANGED!!!";
			return false;
			}
		//echo " ... not changed ... registered.";
		return $details;
		}

	static function register_plugin( $plugin , $pfx , $string_count , $lang , $event , &$strings )
		{
		//echo br , "register_plugin( $plugin , $pfx , $string_count , $lang , $event , $strings )";
		$keys = serialize( array_keys($strings) );
		//echo br , "\$keys = $keys";
		$keys = md5( $keys );

		$name = MLPStrings::do_prefs_name( $plugin );
		$vals = serialize( array( 'pfx'=>doSlash($pfx) , 'num'=>$string_count , 'lang'=>$lang , 'event'=>doSlash($event) , 'md5'=>$keys ) );
		$result = set_pref( doSlash($name) , $vals , L10N_NAME , 2 );
		if( $result !== false )
			{
			global $prefs;
			@$prefs[$name] = $vals;
			}

		#
		#	Perform a one-shot update of the owner field of any existing string that
		# has a matching prefix. This catches and re-enables residual strings on a re-install.
		#
		$where = ' `name` LIKE "'.$pfx.L10N_SEP.'%"';
		safe_update( 'txp_lang' , '`'.L10N_COL_OWNER."`='$plugin'" , $where );
		//safe_update( 'txp_lang' , "`owner`='$plugin'" , $where );

		return $result;
		}

	static function unregister_plugin( $plugin )
		{
		global $prefs;
		$name = doSlash( MLPStrings::do_prefs_name( $plugin ) );
		$ok = safe_delete( 'txp_prefs' , "`name`='$name' AND `event`='".L10N_NAME.'\'' );
		unset( $prefs[$name] );
		return $ok;
		}

	static function insert_strings( $pfx , &$strings , $lang , $event='' , $owner='' , $override = false )
		{
		$debug = 0;
		if( $debug )
			{
			echo br , "insert_strings( $pfx , $strings , $lang , $event , $owner ," , var_dump($override), " )";
			echo br , "where keys = " , var_dump( serialize( array_keys( $strings ) ) );
			}
		global	$txp_current_plugin;
		if( empty($strings) or !is_array($strings) or empty($lang) )
			return null;

		$owner_is_plugin = ( $owner !== '' and $owner !== 'snippets' );
		if( $owner_is_plugin )
			{
			if( empty($owner) )
				$owner = $txp_current_plugin;
			if( empty( $pfx) )
				$pfx = $owner;

			# If needed, register the plugin...
			$num = count($strings);
			if( false === MLPStrings::if_plugin_registered( $owner , $lang , $num , $strings ) )
				MLPStrings::register_plugin( $owner , $pfx , $num , $lang , $event , $strings );
			elseif( !$override )
				return false;
			
			# If the prefix doesn't end with the required sep character, add it...
			$pfx_len = strlen( $pfx );
			if( $pfx[$pfx_len-1] != L10N_SEP )
				{
				$pfx .= L10N_SEP;
				$pfx_len += 1;
				}
			}

		#	Iterate over the $strings and, for each that is not present, enter them into the sql table...
		$lastmod 	= date('YmdHis');
		$lang 		= doSlash( $lang );
		$event 		= doSlash( $event );
		$owner		= doSlash( $owner );
		$where	 	= "`lang`='$lang'";
		foreach( $strings as $name=>$data )
			{
			$data = doSlash($data);

			# If the name isn't prefixed yet, add the prefix...
			if( $owner_is_plugin and (substr( $name , 0 , $pfx_len ) !== $pfx) )
				$name = doSlash($pfx . $name);
			else
				$name = doSlash( $name );

			$set 	= "`lastmod`='$lastmod', `event`='$event', `data`='$data', `".L10N_COL_OWNER."`='$owner'";
			$where2	= "`name`='$name'";
			$added	= safe_insert( 'txp_lang' , $set.', '.$where.', '.$where2 );
			
			if( $debug ) { if( !$added ) dmp( 'not added!' ); else dmp( 'Added '.$added ); }
			
			if( $override && !$added )
				safe_update( 'txp_lang' , $set , $where.' AND '.$where2 );
			}

		# Cleanup empty strings.
		safe_delete( 'txp_lang', "`data`=''");
		return true;
		}

	static function store_translation_of_string( $name , $event , $new_lang , $translation , $id='' , $owner = '' )
		{
		/*
		Can create, delete or update a row in the DB depending upon the calling arguments.
		*/
		global	$txp_current_plugin;

		if( empty($name) or empty($event) or empty($new_lang) )
			{
			return null;
			}

		if( $owner === 'snippet' )
			{
			$event = 'public';
			}

		$owner = doSlash( $owner );
		$name = doSlash( $name );
		$event = doSlash( $event );
		$new_lang = doSlash( $new_lang );
		$translation = doSlash( $translation );
		$id = doSlash( $id );

		$lastmod 		= date('YmdHis');
		$set 	= " `lang`='$new_lang', `name`='$name', `lastmod`='$lastmod', `event`='$event', `data`='$translation', `".L10N_COL_OWNER."`='$owner'" ;

		if( !empty( $id ) )
			{
			$where	= " `id`='$id'";
			if( empty( $translation ) )
				$result = safe_delete( 'txp_lang', $where );
			else
				$result = safe_update( 'txp_lang' , $set , $where );
			}
		else
			$result = safe_insert( 'txp_lang' , $set );

		return $result;
		}

	static function remove_strings( $plugin , $remove_lang , $debug = '' )
		{
		/*
		PLUGIN SUPPORT ROUTINE
		Either: Removes all the occurances of all plugins' strings in the given langs...
		OR:		Removes all of the named plugin's strings.
		*/
		if( $remove_lang and !empty( $remove_lang ) )
			{
			$where = "(`lang` IN ('$remove_lang')) AND (`".L10N_COL_OWNER."` <> '')";
			safe_delete( 'txp_lang' , $where , $debug );
			safe_optimize( 'txp_lang' , $debug );
			}
		elseif( $plugin and !empty( $plugin ) )
			{
			$where = '`'.L10N_COL_OWNER."`=\'$plugin\'";
			safe_delete( 'txp_lang' , $where , $debug );
			safe_optimize( 'txp_lang' , $debug );
			MLPStrings::unregister_plugin( $plugin );
			}
		}

	static function remove_strings_by_name( &$strings , $event = '' , $plugin='' , $lang='' )
		{
		/*
		Uses the keys of the strings array to remove all of the named strings in EITHER ...
			1) ALL languages.
						OR
			2) Just the supplied language.
		*/
		global	$txp_current_plugin , $prefs;
		if( !$strings or !is_array( $strings ) or empty( $strings ) )
			return null;

		if( empty( $plugin ) )
			$plugin = $txp_current_plugin;

		$event 	= doSlash( $event );

		$result = false;
		$n_strings = count( $strings );
		if( $n_strings > 0 )
			{
			$deletes = 0;
			foreach( $strings as $name=>$data )
				{
				$name 	= doSlash($name);
				$where 	= " `name`='$name'";

				if( !empty($lang) )
					$where .= " AND `lang`='".doSlash($lang)."'";

				if( !empty($event) )
					$where .= " AND `event`='".doSlash($event)."'";

				$ok = safe_delete( 'txp_lang' , $where );
				if( $ok === true )
					$deletes++;
				}

			if($deletes === $n_strings)
				$result = true;
			else
				$result = "$deletes of $n_strings";

			safe_optimize( 'txp_lang' );
			}

		if( !empty($plugin) )
			MLPStrings::unregister_plugin( $plugin );

		return $result;
		}

	static function load_strings_into_textarray( $lang )
		{
		/*
		PUBLIC/ADMIN INTERFACE SUPPORT ROUTINE
		Loads all strings of the given language into the global $textarray so that any plugin can call
		gTxt on it's own strings. Can be used for admin and public work.
		*/
		global $textarray;

		$extras = MLPStrings::load_strings($lang);
		$textarray = array_merge( $textarray , $extras );
		return count( $extras );
		}
	static function remove_lang( $lang , $debug = '' )
		{
		$lang = doSlash( $lang );
		$where = " `lang`='$lang'";
		safe_delete( 'txp_lang' , $where , $debug );
		safe_optimize( 'txp_lang' , $debug );
		}

	static function load_strings( $lang , $filter='' )
		{
		/*
		PUBLIC/ADMIN INTERFACE SUPPORT ROUTINE
		Loads all strings of the given language into an array and returns them.
		*/
		$extras = array();
		$where  = ' AND ( event=\'public\' OR event=\'common\' ';
		$close = ')';
		if( @txpinterface == 'admin' )
			$close = 'OR event=\'admin\' )';

		$rs = safe_rows_start('name, data, '.L10N_COL_OWNER ,'txp_lang','lang=\''.doSlash($lang).'\'' . $where . $close . $filter );
		$count = @mysqli_num_rows($rs);
		if( $rs && $count > 0 )
			{
			while ( $a = nextRow($rs) )
				$extras[$a['name']] = $a['data'];
			}
		return $extras;
		}

	static function serialize_strings( $lang , $owner , $prefix , $event )
		{
		$r = array	(
					'owner'		=> $owner,		#	Name the plugin these strings are for.
					'prefix'	=> $prefix,		#	Its unique string prefix
					'lang'		=> $lang,		#	The language of the initial strings.
					'event'		=> $event,		#	public/admin/common = which interface the strings will be loaded into
					);

		$filter = " AND `".L10N_COL_OWNER."`='$owner'";
		$r['strings'] = MLPStrings::load_strings( $lang, $filter );
		$result = chunk_split( base64_encode( serialize($r) ) , 64, n );
		return $result;
		}

	static function discover_registered_plugins()
		{
		/*
		ADMIN INTERFACE SUPPORT ROUTINE
		Gets an array of the names of plugins that have registered strings in the correct format.
		*/
		global $prefs;

		$result = array();
		$p = MLPStrings::do_prefs_name( '' );

		foreach( $prefs as $k=>$v )
			if( false !== strpos($k , $p) )
				$result[MLPStrings::do_prefs_name( $k , false )] = unserialize($v);

		if( count( $result ) > 1 )
			ksort( $result );

		return $result;
		}

	static function get_strings( &$rs , &$stats )
		{
		$result = array();
		if( $rs && mysqli_num_rows($rs) > 0 )
			{
			while ( $a = nextRow($rs) )
				{
				$name = $a['name'];
				$lang = $a['lang'];

				if( !array_key_exists( $name , $result ) )
					$result[$name] = array();

				if( array_key_exists( $lang , $result[$name] ) )
					$result[$name][$lang] += 1;
				else
					$result[$name][$lang] = 1;
				}
			ksort( $result );
			foreach( $result as $name => $langs )
				{
				ksort( $langs );

				#
				#	Build the language stats for the strings...
				#
				foreach( $langs as $lang=>$count )
					{
					if( array_key_exists( $lang, $stats ) )
						$stats[$lang] += $count;
					else
						$stats[$lang] = $count;
					}

				$string_of_langs = rtrim( join( ', ' , array_keys($langs) ) , ' ,' );
				$result[$name] = $string_of_langs;
				}
			ksort( $stats );
			}
		return $result;
		}

	static function get_plugin_strings( $plugin , &$stats , $prefix )
		{
		/*
		ADMIN INTERFACE SUPPORT ROUTINE
		Given a plugin name, will extract a list of strings the plugin has registered, collapsing all
		the translations into one entry. Thus...
		name	lang	data
		alpha	en		Alpha
		alpha	fr		Alpha
		alpha	el		Alpha
		beta	en		Beta
		Gives...
		alpha => 'fr, el, en'  (Sorted order)
		beta  => 'en'
		*/
		$plugin = doSlash( $plugin );
		$prefix = doSlash( $prefix );
		$where = " `".L10N_COL_OWNER."`='$plugin'";
		$rs = safe_rows_start( 'lang, name,'.L10N_COL_OWNER , 'txp_lang', $where );
		return MLPStrings::get_strings( $rs , $stats );
		}

	static function is_complete( $langs , $use_admin = false )
		{
		static $public_langs , $admin_langs;

		if( $use_admin && !isset( $admin_langs ) )
			{
			$admin_langs = MLPLanguageHandler::get_installation_langs();
			$admin_langs = array_flip( $admin_langs );
			ksort( $admin_langs );
			}
		elseif( !isset( $public_langs ) )
			{
			$public_langs = MLPLanguageHandler::get_site_langs();
			$public_langs = array_flip( $public_langs );
			ksort( $public_langs );
			}

		$tmp = ($use_admin) ? $admin_langs : $public_langs;

		$langs = explode( ',' , $langs );
		if( !empty( $langs ) )
			{
			foreach( $langs as $k=>$id )
				{
				$id=trim($id);
				unset( $tmp[$id] );
				}
			}

		$complete = empty( $tmp );
		return $complete;
		}

	static function get_string_set( $string_name , $string_event='' )
		{
		/*
		Given a string name, will extract an array of the matching translations.
		translation_lang => string_id , event , data
		*/
		$result = array();

		$where = ' `name` = "'.doSlash($string_name).'"';
		if( $string_event )
			$where .= ' AND `event`="' . doSlash($string_event) . '"';
		$rs = safe_rows_start( 'lang, id, event, data, '.L10N_COL_OWNER , 'txp_lang', $where );
		if( $rs && mysqli_num_rows($rs) > 0 )
			{
			while ( $a = nextRow($rs) )
				{
				$lang = $a['lang'];
				if( MLPLanguageHandler::is_valid_code( $lang ) )
					{
					unset( $a['lang'] );	# will be used as key, no need to store it twice.
					$result[ $lang ] = $a;
					}
				}
			ksort( $result );
			}
		return $result;
		}

	static function make_nameset( $names )
		{
		if( !is_array( $names ) )
			$names = array( $names );

		$name_set = '';
		foreach( $names as $name )
			{
			$name = doSlash( $name );
			$name_set .= "'$name', ";
			}

		$name_set = rtrim( $name_set , ', ' );
		if ( empty( $name_set ) )
			$name_set = "''";

		return $name_set;
		}
	static function get_set_by_lang( $nameset , $lang )
		{
		$result = array();

		$where = ' `name` IN ('.$nameset.') AND `lang` = "'.doSlash($lang).'"';
		$rs = safe_rows_start( 'name,data,event', 'txp_lang', $where );
		if( $rs && mysqli_num_rows($rs) > 0 )
			{
			while ( $a = nextRow($rs) )
				{
				extract( $a );
				$result[$name] = array($data,$event);
				}
			ksort( $result );
			}
		return $result;
		}
	static function get_set_by_name( $langset , $name )
		{
		$result = array();

		$where = ' `lang` IN ('.$langset.') AND `name` = "'.doSlash($name).'"';
		$rs = safe_rows_start( 'lang,data,event', 'txp_lang', $where );
		if( $rs && mysqli_num_rows($rs) > 0 )
			{
			while ( $a = nextRow($rs) )
				{
				extract( $a );
				$result[$lang] = array($data,$event);
				}
			ksort( $result );
			}
		return $result;
		}
	static function build_txp_langfile( $lang , $exclude_plugins = true , $media = 'file' )
		{
		$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );
		$where = " `lang`='$lang' ";
		if( $exclude_plugins )
			$where .= " AND `".L10N_COL_OWNER."`=''";
		$strings = safe_rows( 'name,event,data' , 'txp_lang' , $where . " ORDER BY `event`,`name` ASC" );
		$total = count( $strings );
		if( $total == 0 )
			return false;

		$e = '';
		$strs = trim( gTxt('l10n-strings') );
		$time = time();
		$out = array();
		$out[] = '# =====================================================================';
		$out[] = '# ';
		$out[] = '# Textpattern | '.$full_name.' ['.$lang.'] | '.$total.' '.$strs;
		$out[] = '# ';
		$out[] = '# '.gTxt('date').' : '.date( 'D, d M Y H:i:s (O/T)' , $time);
		$out[] = '# ';
		$out[] = '# Generated by the MLP Pack; Copyright 2006 Steve Dickinson, GPLv2.';
		$out[] = '# http://txp-plugins.netcarving.com';
		$out[] = '#';
		$out[] = '# =====================================================================';
		$out[] = '# ';
		$out[] = "#@version 4.5.2;".$time;
		$out[] = '# ';
		foreach( $strings as $string )
			{
			$event = $string['event'];
			if( $e != $event )
				{
				if( $e != '' )
					{
					$out[] = '# ';
					$out[] = "# '$e' - $count ".$strs;
					$out[] = '# ';
					}

				$out[] = '# =====================================================================';
				$out[] = '# ';
				$out[] = '#@'.$event;
				$out[] = '# ';
				$e = $event;
				$count = 0;
				}

			$data = $string['data'];
			if( $media !== 'file' )
				$data = txpspecialchars( $data );
			$out[] = $string['name'].' => '.$data;
			++$count;
			}
		$out[] = '# ';
		$out[] = "# '$e' - $count ".$strs;
		$out[] = '# ';
		$out[] = '# =====================================================================';

		if( $media !== 'file' )
			$out = join( '<br/>'.n , $out );
		else
			$out = join( n , $out ).n;
		return $out;
		}

	static function comment_block( $comment , $tabs=0 , $leading=1 )
		{
		$o = str_repeat( str_repeat( t , $tabs ).'#'.n , $leading );
		$o.= str_repeat( t , $tabs ).'#'.t.$comment.n;
		$o.= str_repeat( str_repeat( t , $tabs ).'#' , $leading );
		return $o;
		}
	static function dmp_array( $name , &$vals )
		{
		global $$name , $textarray;

		$o[] = 'global $'.$name.';';
		$o[] = "\$$name = array(";

		#	Find max key string length...
		$max = 0;
		foreach( $$name as $k=>$v )
			{
			$len = strlen( $k );
			if( $len > $max )
				$max = $len;
			}

		foreach( $$name as $k=>$v )
			{
			$len = strlen( $k );
			$qt = "'";
			$replace = array("'"=>"\'", "\n"=>'\n',"\r"=>'\r');

			#	localise value (if possible)...
			if( isset( $vals[$k] ) )
				$v = $vals[$k];

			# If there are any /r/n's then use " as quotes and don't escape singlequotes...
			if( false !== strpos( $v , "\n" ) or false !== strpos( $v , "\r" ) )
				{
				$qt = '"';
				unset( $replace["'"] );
				}

			#	Build the array entry...
			$o[] = t.'\''.$k.'\''.str_repeat(' ',($max-$len)).' => '.$qt.strtr($v , $replace).$qt.',';
			}
		$o[] = t.');';
		return join( n,$o );
		}
 	static function build_l10n_default_strings_file( $lang )
		{
		$langs = MLPLanguageHandler::get_installation_langs();

		if( !in_array( $lang, $langs ) )
			return '';

		$vals = load_lang( $lang );
		$full_name = MLPLanguageHandler::get_native_name_of_lang( $lang );

		$o[] = '<?php';
		$o[] = '';
		$o[] = MLPStrings::comment_block( 'The language of the strings in this file...' );
		$o[] = 'global $l10n_default_strings_lang;';
		$o[] = "\$l10n_default_strings_lang = '$lang';" . MLPStrings::comment_block( $full_name , 1 , 0);
		$o[] = '';
		$o[] = MLPStrings::comment_block( 'These strings are always needed, they will get installed in the language array...' );
		$o[] = MLPStrings::dmp_array( 'l10n_default_strings_perm' , $vals );
		$o[] = '';
		$o[] = MLPStrings::comment_block( 'These are the regular mlp pack strings that will get installed into the txp_lang table...' );
		$o[] = MLPStrings::dmp_array( 'l10n_default_strings' , $vals );
		$o[] = '';
		$o[] = '?>';

		return join( n , $o );
		}

	} # End class MLPStrings

class MLPPlugin extends GBPPlugin
	{
	var $gp = array();
	var $preferences = array(
		'l10n-languages' => array('value' => array(), 'type' => 'gbp_array_text'),
		'l10n-use_browser_languages' => array( 'value' => 1, 'type' => 'yesnoradio' ),
		'l10n-show_legends' => array( 'value' => 1, 'type' => 'yesnoradio' ),
		'l10n-list_sort_order' => array( 'value' => 'ID DESC', 'type' => 'text_input' ),
		'l10n-show_clone_by_id' => array( 'value' => 0, 'type' => 'yesnoradio' ),
		'l10n-send_notifications'	=>	array( 'value' => 1, 'type' => 'yesnoradio' ),
		'l10n-send_notice_to_self'	=>	array( 'value' => 0, 'type' => 'yesnoradio' ),
		'l10n-send_notice_on_changeauthor' => array( 'value' => 0, 'type' => 'yesnoradio' ),
		'l10n-allow_writetab_changes' => array( 'value' => 0, 'type' => 'yesnoradio' ),
		'l10n-inline_editing' => array('value' => 1, 'type' => 'yesnoradio'),
		'l10n-allow_search_delete' => array( 'value' => 0, 'type' => 'yesnoradio' ),
		'l10n-search_public_strings_only' => array( 'value' => 0, 'type' => 'yesnoradio' ),
		'l10n-url_exclusions' => array('value' => array('css','js'), 'type' => 'gbp_array_text'),
		'l10n-url_default_lang_marker' => array( 'value' => 1, 'type' => 'yesnoradio' ),
		'l10n-clean_feeds' => array( 'value' => 1, 'type' => 'yesnoradio' ),
		);
	var $strings_prefix = L10N_NAME;
	var $insert_in_debug_mode = false;
	var $permissions = '1,2,3,6';

	function MLPPlugin( $title_alias , $event , $parent_tab = 'extensions' )
		{
		global $textarray , $production_status , $prefs;
		global $l10n_default_strings , $l10n_default_strings_lang , $l10n_default_strings_perm;

		if( @txpinterface === 'admin' )
			{
			#	Register callbacks to get admin-side plugins' strings registered.
			register_callback(array(&$this, '_initiate_callbacks'), 'l10n' , '' , 1 );

			# First run, setup the languages array to the currently installed admin side languages...
			$langs = MLPLanguageHandler::get_site_langs( false );
			if( NULL === $langs )
				{
				$langs = MLPLanguageHandler::get_installation_langs();
				$prefs[L10N_PREFS_LANGUAGES] = join( ',' , $langs );
				}

			$installed = $this->installed();
			$installed = !empty( $installed );

			# Merge the default language strings into the textarray so that non-English
			# users at least see an English message in the plugin.
			//if( $prefs['language'] !== $l10n_default_strings_lang )
				{
				$textarray = array_merge( $l10n_default_strings , $textarray );
				}

			#	These strings are always needed (for example, by setup/cleanup wizards)...
			$textarray = array_merge( $l10n_default_strings_perm , $textarray );

			#	To ease development, allow new strings to be inserted...
			if( $installed and $this->insert_in_debug_mode and ('debug' === @$production_status) )
				{
				$l10n_default_strings = array_merge( $l10n_default_strings , $l10n_default_strings_perm );
				$ok = MLPStrings::remove_strings_by_name( $l10n_default_strings , 'admin' , 'l10n' , $l10n_default_strings_lang );
				$ok = MLPStrings::insert_strings( $this->strings_prefix , $l10n_default_strings , $l10n_default_strings_lang , 'admin' , 'l10n' , true );
				MLPStrings::load_strings_into_textarray( LANG );
				}
			}

		# Be sure to call the parent constructor *after* the strings it needs are added and loaded!
		GBPPlugin::GBPPlugin( gTxt($title_alias) , $event , $parent_tab );
		}

	function _insert_css()
		{
		return n . '<link href="l10n.css" rel="Stylesheet" type="text/css" />' . n;
		}
	function preload()
		{
		if( has_privs('plugin') )
			new MLPStringView( gTxt('plugins'), 'plugin', $this );
		if( has_privs('page') or has_privs('form') )
			$GLOBALS['mlp_snip_view'] = new MLPSnipView( gTxt('l10n-snippets_tab') , 'snippets' , $this );
		if( has_privs('article.edit') )
			new MLPArticleView( gTxt('articles'), 'article', $this, true );

		if( has_privs('plugin') )
			{
			new GBPPreferenceTabView($this);
			new MLPWizView($this, NULL , gTxt('l10n-wizard') );
			}
		}

	function prefs_save_cb( $event='' , $step='' )
		{
		#
		#	Update the set of translation tables based on any changes made to the site
		# languages...
		#
		$langs = MLPLanguageHandler::get_site_langs();
		$tables = getThings( 'show tables like \''.PFX.L10N_RENDITION_TABLE_PREFIX.'%\'' );

		#
		#	Expand language names to match translation table name format...
		#
		$names = array();
		if( count( $langs ) )
			foreach( $langs as $name )
				{
				$name = PFX.L10N_RENDITION_TABLE_PREFIX.$name;
				$names[] = _l10n_clean_sql_name($name);
				}

		#
		#	Perform the diffs and detect additions/deletions needed...
		#
		$diff_names_tables = array_diff( $names  , $tables );
		$diff_tables_names = array_diff( $tables , $names );
		$add_count = count($diff_names_tables);
		$del_count = count($diff_tables_names);

		if( $add_count )
			{
			foreach( $diff_names_tables as $full_name )
				{
				#
				#	Get the language code...
				#
				$lang = str_replace( PFX.L10N_RENDITION_TABLE_PREFIX , '' , $full_name );
				$lang = strtr( $lang , array( '_'=>'-' ) );
				if( !MLPLanguageHandler::is_valid_code( $lang ) )
					continue;

				#
				#	Add language tables as needed and populate them as far as possible...
				#
				$indexes = "(PRIMARY KEY  (`ID`), KEY `categories_idx` (`Category1`(10),`Category2`(10)), KEY `Posted` (`Posted`), FULLTEXT KEY `searching` (`Title`,`Body`))";
				$sql = "create table `$full_name` $indexes ENGINE=MyISAM select * from `".PFX."textpattern` where ".L10N_COL_LANG."='$lang'";
				$ok = safe_query( $sql );

				#
				#	Add fields for this language...
				#
				_l10n_walk_mappings( array( &$this , 'add_field' ) , $lang );

				#
				#	Conditionally extend the snip-site_slogan to include the new language...
				#
				global $prefs;
				$exists = safe_row( '*' , 'txp_lang' , "`lang`='$lang' AND `name`='snip-site_slogan'" );
				$exists = !empty( $exists );
				if( !$exists and @$prefs['site_slogan'] === 'My pithy slogan' )
					{
					$langname = MLPLanguageHandler::get_native_name_of_lang( $lang );
					MLPStrings::store_translation_of_string( 'snip-site_slogan' , 'public' , $lang , $langname );
					}
				}
			}

		if( $del_count )
			{
			foreach( $diff_tables_names as $full_name )
				{
				#
				#	Drop language tables that are no longer needed...
				#
				$sql = 'drop table `'.$full_name.'`';
				$ok = safe_query( $sql );

				#
				#	Get the language code...
				#
				$lang = str_replace( PFX.L10N_RENDITION_TABLE_PREFIX , '' , $full_name );
				$lang = strtr( $lang , array( '_'=>'-' ) );
				if( !MLPLanguageHandler::is_valid_code( $lang ) )
					continue;

				#
				#	Remove fields for this language...
				#
				_l10n_walk_mappings( array( &$this , 'drop_field' ) , $lang );
				}
			}

		#
		#	Process the new default language ... copy fields as needed...
		#
		_l10n_walk_mappings( array( &$this , 'copy_defaults' ) , $langs[0] );
		}
	function add_field( $table , $field , $attributes , $language )
		{
		$f = _l10n_make_field_name( $field , $language );
		$exists = getThing( "SHOW COLUMNS FROM $table LIKE '$f'" );
		if( !$exists )
			{
			$sql = "ADD `$f` ".$attributes['sql'];
			$ok = safe_alter( $table , $sql );
			}
		}
	function drop_field( $table , $field , $attributes , $language )
		{
		$f = _l10n_make_field_name( $field , $language );
		$sql = "DROP `$f`";
		$ok = safe_alter( $table , $sql );
		}
	function copy_defaults( $table , $field , $attributes , $language )
		{
		$f = _l10n_make_field_name( $field , $language );

		#
		#	If we make an existing lang the default, overwrite the master field
		# with any data from the (now default) field...
		#
		safe_update( $table , "`$field`=`$f`" , "`$f` <> ''" );

		#
		#	Copy the master fields over to the new default field
		#
		safe_update( $table , "`$f`=`$field`" , "`$field` <> ''" );

		#
		#	For certain tables, iterate over each site language x row, setting
		# any blanks to the new default value...
		#
		$extend_all = array( 'txp_category' , 'txp_section' );
		if( in_array( $table , $extend_all ) )
			{
			$langs = MLPLanguageHandler::get_site_langs();
			foreach( $langs as $lang )
				{
				if( $lang === $language )
					continue;	# skip the default language, already done.

				$f = _l10n_make_field_name( $field , $lang );
				safe_update( $table , "`$f`=`$field`" , "`$f`=''" );
				}
			}
		}

	function installed( $recheck=false )
		{
		static $result;
		if (!isset($result) || $recheck)
			$result = Txp::get('\Netcarver\MLP\Kickstart')->l10n_installed();
		return $result;
		}

	function _process_string_callbacks( $event , $step , $pre , $func )
		{
		$key = '';
		if( !is_callable($func , false , $key) )
			return "Cannot call function '$key'.";

		$r = call_user_func($func, $event, $step);
		if( !is_array( $r ) )
			return "Call of '$key' returned a non-array value.";

		extract( $r );

		$result = "Skipped insertion of strings for '$key'.";
		if( $owner and $prefix and $strings and $lang and $event and (count($strings)) )
			{
			//echo br , "In _process_string_callbacks( $event , $step , $pre , $func ), inserting strings...";
			if( MLPStrings::insert_strings( $prefix , $strings , $lang , $event , $owner ) )
				$result = true;
			}

		return $result;
		}

	function _initiate_callbacks( $event , $step='' , $pre=0 )
		{
		$results = array();

		$tab = gps( 'tab' );
		$plugin = gps( 'plugin' );
		if( $tab === 'plugin' and empty($plugin) )
			{
			#	Force the loading of public side plugins on a visit to the MLP>Plugins, in case they do register strings...
			//echo "Initiating callbacks and loading active public plugins ... ";
			load_plugins( 0 );

			#	Initiates our string enumeration event...
			$results = $this->_do_callback( "l10n.enumerate_strings", '', 0, array(&$this , '_process_string_callbacks') );
			}

		return serialize($results);
		}

	function _do_callback( $event, $step='', $pre=0, $func=NULL )
		{
		#	Graeme, move this to base class??
		global $plugin_callback;

		#	Make sure we use a copy of the array to avoid messing with it's internal pointer.
		if( !is_array($plugin_callback) )
			return;

		$results = array();

		$cb_copies = $plugin_callback;
		reset( $cb_copies );
		foreach ($cb_copies as $c)
			{
			if( $c['event'] == $event and (empty($c['step']) or $c['step'] == $step) and $c['pre'] == $pre)
				{
				$key = '';
				if( !is_callable($c['function'] , false , $key ) )
					continue;
				# If a processing routinue has been specified then use it otherwise use the callback directly.
				if( $func and is_callable($func) )
					$results[ $key ] = call_user_func( $func , $event , $step , $pre , $c['function'] );
				else
					$results[ $key ] = call_user_func($c['function'], $event, $step);
				}
			}
		return $results;
		}

	function serve_file( $data , $title , $desc='File Download' , $type='application/octet-stream' )
		{
		ob_clean();
		$size = strlen( $data );
		header('Content-Description: '.$desc);
		header('Content-Type: ' . $type);
		header('Content-Length: ' . $size);
		header('Content-Disposition: attachment; filename="' . $title . '"');
		echo $data;
		ob_flush();
		flush();
		exit;
		}

	function main()
		{
		require_privs($this->event);

		$out[] = '<div class="l10n_main_tab">';
		$out[] = $this->_insert_css();
		if( $this->installed(1) )
			{
			# Only render the common area at the head of the tabs if the table is installed ok.
			foreach( $this->pref('l10n-languages') as $key )
				{
				$safe_key = trim( $key );	# make sure we trim any spaces out -- they mess up the gTxt call.
				$languages['value'][$safe_key] = gTxt($safe_key);
				}
			}
		$out[] = '</div>';

		echo join('', $out);
		}

	function end()
		{
		$step = gps('step');
		if( $step )
			{
			switch( $step )
				{
				case 'prefs_save':
					$this->prefs_save_cb();
					#	Force a redirect to ourself to refresh the view with any tab changes as needed.
					$this->redirect( array() );
				break;
				}
			}
		}
	}


class MLPSnipView extends GBPAdminTabView
	{
	var $tabs = array();
	var $active_tab = 0;
	var $use_tabs = false;

	function MLPSnipView( $title, $event, &$parent, $is_default = NULL )
		{
		$this->tabs[] = new MLPStringView( gTxt('search') , 'search' , $this );
		$this->tabs[] = new MLPStringView( gTxt('l10n-specials') , 'special' , $this );
		if (has_privs('page'))
			$this->tabs[] = new MLPStringView( gTxt('pages') , 'page' , $this , true );
		if (has_privs('form'))
			$this->tabs[] = new MLPStringView( gTxt('forms') , 'form' , $this );
		$this->tabs[] = new MLPSnipIOView( gTxt( 'l10n-inout' ) , 'inout' , $this );

		GBPAdminTabView::GBPAdminTabView( $title , $event , $parent , $is_default );
		}
	function get_canvas_style()
		{
		if( $this->is_active )
			{
			return ' style="padding: 0;"';
			}
		return false;
		}
	function &add_tab($tab, $is_default = NULL)
		{
		# Check to see if the tab is active...
		$gps_tab = gps(gbp_tab);
		$sub_tab = gps('subtab');

		if (($is_default && !$gps_tab) || ($gps_tab == $tab->event && $sub_tab == $tab->sub_tab) )
			$this->active_tab = count($this->tabs);

		# Store the tab
		//$this->tabs[] = $tab;

		# We've got a tab, lets assume we want to use it
		$this->use_tabs = true;

		return $this;
		}
	function preload()
		{
		# Let the active_tab know it's active and call it's preload()
		$tab = &$this->tabs[$this->active_tab];
		$tab->is_active = 1;
		$tab->preload();
		}

	function main()
		{
		$this->render_tabs();
		$this->render_tab_main();
		}
	function render_tab_main()
		{
		$tab = &$this->tabs[$this->active_tab];
		$tab->main();
		}
	function render_tabs()
		{
		#	This table, which contains the tags, will have to be changed if any improvements
		# happen to the admin interface
		$out[] = '<div id="l10n_tabs_row" class="txp-control-panel">';
		$out[] = '<p class="txp-buttons">';

		# Force the wizard to be the only tab if the plugin isn't installed
		foreach (array_keys($this->tabs) as $key)
			{
			$tab = &$this->tabs[$key];
			$out[] = $tab->render_tab();
			}

		$out[] = '</p>';
		$out[] = '</div><div class="l10n_subtab">';

		echo join('', $out);
		}
	}
class MLPSubTabView extends GBPAdminTabView
	{
	var $sub_tab = '';
	function MLPSubTabView( $title, $event, &$parent, $is_default = NULL , $subtab = '' )
		{
		if( !empty($subtab) )
			$this->sub_tab = $subtab;
		GBPAdminTabView::GBPAdminTabView( $title , $event , $parent , $is_default );
		}

	function render_tab()
		{
		# Grab the url to this tab
		$url = $this->url(array(gbp_tab => $this->event), true);

		# Will need updating if any improvements happen to the admin interface
		$out[] = '<a class="navlink' . ($this->is_active ? ' active' : '') . '" href="' .$url. '">' .$this->title. '</a>'.n;

		return join('', $out);
		}
	function url( $vars, $gp=false )
		{
		$vars = array_merge( $vars , array('subtab'=>$this->sub_tab) );
		return $this->parent->url( $vars , $gp );
		}
	}
class MLPStringView extends GBPAdminTabView
	{
	/*
	Implements a three-pane view for the categorisation, selection and editing of string based
	data from the txp_lang table.
	*/

	var $sub_tab = '';
	function render_tab()
		{
		# Grab the url to this tab
		$url = $this->url(array(gbp_tab => $this->event), true);

		# Will need updating if any improvements happen to the admin interface
		$out[] = '<a class="navlink' . ($this->is_active ? ' active' : '') . '" href="' .$url. '">' .$this->title. '</a>'.n;

		return join('', $out);
		}
	function url( $vars, $gp=false )
		{
		$vars = array_merge( $vars , array('subtab'=>$this->sub_tab) );
		return $this->parent->url( $vars , $gp );
		}

	function MLPStringView($title, $event, &$parent, $is_default = NULL)
		{
		if( $event !== 'plugin' )
			{
			$this->sub_tab = $event;
			GBPAdminTabView::GBPAdminTabView( $title, 'snippets', $parent, $is_default );
			}
		else
			GBPAdminTabView::GBPAdminTabView( $title, $event, $parent, $is_default );
		}


	function xml()
		{
		$xml	= gps('XMLHTTP');
		$xml	= !empty($xml);
		return $xml;
		}

	function preload()
		{
		$step 	= gps('step');

		if( $step )
			{
			switch( $step )
				{
				# Called to save the stringset the user has been editing.
				case 'l10n_save_strings' :
					$this->save_strings();
					break;

				# Called if the user chooses to delete the string set for a removed plugin.
				case 'l10n_remove_stringset' :
					$this->remove_strings();
					break;

				# Called if the user chooses to remove a specific languages' strings.
				# eg if they entered some french translations but later drop french from the site.
				case 'l10n_remove_languageset' :
					$this->remove_strings();
					break;

				case 'l10n_save_pageform':
					$this->save_pageform();
					break;

				case 'l10n_export_languageset':
					$this->export_languageset();
					break;

				case 'l10n_import_languageset':
					$this->import_languageset();
					break;

				case 'l10n_remove_language':
					$this->remove_language();
					break;

				case 'l10_search_for_content':
					$this->search_for_content();
					break;
				case 'l10n_search_for_names':
					$this->search_for_names();
					break;
				}
			}
		}

	function main()
		{
		$id = gps(gbp_id);
		$step = gps('step');
		$pf_steps = array('l10n_save_pageform', 'l10n_edit_pageform', 'l10n_localise_pageform');
		$pl_steps = array('l10n_import_languageset');
		$can_edit = $this->pref('l10n-inline_editing');

		if( !empty($this->sub_tab) )
			$this->event = $this->sub_tab;

		switch ($this->event)
			{
			case 'search':
				if( !$this->xml() )
					$this->render_search_pane( $id );
				elseif( $id )
					$this->render_string_edit( 'search' , 'search' , $id );
				break;

			case 'special':
				$this->render_owner_list('special');
				$this->render_specials_list( $id );
				if( $container = gps('container') and $id )
					$this->render_string_edit( 'special', 'special' , $id );
				break;

			case 'page':
				$this->render_owner_list('page');
				if ($container = gps('container'))
					{
					$this->render_string_list( 'txp_page' , 'user_html' , $container , $id );
					if( $id )
						$this->render_string_edit( 'page', $container , $id );
					elseif( $can_edit and in_array($step , $pf_steps) )
						$this->render_pageform_edit( 'txp_page' , 'name' , 'user_html' , $container );
					}
				break;

			case 'form':
				$this->render_owner_list('form');
				if ($container = gps('container'))
					{
					$this->render_string_list( 'txp_form' , 'Form' , $container , $id );
					if( $id )
						$this->render_string_edit( 'form' , $container , $id );
					elseif( $can_edit and in_array($step , $pf_steps) )
						$this->render_pageform_edit( 'txp_form' , 'name' , 'Form' , $container );
					}
				break;

			case 'plugin':
				$this->render_owner_list('plugin');
				if( $step and in_array( $step , $pl_steps ) )
					{
					$this->render_import_list();
					}
				elseif( $container = gps(L10N_PLUGIN_CONST) and $prefix = gps('prefix') )
					{
					$this->render_plugin_string_list( $container , $id , $prefix );
					if( $id and $event=gps('string_event') )
						{
						$owner = $container;
						$this->render_string_edit( 'plugin', $container , $id, $owner , $event );
						}
					}
				break;
			}
		}


	function search_for_names()
		{
		#
		#	Start our XML output...
		#
		ob_start();
		header( "Content-Type: text/xml" );
		print '<?xml version=\'1.0\' encoding=\'utf-8\'?>'.n;

		#
		#	Grab the names of every string in the system...
		#
		$admin_langs = MLPLanguageHandler::get_installation_langs();

		$stats = array();
		if( $this->pref('l10n-search_public_strings_only') )
			$where = '`event` in ("public","common")';
		else
			$where = '1=1';
		$full_names = safe_rows_start( 'name,lang', 'txp_lang', $where . ' ORDER BY name ASC' );
		$names = MLPStrings::get_strings( $full_names , $stats );
		$num_names = count( $names );

		if( !$names || $num_names == 0 )
			exit;



		#
		#	Grab the search term...
		#
		$search_term = gps( 'l10n-sfn' );
		$out = array();
		switch( $search_term )
			{
			case '':
			case 'undefined':
				#
				#	send a full list of strings...
				#
				foreach( $names as $string => $value )
					{
					$out[] = '<li id="' . $string . '" class="l10n_hidden"><a href="'.hu.'" onClick="do_string_edit(\''.$string.'\'); return false;">' . $string . '</a></li>';
					}
				break;

			case '-':
				#
				#	send those missing a rendition in any language...
				#
				foreach( $names as $string => $value )
					{
					$lang_classes = '';
					$vals = explode( ',', $value );
					$vals = doArray( $vals , 'trim' );
					$missing = array_diff( $admin_langs , $vals );
					if( !empty( $missing ) )
						{
						$out[] = '<li id="' . $string . '" class="l10n_hidden"><a href="'.hu.'" onClick="do_string_edit(\''.$string.'\'); return false;">' . $string . ' [' . join( ', ' , $missing ). ']</a></li>';
						}
					}
				break;

			default:
				#
				#	send those missing a rendition in the specified language...
				#
				foreach( $names as $string => $value )
					{
					$lang_classes = '';
					$vals = explode( ',', $value );
					$vals = doArray( $vals , 'trim' );
					$missing = array_diff( $admin_langs , $vals );
					if( !empty( $missing ) )
						{
						foreach( $missing as $l )
							if( $l === $search_term )
								$out[] = '<li id="' . $string . '" class="l10n_hidden"><a href="'.hu.'" onClick="do_string_edit(\''.$string.'\'); return false;">' . $string . '</a></li>';
						}
					}
				break;
			}

		$subs['{interface}'] = 	($this->pref('l10n-search_public_strings_only')) ? 'public' : '';

		print graf( '<span id="l10n_result_count">'.$search_term.'</span>/' . count( $out ) . ' ' . gTxt('l10n-strings_match' , $subs) ).n;
		print '<ul id="l10n_sbn_result_list" class="l10n_visible" >';
		print join( '' , $out );
		print '</ul>'.n;

		#
		#	Done; send it out...
		#
		exit;
		}
	function search_for_content()
		{
		#
		#	Start our XML output...
		#
		ob_start();
		header( "Content-Type: text/xml" );
		print '<?xml version=\'1.0\' encoding=\'utf-8\'?>'.n;

		#
		#	Grab the search term...
		#
		$search_term = gps( 'l10n-sfc' );

		if( empty( $search_term ) )
			{
			$rs = array();
			$count = 0;
			}
		else
			{
			#
			#	If it's not empty, build and execute SQL query...
			#
			$search_term = doSlash( $search_term );
			$where = "`data` LIKE '%$search_term%'";

			if( $this->pref('l10n-search_public_strings_only') )
				$where = '`event` in ("public","common") AND ' . $where;


			$lang = gps( 'l10n-lang' );
			$lang = doSlash( $lang );
			if( $lang and $lang!=='-' )
				$where .= " AND `lang`='$lang'";

			$rs = safe_rows_start( 'DISTINCT name,data,lang', 'txp_lang', $where . " ORDER BY name ASC LIMIT 200" );
			$count = @mysqli_num_rows($rs);
			}

		if( $rs and $count > 0 )
			{
			$subs['{interface}'] = 	($this->pref('l10n-search_public_strings_only')) ? 'public' : '';
			$o[] = '<div>'.n.'<h3>' . $count.' '.gTxt('l10n-strings_match' , $subs).'</h3>'.n.'<ul>'.n;
			while ( $a = nextRow($rs) )
				{
				$name = $a['name'];
				$name = '<a href="'.hu.'" onClick="do_string_edit(\''.$name.'\'); return false;">' . $name . '</a>';

				#
				#	Now encode the data and highlight the search term...
				#
				$data = $a['data'];
				$data = txpspecialchars($data);
				$data = str_ireplace( $search_term, '<span class="l10n_highlite">'.$search_term.'</span>', $data );

				if( empty($lang) or $lang==='-' )
					$language = ' ['.MLPLanguageHandler::get_native_name_of_lang( $a['lang'] ).']';
				else
					$language = '';

				$o[] = "<li>$name$language<br/>$data</li>".n;
				}
			$o[] = '</ul>'.n.'</div>'.n;
			$o = join( '' , $o );

			print $o;
			}
		else
			{
			print '<div>'.n.'<h3>' . gTxt('none') . '</h3>'.n.'</div>';
			}

		#
		#	Done; send it out...
		#
		exit;
		}

	function render_search_pane( $id )
		{
		global $l10n_language;

		$site_langs  = MLPLanguageHandler::get_site_langs();
		$admin_langs = MLPLanguageHandler::get_installation_langs();

		#
		#	Grab the names of every string in the system...
		#
		$stats = array();
		$full_names = safe_rows_start( 'name,lang', 'txp_lang', '1=1 ORDER BY name ASC' );
		$names = MLPStrings::get_strings( $full_names , $stats );
		$num_names = count( $names );

		#
		#	Render the search column...
		#
		$subs['{interface}'] = 	($this->pref('l10n-search_public_strings_only')) ? 'Public' : '';

		$out[] = 	'<div class="l10n_owner_list">' . n;
		$out[] = 	'<h3>' . gTxt('l10n-search_for_strings' , $subs) . '</h3>' . n;

		#
		#	Render the search type picker form...
		#
		$method = cs( 'l10n_string_search_by' );
		if( empty( $method ))
			$method = 'name';
		$ch1 = ($method == 'name') ? ' checked="checked"' : '';
		$ch2 = ($method == 'cont') ? ' checked="checked"' : '';
		$picker[] = t.'<input type="radio" name="search_by" value="name" id="sbn_radio_button"'.$ch1.' tabindex="0" class="radio" onClick="update_search(\'sbn_radio_button\')" />'.n;
		$picker[] = t.'<label for="sbn_radio_button">'.gTxt('l10n-by_name').'</label><br/>' . n;
		$picker[] = t.'<input type="radio" name="search_by" value="cont" id="sbc_radio_button"'.$ch2.' tabindex="1" class="radio" onClick="update_search(\'sbc_radio_button\')" />'.n;
		$picker[] = t.'<label for="sbc_radio_button">'.gTxt('l10n-by_content').'</label><br/>' . n;
		$out[] = form( join( '', $picker ) ) . br . n;


		$langs = MLPLanguageHandler::get_installation_langs();
		$langs = MLPLanguageHandler::do_fleshout_names( $langs , '' , false );
		$sel   = gTxt('l10n-all_languages');
		$langs = array_merge( array( '-' => $sel ) , $langs );

		#
		#	Render the search-by-name box...
		#
		$value = cs( 'search_string_name_live' );
		$f[] = fInput( 	'edit',
						'l10n_search_by_name',
						$value,
						'',							 			/*class*/
						gTxt('l10n-sbn_title'),					/*title*/
						'',										/*onClick*/
						'20', 									/*size*/
						'1', 									/*tab*/
						'l10n_search_by_name' 					/*id*/
						);

		$f[] = graf( gTxt('l10n-sbn_rubrik') ) . n;
		$subtype = cs( 'l10n_string_search_by_subtype' );
		if( empty( $subtype ))
			$subtype = 'all';
		$ch1 = ($subtype == 'all') ? ' checked="checked"' : '';
		$ch2 = ($subtype == 'missing') ? ' checked="checked"' : '';
		$f[] = t.'<input type="radio" name="search_by" value="all" id="sbn_all_radio_button"'.$ch1.' tabindex="0" class="radio" onClick="update_search(\'sbn_all_radio_button\')" />'.n;
		$f[] = t.'<label for="sbn_all_radio_button">'.gTxt('all strings').'</label><br/>' . n;
		$f[] = t.'<input type="radio" name="search_by" value="missing" id="sbn_missing_radio_button"'.$ch2.' tabindex="1" class="radio" onClick="update_search(\'sbn_missing_radio_button\')" />'.n;
		$f[] = t.'<label for="sbn_missing_radio_button">'.gTxt('missing renditions in&#8230;').'</label><br/>' . n;
		$language = cs( 'search_string_name_lang' );
		if( empty($language) )
			$language = $l10n_language['long'];
		$f[] = graf( selectInput( 'l10n-lang' , $langs , $language , 0 , ' onchange="on_sbn_lang_change()"' , 'sbn_lang_selection' ) ) . n;
		$out[] = '<div id="l10n_div_s_by_n" class="'.(($method=='name')?'l10n_visible':'l10n_hidden').'">' . n;
		$out[] = form( join( '', $f) ) . n;
		$out[] = '</div>' . n;
		#
		#	===============================================================
		#
		#	Render the search-by-content form...
		#
		$out[] = 	'<div id="l10n_div_s_by_c" class="'.(($method=='cont')?'l10n_visible':'l10n_hidden').'">' . n;


		$value = cs( 'search_string_content' );
		$language = cs( 'search_string_lang' );
		if( empty($language) )
			$language = $l10n_language['long'];
		$f = array();
		$f[] = graf( selectInput( 'l10n-lang' , $langs , $language , 0 , '' , 'sbc_lang_selection' ) ) . n;
		$f[] = fInput( 	'edit',
						'l10n-sfc',
						$value,
						'',
						gTxt('l10n-sbn_title'),
						'',
						'20',
						'1',
						'l10n_search_by_content'
						) . n;
		$f[] = fInput( 	'submit',
						'',
						gTxt('go'),
						'',
						'',
						"do_content_search(); return false;"
						) . n;

		$out[] = form( join('', $f) , '' , 'false' ) . n;
		$out[] = '</div>' . n;


		#
		#	Render the stats...
		#
		$out[] = '<br /><h3>'.gTxt('l10n-summary').'</h3>'.n;
		$out[] = '<table>'.n.'<thead>'.n.tr( '<td align="right">'.gTxt('language').'</td>'.n.'<td align="right">&nbsp;&nbsp;&#035;&nbsp;</td>' . td('') . td('') ).n.'</thead><tbody>';
		$extras_found = false;
		$plugin = gps( 'plugin' );
		foreach( $stats as $iso_code=>$count )
			{
			$lang_extras_found = false;
			$name = MLPLanguageHandler::get_native_name_of_lang( $iso_code );
			$out[]= tr( td( $name ).td( '&nbsp;'.$count ) , ' style="text-align:right;" ' );
			}
		$out[] = tr( tdcs( '<hr/>' , 2 ) );
		$out[] = tr( td( gTxt('l10n-total').' '.gTxt('l10n-renditions') ).td('&nbsp;'.array_sum($stats)) , ' style="text-align:right;" ' );
		$out[] = tr( td( gTxt('l10n-total').' '.gTxt('l10n-strings') ).td('&nbsp;'.$num_names) , ' style="text-align:right;" ' );
		$out[] = tr( tdcs( '<hr/>' , 2 ) );
		$out[] = '</tbody></table>';


		$out[] = '</div>' . n;
 		#
		#	===============================================================
		#
		#	Render the results column.
		#
		$out[] = '<div class="l10n_string_list" id="l10n_sbn_result_div">';
		$out[] = '<h3>'.gTxt('search_results').'</h3>'.n;
		$out[] = '<div id="l10n_div_sbn_result_list">'.n;
		$out[] = '</div>' . n;

		#
		#	DIV for the search-by-content result list...
		#
		$out[] = '<div class="l10n_hidden" id="l10n_sbc_result_list">'.'</div>'.n;

		#
		#	Closing DIV
		#
		$out[] = '</div>' . n;

		#
		#	DIV for string edit pane.
		#
		$out[] = '<div class="l10n_values_list" id="l10n_div_string_edit">';
		if( $id )
			$this->render_string_edit( 'search' , 'search' , $id );
		$out[] = '</div>'.n;

		echo join('', $out);
		}

	function _generate_list( $table , $fname , $fdata )						# left pane subroutine
		{
		$rs = safe_rows_start( "$fname as name, $fdata as data", $table, '1=1 ORDER BY '.$fname ) ;
		if( $rs && mysqli_num_rows($rs) > 0 )
			{
			$can_edit = $this->pref('l10n-inline_editing');
			$explain = false;
			while ( $a = nextRow($rs) )
				{
				$count = 0;
				$snippets 	= array();
				$snippets = MLPSnips::find_snippets_in_block( $a['data'] , $count );
				if( !$can_edit && !$count )
					continue;
				$marker = ($count) ? ' ['.$count.']' : '';
				$guts = $a['name'].$marker;
				$out[] = '<li><a href="'.$this->url( array('container'=>$a['name']) , true).'">'.$guts.'</a></li>' . n;
				}
			$out[] = br . gTxt('l10n-pageform-markup') . n;
			}
		else
			$out[] = '<li>'.gTxt('none').'</li>'.n;
		return join('', $out);
		}

	function _extend_plugin_list()
		{
		global $plugins_ver;
		static $full_plugin_list;

		if( !isset( $full_plugin_list ) )
			{
			#
			#	Note the plugins_ver list only contains admin and library plugins at the moment.
			# So expand the plugin list to include public plugins from the txp_plugin table.
			# Cache directory is ignored to stop the eval() of the plugin.
			#
			$temps = safe_column( 'name' , 'txp_plugin' , "`status`='1' and `type`='0'" );
			$full_plugin_list = array_merge( $temps , $plugins_ver );
			}

		return $full_plugin_list;
		}

	function _generate_plugin_list()											# left pane subroutine
		{
		$rps = MLPStrings::discover_registered_plugins();
		if( count( $rps ) )
			{
			$plugins = $this->_extend_plugin_list();

			foreach( $rps as $plugin=>$vals )
				{
				if( !is_array( $vals ) )
					continue;

				extract( $vals );
				$plugin_found = array_key_exists( $plugin, $plugins );

				$marker = ( !$plugin_found )
					? ' <strong>*</strong>' : '';
				$out[] = '<li><a href="' . $this->parent->url( array(L10N_PLUGIN_CONST=>$plugin,'prefix'=>$pfx) , true ) . '">' .
						//$plugin . br . ' [~' .$num . sp . MLPLanguageHandler::get_native_name_of_lang($lang) . '] ' . $marker.
						$plugin . ' [' .$event . '] ' . $marker.
						'</a></li>';
				}
			}
		else
			$out[] = '<li>'.gTxt('none').'</li>'.n;

		return join('', $out);
		}

	function _render_list_filter( $id )
		{
		return '<script type="text/javascript" src="jquery.js"></script>'.
		'<script> $(document).ready(function(){ $(\'#'.$id.'_inline_search\').'.
		'search(\'#'.$id.' li\'); }); $.fn.search = function(searchElements) {'.
		'$(this).keyup(function(){ var searchString = $(this).val();'.
		' if (searchString.length > 0){ $(searchElements).hide();'.
		'$(searchElements+\':contains(\' +searchString+ \')\').show(); }'.
		'else { $(searchElements).show(); } }); }; </script>'.
		'<div style="text-align:left;"><label>'.gTxt('l10n-filter_label').'</label><br />'.
		'<input type="text" id="'.$id.'_inline_search" /></div>';
		}

	function render_owner_list( $type )										#	Render the left pane
		{
		/*
		Renders a list of resource owners for the left-hand pane.
		*/
		$out[] = '<div class="l10n_owner_list">';

		switch( $type )
			{
			case 'special':
				$out[] = 	'<h3>' . gTxt('l10n-specials') . '</h3>' . n .
							'<div id="l10n_specials">' . n .
							graf( gTxt( 'l10n-explain_specials' ) );
				break;

			case 'plugin':
				$out[] = $this->_render_list_filter( 'l10n_plugins' );
				$out[] = 	'<h3>' . gTxt('l10n-registered_plugins') . '</h3>' . n .
							'<div id="l10n_plugins">' . n .
							'<ol>' . n;
				$out[] = $this->_generate_plugin_list();
				$out[] = n . '</ol>';
				break;

			case 'page':
				$out[] = $this->_render_list_filter( 'l10n_pages' );
				$out[] = 	'<h3>' . gTxt('pages') . '</h3>' . n .
							'<div id="l10n_pages"' . n .
							'<ol>' . n;
				$out[] = $this->_generate_list( 'txp_page' , 'name' , 'user_html' );
				$out[] = n . '</ol>';
				break;

			default:
				case 'form':
				$out[] = $this->_render_list_filter( 'l10n_forms' );
				$out[] = 	'<h3>' . gTxt('forms') . '</h3>' . n .
							'<div id="l10n_forms">' . n .
							'<ol>' . n;
				$out[] = $this->_generate_list( 'txp_form' , 'name' , 'Form' );
				$out[] = n . '</ol>';
				break;
			}

		$out[] = n . '</div>';
		$out[] = n . '</div>';
		echo join('', $out);
		}

	function _render_string_list( $strings , $owner_label , $owner_name , $prefix , $event )	# Center pane string render subroutine
		{
		#echo "_render_string_list( \$strings , \$owner_label[$owner_label] , \$owner_name[$owner_name] , \$prefix[$prefix] , \$event[$event] )".br.n;
		
		$strings_exist 	= ( count( $strings ) > 0 );
		if( !$strings_exist )
			return '';

		$site_langs = MLPLanguageHandler::get_site_langs();

		$needs_legend = false;
		$strip_prefix = 'plugin'===$owner_label;
		$prefix_len   = ($strip_prefix) ? strlen($prefix)+1 : 0;

		$out[] = '<ol>';
		if( $strings_exist )
			{
			foreach( $strings as $string=>$langs )
				{
				$complete = MLPStrings::is_complete( $langs , ($event!=='public') );
				if( !$complete )
					$needs_legend = true;
				$guts = (( $strip_prefix ) ? substr( $string , $prefix_len ) : $string ) . ' ['.( ($langs) ? $langs : gTxt('none') ).']';
				if( !$complete )
					$guts .= ' <strong>*</strong>';
				$out[]= '<li><a href="' .
					$this->url( array($owner_label=>$owner_name, gbp_id=>$string, 'prefix'=>$prefix, 'string_event'=>$event) , true ) .
					'">' . $guts . '</a></li>';
				}
			}
		else
			$out[] = '<li>'.gTxt('none').'</li>'.n;

		$out[] = '</ol>';

		if( $needs_legend )
			{
			if( $event !== 'public' )
				$event = 'admin';
			$event = MLPStrings::convert_case( gTxt( $event ) , MB_CASE_LOWER );
			$out[] = graf( gTxt('l10n-add_string_rend',array('{side}'=>$event)) );
			}

		return join('', $out);
		}

	function _render_string_stats( $string_name , &$stats )					# Right pane stats render subroutine
		{
		$site_langs  = MLPLanguageHandler::get_site_langs();
		$admin_langs = MLPLanguageHandler::get_installation_langs();

		$out[] = '<h3>'.gTxt('l10n-summary').'</h3>'.n;
		$out[] = '<table>'.n.'<thead>'.n.tr( '<td align="right">'.gTxt('language').'</td>'.n.'<td align="right">&nbsp;&nbsp;&#035;&nbsp;</td>' . td('') . td('') ).n.'</thead><tbody>';
		$extras_found = false;
		$plugin = gps( 'plugin' );
		foreach( $stats as $iso_code=>$count )
			{
			$lang_extras_found = false;
			$name = MLPLanguageHandler::get_native_name_of_lang( $iso_code );
			$remove = '';
			$export = '';
			$supported = in_array( $iso_code , $site_langs ) || in_array( $iso_code , $admin_langs );
			if( !$supported )
				{
				$extras_found = true;
				$lang_extras_found = true;
				if( !empty($plugin) )
					{
					$remove[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('delete'), '').'</span>';
					$remove[] = sInput( 'l10n_remove_languageset');
					$remove[] = $this->parent->form_inputs();
					$remove[] = hInput( 'plugin' , $plugin );
					$remove[] = hInput( 'prefix' , gps( 'prefix' ) );
					$remove[] = hInput( 'lang_code' , $iso_code );
					$remove[] = hInput( 'subtab' , $this->sub_tab );
					$remove = form( join( '' , $remove ) ,
									'' ,
									"verify('" . doSlash(gTxt('l10n-lang_remove_warning' , array('{var1}'=>$name)) ) .
									 doSlash(gTxt('are_you_sure')) . "')");
					}
				}

			$details =  MLPStrings::if_plugin_registered( $string_name , $iso_code );
			if( false !== $details )
				{
				$export[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-export'), '').'</span>';
				$export[] = sInput( 'l10n_export_languageset');
				$export[] = $this->parent->form_inputs();
				$export[] = hInput( 'language' , $iso_code );
				$export[] = hInput( 'prefix' , $details['pfx'] );
				$export[] = hInput( 'plugin' , $string_name );
				$export = form( join( '' , $export ) );
				}

			$out[]= tr( td( ($lang_extras_found ? ' * ' : '').$name ).td( '&nbsp;'.$count.'&nbsp;' ).td($export).td($remove) , ' style="text-align:right;" ' );
			}
		$out[] = tr( tdcs( '<hr/>' , 4 ) );
		$out[] = tr( td( gTxt('l10n-total') ).td('&nbsp;'.array_sum($stats).'&nbsp;').td('').td('') , ' style="text-align:right;" ' );
		$out[] = tr( tdcs( '<hr/>' , 4 ) );
		$out[] = '</tbody></table>';

		if( $extras_found )
			{
			$out[] = gTxt('l10n-explain_extra_lang');

			if( empty( $string_name ) )
				{
				foreach( $stats as $iso_code=>$count )
					{
					$supported = in_array( $iso_code , $site_langs ) || in_array( $iso_code , $admin_langs );
					if( !$supported )
						{
						$remove = array();
						$name = MLPLanguageHandler::get_native_name_of_lang( $iso_code );
						$count = safe_count( 'txp_lang' , "`lang`='$iso_code'" );

						$remove[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('delete'), '').'</span>';
						$remove[] = sInput( 'l10n_remove_language');
						$remove[] = $this->parent->form_inputs();
						$remove[] = hInput( 'container' , gps('container') );
						$remove[] = hInput( 'lang_code' , $iso_code );
						$remove[] = hInput( 'subtab' , $this->sub_tab );
						$out[]    = form( gTxt('l10n-delete_whole_lang' , array('{var1}'=>$name,'{var2}'=>$count) ) . sp . join( '' , $remove ) ,
										'' ,
										"verify('" . doSlash(gTxt('l10n-lang_remove_warning2' , array('{var1}'=>$name)) ) .
										 doSlash(gTxt('are_you_sure')) . "')") . br . n;
						}
					}
				}
			}

		if( !empty( $string_name ) )
			{
			$import[] = gTxt('l10n-import_title' , array( '{type}'=>gTxt('l10n-plugin') )) . br;
			$import[] = '<textarea name="data" cols="60" rows="2" id="l10n_string_import">';
			$import[] = '</textarea>' .br . br;
			$import[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-import'), '').'</span>';
			$import[] = sInput( 'l10n_import_languageset');
			$import[] = $this->parent->form_inputs();
			$import[] = hInput( 'plugin' , gps('plugin') );
			$import[] = hInput( 'prefix' , gps('prefix') );
			$import[] = hInput( 'language' , gps('language') );
			$import[] = hInput( 'subtab' , $this->sub_tab );
			$out[] = form( join( '' , $import ) , 'border: 1px solid #ccc; padding:1em; margin:1em;' );
			}

		return join( '' , $out );
		}

	function render_plugin_string_list( $plugin , $string_name , $prefix )	# Center pane plugin wrapper
		{
		/*
		Show all the strings and localisations for the given plugin.
		*/
		$stats 			= array();
		$strings 		= MLPStrings::get_plugin_strings( $plugin , $stats , $prefix );
		$raw_count 		= count( $strings );
		$strings_exist 	= ( $raw_count > 0 );
		$details		= MLPStrings::if_plugin_registered( $plugin , '' );
		$event			= $details['event'];

		$out[] = '<div class="l10n_plugin_list">';
		$out[] = '<h3>'.$plugin.' '.gTxt('l10n-strings').'</h3>'.n;
		$out[] = '<span style="float:right;"><a href="' .
				 $this->url( array( L10N_PLUGIN_CONST => $plugin, 'prefix'=>$prefix ) , true ) . '">' .
				 gTxt('l10n-statistics') . '&#187;</a></span>' . br . n;

		#if( $raw_count > 10 )
		$out[] = $this->_render_list_filter( 'l10n_plugin_strings' );
		$out[] = '<div id="l10n_plugin_strings">' . n;
		$out[] = br . n . $this->_render_string_list( $strings , L10N_PLUGIN_CONST , $plugin , $prefix, $event );
		$out[] = '</div></div>';

		# Render default view details in right hand pane...
 		if( empty( $string_name ) )
			{
			$out[] = '<div class="l10n_values_list">';
			$out[] = $this->_render_string_stats( $plugin , $stats );

			# If the plugin is not present offer to delete the lot
			$plugins = $this->_extend_plugin_list();
			if( !array_key_exists( $plugin, $plugins ) )
				{
				$out[] = '<h3>'.gTxt('l10n-no_plugin_heading').'</h3>'.n;
				$del[] = graf( gTxt('l10n-remove_plugin') );
				$del[] = '<div class="l10n_form_submit">'.fInput('submit', '', gTxt('delete'), '').'</div>';
				$del[] = sInput('l10n_remove_stringset');
				$del[] = $this->parent->form_inputs();
				$del[] = hInput(L10N_PLUGIN_CONST, $plugin);

				$out[] = form(	join('', $del) ,
								'border: 1px solid grey; padding: 0.5em; margin: 1em;' ,
								"verify('".doSlash(gTxt('l10n-delete_plugin')).' '.doSlash(gTxt('are_you_sure'))."')");
				}

			$out[] = '</div>';
			}

		echo join('', $out);
		}

	function render_string_list( $table , $fdata , $owner , $id='' )			# Center pane snippet wrapper
		{
		/*
		Renders a list of strings belonging to the chosen owner in the center pane.
		*/
		$stats 	= array();
		$data 	= safe_field( $fdata , $table , " `name`='$owner'" );
		$count = 0;
		$snippets = MLPSnips::find_snippets_in_block( $data , $count );
		$strings  = MLPSnips::get_snippet_strings( $snippets , $stats );
		$can_edit = $this->pref('l10n-inline_editing');

		$out[] = '<div class="l10n_string_list">';
		$out[] = '<h3>'.$owner.' '.gTxt('l10n-snippets').'</h3>'.n;
		$out[] = '<span style="float:right;"><a href="' .
				 $this->url( array( 'container' => $owner ) , true ) . '">' .
				 gTxt('l10n-statistics') . '&#187;</a></span>' . br . n;
		if( $can_edit )
			 $out[] = '<span style="float:right;"><a href="' .
					 $this->parent->url( array( 'container'=>$owner , 'step'=>'l10n_edit_pageform' , 'subtab'=>$this->sub_tab ) , true ) . '">' .
					 gTxt('l10n-edit_resource' , array('{type}'=>gTxt($this->event),'{owner}'=>$owner) ) .
					 '&#187;</a></span>' . br . n;

		#	Render the list...
		#if( $count > 10 )
		$out[] = $this->_render_list_filter( 'l10n_string_list' );
		$out[] = '<div id="l10n_string_list">' . n;
		$out[] = br . n . $this->_render_string_list( $strings , 'container', $owner , '' , 'public' ) . n;
		$out[] = '</div></div>';

		#	Render default view details in right hand pane...
		$step = gps('step');
 		if( empty( $id ) and empty( $step ) )
			{
			$out[] = '<div class="l10n_values_list">';
			$out[] = $this->_render_string_stats( '' , $stats );
			$out[] = '</div>';
			}

		echo join('', $out);
		}

	function render_specials_list( $id='')
		{
		/*
		Renders a list of special strings...
		*/
		$stats 	= array();
		$owner = 'special';
		$raw_count = 1;
		$snippets = MLPSnips::get_special_snippets();
		$strings  = MLPSnips::get_snippet_strings( $snippets , $stats );

		$out[] = '<div class="l10n_string_list">';
		$out[] = '<h3>'.gTxt('l10n-special').' '.gTxt('l10n-snippets').'</h3>'.n;
		$out[] = '<span style="float:right;"><a href="' .
				 $this->url( array( 'owner' => $owner ) , true ) . '">' .
				 gTxt('l10n-statistics') . '&#187;</a></span>' . br . n;

		#	Render the list...
		$out[] = br . n . $this->_render_string_list( $strings , 'container', $owner , '' , 'public' ) . n;
		$out[] = '</div>';

		#	Render default view details in right hand pane...
		$step = gps('step');
 		if( empty( $id ) and empty( $step ) )
			{
			$out[] = '<div class="l10n_values_list">';
			$out[] = $this->_render_string_stats( '' , $stats );
			$out[] = '</div>';
			}

		echo join('', $out);
		}
	function render_pageform_edit( $table , $fname, $fdata, $owner )			# Right pane page/form edit textarea.
		{
		$out[] = '<div class="l10n_values_list">';
		$out[] = '<h3>'.gTxt('l10n-edit_resource' , array('{type}'=>$this->event,'{owner}'=>$owner) ).'</h3>' . n;

		$data = safe_field( $fdata , $table , '`'.$fname.'`=\''.doSlash($owner).'\'' );

		$f[] = '<p><textarea name="data" cols="70" rows="20" title="'.gTxt('l10n-textbox_title').'">' .
			 txpspecialchars($data) .
			 '</textarea></p>'.br.n;
		$f[] = '<div class="l10n_form_submit">'.fInput('submit', '', gTxt('save'), '').'</div>';
		$f[] = sInput('l10n_save_pageform');
		$f[] = $this->parent->form_inputs();
		$f[] = hInput('container', $owner);
		$f[] = hInput('subtab' , $this->sub_tab );
		$out[] = form( join('', $f) , 'padding: 0.5em; margin: 1em;' );

		$out[] = '</div>';
		echo join('', $out);
		}

	function render_string_edit( $type , $container , $id, $owner = '' , $event='public' )	# Right pane string edit routine
		{
		/*
		Render the edit controls for all localisations of the chosen string.
		This can either be called in the flow of normal HTTP request processing or
		as an XMLHttp request from the l10n JavaScript.
		*/
		$xml = $this->xml();
		if( $xml )
			{
			while (@ob_end_clean());
			ob_start();
			header( "Content-Type: text/xml" );
			echo '<?xml version=\'1.0\' encoding=\'utf-8\'?>'.n.'<div>';
			}
		else
			$out[] = '<div class="l10n_values_list" id="l10n_div_string_edit">';

		$debug = false;
		if( $debug ) $out[] = 'render_string_edit( '.$type.' , '.$container.' , '.$id.' , '.$owner.' , '.$event.' )'; 
			
		$out[] = '<h3>'.gTxt('l10n-renditions_for').' "'.$id.'"</h3>'.n.'<form action="index.php" method="post"><dl>';

		$x = MLPStrings::get_string_set( $id );
		$final_codes = array();

		#	Complete the set with any missing language codes and empty data...
		$lang_codes = MLPLanguageHandler::get_site_langs();
		if( $type === 'plugin' )
			{
			$admin_plugin = safe_field( 'type' , 'txp_plugin' , "`name`='$owner'" );

			#
			#	if this string is in an admin or library plugin then use the
			# installation (admin) languages...
			#
			if( $admin_plugin > 0 )
				$lang_codes = MLPLanguageHandler::get_installation_langs();
			}
		elseif( $type === 'search' )
			{
			$lang_codes = MLPLanguageHandler::get_installation_langs();
			}

		# Work out what event to use...
		$default_event = $event;
		switch( count( $x ) )
			{
			case 0:	# No string with this name exists in the DB yet
				break;
			case 1: # Use existing event
				$usedlangcodes = array_keys($x);
				$default_event = $x[$usedlangcodes[0]]['event'];
				break;
			default: # Use the most frequent existing event...
				foreach( array_values($x) as $i => $data)
					$events[] = $data['event'];
				$freq = array_count_values( $events );
				$max = max($freq);
				foreach ($freq as $key => $val) 
					{
					if ($val === $max)
						{
						$default_event = $key;
						break;
						}
					}
				break;
			}
			
		$default_owner = $owner;
		switch( count( $x ) )
			{
			case 0:	# No string with this name exists in the DB yet
				break;
			case 1: # Use existing owner
				$usedlangcodes = array_keys($x);
				$default_owner = $x[$usedlangcodes[0]][L10N_COL_OWNER];
				break;
			default: # Use the most frequent existing owner...
				foreach( array_values($x) as $i => $data)
					$owners[] = $data[L10N_COL_OWNER];
				$freq = array_count_values( $owners );
				$max = max($freq);
				foreach ($freq as $key => $val)
					{
					if ($val === $max)
						{
						$default_owner = $key;
						break;
						}
					}
				break;
			}

		foreach($lang_codes as $code)
			{
			if( !array_key_exists( $code , $x ) )
				$x[ $code ] = array( 'id'=>'', 'event'=>$default_event, 'data'=>'' , L10N_COL_OWNER => $default_owner );
			}
		ksort( $x );

		if( $debug ) dmp( $x );

		foreach( $x as $code => $data )
			{
			$final_codes[] = $code;
			$e = $data['event'];
			if( empty( $e ) )
				$e = $event;
			$lang = MLPLanguageHandler::get_native_name_of_lang($code);
			$dir  = MLPLanguageHandler::get_lang_direction_markup( $code );

			$warning = '';
			if( empty( $data['id'] ) )
				$warning .= ' * '.gTxt('l10n-missing').sp;
			elseif( empty( $data['data'] ))
				$warning .= ' * '.gTxt('l10n-empty').sp;

			$out[] = '<dt>'.$lang.' ['.$code.']. '.$warning.sp.'<a href="#" onClick="toggleDirection(\''.$code.'\')"><span id="'.$code.'-toggle">'.gTxt('l10n-toggle').'</span></a>' .'</dt>';
			$out[] = '<dd><p>'.
						'<textarea class="l10n_string_edit" id="' . $code . '-data" name="' . $code . '-data" cols="60" rows="2" title="' .
						gTxt('l10n-textbox_title') . '"'. $dir .'>' . txpspecialchars( $data['data'] ) . '</textarea>' .
						hInput( $code.'-id' , $data['id'] ) .
						hInput( $code.'-event' , $e ) .
						'</p></dd>';
			}

		$out[] = '</dl>';
		$out[] = '<div class="l10n_form_submit">'.fInput('submit', '', gTxt('save'), '').'</div>';
		$out[] = sInput('l10n_save_strings');
		$out[] = $this->parent->form_inputs();
		$out[] = hInput('codes', trim( join( ',' , $final_codes ) , ', ' ) );
		//$out[] = hInput(L10N_LANGUAGE_CONST, gps(L10N_LANGUAGE_CONST));
		$out[] = hInput('prefix', gps('prefix'));
		if( $type === 'plugin' )
			$out[] = hInput(L10N_PLUGIN_CONST, $container);
		else
			{
			$out[] = hInput('container', $container);
			$out[] = hInput('subtab' , $this->sub_tab );
			}
		$out[] = hInput('l10n_type', $type );
		$out[] = hInput('owner', $default_owner );
		$out[] = hInput('string_event', $event);
		$out[] = hInput(gbp_id, $id);
		$out[] = '</form>'.n;
		$out[] = '</div>';
		echo join('', $out);
		if( $xml )
			{
			exit;
			}
		}

	function render_import_list()
		{
		$d = gps( 'data' );
		$d = @unserialize( @base64_decode( @str_replace( "\r\n", '', $d ) ) );

		$o[] = '<div style="float:left;">';
		$o[] = '<h2>'.gTxt('preview').' '.gTxt('file').'</h2>';
		if( !isset($d['owner']) or !isset($d['prefix']) or !isset($d['event']) or !isset($d['strings']) )
			$o[] = gTxt('l10n-invalid_import_file');
		else
			{
			$f1[] = gTxt('plugin') . ': <strong>'.$d['owner'].'</strong>'.br.n;
			$f1[] = gTxt('language') . ': <strong>'.MLPLanguageHandler::get_native_name_of_lang($d['lang']).' ['.$d['lang'].']</strong>'.br.br.n;
			$f1[] = hInput( 'data' , gps('data') );
			$f1[] = hInput( 'plugin' , $d['owner'] );
			$f1[] = hInput( 'prefix' , $d['prefix'] );
			$f1[] = hInput( 'language' , gps('language') );
			$f1[] = sInput( 'l10n_import_languageset');
			$fl[] = hInput( 'subtab' , $this->sub_tab );
			$f1[] = hInput( 'commit', 'true' );
			$f1[] = $this->parent->form_inputs();

			$direction_markup = MLPLanguageHandler::get_lang_direction_markup( $d['lang'] );

			foreach( $d['strings'] as $k=>$v )
				{
				$v = txpspecialchars( $v );
				$l[] = tr( '<td style="text-align: right;">'.$k.' : </td>' . n . td("<input type=\"text\" readonly size=\"100\" value=\"$v\" $direction_markup/>") ) .n ;
				}

			$f2[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('save'), '').'</span>';
			$content = join( '' , $f1 ) . tag( join( '' , $l ) , 'table' ) . join( '' , $f2 );
			$o[] = form( $content , '' ,
						"verify('" . doSlash( gTxt('l10n-import_warning') ) . ' ' . doSlash(gTxt('are_you_sure')) . "')");
			}
		$o[] = '</div>';
		echo join( '' , $o );
		}

	function remove_strings()
		{
		$remove_langs 	= gps('lang_code');
		$plugin 		= gps( L10N_PLUGIN_CONST );
		MLPStrings::remove_strings( $plugin , $remove_langs );
		unset( $_POST['step'] );
		}
	function remove_language()
		{
		$remove_lang 	= gps('lang_code');
		if( !empty($remove_lang) )
			MLPStrings::remove_lang( $remove_lang );
		unset( $_POST['step'] );
		}

	function save_strings()
		{
		$string_name 	= gps( gbp_id );
		$owner       	= gps( 'owner' );
		$codes			= gps( 'codes' );
		$container		= gps( 'container' );
		$lang_codes		= explode( ',' , $codes );
		$i				= 0;

		foreach($lang_codes as $code)
			{
			$t = gps( $code.'-data' );
			if( !empty( $t ) )
				$i += 1;
			}

		# allow deletions from the search page only.
		$search_page	= ($container === 'search');
		$allow_delete	= ( '1' == $this->pref('l10n-allow_search_delete') ) ? $search_page : false;
		if( !$allow_delete and (0 === $i) )
			{
			$this->parent->message = gTxt('l10n-cannot_delete_all');
			return;
			}

		foreach($lang_codes as $code)
			{
			$translation 	= gps( $code.'-data' );
			$id 			= gps( $code.'-id' );
			if( $owner == 'snippet' )
				$event = 'public';
			else
				$event			= gps( $code.'-event' );
			$exists			= !empty( $id );
			if( !$exists and empty( $translation ) )
				continue;

			MLPStrings::store_translation_of_string( $string_name , $event , $code , $translation , $id , $owner );
			}
		}

	function save_pageform()
		{
		$data = doSlash( gps('data') );
		$owner = doSlash( gps('container') );

		if( !empty( $this->sub_tab) )
			$tab = doSlash( gps( 'subtab' ) );
		else
			$tab = doSlash( gps( gbp_tab ) );

		if( $tab === 'form' )
			safe_update( 'txp_form' , "`Form`='$data'" , "`name`='$owner'" );
		elseif( $tab === 'page' )
			safe_update( 'txp_page' , "`user_html`='$data'" , "`name`='$owner'" );
		}


	function export_languageset()
		{
		$plugin = gps('plugin');
		$lang   = gps('language');
		$prefix = gps('prefix');

		$details =  MLPStrings::if_plugin_registered( $plugin , $lang );
		if( false !== $details )
			{
			//$details = unserialize( $details );
			$data = MLPStrings::serialize_strings( $lang , $plugin , $prefix , $details['event'] );
			$this->parent->serve_file( $data , $plugin . '.' . $lang . '.inc' );
			}
		}

	function import_languageset()
		{
		$commit = gps( 'commit' );
		if( !empty($commit) and ('true' === $commit) )
			{
			$d 	= gps( 'data' );
			$d = unserialize( base64_decode( str_replace( "\r\n", '', $d ) ) );
			if( is_array( $d ) )
				{
				if( array_key_exists( 'strings' , $d ) )
					MLPStrings::insert_strings( $d['prefix'] , $d['strings'] , $d['lang'] , $d['event'] , $d['owner'] , true );
				}
			unset( $_POST['step'] );
			}
		}

	}

class MLPSnipIOView extends MLPSubTabView
	{
	function MLPSnipIOView($title, $event, &$parent, $is_default = NULL)
		{
		MLPSubTabView::MLPSubTabView( $title , 'snippets' , $parent , $is_default , $event );
		}

	function preload()
		{
		$step = gps('step');
		if( $step )
			{
			switch( $step )
				{
				case 'l10n_export_languageset':
					$this->export_languageset();
					break;

				case 'l10n_import_languageset':
					$this->import_languageset();
					break;

				case 'l10n_export_txp_file':
					$this->export_txp_file();
					break;
				case 'l10n_export_l10n_string_file':
					$this->export_l10n_stringfile();
					break;
				}
			}
		}

	function main()
		{
		$step = gps('step');
		switch( $step )
			{
			case 'l10n_import_languageset':
				$this->render_import_list();
				break;

			default:
				$this->render_main();
				break;
			}
		}

	function pop_help($helpvar)
		{
		$script = hu.basename(txpath).'/index.php';
		return '<a href="'.$script.'?event=plugin&step=plugin_help&name='.$this->parent->parent->plugin_name.'#'.$helpvar.'" class="pophelp">?</a>';
		}
	function render_main()
		{
		global $l10n_language;
		$site_langs 		= MLPLanguageHandler::get_site_langs();
		$installation_langs	= MLPLanguageHandler::get_installation_langs();
		$installation_langs	= MLPLanguageHandler::do_fleshout_names( $installation_langs );

		$snip_string = gTxt('l10n-snippet');

		#
		#	Here's the "Export Snippet Strings" box...
		#
		$out[] = '<div class="l10n_export_list">'.n.'<div class="l10n_bordered">';
		$out[] = gTxt('l10n-export_title' , array( '{type}'=>$snip_string , '{help}'=>$this->pop_help('l10n_export_languageset') ), 'raw').br;
		$out[] = '<table>'.n.'<thead>'.n.tr( '<td align="right">'.gTxt('language').'</td>'.n.'<td align="right">'.sp.sp.gTxt('select').sp.'</td>' ).n.'</thead><tbody>';
		foreach( $site_langs as $lang )
			{
			$name = t . '<label for="'.$lang.'">' . MLPLanguageHandler::get_native_name_of_lang( $lang ) . '</label>';
			$choice = t . '<input type="checkbox" class="checkbox" value="'.$lang.'" name="'.$lang.'" id="'.$lang.'"/>' . n;
			$export[]= tr( td( $name ).td( $choice ) , ' style="text-align:right;" ' );
			}
		$export[] = sInput( 'l10n_export_languageset');
		$export[] = $this->parent->form_inputs();
		$export[] = hInput( 'subtab' , $this->sub_tab );
		$export[] = tr( td(sp) . td(sp.sp.'<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-export'), '').'</span>') );

		$out[] = form( join( '' , $export) );
		$out[] = '</tbody></table>'.n.'</div>'.n.n;


		#
		#	Here's the "export textpattern strings" box...
		#
		$out[] = '<div class="l10n_bordered">';
		$out[] = gTxt('l10n-export_title' , array( '{type}'=>'Textpattern' , '{help}'=>$this->pop_help('l10n_export_txp_file') ), 'raw'). br;
		$out[] = '<table>'.n.'<tbody>';
		$export = array();
		$export[] = tr(
						td( gTxt('language').' :'.selectInput( 'lang' , $installation_langs , $l10n_language['long'] ) ) .
						td(sp.sp.'<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-export'), '').'</span>') );
		$export[] = sInput( 'l10n_export_txp_file');
		$export[] = $this->parent->form_inputs();
		$export[] = hInput( 'subtab' , $this->sub_tab );
		//$export[] = tr( td(sp.sp.'<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-export'), '').'</span>') );

		$out[] = form( join( '' , $export) );
		$out[] = '</tbody></table>'.n.'</div>'.n;

		#
		#	Here's the "export l10n strings" box...
		#
		$out[] = '<div class="l10n_bordered">';
		$out[] = gTxt('l10n-export_title' , array( '{type}'=>'MLP Pack' , '{help}'=>$this->pop_help('l10n_export_l10n_string_file')), 'raw'). br;
		$out[] = '<table>'.n.'<tbody>';
		$export = array();
		$export[] = tr(
						td( gTxt('language').' :'.selectInput( 'lang' , $installation_langs , $l10n_language['long'] ) ) .
						td(sp.sp.'<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-export'), '').'</span>') );
		$export[] = sInput( 'l10n_export_l10n_string_file');
		$export[] = $this->parent->form_inputs();
		$export[] = hInput( 'subtab' , $this->sub_tab );

		$out[] = form( join( '' , $export) );
		$out[] = '</tbody></table>'.n.'</div>'.n;

		$out[] = '</div>'.n;	#	End of the export div.

		$import[] = '<div class="l10n_bordered l10n_import_list">';
		$import[] = gTxt('l10n-import_title' , array( '{type}'=>$snip_string) ) . br;
		$import[] = '<textarea name="data" cols="40" rows="2" id="l10n_string_import">';
		$import[] = '</textarea>' .br . br;
		$import[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-import'), '').'</span>';
		$import[] = sInput( 'l10n_import_languageset');
		$import[] = $this->parent->form_inputs();
		$import[] = hInput( 'language' , gps('language') );
		$import[] = hInput( 'subtab' , $this->sub_tab );
		$out[] = form( join( '' , $import ) ).n.'</div>'.n.n;

		echo join( '' , $out );
		}

	function export_txp_file()
		{
		global $l10n_language;

		$lang = gps( 'lang' );
		if( empty( $lang ) )
			$lang = $l10n_language['long'];

		$langs = MLPLanguageHandler::get_installation_langs();
		if( !in_array( $lang , $langs ) )
			{
			echo br , gTxt('l10n-cannot_export' , array( '{lang}'=>$lang ) );
			exit(0);
			}

		$file  = MLPStrings::build_txp_langfile( $lang );
		$title = $lang.'.txt';
		$desc  = 'Textpattern '.$lang.' '.gTxt('l10n-strings');
		$this->parent->parent->serve_file( $file , $title , $desc , 'text/plain; charset=utf-8' );
		}

	function export_l10n_stringfile()
		{
		global $l10n_language;

		$lang = gps( 'lang' );
		if( empty( $lang ) )
			$lang = $l10n_language['long'];

		$langs = MLPLanguageHandler::get_installation_langs();
		if( !in_array( $lang , $langs ) )
			{
			echo br , gTxt('l10n-cannot_export' , array( '{lang}'=>$lang ) );
			exit(0);
			}

		$file  = MLPStrings::build_l10n_default_strings_file( $lang );
		$title = 'l10n_'.$lang.'_strings.php';
		$desc  = 'MLP Pack '.$lang.' '.gTxt('l10n-strings');
		$this->parent->parent->serve_file( $file , $title , $desc, 'text/plain; charset=utf-8' );
		}

	function _get_snippet_names( $table , $row , $unused )
		{
		$results = array();
		$raw_count = 0;

		$snips = MLPSnips::find_snippets_in_block( $row['data'] , $raw_count );
		foreach( $snips as $k=>$v )
			$results[$v] = $v;

		return $results;
		}
	function get_special_snippets()
		{
		$snippets = array();
		$snips = MLPSnips::get_special_snippets();
		foreach( $snips as $k=>$v )
			$snippets[$v] = $v;
		return $snippets;
		}

	function export_languageset()
		{
		$plugin = gps('owner');
		$lang   = gps('language');

		$sources = array	(
							array	(
									'table'	=>	'txp_page',
									'name'	=>	'name',
									'data'	=>	'user_html',
									'fn'	=>	'',
									),
							array	(
									'table'	=>	'txp_form',
									'name'	=>	'name',
									'data'	=>	'Form',
									'fn'	=>	'',
									),
							array	(
									'table'	=>	'',
									'name'	=>	'',
									'data'	=>	'',
									'fn'	=>	'get_special_snippets',
									),
							);
		$snippet_names = array();

		#
		#	Scan sources for the name of snippets...
		#
		foreach( $sources as $source )
			{
			$snips = array();
			extract( $source );
			if( !empty( $fn ) )
				{
				$key = '';
				if( is_callable( array($this,$fn) , false , $key ) )
					$snips = call_user_func( array($this,$fn) );
				}
			else
				{
				$snips = MLPTableManager::walk_table_return_array( $table , $name , $data , array($this,'_get_snippet_names') );
				}
			if( is_array( $snips ) )
				$snippet_names = array_merge( $snippet_names , $snips );
			}

		sort( $snippet_names );

		$snippet_nameset = MLPStrings::make_nameset($snippet_names);

		#
		#	For each selected language, grab the snippet strings from the txp_lang table and add it to the
		# export structure...
		#
		$site_langs 	= MLPLanguageHandler::get_site_langs();

		$export_data = array();
		foreach( $site_langs as $lang )
			{
			$lang_set = array( 'lang'=>$lang );
			$present = ($lang === gps( $lang ) );
			if( !$present )
				continue;

			$lang_set = MLPStrings::get_set_by_lang( $snippet_nameset , $lang );

			$export_data[$lang] = $lang_set;
			}

		#
		#	Serve the export data as a file...
		#
		$export_data = array( 'header'=>L10N_SNIPPET_IO_HEADER , 'data'=>$export_data );
		$export_data = chunk_split( base64_encode( serialize($export_data) ) , 64, n );
		$this->parent->parent->serve_file( $export_data , 'snippets.inc' );
		}

	function render_import_list()
		{
		$count = 0;
		$site_langs = MLPLanguageHandler::get_site_langs();
		$d = gps( 'data' );
		$d = @unserialize( @base64_decode( @str_replace( "\r\n", '', $d ) ) );
		$subtab = gps('subtab');

		$o[] = '<div style="float:left;">';
		$o[] = '<h2>'.gTxt('preview').' '.gTxt('file').'</h2>';

		if( is_array( $d ) and !empty( $d ) )
			{
			if( !isset( $d['header'] ) or $d['header'] !== L10N_SNIPPET_IO_HEADER )
				{
				$o[] = gTxt('l10n-invalid_import_file');
				}
			else
				{
				$f[] = hInput( 'data' , gps('data') ).n;
				$f[] = hInput( 'language' , gps('language') ).n;
				$f[] = sInput( 'l10n_import_languageset').n;
				$f[] = hInput( 'subtab' , $this->sub_tab );
				$f[] = hInput( 'commit', 'true' ).n;
				$f[] = $this->parent->form_inputs().n;
				foreach( $d['data'] as $lang=>$set )
					{
					$dir_markup	= MLPLanguageHandler::get_lang_direction_markup( $lang );

					if( empty( $lang ) or !in_array( $lang, $site_langs ) or empty( $set ) )
						{
						$l[] = tr( n.td( sp ).n.td( gTxt('l10n-language_not_supported') ) ).n;
						}
					else
						{
						$l[] = tr( n.tdcs( gTxt('language') . ': <strong>'.MLPLanguageHandler::get_native_name_of_lang($lang).' ['.$lang.']&#8230;</strong>'.br.br.n , 2 ) ).n;
						foreach( $set as $name=>$couplet )
							{
							$data	= txpspecialchars($couplet[0]);
							$event	= txpspecialchars($couplet[1]);

							if( empty( $name ) or empty($event) or empty($data) )
								continue;

							$l[] = tr( n.t.'<td style="text-align: right;">'.$name.' <em>('.$event.')</em> : </td>' . n . td("<input type=\"text\" readonly size=\"100\" value=\"$data\" $dir_markup/>") ) .n;
							$count++;
							}
						}
					$l[] = tr( n.t.tdcs( sp , 2 ) ).n;
					}
				$l[] = tr( n.tdcs( sp , 2 ) ).n;
				$l[] = tr( n.tdcs( gTxt( 'l10n-total' ) . sp . ': ' . $count . sp . gTxt('strings') , 2 ) ).n;
				$l[] = tr( n.tdcs( sp , 2 ) ).n;

				$f2[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('save'), '').'</span>';
				$content = join( '' , $f ) . tag( join( '' , $l ) , 'table' ) . join( '' , $f2 );
				$o[] = form( $content , '' ,
							"verify('" . doSlash( gTxt('l10n-import_warning') ) . ' ' . doSlash(gTxt('are_you_sure')) . "')");
				}
			}

		$o[] = '</div>';
		echo join( '' , $o );
		}

	function import_languageset()
		{
		$commit = gps( 'commit' );
		if( empty($commit) or ('true' !== $commit) )
			{
			return;
			}

		$site_langs 	= MLPLanguageHandler::get_site_langs();

		$d 	= gps( 'data' );
		$d = unserialize( base64_decode( str_replace( "\r\n", '', $d ) ) );
		$count = 0;

		if( is_array( $d ) and !empty( $d ) )
			{
			if( !isset( $d['header'] ) or $d['header'] !== L10N_SNIPPET_IO_HEADER )
				{
				return;
				}

			foreach( $d['data'] as $lang=>$set )
				{
				if( empty( $lang ) or !in_array( $lang, $site_langs ) or empty( $set ) )
					continue;

				foreach( $set as $name=>$couplet )
					{
					$data = $couplet[0];
					$event = $couplet[1];

					if( empty( $name ) or empty($event) or empty($data) )
						continue;

					$name = doSlash( $name );
					$event = doSlash( $event );
					$data = doSlash( $data );

					$set = "`lang`='$lang', `event`='$event', `data`='$data', `".L10N_COL_OWNER."`='', `name`='$name'";

					$id = safe_field( 'id' , 'txp_lang' , "`name`='$name' AND `lang`='$lang'" );
					if( false === $id )
						{
						$res = safe_insert( 'txp_lang', $set);
						if( $res !== false and $res !== 0 )
							$count++;
						}
					else
						{
						$res = safe_update( 'txp_lang', $set, "`id`='$id'");
						if( true === $res )
							$count++;
						}
					}
				}
			}

			$this->parent->parent->message = gTxt('l10n-import_count',array('{count}'=>$count,'{type}'=>gTxt('l10n-snippet')));
			unset( $_POST['step'] );
		}

	}

class MLPArticleView extends GBPAdminTabView
	{
	var $clone_by_id = '';
	var	$statuses = array();
	function MLPArticleView( $title, $event, &$parent, $is_default = NULL )
		{
		$this->statuses = array(
			1 => gTxt('draft'),
			2 => gTxt('hidden'),
			3 => gTxt('pending'),
			4 => gTxt('live'),
			5 => gTxt('sticky'),
			);
		GBPAdminTabView::GBPAdminTabView( $title , $event , $parent , $is_default );
		}

	function preload()
		{
		$rebuild = gps( 'rebuild' );
		$step = gps('step');
		if( $step )
			{
			switch( $step )
				{
				case 'clone_all_from':
					$this->clone_all_from();
					break;

				case 'clone':
					$this->clone_for_translation();
				break;

				case 'l10n_change_pageby':
					event_change_pageby('article');
				break;

				case 'delete_article':
					$this->delete_article();
				break;

				case 'delete_rendition':
					$rebuild = $this->delete_rendition();
				break;
				}
			}

		if( $rebuild )
			{
			$results = MLPArticles::check_groups();
			if( !empty( $results ) )
				{
				$desc = '';
				foreach( $results as $record )
					{
					$desc .= $record[3] . br . n;
					}
				$desc .= gTxt('l10n-table_rebuilt') . n;
				$this->parent->message = $desc;
				}
			else
				$this->parent->message = gTxt('l10n-article_table_ok');
			}
		}

	function clone_all_from()
		{
		$has_privs = has_privs( 'l10n.clone' );
		if( !$has_privs )
			return;		# User cannot clone articles.

		$vars = array( 'target_lang', 'clone_from_language' );
		extract( gpsa( $vars ) );
		$langs = MLPLanguageHandler::get_site_langs();

		if( !in_array( $target_lang , $langs ) || !in_array( $clone_from_language, $langs) )
			{
			echo br , 'Invalid target or source langauge: ' , $target_lang , ' :: ' , $clone_from_language ;
			return;
			}

		#echo br , 'Cloning all non-empty ' , $clone_from_language , ' => empty ' , $target_lang ;

		# Extract all existing rendition's group IDs in the target language...
		$existing_target_rends = array();
		$tmp = safe_rows( '`'.L10N_COL_GROUP.'` as `article`' , 'textpattern' , '`'.L10N_COL_LANG.'`=\''.$target_lang.'\'' );
		foreach( $tmp as $k=>$data )
			$existing_target_rends[] = $data['article'];
		unset( $tmp );

		# Extract all non-empty source renditions...
		$rows = safe_rows( '*' , 'textpattern' , '`'.L10N_COL_LANG.'`=\''.$clone_from_language.'\'' );
		foreach( $rows as $row )
			{
			$article_id = $row[L10N_COL_GROUP];

			if( in_array($article_id , $existing_target_rends) )
				{
				#echo br , 'Article '.$article_id.' already has an ['.$target_lang.'] rendition. Skipping it.';
				continue;
				}
			else
				{
				$new_rendition_id = $this->_clone_rendition( $row , $article_id , $target_lang );
				#echo br , 'Article '.$article_id.' has no ['.$target_lang.'] rendition. CLONED TO RENDITION :'.$new_rendition_id;
				}

			}
		}

	function _clone_rendition( $source , $article_id , $target_lang , $new_author='' )
		{
		global $DB;
		unset( $source['ID' ] );
		if( !empty( $new_author ) )
			$source['AuthorID'] = $new_author;
		$source[L10N_COL_LANG] = $target_lang;
		$source['Status'] = 1;
		$source['LastMod'] = 'now()';
		$source['feed_time'] = 'now()';
		$source['uid'] = md5(uniqid(rand(),true));
		$source['comments_count'] = 0;	# Don't clone the comment count!

		$insert = array();
		foreach( $source as $k => $v )
			{
			$v = doSlash( $v );
			if( $v === 'now()' )
				$insert[] = "`$k`= $v";
			else
				$insert[] = "`$k`='$v'";
			}
		$insert_sql = join( ', ' , $insert );

		#
		#	Insert into the master textpattern table...
		#
		safe_insert( 'textpattern' , $insert_sql );
		$rendition_id = mysqli_insert_id($DB->link);

		#
		#	Add this to the group (article) table...
		#
		MLPArticles::add_rendition( $article_id , $rendition_id , $target_lang );

		#
		#	Add into the rendition table for this lang ensuring this has the ID of the
		# just added master entry!
		#
		$insert[] = '`ID`='.doSlash( $rendition_id );
		$insert_sql = join( ', ' , $insert );
		$table_name = _l10n_make_textpattern_name( array( 'long'=>$target_lang ) );
		safe_insert( $table_name , $insert_sql );

		return $rendition_id;
		}

	function clone_for_translation()
		{
		$has_privs = has_privs( 'l10n.clone' );
		if( !$has_privs )
			return;		# User cannot clone articles.

		$vars = array( 'rendition' );
		extract( gpsa( $vars ) );
		$rendition = (int)$rendition;
		$langs = MLPLanguageHandler::get_site_langs();

		$clone_to = array();
		foreach( $langs as $lang )
			{
			$clone = ( $lang === gps( $lang ));
			if( $clone )
				{
				$new_author = gps( $lang.'-AuthorID' );
				$clone_to[$lang] = $new_author;
				}
			}

		if( count( $clone_to ) < 1 )
			{
			$this->parent->message = gTxt('l10n-no_langs_selected');
			$_POST['step'] = 'start_clone';
			return;
			}

		#
		#	Prepare the source rendition data...
		#
		$source = safe_row( '*' , 'textpattern' , "`ID`=$rendition" );
		$article_id = (int)$source[L10N_COL_GROUP];

		#
		#	Create the articles, substituting new authors and status as needed...
		#
		$notify   = array();		#	For email notices.
		foreach( $clone_to as $lang=>$new_author )
			{
			$rendition_id = $this->_clone_rendition( $source , $article_id , $lang , $new_author );

			#	Now we know rendition & article IDs, store against author for email notification...
			$language = MLPLanguageHandler::get_native_name_of_lang( $lang );
			$notify[$new_author][$lang] = array( 'id' => "$rendition_id" , 'title'=>$source['Title'] , 'language'=>$language );
			}

		#
		#	Send the notifications?
		#
		$send_notifications = ( '1' == $this->pref('l10n-send_notifications') ) ? true : false;
		$notify_self = ( '1' == $this->pref('l10n-send_notice_to_self') ) ? true : false;
		if( $send_notifications )
			{
			global $sitename, $siteurl, $txp_user;

			extract(safe_row('RealName AS txp_username,email AS replyto','txp_users',"name='$txp_user'"));

			foreach( $notify as $new_user => $list )
				{
				#
				#	Skip if no articles...
				#
				$count = count( $list );
				if( $count < 1 )
					continue;

				#
				#	Skip if users are the same and no notifications are to be sent in that case...
				#
				$same = ($new_user == $txp_user);
				if( $same and !$notify_self )
					continue;

				#
				#	Construct a list of links to the renditions assigned to this user...
				#
				$links = array();
				foreach( $list as $lang => $record )
					{
					extract( $record );
					$msg = gTxt('title')  . ": \"$title\"\r\n";
					$msg.= gTxt( 'l10n-xlate_to' ) . "$language [$lang].\r\n";
					$msg.= "http://$siteurl/textpattern/index.php?event=article&step=edit&ID=$id\r\n";
					$links[] = $msg;
					}

				extract(safe_row('RealName AS new_user,email','txp_users',"name='$new_user'"));

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

				$body.= join( "\r\n" , $links ) . "\r\n" . gTxt( 'l10n-email_end' , $subs );
				$subject = gTxt( 'l10n-email_xfer_subject' , $subs );

				@txpMail($email, $subject, $body, $replyto);
				}
			}
		}
	function delete_article()
		{
		$has_privs = has_privs( 'article.delete' );
		if( !$has_privs )
			return; 	# User cannot delete articles

		#
		#	Deletes an article (multiple renditions) from the DB.
		#
		$vars = array( 'article' );
		extract( gpsa( $vars ) );

		$article = (int)$article;
		if( 0 == $article )
			return false;

		#
		#	Read the translation from the master table, extracting Group and Lang...
		#
		$renditions = safe_rows( '*' , 'textpattern' , L10N_COL_GROUP."=$article" );

		#
		#	Delete from the master table...
		#
		$master_deleted = safe_delete( 'textpattern' , L10N_COL_GROUP."=$article" );

		#
		#	Delete from the rendition tables...
		#
		foreach( $renditions as $rendition )
			{
			$lang = $rendition[L10N_COL_LANG];
			$rendition_table = _l10n_make_textpattern_name( array( 'long'=>$lang ) );
			safe_delete( $rendition_table , L10N_COL_GROUP."=$article" );
			}

		#
		#	Delete from the articles table...
		#
		MLPArticles::destroy_article( $article );
		}

	function delete_rendition()
		{
		$has_privs = has_privs( 'article.delete' );
		if( !$has_privs )
			return false;

		$vars = array( 'rendition' );
		extract( gpsa( $vars ) );

		$rendition = (int)$rendition;
		if( 0 == $rendition )
			return false;

		#
		#	Read the translation from the master table, extracting Group and Lang...
		#
		$details = safe_row( '*' , 'textpattern' , "`ID`=$rendition" );
		if( empty( $details ) )
			return true;

		$lang = $details[L10N_COL_LANG];
		$article = $details[L10N_COL_GROUP];

		#
		#	Delete from the master table...
		#
		$master_deleted = safe_delete( 'textpattern' , "`ID`=$rendition" );

		#
		#	Delete from the correct language rendition table...
		#
		$rendition_table = _l10n_make_textpattern_name( array( 'long'=>$lang ) );
		$rendition_deleted = safe_delete( $rendition_table , "`ID`=$rendition" );

		#
		#	Delete from the article table...
		#
		$article_updated = MLPArticles::remove_rendition( $article , $rendition , $lang );

		$result = false;
		if( $master_deleted and $rendition_deleted and $article_updated )
			$this->parent->message = gTxt( 'l10n-rendition_delete_ok' , array('{rendition}' => $rendition) );
		else
			{
			$results = MLPArticles::check_groups();
			if( !empty( $results ) )
				{
				$this->parent->message = $results[0][3];
				}
			else
				{
				$this->parent->message = 'Groups ok.';
				}
			}
		return $result;
		}

	function main()
		{
		switch ($this->event)
			{
			case 'article':
				{
				$step = gps('step');
				if( $step )
					{
					switch( $step )
						{
						case 'start_clone_all_from':
							$this->render_start_clone_all_from();
							break;

						case 'start_clone':
							$this->render_start_clone();
							break;

						case 'force_update_ids':
							MLPArticles::force_integer_ids();
							$this->render_article_table();
							break;

						default:
							$this->render_article_table();
							break;
						}
					}
				else
					$this->render_article_table();
				}
				break;
			}
		}


	function _apply_filter( $name , $set , $langs )
		{
		#
		#	This function works by reducing a working set, eliminating translations that don't match the criteria...
		#
		$string = gps($name);
		if( empty($string) or empty($langs) )
			return $langs;


		if( 'match_status' === $name )
			{
			#
			#	Convert to title case for the comparison...
			#
			$matches = do_list( MLPStrings::convert_case( $string, MB_CASE_TITLE ) );

			#
			#	Status strings to status codes...
			#
			$sesutats = array_flip( $this->statuses );
			foreach( $matches as $key=>$status )
				{
				if( is_numeric( $status ) )
					continue;

				if( array_key_exists( $status , $sesutats ) )
					$matches[$key] = $sesutats[$status];
				}
			}
		else
			{
			#
			#	Convert names or sections to lower case for the comparison...
			#
			$matches = do_list( MLPStrings::convert_case( $string, MB_CASE_LOWER ) );
			}

		#
		#	Do the comparison here...
		#
		foreach( $set as $lang=>$item )
			{
			$item = MLPStrings::convert_case( $item, MB_CASE_LOWER );
			if( !in_array($item , $matches) )
				unset($langs[$lang]);
			}

		return $langs;
		}
	function _render_filter_form($page)
		{
		$f[] = '<label for="match_section">'.gTxt('Section').'</label>'.sp.
				fInput( /*type*/ 	'input',
						/*name*/	'match_section',
						/*value*/	gps('match_section'),
						/*class*/	'',
						/*title*/	'',
						/*onClick*/	'',
						/*size*/	'',
						/*tab*/		'1',
						/*id*/		'match_section' ).sp.n;
		$f[] = '<label for="match_author">'.gTxt('Author').'</label>'.sp.
				fInput( /*type*/ 	'input',
						/*name*/	'match_author',
						/*value*/	gps('match_author'),
						/*class*/	'',
						/*title*/	'',
						/*onClick*/	'',
						/*size*/	'',
						/*tab*/		'2',
						/*id*/		'match_author' ).sp.n;
		$f[] = '<label for="match_status">'.gTxt('Status').'</label>'.sp.
				fInput( /*type*/ 	'input',
						/*name*/	'match_status',
						/*value*/	gps('match_status'),
						/*class*/	'',
						/*title*/	'',
						/*onClick*/	'',
						/*size*/	'',
						/*tab*/		'3',
						/*id*/		'match_status' ).n;
		$f[] = $this->form_inputs().n;
		$f[] = fInput( 'submit', 'search', gTxt('go'), '' , '', '', '', '4' );
		$f = n.join('', $f).n;

		$x[] = '<label for="clone-rendition">'.gTxt('l10n-clone_by_rend_id').'</label>'.sp.
				fInput( /*type*/ 	'input',
						/*name*/	'rendition',
						/*value*/	('start_clone' === gps('step')) ? gps('rendition') : '',
						/*class*/	'',
						/*title*/	'',
						/*onClick*/	'',
						/*size*/	'',
						/*tab*/		'5',
						/*id*/		'rendition' ).n;
		$x[] = $this->form_inputs().n;
		$x[] = sInput( 'start_clone' );
		$x[] = hInput( 'page' , $page );
		$x[] = fInput( 'submit', 'search', gTxt('go'), '' , '', '', '', '6' );
		$x = n.join('', $x).n;

		$out  = n.n.'<div id="l10n-filters">'.n;
		$out .= form($f).n;
		if( $this->pref('l10n-show_clone_by_id') )
			{
			$out .= br.n.form($x).n;
			if( $this->clone_by_id )
				{
				$out .= graf( gTxt('l10n-cannot_clone').sp.$this->clone_by_id , ' class="warning" ' );
				$this->clone_by_id = '';
				}
			}
		$out .= '</div>'.n;

		return $out;
		}

	function get_rendition_counts()
		{
		static $rendition_counts = true;
		if( true === $rendition_counts )
			{
			$rendition_counts = array();
			$rows = safe_rows( '`'.L10N_COL_LANG.'` as `lang`, COUNT(*) as `count`', 'textpattern' ,  '1=1 GROUP BY `'.L10N_COL_LANG.'`;' );
			foreach( $rows as $data )
				$rendition_counts[ $data['lang'] ] = (int)$data['count'];
			unset( $rows );
			}
		return $rendition_counts;
		}

	function render_article_table()
		{
		$event = $this->parent->event;

		#
		#	Pager calculations...
		#
		extract( get_prefs() );				#	Need to do this to keep the articles/page count in sync.
		extract( gpsa(array('page')) );

		$valid_sort = array('ID DESC', 'ID ASC', 'NAMES DESC', 'NAMES ASC');
		$sortby = strtoupper(get_pref('l10n_l10n-list_sort_order'));
		$sortby = in_array($sortby, $valid_sort) ? $sortby : $valid_sort[0];

		$total = MLPArticles::get_total();
		$limit = max(@$article_list_pageby, 15);
		list($page, $offset, $numPages) = pager($total, $limit, $page);

		#
		#	User permissions...
		#
		$can_delete = has_privs( 'article.delete' );
		$can_clone  = has_privs( 'l10n.clone' );

		#
		#	Init language related vars...
		#
		$langs = MLPLanguageHandler::get_site_langs();
		$full_lang_count = count( $langs );
		$default_lang = MLPLanguageHandler::get_site_default_lang();

		#
		#	Render the filter/search form...
		#
		$o[] = $this->_render_filter_form( $page );

		#
		#	Start the table...
		#
		$o[] = startTable( /*id*/ 'l10n_articles_table' , /*align*/ '' , /*class*/ '' , /*padding*/ '5px' );
		$o[] = '<caption><strong>'.gTxt('l10n-renditions').'</strong></caption>';

		$rendition_counts = $this->get_rendition_counts();

		#
		#	Setup the colgroup/thead...
		#
		$colgroup[] = n.t.'<col id="id" />';
		$thead[] = tag( gTxt('articles') , 'th' , ' class="id"' );
		foreach( $langs as $lang )
			{
			$colgroup[] = n.t.'<col id="'.$lang.'" />';
			$name = MLPLanguageHandler::get_native_name_of_lang($lang);

			$clone_all_link = '';
			if( @$rendition_counts[$lang] < $total )
				$clone_all_link = '<a href="' . $this->parent->url( array('page'=>$page,'step'=>'start_clone_all_from','target_lang'=>$lang), true ) .
				'" class="clone_all-link" title="' . gTxt('l10n-clone_all_from',array('{lang}'=>$name) ) . '"><img src="txp_img/l10n_clone_all.png" /></a>';

			#
			#	Default language markup -- if needed.
			#
			if( $lang === $default_lang )
				$name .= br . gTxt('default');
			else
				$name .= ' '.$clone_all_link;

			$thead[] = hCell( $name );
			$counts[$lang] = 0;
			}
		$o[] = n . tag( join( '' , $colgroup ) , 'colgroup' );
		$o[] = n .  tr( join( '' , $thead ) );

		$counts['article'] = 0;		#	Initialise the article count.
		$w = '';					#	Var for td width -- set empty to skip its inclusion / other val overrides css.
		$body = array();

		#
		#	Process the articles table...
		#
		#	Use values from the pager to grab the right sections of the table.
		#
		$articles = MLPArticles::get_articles( '1=1' , $sortby , $offset , $limit );
		if( count( $articles ) )
			{
			while( $article = nextRow($articles) )
				{
				$num_visible = 0;		# 	Holds a count of visible (=Sticky/Live) translations of this article.
				$trclass = '';			#	Class for the row (=article)
				$cells = array();		#	List of table cells (=translations) in this row
				$sections = array();	#	Holds a list of the unique sections used by translations in this article.

				#
				#	Pull out the article (NB: Not translation!)...
				#
				extract( $article );
				$members = unserialize( $members );
				$n_translations_expected = count( $members );

				#
				#	Pull the translations for this article from the master translations table
				# (that is, from the textpattern table)...
				#
				$translations = safe_rows( '*' , 'textpattern' , L10N_COL_GROUP."=$ID" );
				$n_translations = count( $translations );
				$n_valid_translations = 0;

				#
				#	Index the translations for later use...
				#
				$index = array();
				$tr_statuses = array();
				$tr_sections = array();
				$tr_authors  = array();
				for( $i=0 ; $i < $n_translations ; $i++ )
					{
					$lang = $translations[$i][L10N_COL_LANG];
					if( in_array( $lang , $langs ) )
						{
						$n_valid_translations++;
						$index[$lang] = $i;

						#
						#	Record the sections/status/authors for possible filtering...
						#
						$tr_sections[$lang] = $translations[$i]['Section'];
						$tr_statuses[$lang] = $translations[$i]['Status'];
						$tr_authors[$lang]  = $translations[$i]['AuthorID'];
						}
					else
						continue;

					#
					#	Check that the translation is recorded in the article members!
					#
					if( !array_key_exists( $lang , $members ) )
						{
						$this->parent->message = gTxt( 'l10n-missing_rendition' , array( '{id}'=>$ID ) );
						$members[$lang] = (int)$translations[$i]['ID'];
						MLPArticles::_update_article( $ID , $names , $members );
						$n_valid_translations++;
						}
					else
						{
						$master_id = (int)$translations[$i]['ID'];
						$rend_id   = (int)$members[$lang];
						if( $master_id != $members[$lang] )
							{
							//echo br , "Found incorrect rendition ID $rend_id in article table. Replacing with ID $master_id.";
							$members[$lang] = $master_id;
							MLPArticles::_update_article( $ID , $names , $members );
							$n_valid_translations++;
							}
						}
					}

				#
				#	Are all expected translations present?
				#
				$all_translations_present = ($n_valid_translations === $full_lang_count);

				#
				#	Apply filters...
				#
 				$res = $this->_apply_filter( 'match_section' , $tr_sections , $tr_sections );
				$res = $this->_apply_filter( 'match_author'  , $tr_authors  , $res );
				$res = $this->_apply_filter( 'match_status'  , $tr_statuses , $res );
				if( empty($res) )
					continue;

				#
				#	This article has at least one translation that passes the filter so increment the article count...
				#
				$counts['article']+= 1;

				#
				#	Compose the leading (article) cell...
				#
				if( $can_delete )
					$delete_art_link = '<a href="'. $this->parent->url( array('page'=>$page,'step'=>'delete_article', 'article'=>$ID), true ) .
										'" title="' . gTxt('delete') . ' ' . gTxt('article') .
										'" class="clone-link" onclick="return verify(\'' .
										doSlash(gTxt('confirm_delete_popup')) .
										'\')"><img src="txp_img/l10n_delete.png" /></a>';
				else
					$delete_art_link = '';
				$cells[] = td( $delete_art_link . $ID . br . txpspecialchars($names) , '' , 'id' );

				#
				#	Compose the rest of the row - one cell per translation...
				#
				foreach( $langs as $lang )
					{
					if( !array_key_exists( $lang , $members ) )
						{
						if( $lang === $default_lang )
							$cells[] = td( gTxt('default') . gTxt('l10n-missing') , $w , 'warning' );
						else
							$cells[] = td( '' , $w , 'empty' );
						}
					else
						{
						#
						#	Ok, there is a translation for this language so...
						#
						$tdclass = '';
						$msg = '';
						$id = $members[$lang];

						#
						#	Get the details for this translation
						#
						$i = $index[$lang];
 						$details = $translations[$i];
						$author  = $details['AuthorID'];
						$status_no = $details['Status'];
						if( $status_no >= 4 )
							$num_visible++;

						$tdclass .= 'status_'.$status_no;
						$status = !empty($status_no) ? $this->statuses[$status_no] : '';
						if( empty($details['Title']) )
							$title = '<em>'.eLink('article', 'edit', 'ID', $id, gTxt('untitled')).'</em>';
						else
							$title = eLink('article', 'edit', 'ID', $id, $details['Title'] );

						#
						#	Check for consistency with the group data...
						#	Deprecated?
						if( $details[L10N_COL_LANG] != $lang )
							{
							$tdclass .= 'warning';
							$msg = br . strong( gTxt('l10n-warn_lang_mismatch') ) . br . "Art[$lang] : tsl[{$details[L10N_COL_LANG]}]";
							}

						#
						#	Grab the section and check for consistency across the row...
						#
						$section = $details['Section'];
						$sections[$section] = $ID;

						#
						#	Make a clone link if possible...
						#
						if( !$can_clone or $all_translations_present )
							$clone_link = '';
						else
							$clone_link = 	'<a href="' . $this->parent->url( array('page'=>$page,'step'=>'start_clone','rendition'=>$id,'article'=>$ID), true ) .
											'" class="clone-link" title="' . gTxt('l10n-clone') . '"><img src="txp_img/l10n_clone.png" /></a>';

						#
						#	Make the delete link...
						#
						if( $can_delete )
							$delete_trans_link = 	'<a href="' . $this->parent->url( array('page'=>$page,'step'=>'delete_rendition', 'rendition'=>$id), true ) .
													'" title="' . gTxt('delete') .
													'" class="delete-link" onclick="return verify(\'' .
													doSlash(gTxt('confirm_delete_popup')) .
													'\')"><img src="txp_img/l10n_delete.png" /></a>';
						else
							$delete_trans_link = '';

						$content = 	$delete_trans_link . strong( $title ) . br . small($section . ' &#8212; ' . $author) .
									$msg . $clone_link;
						$cells[] = td( $content , $w , trim($tdclass) );
						$counts[$lang] += 1;
						}
					}


				#
				#	Tag articles which are fully visible or have warnings...
				#
				if( count( $sections ) != 1 )
					{
					$trclass .= ' warning';
					$cells[0] = td( $ID . br . gTxt('l10n-warn_section_mismatch') , $w , 'id' );
					}
				else if( $num_visible == $full_lang_count )
					{
					$trclass .= ' fully_visible';
					}
				$trclass .= (0 == ($counts['article'] & 0x01)) ? '' : ' odd';
				$trclass = trim( $trclass );
				if( !empty( $trclass ) )
					$trclass = ' class="' . $trclass . '"';
				$css_id = ' id="article_' . $ID . '"';
				$body[] = n.tr( n.join('' , $cells) , $css_id . $trclass );
				}
			}

		#
		#	Show the counts for the page...
		#
		$show_legend = ( '1' == $this->pref('l10n-show_legends') ) ? true : false;

		if( $show_legend )
			{
			$cells = array();
			$cells[] = td( $counts['article'] , '' , 'id count' );
			foreach( $langs as $lang )
				{
				$cells[] = td( $counts[$lang] , '' , 'count' );
				}
			$body[] = n.tr( n.join('' , $cells) );

			#
			#	Show the table legend...
			#
			$cells = array();
			$l[] = $this->add_legend_item( 'status_1' , $this->statuses[1] );
			$l[] = $this->add_legend_item( 'status_2' , $this->statuses[2] . '/'. gTxt('none') );
			$l[] = $this->add_legend_item( 'status_3' , $this->statuses[3] );
			$l[] = $this->add_legend_item( 'status_4' , $this->statuses[4] );
			$l[] = $this->add_legend_item( 'status_5' , $this->statuses[5] );
			$l[] = br.br;
			$l[] = $this->add_legend_item( 'fully_visible' , gTxt('l10n-legend_fully_visible') );
			$l[] = $this->add_legend_item( 'warning' , gTxt('l10n-legend_warning') );
			if( $can_delete or $can_clone )
				$l[] = br.br;
			if( $can_delete )
				{
				$l[] = t.tag( '<img src="txp_img/l10n_delete.png" />' , 'dt' ).n;
				$l[] = t.tag( gTxt('delete') , 'dd' ).n;
				}
			if( $can_clone )
				{
				$l[] = t.tag( '<img src="txp_img/l10n_clone.png" />' , 'dt' ).n;
				$l[] = t.tag( gTxt('l10n-clone') , 'dd' ).n;
				}
			$l = tag( n.join('',$l) , 'dl' );
			$cells[] = tdcs( $l , $full_lang_count+1, '' , 'legend' );
			$body[] = n.tr( n.join('' , $cells) );
			}

		$o[] = tag( join( '' , $body) , 'tbody' );
		$o[] = endTable();
		$o[] = n.nav_form( $event, $page, $numPages, '', '', '', '');
		$o[] = n.pageby_form( $event, max(@$article_list_pageby, 15) );

		echo join( '' , $o );
		}

	function render_start_clone()
		{
		$vars = array( 'rendition' , 'page' );
		extract( gpsa( $vars ) );

		if( empty( $rendition ) )	# Empty input
			{
			$this->clone_by_id = gTxt('l10n-empty_rend_id');
			return $this->render_article_table();
			}

		$rendition = (int)$rendition;
		if( 0 == $rendition )	# Invalid string to int conversion
			{
			$this->clone_by_id = gTxt('l10n-invalid_rend_id');
			return $this->render_article_table();
			}

		#
		#	Get the un-translated languages for the article that owns this rendition...
		#
		$details = safe_row( '*' , 'textpattern' , "`ID`='$rendition'" );

		if( empty( $details ) )		# No matching value
			{
			$this->clone_by_id = gTxt('l10n-no_rend_matching_id');
			return $this->render_article_table();
			}

		$title   = $details['Title'];
		$article = $details[L10N_COL_GROUP];
		$author  = $details['AuthorID'];
		$to_do = MLPArticles::get_remaining_langs( $article );
		$count = count( $to_do );

		if( 0 == $count )
			{
			$this->clone_by_id = gTxt('l10n-article_fully_populated' , array('{title}'=>$title , '{article}'=>$article) );
			return $this->render_article_table();
			}

		#
		#	Get the list of possible authors...
		#
		$assign_authors = false;
		$authors = safe_column('name', 'txp_users', "privs not in(0,6)");
		if( $authors )
			$assign_authors = true;

		$o[] = startTable( /*id*/ 'l10n_clone_table' , /*align*/ '' , /*class*/ '' , /*padding*/ '5px' );
		$o[] = '<caption><strong>'.gTxt('l10n-clone_and_translate' , array( '{article}'=>$title ) ).'</strong></caption>';

		#
		#	If there is only one available unused language, check it by default.
		#
		$checked = '';
		if( $count === 1 )
			$checked = 'checked';

		#
		#	Build the thead...
		#
		$thead[] = hCell( gTxt('l10n-into').'&#8230;' );
		$thead[] = hCell( gTxt('l10n-by').'&#8230;' );
		$o[] = n .  tr( join( '' , $thead ) );

		#
		#	Build the clone selection form...
		#
		foreach( $to_do as $lang=>$name )
			{
			$r = td(	'<input type="checkbox" class="checkbox" '.$checked.' value="'.$lang.'" name="'.$lang.'" id="'.$lang.'"/>' .
						'<label for="'.$lang.'">'.$name.'</label>' );
			$r .= td( stripslashes(selectInput($lang.'-AuthorID' , $authors , $author , false )) );
			$f[] =	tr( $r );
			}

		#
		#	Submit and hidden entries...
		#
		$s = '<input type="submit" value="'.gTxt('l10n-clone').'" class="publish" />' . n;
		$s .= eInput( $this->parent->event );
		$s .= sInput( 'clone' );
		$s .= hInput( 'rendition' , $rendition );
		$s .= hInput( 'page' , $page );

		$f[] = tr( tdrs( $s , 2 ) );

		$o[] = tag( form( join( '' , $f )) , 'tbody' );
		$o[] = endTable();

		echo join( '' , $o );
		}

	function render_start_clone_all_from()
		{
		$vars = array( 'target_lang' , 'page' );
		extract( gpsa( $vars ) );

		if( empty( $target_lang ) )	# Empty target language...
			{
			$this->clone_by_id = gTxt('l10n-no_langs_selected');
			return $this->render_article_table();
			}

		#
		#	Init language related vars...
		#
		$langs = MLPLanguageHandler::get_site_langs();
		$full_lang_count = count( $langs );
		$default_lang = MLPLanguageHandler::get_site_default_lang();

		# remove the target from the src list...
		$langs = array_diff( $langs , array( $target_lang ) );

		# remove empty source languages from the src list...
		$langs = array_intersect( $langs , array_keys( $this->get_rendition_counts() ) );

		$langs = MLPLanguageHandler::do_fleshout_names( $langs );
		$name = MLPLanguageHandler::get_native_name_of_lang($target_lang);

		$o[] = startTable( /*id*/ 'l10n_clone_all_table' , /*align*/ '' , /*class*/ '' , /*padding*/ '5px' );
		$o[] = '<caption><strong>'.gTxt('l10n-clone_all_from' , array( '{lang}'=>$name.' ['.$target_lang.'] ' ) ).'</strong></caption>';

		$s = selectInput( 'clone_from_language' , $langs , $default_lang , '' , '' ).' ';
		$s .= '<input type="submit" value="'.gTxt('l10n-clone').'" class="publish" />' . n;
		$s .= eInput( $this->parent->event );
		$s .= sInput( 'clone_all_from' );
		$s .= hInput( 'page' , $page );
		$s .= hInput( 'tab' , 'article' );
		$s .= hInput( 'target_lang' , $target_lang );

		$f[] = tr( tdrs( $s , 1 ) );
		$verify = ' verify(\'' . 	doSlash(gTxt('l10n-verify_clone_all' , array('{targ_lang}'=>$name.' ['.$target_lang.']') )) . '\')';

		$o[] = tag( form( join( '' , $f ) , '' , $verify ) , 'tbody' );
		$o[] = endTable();

		echo join( '' , $o );
		}

	function add_legend_item( $id , $text )
		{
		$r[] = t.tag( '&#160;' , 'dt' , " class=\"$id\"" ).n;
		$r[] = t.tag( $text , 'dd' ).n;
		return join( '' , $r );
		}

	}


class MLPWizView extends GBPWizardTabView
	{
	var $permissions = '1';
	function get_steps()
		{
		#
		#	Override this method in derived classes to return the appropriate setup/cleanup steps.
		#
		$steps = array(
			'1' => array(
				'setup'   => gTxt('l10n-setup_1_main',array('{field}'=>L10N_COL_OWNER,'{table}'=>'txp_lang')),
				'cleanup' => gTxt('l10n-drop_field',array('{field}'=>L10N_COL_OWNER,'{table}'=>'txp_lang'))
				),
			'2' => array(
				'setup'   => gTxt('l10n-setup_2_main'),
				'cleanup' => gTxt('l10n-clean_2_main')
				),
			'3' => array(
				'setup'   => gTxt('l10n-setup_3_main' , array('{lang}'=>L10N_COL_LANG, '{group}'=>L10N_COL_GROUP) ),
				),
			'3a'=> array(
				'cleanup' => gTxt('l10n-clean_3a_main' , array('{lang}'=>L10N_COL_LANG, '{group}'=>L10N_COL_GROUP)).br.gTxt('l10n-clean_3a_main_2'), 'optional' => true , 'checked'=>0
				),
			'4' => array(
				'setup'   => gTxt('l10n-setup_4_main'),
				),
			'4a'=> array(
				'cleanup' => gTxt('l10n-clean_4a_main'), 'optional'=>true, 'checked'=>0
				),
			'5' => array(
				'setup'   => gTxt('l10n-op_table',array('{op}'=>'Add' ,'{table}'=>L10N_ARTICLES_TABLE)),
				'cleanup' => gTxt('l10n-op_table',array('{op}'=>'Drop','{table}'=>L10N_ARTICLES_TABLE)),
				),
			'6' => array(
				'setup'   => gTxt('l10n-setup_6_main',array( '{count}'=>'all existing')),
				),
			'7' => array(
				'setup'   => gTxt('l10n-op_tables',array('{op}'=>'Add' ,'{tables}'=>'per-language l10n_textpattern')),
				'cleanup' => gTxt('l10n-op_tables',array('{op}'=>'Drop','{tables}'=>'per-language l10n_textpattern')),
				),
			'8' => array(
				'cleanup' => gTxt('l10n-clean_8_main')
				),
			'10'=> array(
				'setup'   => gTxt('l10n-comment_op',array('{op}'=>'Clear')),
				'cleanup' => gTxt('l10n-comment_op',array('{op}'=>'Restore')),
				),
			'11'=> array(
				'setup'   => gTxt('l10n-setup_11_main') , 'optional'=>true, 'checked'=>1
				),
			);

		#
		#	Add extra installation step if the site slogan is still the default...
		#
		global $prefs;
		if( @$prefs['site_slogan'] === 'My pithy slogan' )
			{
			$steps['12'] = array( 'setup' => gTxt('l10n-setup_12_main') , 'optional'=>true, 'checked'=>1 );
			}

		#
		#	If there are any of Graeme's gbp_ tags hanging around from v0.5 of gbp_l10n then upgrade them to the new ones...
		#
		$page_data = MLPTableManager::walk_table_find( 'txp_page' , 'name' , 'user_html' , 'gbp_localize' , true );
		$form_data = MLPTableManager::walk_table_find( 'txp_form' , 'name' , 'Form' , 'gbp_localize' , true );
		$data = array_merge( $page_data, $form_data );
		if( !empty( $data ) )
			{
			$steps['13'] = array( 'setup' => gTxt('l10n-setup_13_main') );
			}

		return $steps;
		}

	function installed()
		{
		return Txp::get('\Netcarver\MLP\Kickstart')->l10n_installed();
		}

	function get_strings( $language = '' )
		{
		global $l10n_default_strings_perm;
		global $l10n_default_strings;
		global $textarray;

		#
		#	Get base class defaults...
		#
		$defaults = GBPWizardTabView::get_strings( $language );

		#
		#	Merge our wizard strings...
		#
		foreach( $defaults as $k=>$v )
			{
			list( $prefix , $key ) = explode( L10N_SEP , $k );
			$key = L10N_NAME.L10N_SEP.$key;
			$key = strtolower($key);

			if( isset($textarray[$key]) )
				{
				$v = $textarray[$key];
				}
			elseif( isset( $l10n_default_strings_perm[$key] ) )
				{
				$v = $l10n_default_strings_perm[$key];
				}
			elseif( isset( $l10n_default_strings[$key] ) )
				{
				$v = $l10n_default_strings[$key];
				}

			$merged[$k] = $v;
			}

		return $merged;
		}

	function get_required_versions()
		{
		global $prefs, $DB;

		$can_setup_cleanup = (gps( 'skip-wiz-privilege-check' )) ? true : $this->can_install();

		$tests = array(
					'TxP' => array(
						'current'	=> $prefs['version'] ,
						'min'		=> '4.5.0' ,
						),
					'PHP' => array(
						'current'	=> PHP_VERSION ,
						'min'		=> '5.2.0' ,
						),
					'MySQL'  => array(
						'current'	=> $DB->version ,
						'min'		=> '4.1' ,
						),
					);

		if( true !== $can_setup_cleanup )
			{
			$req_privs  = $this->get_req_privs();

			$list = '';
			foreach( $req_privs as $privs )
				$list .= join( ', ' , $privs ) . ', ';
			$list = trim( $list , ', ' );

			$tests['MySQL Privileges'] = array(
				'current'	=> $can_setup_cleanup, # list of missing privileges.
				'min'		=> $list ,	# list of required privs.

				# Setup a custom handler for this test...
				'custom_handler'=> array( &$this , '_report_privileges' ),
				);
			}

		return $tests;
		}
	function _report_privileges( $name , $data )
		{
		global $txpcfg;

		$db		= $txpcfg['db'];
		$host	= $txpcfg['host'];
		$user	= $txpcfg['user'];

		$subs = array( '{name}'=>$name , '{db}'=>$db , '{host}'=>$host , '{user}'=>$user , '{missing}'=>$data['current'] , '{privs}'=>$data['min'] );
		$p = gTxt( 'l10n-report_privs' , $subs );

		$f1[] = eInput( 'l10n' );
		$f1[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-try_again'), '').'</span>';
		$f1 = form( join( br . n , $f1 ) );

		$f2[] = eInput( 'l10n' );
		$f2[] = hInput( 'debugwiz' , '1' );
		$f2[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-try_again') . ' ' . gTxt('l10n-show_debug'), '').'</span>';
		$f2 = form( join( br . n , $f2 ) );

		$f3[] = eInput( 'l10n' );
		$f3[] = hInput( 'skip-wiz-privilege-check' , '1' );
		$f3[] = '<span class="l10n_form_submit">'.fInput('submit', '', gTxt('l10n-skip_priv_check'), '').'</span>' . br . n;
		$f3 = form( join( br . n , $f3 ) );

		return $p . $f1 . $f2 . $f3;
		}
	function get_req_privs()
		{
		static $req_privs	= array(
			'setup'		=> array( 'SELECT' , 'INSERT' , 'UPDATE' , 'DELETE' , 'CREATE' , 'CREATE TEMPORARY TABLES' , 'ALTER' , 'LOCK TABLES', 'INDEX' ),
			'cleanup'	=> array( 'DROP' )
			);
		return $req_privs;
		}
	function check_row( $row )
		{
		#
		#	Output: (bool)TRUE	=> Meets minimum spec for privs.
		#			(string) 	=> List of missing privs.
		#
		//echo br, "Processing row: $row";

		global $txpcfg;
		$user		= $txpcfg['user'];
		//$db			= $txpcfg['db'];
		$result		= true;
		$outlist	= '';
		$fails 		= array( 'setup' => array() , 'cleanup' => array() );
		$req_privs  = $this->get_req_privs();

		if( false === strpos( $row , 'GRANT ALL PRIVILEGES' ) )
			{
			#
			#	Strip off 'GRANT' and anything after the 'ON ...'
			#
			$pos = strpos( $row , ' ON' );
			if( false !== $pos && $pos > 6)
				{
				$row = substr( $row , 6, $pos - 6);
				//echo br, "Processing row: $row";

				#
				#	Explode the privs on the ','...
				#
				$privs = explode( ', ' , $row );
				//echo br , dmp( $privs );

				#
				#	Build missing list if there are less than "all" privileges...
				#
				foreach( $req_privs as $type=>$list )
					{
					foreach( $list as $priv )
						{
						//echo br , "Checking for priv: `$type`:`$priv`";
						$has_priv = in_array( $priv , $privs );
						if( !$has_priv )
							$fails[$type][] = $priv;
						}
					}
				}
			}


		#
		#	Process results...
		#
		foreach( $fails as $type=>$list )
			{
			if( !empty( $list ) )
				{
				$list = join( ', ' , $list );
				//echo br, "User '$user' needs these privilages to $type the MLP Pack: $list";
				$result = false;
				$outlist .= $list.', ';
				}
			}

		if( !$result )
			$result = trim( $outlist , ', ' );
		return $result;
		}
	function strip_pws( $rows )
		{
		#
		#	Remove password hashes from debug output...
		#
		$result = array();

		if( !empty( $rows ) )
			{
			$pattern = "/PASSWORD \'(.*)\'/";
			foreach( $rows as $row )
				{
				$matches = array();
				$count = preg_match( $pattern , $row , $matches );
				if( $count === 1 )
					$row = strtr( $row , array( $matches[1] => '****************' ) );

				$result[] = $row;
				}
			}

		return $result;
		}
	function can_install()
		{
		global $txpcfg, $DB;
		$host  = $txpcfg['host'];
		$user  = $txpcfg['user'];
		$version = $DB->version;
		$matched = false;

		$debug = gps( 'debugwiz' );
		$debug = (!empty($debug));

		#
		#	Make sure we escape the MySQL special name characters...
		#
		$db_lean = $txpcfg['db'];
		$db = strtr( $db_lean , array( '_' => '\_' , '%' => '\%' ) );

		if( $debug ) echo br , "Testing for privs to DB:`$db` on Server:$host, v:$version. Connected using user: $user.";

		#
		#	Test the privilages of the user used to connect to the TxP DB...
		#
		if( $user === 'root' )
			{
			if( $debug ) echo br , 'Using root - skipping privileges checking.';
			return true;
			}

		#
		#	This should work for all versions of MySQL...
		#
		$sql  = "SHOW GRANTS FOR '$user'@'$host';";
		if( $debug )
			$rows = getThings( $sql , 1 );
		else
			$rows = getThings( $sql );

		#
		#	But, if it failed then retry using a different command (if possible)...
		#
		if( empty( $rows ) )
			{
			if( $debug ) echo br , "Initial SHOW GRANTS query failed";
			if( version_compare( $version, '4.1.2' , '>=') )
				{
				$sql  = "SHOW GRANTS;";
				if( $debug )
					{
					echo ', re-trying.';
					$rows = getThings( $sql , 1 );
					}
				else
					$rows = getThings( $sql );
				}
			}

		if( !empty( $rows ) )
			{
			$rows = $this->strip_pws( $rows );
			if( $debug ) echo dmp( $rows );
			$global_row = '';

			foreach( $rows as $row )
				{
				if( false !== strpos( $row , 'GRANT USAGE' ) )
					continue;

				if( false !== strpos( $row , 'ON *.*' ) )
					{
					$global_row = $row;
					if( $debug ) echo br, "Storing global row for processing later.";
					}
				elseif( false !== strpos( $row , "ON `$db`" ) OR false !== strpos( $row , "ON `$db_lean`" ) )
					{
					$matched = $this->check_row( $row );
					if( $matched === true )
						break;
					}
				elseif( false !== strpos( $row , '%' ) )
					{
					#
					#	Check for wildcard DB cases in the grants list.
					#
					$matches = array();
					$pattern = "/ ON `(.*)`/";

					#
					#	Extract the DB name...
					#
					if( $debug ) echo br , "Extracting DB name pattern [$pattern] from $row.";
					$count = preg_match( $pattern , $row , $matches );
					if( $count !== 1 )
						{
						if( $debug ) echo br , "Could not match DB name pattern.";
						continue;
						}
					$name = $matches[1];
					if( $debug ) echo br , "Matched db name: [$name] - ";

					#
					#	Get start of the name...
					#
					$s = strpos( $name , '%' );
					$name = substr( $name , 0 , $s );
					if( $debug ) echo "Stripped down to [$name] - ";
					$len = strlen( $name );

					#
					#	Strip escape sequences...
					#
					if( $len > 0 )
						{
						$name = strtr( $name , array( "\\\\"=>'' , "\\"=>'' ));
						if( $debug ) echo "Stripped down to [$name] - ";
						}

					#
					#	Prepare the comparison string...
					#
					$len = strlen( $name );
					$cmp = substr( $db_lean , 0 , $len );

					#
					#	Compare to the db name we are testing for...
					#
					if( $debug ) echo "Comparing [$name] with [$cmp] ... ";
					if( $name === $cmp )
						{
						if( $debug ) echo "matched! Checking privs as usual... ";
						$matched = $this->check_row( $row );
						if( $matched === true )
							break;
						}
					}
				}

			if( ($matched !== true) and !empty( $global_row ) )
				{
				if( $debug ) echo br,"Processing global row: $global_row";
				$matched = $this->check_row( $global_row );
				}
			}
		else
			{
			#
			#	The SHOW GRANTS query failed. So we cannot check anything using that.
			# Instead, allow installation to continue. Should we show a warning to the user
			# At the head of the setup wizard?
			#
			$matched = true;
			if( $debug ) echo br , 'Could not determine your user grants on the database; will continue anyway.';
			}

		if( $matched === false )
			{
			$matched = gTxt( 'l10n-missing_all_privs' , array( '{escaped_db}' => $db , '{db}'=>$db_lean ) );
			}

		if( $debug ) echo br,br,'Matched: ',var_dump($matched);

		return $matched;
		}

	function setup_1()		# Extend the `txp_lang.data` field from TINYTEXT to TEXT and add the `l10n_owner` field
		{
		//$db_charsetcollation = _l10n_get_db_charsetcollation();

		$this->add_report_item( gTxt('l10n-setup_1_title') );

		$sql = " CHANGE `data` `data` TEXT NULL";
		$ok = safe_alter( 'txp_lang' , $sql );
		$this->add_report_item( gTxt('l10n-setup_1_extend') , $ok , true );

		$sql = "ADD `".L10N_COL_OWNER."` TINYTEXT NULL AFTER `data`";
		$ok = safe_alter( 'txp_lang' , $sql );
		$this->add_report_item( gTxt('l10n-add_field',array('{field}'=>L10N_COL_OWNER ,'{table}'=>'txp_lang')) , $ok , true );
		}

	function setup_2()		# Add strings...
		{
		global $l10n_wiz_upgrade , $l10n_default_strings_lang , $l10n_default_strings_perm, $l10n_default_strings , $prefs;

		#
		#	First things first, try to set the installation langs...
		#
		$gbp_l10n_key = 'gbp_admin_library_languages';
		$l10n_wiz_upgrade = array();
		$prev_gbp_l10n_langs = array();
		if( isset( $prefs[$gbp_l10n_key] ) )
			$prev_gbp_l10n_langs = $prefs[$gbp_l10n_key];

		$prev_l10n_langs = $this->parent->preferences['l10n-languages']['value'];

		if( !empty( $prev_gbp_l10n_langs ) )		# upgrade from gbp_l10n, use the language setting from that plugin.
			{
			#
			#	Expand all two digit codes...
			#
			$langs = array();
			foreach( $prev_gbp_l10n_langs as $lang )
				{
				$lang = trim( $lang );
				$len = strlen( $lang );
				if( 2 === $len )
					{
					$ok = MLPLanguageHandler::iso_639_langs( $lang , 'valid_short' );
					if( $ok )
						$lang = MLPLanguageHandler::iso_639_langs( $lang , 'short2long' );
					}
				elseif( 5 === $len )
					{
					$ok = MLPLanguageHandler::iso_639_langs( $lang , 'valid_long' );
					}
				if( $ok )
					$langs[] = $lang;
				}

			$l10n_wiz_upgrade = $langs;
			$languages = $langs;
			safe_delete( 'txp_prefs' , "`name`='$gbp_l10n_key'" );
			}
		elseif( !empty($prev_l10n_langs) )			# reinstall, keep old language settings.
			$languages = $prev_l10n_langs;
		else										# fresh install, use all currently installed languages.
			$languages = MLPLanguageHandler::get_installation_langs();

		$this->set_preference('l10n-languages', $languages);
		$this->add_report_item( gTxt( 'l10n-setup_2_langs' , array( '{langs}' => join( ', ' , $languages ) ) ) , true );

		#
		#	Reset the session variable (in case of a language switch and then a reinstall)...
		#
		Txp::get('\Netcarver\MLP\Kickstart')->l10n_session_start();
		$temp = LANG;
		$tmp = substr( $temp , 0 , 2 );
		if( !empty($temp) )
			{
			$_SESSION['l10n_admin_short_lang'] = $tmp;
			$_SESSION['l10n_admin_long_lang']  = $temp;
			}


		# Adds the strings this class needs. These lines makes them editable via the "plugins" string tab.
		$l10n_default_strings = array_merge( $l10n_default_strings , $l10n_default_strings_perm );
		$ok = MLPStrings::insert_strings( $this->parent->strings_prefix , $l10n_default_strings , $l10n_default_strings_lang , 'admin' , 'l10n' );
		$this->add_report_item( gTxt('l10n-setup_2_main') , $ok );

		#
		#	Also add any strings we can for other installation languages...
		#
		$langs = MLPLanguageHandler::get_installation_langs();
		if( empty( $langs ) )
			return;

		$tmp_lang     = $l10n_default_strings_lang;
		$tmp_str_perm = $l10n_default_strings_perm;
		$tmp_str_def  = $l10n_default_strings;

		foreach( $langs as $lang )
			{
			if( $lang === $tmp_lang )
				continue;

			$merged = array();
			$file_name = txpath.DS.'lib'.DS.'l10n_'.$lang.'_strings.php';
			if( is_readable($file_name) )
				{
				include_once $file_name;
				$merged = array_merge( $l10n_default_strings , $l10n_default_strings_perm );
				MLPStrings::insert_strings( $this->parent->strings_prefix , $merged , $l10n_default_strings_lang , 'admin' , 'l10n' , true );
				}
			}
		if( isset( $merged ) )
			unset( $merged );

		#
		#	Restore values...
		#
		$l10n_default_strings_lang	= $tmp_lang;
		$l10n_default_strings_perm	= $tmp_str_perm;
		$l10n_default_strings		= $tmp_str_def;
		}

	function setup_3()		# Extend the textpattern table...
		{
		//$db_charsetcollation = _l10n_get_db_charsetcollation();
		$sql = array();

		$desc = 'COLUMNS';
		$result = safe_show( $desc , 'textpattern' );
		$lang_found  = false;
		$article_id_found = false;

		if( count( $result ) )
			{
			foreach( $result as $r )
				{
				if( !$lang_found and $r['Field'] === L10N_COL_LANG )
					$lang_found = true;
				if( !$article_id_found and $r['Field'] === L10N_COL_GROUP )
					$article_id_found = true;

				if( $article_id_found and $lang_found )
					break;
				}
			}

		if( !$lang_found )
			$sql[] = ' ADD `'.L10N_COL_LANG.'` VARCHAR( 8 ) NOT NULL DEFAULT \'-\' AFTER `LastModID` , ADD INDEX(`'.L10N_COL_LANG.'`)';

		if( !$article_id_found )
			$sql[] = ' ADD `'.L10N_COL_GROUP.'` INT( 11 ) NOT NULL DEFAULT \'0\' AFTER `'.L10N_COL_LANG.'` , ADD INDEX(`'.L10N_COL_GROUP.'`)';

		$this->add_report_item( gTxt('l10n-setup_3_title') );
		if( !empty( $sql ) )
			{
			$ok = safe_alter( 'textpattern' , join(',', $sql) );

			if( $lang_found )
				$this->add_report_item( gTxt('l10n-skip_field',array('{field}'=>L10N_COL_LANG,'{table}'=>'textpattern')) , $ok , true );
			else
				$this->add_report_item( gTxt('l10n-add_field',array('{field}' =>L10N_COL_LANG,'{table}'=>'textpattern')) , $ok , true );
			if( $article_id_found )
				$this->add_report_item( gTxt('l10n-skip_field',array('{field}'=>L10N_COL_GROUP,'{table}'=>'textpattern')) , $ok , true );
			else
				$this->add_report_item( gTxt('l10n-add_field',array('{field}' =>L10N_COL_GROUP,'{table}'=>'textpattern')) , $ok , true );
			}
		else
			{
			$this->add_report_item( gTxt('l10n-skip_field',array('{field}'=>L10N_COL_LANG ,'{table}'=>'textpattern')) , true , true );
			$this->add_report_item( gTxt('l10n-skip_field',array('{field}'=>L10N_COL_GROUP,'{table}'=>'textpattern')) , true , true );
			}
		}
	function setup_4() 		# Localise fields in content tables
		{
		$this->add_report_item( gTxt('l10n-setup_4_main').'&#8230;' );
		_l10n_walk_mappings( array( &$this , 'setup_4_cb' ) );
		}
	function setup_4_cb( $table , $field , $attributes )
		{
		$langs      = MLPLanguageHandler::get_site_langs();
		$default    = MLPLanguageHandler::get_site_default_lang();

		$extend_all = array( 'txp_category' , 'txp_section' );
		$do_all     = in_array( $table , $extend_all );

		$safe_table = safe_pfx( $table );
		foreach( $langs as $lang )
			{
			$f = _l10n_make_field_name( $field , $lang );
			$exists = getThing( "SHOW COLUMNS FROM $safe_table LIKE '$f'" );
			if( $exists )
				{
				$this->add_report_item( gTxt('l10n-skip_field',array('{field}'=>$f,'{table}'=>$table)) , true , true );
				continue;
				}

			$sql = "ADD `$f` ".$attributes['sql'];
			$ok = safe_alter( $table , $sql );
			$this->add_report_item( gTxt('l10n-add_field',array('{field}'=>$f,'{table}'=>$table)) , $ok , true );

			if( !$ok )
				continue;

			if( $do_all or $lang===$default )
				{
				$sql = "UPDATE $safe_table SET `$f`=`$field` WHERE `$f`=''";
				$ok = safe_query( $sql );

				if( $lang === $default )
					$this->add_report_item( gTxt('l10n-copy_defaults',array('{field}'=>$f,'{table}'=>$table)) , $ok , true );
				}
			}
		}
	function setup_5()		# Create the articles table
		{
		$ok = MLPArticles::create_table();
		$this->add_report_item( gTxt('l10n-op_table',array('{op}'=>'Add' ,'{table}'=>L10N_ARTICLES_TABLE)) , $ok );
		}

	function setup_6()		# Run the import routine selected by the user from the install wizard tab...
		{
		global $l10n_wiz_upgrade;

		$ok = false;
		if( !empty($l10n_wiz_upgrade) )
			$this->_upgrade_gbp_l10n();

		$ok = $this->_import_fixed_lang();
		$this->add_report_item( ($ok===true) ? gTxt('l10n-setup_6_main',array( '{count}'=>'all existing')) : gTxt('l10n-setup_6_main',array( '{count}'=>$ok))  , true );
		}

	function setup_7()		# Create per-language copies of textpattern table
		{
		# Create the first instances of the language tables as straight copies of the existing
		# textpattern table so users on the public side still see everything until we start editing
		# articles.
		global $DB;
		$langs = $this->pref('l10n-languages');
		$this->add_report_item( gTxt('l10n-op_tables',array('{op}'=>'Add' ,'{tables}'=>'per-language article')).'&#8230;' );
		foreach( $langs as $lang )
			{
			$code       = MLPLanguageHandler::compact_code( $lang );
			$table_name = _l10n_make_textpattern_name( $code );
			$indexes = "(PRIMARY KEY  (`ID`), KEY `categories_idx` (`Category1`(10),`Category2`(10)), KEY `Posted` (`Posted`), FULLTEXT KEY `searching` (`Title`,`Body`))";

			$sql = "create table `".PFX."$table_name` $indexes ENGINE=MyISAM select * from `".PFX."textpattern` where `".L10N_COL_LANG."`='$lang'";
			$ok = safe_query( $sql );
			if (mysqli_error($DB->link) == "Table '".PFX."$table_name' already exists")
				$ok = 'skipped';

			$this->add_report_item( gTxt('l10n-op_table',array('{op}'=>'Add' ,'{table}'=>MLPLanguageHandler::get_native_name_of_lang( $lang ).' ['.$table_name.']')) , $ok , true );
			}
		}

	function setup_10()		# Blank out the comments default invite
		{
		$default = @$GLOBALS['prefs']['comments_default_invite'];
		$ok = set_pref( 'comments_default_invite', '', 'comments', 0 );
		$this->add_report_item( gTxt('l10n-comment_op',array('{op}'=>'Clear')) , $ok );
		}

	function setup_11()		# Optionally insert l10n tags into the pages/forms.
		{
		static $default_md5s = array (
			'txp_page'     => array (        #Txp 4.04                            #Txp 4.05                           #txp 4.07 -- Add new entries as required.
				'default'          => array( 'c9797b38809d64cb8f5d33ad1f62a144',	'fb13b4120c263898cd33bf82b51fd896', 'fb7f068c1627f25f924ff40d09bc0fc8' ),
				'archive'          => array( 'c9797b38809d64cb8f5d33ad1f62a144',	'fb13b4120c263898cd33bf82b51fd896', '31f30171455dd5a9d96aeb5e0c86f8a1' ),
				'error_default'    => array( '909ada7984ebdc41a86f74861d6a0944',	'faca32c1d818cc017e1389124742fb74', '056b8532e6c875e6a9e4a7bf6aeded95' )
				),
			);

		#	Determine if we are running a default installation...
		$table = 'txp_page';
		$pages = safe_rows( "name, user_html as data", $table, '1=1' ) ;
		$skipped = '';
		foreach( $pages as $page )
			{
			extract( $page );

			$checksum = md5( $data );
			$md5_array = is_array( @$default_md5s[$table][$name]) ? $default_md5s[$table][$name] : array() ;
			if( in_array( $checksum , $md5_array ) )
				{
				$f = '<div id="sidebar-1">';
				$err404 = ($name==='error_default') ? 'on404="1" ' : '' ;
				$r = "<txp:l10n_lang_list $err404/>";
				$data = str_replace( $f , $f.n.t.$r , $data );
				}

			$f = ' lang="en"';
			$r = ' lang="<txp:l10n_get_lang/>"';
			$data = str_replace( $f , $r , $data );
			$f = ' xml:lang="en"';
			$r = ' xml:lang="<txp:l10n_get_lang type="long" />"';
			$data = str_replace( $f , $r , $data );

			$f = '<body>';
			$r = '<body dir="<txp:l10n_get_lang_dir />" >';
			$data = str_replace( $f , $r , $data );

			#	Save it...
			$name = doSlash( $name );
			$data = doSlash( $data );
			safe_update( $table , "`user_html`='$data'", "`name`='$name'" );
			}

		$this->add_report_item( gTxt('l10n-setup_11_main').$skipped , true );
		}

	function setup_12()		# Configure site slogan to reflect the browse language...
		{
		$langs = MLPLanguageHandler::get_site_langs();
		foreach( $langs as $code )
			{
			$langname = MLPLanguageHandler::get_native_name_of_lang( $code );
			MLPStrings::store_translation_of_string( 'snip-site_slogan' , 'public' , $code , $langname );
			}
		$this->add_report_item( gTxt('l10n-setup_12_main') , true );
		}

	function setup_13()		# Remove legacy gbp_localize tags...
		{
		$pdata = MLPTableManager::walk_table_replace_simple( 'txp_page' , 'name' , 'user_html' , "<txp:gbp_localize>"  , '' );
		$pdata = MLPTableManager::walk_table_replace_simple( 'txp_page' , 'name' , 'user_html' , "</txp:gbp_localize>"  , '' );
		$fdata = MLPTableManager::walk_table_replace_simple( 'txp_form' , 'name' , 'Form'      , "<txp:gbp_localize>"  , '' );
		$fdata = MLPTableManager::walk_table_replace_simple( 'txp_form' , 'name' , 'Form'      , "</txp:gbp_localize>"  , '' );
		$this->add_report_item( gTxt('l10n-setup_13_main') , true );
		}

	function cleanup_1()	# Drop the txp_lang.l10n_owner field
		{
		$sql = 'DROP `'.L10N_COL_OWNER.'`';
		$ok = safe_alter( 'txp_lang' , $sql );
		$this->add_report_item( gTxt('l10n-drop_field',array('{field}'=>L10N_COL_OWNER, '{table}'=>'txp_lang')) , $ok );
		}

	function cleanup_2()	# Remove MLP strings/de-register plugins
		{
		global $l10n_default_strings_perm , $l10n_default_strings;

		# Remove the l10n strings...
		$this->add_report_item( gTxt('l10n-clean_2_main') );
		$temp = array_merge( $l10n_default_strings_perm , $l10n_default_strings );
		$ok = MLPStrings::remove_strings_by_name( $temp , 'admin' , 'l10n' );
		$this->add_report_item( ($ok===true)?gTxt('l10n-clean_2_remove_all'): gTxt('l10n-clean_2_remove_count',array('{count}'=>$ok)) , true , true );

		$rps = MLPStrings::discover_registered_plugins();
		if( count($rps) )
			{
			foreach($rps as $name=>$vals)
				{
				if( !is_array( $vals ) )
					continue;

				$ok = MLPStrings::unregister_plugin( $name );
				$this->add_report_item( gTxt( 'l10n-clean_2_unreg' , array( '{name}'=>$name ) ) , $ok , true );
				}
			}

		#
		#	Set TxP's language preference to the currently active admin language this user is viewing the site in...
		#
		safe_update('txp_prefs', "`val`='".doSlash(LANG)."'" , "`name`='language'");
		}

	function cleanup_3a()	# Drop lang/group from textpattern
		{
		$sql = 'drop `'.L10N_COL_LANG.'`, drop `'.L10N_COL_GROUP.'`';
		$ok = safe_alter( 'textpattern' , $sql );
		$this->add_report_item( gTxt('l10n-clean_3a_main' , array( '{lang}'=>L10N_COL_LANG , '{group}'=>L10N_COL_GROUP ) ) , $ok );
		}

	function cleanup_4a()	# Remove Localised content from tables
		{
		$this->add_report_item( gTxt('l10n-clean_4a_main').'&#8230;' );
		_l10n_walk_mappings( array( &$this , 'cleanup_4a_cb' ) );
		}
	function cleanup_4a_cb( $table , $field , $attributes )
		{
		$langs = MLPLanguageHandler::get_site_langs();
		foreach( $langs as $lang )
			{
			$f = _l10n_make_field_name( $field , $lang );
			$sql = "DROP `$f`";
			$ok = safe_alter( $table , $sql );
			$this->add_report_item( gTxt('l10n-drop_field',array('{field}'=>$f,'{table}'=>$table)) , $ok , true );
			}
		}
	function cleanup_5()	# Drop articles table
		{
		$ok = MLPArticles::destroy_table();
		$this->add_report_item( gTxt('l10n-op_table',array('{op}'=>'Drop','{table}'=>L10N_ARTICLES_TABLE)) , $ok );
		}

	function cleanup_7()	# Drop the per-language textpattern_XX tables...
		{
		global $prefs;
		$langs = $this->pref('l10n-languages');
		$this->add_report_item( gTxt('l10n-op_tables',array('{op}'=>'Drop','{tables}'=>'per-language article')).'&#8230;' );
		foreach( $langs as $lang )
			{
			$code  = MLPLanguageHandler::compact_code( $lang );
			$table_name = _l10n_make_textpattern_name( $code );
			$sql = 'drop table `'.PFX.$table_name.'`';
			$ok = safe_query( $sql );
			$this->add_report_item( gTxt('l10n-op_table',array('{op}'=>'Drop' ,'{table}'=>MLPLanguageHandler::get_native_name_of_lang( $lang ).' ['.$table_name.']')) , $ok , true );
			}
		}

	function cleanup_8()	# Delete cookies
		{
		$langs = $this->pref('l10n-languages');
		$this->add_report_item( gTxt('l10n-clean_8_main').'&#8230;' );
		foreach( $langs as $lang )
			{
			$lang = trim( $lang );
			$time = time() - 3600;
			$ok = setcookie( $lang , $lang , $time );
			$this->add_report_item( gTxt('l10n-delete_cookie',array('{lang}'=>MLPLanguageHandler::get_native_name_of_lang( $lang ))) , $ok , true );
			}
		}

	function cleanup_10()	# Restore comments default invitation.
		{
		$default = gTxt('comment');
		$ok = set_pref( 'comments_default_invite', $default, 'comments', 0 );
		$this->add_report_item( gTxt('l10n-comment_op',array('{op}'=>'Restore')) , $ok );
		}

	function _import_fixed_lang()
		{
		# 	Scans the articles, creating a group for each and adding it and setting the
		# language to the site default...
		$where = "1";
		$rs = safe_rows_start( 'ID , Title , '.L10N_COL_LANG.' , `'.L10N_COL_GROUP.'`' , 'textpattern' , $where );
		$count = @mysqli_num_rows($rs);

		$i = 0;
		if( $rs && $count > 0 )
			{
			while ( $a = nextRow($rs) )
				{
				$title = $a['Title'];
				$lang  = $a[L10N_COL_LANG];
				$article_id = $a[L10N_COL_GROUP];
				$id    = $a['ID'];

				if( $lang !== '-' and $article_id !== 0 )
					{
					#
					#	Use any existing Lang/Group data there might be...
					#
					if( true === MLPArticles::add_rendition( $article_id , $id , $lang , true , true , $title ) )
						$i++;
					}
				else
					{
					#
					#	Create a fresh group and add the info...
					#
					if( MLPArticles::create_article_and_add( $a ) )
						$i++;
					}
				}
			}
		if( $i === $count )
			return true;

		return "$i of $count";
		}

	function _upgrade_table( $table , $table_key )
		{
		global $l10n_wiz_upgrade;

		$keys = safe_rows( '*' , $table , "1=1" );
		foreach( $keys as $key )
			{
			$index = $key[$table_key];

			#	Pull all gbp_l10n rows that are associated with this key...
			$ttable = PFX.$table;
			$rows = safe_rows('id,entry_value,language', 'gbp_l10n', "`entry_column` = 'title' AND `entry_id`='$index' AND `table` = '$ttable'" );
			if( empty( $rows ) )
				continue;

			#	Build up values for each field...
			$set = array();
			foreach( $rows as $row )
				{
				$lang  = MLPLanguageHandler::find_lang( $row['language'] , $l10n_wiz_upgrade );
				$field = _l10n_make_field_name( 'title' , $lang );

				$f_value = doSlash( $row['entry_value'] );
				$set[] = "`$field`='$f_value'";

				if( $lang === $l10n_wiz_upgrade[0] )
					$set[] = "`title`='$f_value'";
				}

			#	Write the row back...
			$set = join( ', ', $set );
			safe_update( $table , $set , "`$table_key`='$index'" );

			#	Delete the gbp_l10n entries used...
			safe_delete( 'gbp_l10n' , "`entry_column` = 'title' AND `entry_id`='$index' AND `table` = '$ttable'" );
			}
		}

	function _upgrade_gbp_l10n()
		{
		global $l10n_wiz_upgrade;	# holds the languages of the previous gbp_l10n installation.

		#	Grab the localised section and category titles (if any)...
		$this->_upgrade_table( 'txp_section'  , 'name' );
		$this->_upgrade_table( 'txp_category' , 'id' );

		#	I'm not going to attempt to resolve the localised items from the gbp_l10n table
		# as I'll have to make (probably) incorrect assumptions about what the default
		# language is for each article.
		}

	}	# End of MLPWizView class

?>