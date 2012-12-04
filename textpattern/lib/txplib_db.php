<?php

if (!defined('PFX')) {
	if (!empty($txpcfg['table_prefix'])) {
		define ("PFX",$txpcfg['table_prefix']);
	} else define ("PFX",'');
}

if (get_magic_quotes_runtime())
{
	set_magic_quotes_runtime(0);
}

class DB {
	function DB()
	{
		global $txpcfg;

		$this->host = $txpcfg['host'];
		$this->db	= $txpcfg['db'];
		$this->user = $txpcfg['user'];
		$this->pass = $txpcfg['pass'];
		$this->client_flags = isset($txpcfg['client_flags']) ? $txpcfg['client_flags'] : 0;

		$this->link = @mysql_connect($this->host, $this->user, $this->pass, false, $this->client_flags);
		if (!$this->link) die(db_down());

		$this->version = mysql_get_server_info();

		if (!$this->link) {
			$GLOBALS['connected'] = false;
		} else $GLOBALS['connected'] = true;
		@mysql_select_db($this->db) or die(db_down());

		$version = $this->version;
		// be backwardscompatible
		if ( isset($txpcfg['dbcharset']) && (intval($version[0]) >= 5 || preg_match('#^4\.[1-9]#',$version)) )
			mysql_query("SET NAMES ". $txpcfg['dbcharset']);
	}
}
$DB = new DB;

// -------------------------------------------------------------
	function safe_remap_tables($table) {
		#
		#	Called to re-map table names as needed...
		#
		global $prefs;
		if (isset($prefs['db_remap_tables_func']) and is_callable($prefs['db_remap_tables_func']))
			$table = call_user_func($prefs['db_remap_tables_func'], $table);
		return $table;
	}

//-------------------------------------------------------------
	function safe_remap_fields($fields,$table) {
		#
		#	Called at the end of the get_prefs() routine to trim/remap whatever prefs you need...
		#
		global $prefs;
		if (isset($prefs['db_remap_fields_func']) and is_callable($prefs['db_remap_fields_func']))
			$fields = call_user_func($prefs['db_remap_fields_func'],$fields,$table);
		return $fields;
	}

//-------------------------------------------------------------
	function safe_process_results( $thing , $table , $where , $results , $is_a_set=false ) {
		global $prefs;

		if (isset($prefs['db_process_result_func']) and is_callable($prefs['db_process_result_func']))
			$results = call_user_func( $prefs['db_process_result_func'] , $thing , $table , $where , $results , $is_a_set );
		return $results;
	}

//-------------------------------------------------------------
	function safe_pfx($table) {
		$table = safe_remap_tables($table);
		$name = PFX.$table;
		if (preg_match('@[^\w._$]@', $name))
			return '`'.$name.'`';
		return $name;
	}

//-------------------------------------------------------------
	function safe_pfx_j($table)
	{
		$ts = array();
		foreach (explode(',', $table) as $t) {
			$t = safe_remap_tables($t);
			$name = PFX.trim($t);
			if (preg_match('@[^\w._$]@', $name))
				$ts[] = "`$name`".(PFX ? " as `$t`" : '');
			else
				$ts[] = "$name".(PFX ? " as $t" : '');
		}
		return join(', ', $ts);
	}

// -------------------------------------------------------------
	function safe_escape($in='')
	{
		global $DB;
		return mysql_real_escape_string($in, $DB->link);
	}

//-------------------------------------------------------------
	function safe_query($q='',$debug='',$unbuf='')
	{
		global $DB, $txpcfg, $qcount, $qtime, $production_status;
		$method = (!$unbuf) ? 'mysql_query' : 'mysql_unbuffered_query';
		if (!$q) return false;
		if ($debug or TXP_DEBUG === 1) dmp($q);

		$start = getmicrotime();
		$result = $method($q,$DB->link);
		$time = getmicrotime() - $start;
		@$qtime += $time;
		@$qcount++;
		if ($result === false and (txpinterface === 'admin' or @$production_status == 'debug' or @$production_status == 'testing')) {
			$caller = ($production_status == 'debug') ? n . join("\n", get_caller()) : '';
			trigger_error(mysql_error() . n . $q . $caller, E_USER_WARNING);
		}

		trace_add("[SQL ($time): $q]");

		if(!$result) return false;
		return $result;
	}

// -------------------------------------------------------------
	function safe_delete($table, $where, $debug='')
	{
		$q = "delete from ".safe_pfx($table)." where $where";
		if ($r = safe_query($q,$debug)) {
			return true;
		}
		return false;
	}

// -------------------------------------------------------------
	function safe_update($table, $set, $where, $debug='')
	{
		$q = "update ".safe_pfx($table)." set $set where $where";
		if ($r = safe_query($q,$debug)) {
			return true;
		}
		return false;
	}

// -------------------------------------------------------------
	function safe_insert($table,$set,$debug='')
	{
		global $DB;
		$q = "insert into ".safe_pfx($table)." set $set";
		if ($r = safe_query($q,$debug)) {
			$id = mysql_insert_id($DB->link);
			return ($id === 0 ? true : $id);
		}
		return false;
	}

// -------------------------------------------------------------
// insert or update
	function safe_upsert($table,$set,$where,$debug='')
	{
		// FIXME: lock the table so this is atomic?
		$r = safe_update($table, $set, $where, $debug);
		if ($r and (mysql_affected_rows() or safe_count($table, $where, $debug)))
			return $r;
		else
			return safe_insert($table, join(', ', array($where, $set)), $debug);
	}

// -------------------------------------------------------------
	function safe_alter($table, $alter, $debug='')
	{
		$q = "alter table ".safe_pfx($table)." $alter";
		if ($r = safe_query($q,$debug)) {
			return true;
		}
		return false;
	}

// -------------------------------------------------------------
	function safe_optimize($table, $debug='')
	{
		$q = "optimize table ".safe_pfx($table)."";
		if ($r = safe_query($q,$debug)) {
			return true;
		}
		return false;
	}

// -------------------------------------------------------------
	function safe_repair($table, $debug='')
	{
		$q = "repair table ".safe_pfx($table)."";
		if ($r = safe_query($q,$debug)) {
			return true;
		}
		return false;
	}

// -------------------------------------------------------------
	function safe_field($thing, $table, $where, $debug='')
	{
		$thing = safe_remap_fields( $thing , $table );
		$q = "select $thing from ".safe_pfx_j($table)." where $where";
		$r = safe_query($q,$debug);
		if (@mysql_num_rows($r) > 0) {
			$f = mysql_result($r,0);
			mysql_free_result($r);
			$f = safe_process_results( $thing , $table , $where , $f );
			return $f;
		}
		return false;
	}

// -------------------------------------------------------------
	function safe_column($thing, $table, $where, $debug='')
	{
		$thing = safe_remap_fields( $thing , $table );
		$q = "select $thing from ".safe_pfx_j($table)." where $where";
		$rs = getRows($q,$debug);
		if ($rs) {
			foreach($rs as $a) {
				$v = array_shift($a);
				$out[$v] = $v;
			}
			return $out;
		}
		return array();
	}

// -------------------------------------------------------------
/**
 * Fetch a column as an numeric array
 *
 * @param string $thing     field name
 * @param string $table     table name
 * @param string $where     where clause
 * @param bool $debug       dump query
 * @return array    numeric array of column values
 * @since 4.5.0
 */
	function safe_column_num($thing, $table, $where, $debug='')
	{
		$thing = safe_remap_fields( $thing , $table );
		$q = "select $thing from ".safe_pfx_j($table)." where $where";
		$rs = getRows($q,$debug);
		if ($rs) {
			foreach($rs as $a) {
				$v = array_shift($a);
				$out[] = $v;
			}
			return $out;
		};
		return array();
	}

// -------------------------------------------------------------
	function safe_row($things, $table, $where, $debug='')
	{
		$things = safe_remap_fields( $things , $table );
		$q = "select $things from ".safe_pfx_j($table)." where $where";
		$rs = getRow($q,$debug);
		if ($rs) {
			$rs = safe_process_results( $things , $table , $where , $rs );
			return $rs;
		}
		return array();
	}


// -------------------------------------------------------------
	function safe_rows($things, $table, $where, $debug='')
	{
		$things = safe_remap_fields( $things , $table );
		$q = "select $things from ".safe_pfx_j($table)." where $where";
		$rs = getRows($q,$debug);
		if ($rs) {
			return $rs;
		}
		return array();
	}

// -------------------------------------------------------------
	function safe_rows_start($things, $table, $where, $debug='')
	{
		$things = safe_remap_fields( $things , $table );
		$q = "select $things from ".safe_pfx_j($table)." where $where";
		return startRows($q,$debug);
	}

//-------------------------------------------------------------
	function safe_count($table, $where, $debug='')
	{
		return getThing("select count(*) from ".safe_pfx_j($table)." where $where",$debug);
	}

// -------------------------------------------------------------
	function safe_show($thing, $table, $debug='')
	{
		$thing = safe_remap_fields( $thing , $table );
		$q = "show $thing from ".safe_pfx($table)."";
		$rs = getRows($q,$debug);
		if ($rs) {
			return $rs;
		}
		return array();
	}


//-------------------------------------------------------------
	function fetch($col,$table,$key,$val,$debug='')
	{
		$col = safe_remap_fields( $col , $table );
		$key = doSlash($key);
		$val = (is_int($val)) ? $val : "'".doSlash($val)."'";
		$q = "select $col from ".safe_pfx($table)." where `$key` = $val limit 1";
		if ($r = safe_query($q,$debug)) {
			$thing = (mysql_num_rows($r) > 0) ? mysql_result($r,0) : '';
			mysql_free_result($r);
			$thing = safe_process_results( $col , $table , array($key,$val) , $thing );
			return $thing;
		}
		return false;
	}

//-------------------------------------------------------------
	function getRow($query,$debug='')
	{
		if ($r = safe_query($query,$debug)) {
			$row = (mysql_num_rows($r) > 0) ? mysql_fetch_assoc($r) : false;
			mysql_free_result($r);
			return $row;
		}
		return false;
	}

//-------------------------------------------------------------
	function getRows($query,$debug='')
	{
		if ($r = safe_query($query,$debug)) {
			if (mysql_num_rows($r) > 0) {
				while ($a = mysql_fetch_assoc($r)) $out[] = $a;
				mysql_free_result($r);
				return $out;
			}
		}
		return false;
	}

//-------------------------------------------------------------
	function startRows($query,$debug='')
	{
		return safe_query($query,$debug);
	}

//-------------------------------------------------------------
	function nextRow($r)
	{
		$row = mysql_fetch_assoc($r);
		if ($row === false)
			mysql_free_result($r);
		return $row;
	}

//-------------------------------------------------------------
	function numRows($r)
	{
		return mysql_num_rows($r);
	}

//-------------------------------------------------------------
	function getThing($query,$debug='')
	{
		if ($r = safe_query($query,$debug)) {
			$thing = (mysql_num_rows($r) != 0) ? mysql_result($r,0) : '';
			mysql_free_result($r);
			return $thing;
		}
		return false;
	}

//-------------------------------------------------------------
	function getThings($query,$debug='')
	// return values of one column from multiple rows in an num indexed array
	{
		$rs = getRows($query,$debug);
		if ($rs) {
			foreach($rs as $a) $out[] = array_shift($a);
			return $out;
		}
		return array();
	}

//-------------------------------------------------------------
	function getCount($table,$where,$debug='')
	{
		return getThing("select count(*) from ".safe_pfx_j($table)." where $where",$debug);
	}

// -------------------------------------------------------------
	function getTree($root, $type, $where='1=1', $tbl='txp_category')
	{

		$root = doSlash($root);
		$type = doSlash($type);

		$rs = safe_row(
			"lft as l, rgt as r",
			$tbl,
			"name='$root' and type = '$type'"
		);

		if (!$rs) return array();
		extract($rs);

		$out = array();
		$right = array();

		$rs = safe_rows_start(
			"id, name, lft, rgt, parent, title",
			$tbl,
			"lft between $l and $r and type = '$type' and name != 'root' and $where order by lft asc"
		);

		while ($rs and $row = nextRow($rs)) {
			extract($row);
			while (count($right) > 0 && $right[count($right)-1] < $rgt) {
				array_pop($right);
			}

			$out[] =
				array(
					'id' => $id,
					'name' => $name,
					'title' => $title,
					'level' => count($right),
					'children' => ($rgt - $lft - 1) / 2,
					'parent' => $parent
				);

			$right[] = $rgt;
		}
		return($out);
	}

// -------------------------------------------------------------
	function getTreePath($target, $type, $tbl='txp_category')
	{

		$rs = safe_row(
			"lft as l, rgt as r",
			$tbl,
			"name='".doSlash($target)."' and type = '".doSlash($type)."'"
		);
		if (!$rs) return array();
		extract($rs);

		$rs = safe_rows_start(
			"*",
			$tbl,
				"lft <= $l and rgt >= $r and type = '".doSlash($type)."' order by lft asc"
		);

		$out = array();
		$right = array();

		while ($rs and $row = nextRow($rs)) {
			extract($row);
			while (count($right) > 0 && $right[count($right)-1] < $rgt) {
				array_pop($right);
			}

			$out[] =
				array(
					'id' => $id,
					'name' => $name,
					'title' => $title,
					'level' => count($right),
					'children' => ($rgt - $lft - 1) / 2
				);

			$right[] = $rgt;
		}
		return $out;
	}

// -------------------------------------------------------------
	function rebuild_tree($parent, $left, $type, $tbl='txp_category')
	{
		$left  = assert_int($left);
		$right = $left+1;

		$parent = doSlash($parent);
		$type   = doSlash($type);

		$result = safe_column("name", $tbl,
			"parent='$parent' and type='$type' order by name");

		foreach($result as $row) {
			$right = rebuild_tree($row, $right, $type, $tbl);
		}

		safe_update(
			$tbl,
			"lft=$left, rgt=$right",
			"name='$parent' and type='$type'"
		);
		return $right+1;
	}

//-------------------------------------------------------------
	function rebuild_tree_full($type, $tbl='txp_category')
	{
		# fix circular references, otherwise rebuild_tree() could get stuck in a loop
		safe_update($tbl, "parent=''", "type='".doSlash($type)."' and name='root'");
		safe_update($tbl, "parent='root'", "type='".doSlash($type)."' and parent=name");

		rebuild_tree('root', 1, $type, $tbl);
	}

//-------------------------------------------------------------
	function l10n_installed( $check_plugin_active = false )
		{
		static $result;

		if( isset( $result ) )
			return $result;

		$installed 	= getThing( 'show tables like \''.PFX.'l10n_articles\'' );
		$installed 	= !empty( $installed );
		$active 	= true;

		if( $installed && $check_plugin_active )
			{
			$res 	= safe_field( 'status', 'txp_plugin', "name='l10n'");
			$active	= ($res == 1);

			if( !$active )
				{
				// when plugin isn't installed in the db or isn't active check the plugin cache folder
				$cache_dir = safe_field( 'val', 'txp_prefs', "name='plugin_cache_dir'");
				$active = (count( glob( $cache_dir . '/l10n*.php' ) ) > 0);
				}
			}

		$result = ($installed && $active);
		return $result;
		}

//-------------------------------------------------------------
	define( 'L10N_DIRTY_FLAG_VARNAME', 'l10n_txp_dirty' );
	function get_prefs()
		{
			global $txp_user, $dbversion, $thisversion, $txp_using_svn, $l10n_version;

			// IMPORTANT: Bump this for each release so the dirty flag is set
			$l10n_version = '4.5.2.20121204';

			$out = array();

			// get current user's private prefs
			if ($txp_user and (version_compare($dbversion, '4.0.9', '>=') or $txp_using_svn)) {
				$r = safe_rows_start('name, val', 'txp_prefs', 'prefs_id=1 AND user_name=\''.doSlash($txp_user).'\'');
				if ($r) {
					while ($a = nextRow($r)) {
						$out[$a['name']] = $a['val'];
					}
				}
			}

			// get global prefs, eventually override equally named user prefs.
			if (version_compare($dbversion, '4.0.9', '>=') or $txp_using_svn)
				{
				$r = safe_rows_start('name, val', 'txp_prefs', 'prefs_id=1 AND user_name=\'\'');
				}
			else
				{
				$r = safe_rows_start('name, val', 'txp_prefs', 'prefs_id=1');
				}
			if ($r) {
				while ($a = nextRow($r)) {
					$out[$a['name']] = $a['val'];
				}
			}

			if ( @txpinterface==='admin')
				{
				$installed_l10n_ver = get_pref('l10n_version');

				if(!$dbversion or ($dbversion != $thisversion) or ($l10n_version != $installed_l10n_ver) or $txp_using_svn)
					{
					$name = L10N_DIRTY_FLAG_VARNAME;
					if (!array_key_exists(L10N_DIRTY_FLAG_VARNAME , $out))
						{
						safe_insert('txp_prefs', "name = '$name', val = 'DIRTY', event = 'l10n', html = 'text_input', type = '2', prefs_id = 1");
						}
					else
						{
						safe_update('txp_prefs', "val = 'DIRTY'", "name like '$name'");
						}
					}
				}

			$exception = false;
			$exception_events = array( 'prefs' );	# These txp pages do their own language loading from the $prefs['language'] setting.
			if( isset( $event ) )
				$exception = in_array( $event , $exception_events );

			if( defined('LANG') && !$exception )
				return $out;

			if( (@txpinterface==='admin') or (@txpinterface==='public') )
				{
				if( l10n_installed( true ) )
					{
					#	First call and the plugin is installed and active so guess
					# which language the user is browsing in based on the long session variable.
					# This will not be set for the first visit to a page but it does reduce the need
					# for reloading the strings.
					#
					# If this guess later proves to be wrong -- for example, on the first call or when
					# the user switches browse language -- then $textarray will be reloaded.
					#
					@session_start();
					$language = '';
					if( @txpinterface==='admin' )
						$language = @$_SESSION['l10n_admin_long_lang'];
					else
						$language = @$_SESSION['l10n_long_lang'];
					if( !empty( $language ) )
						{
						$out['language'] = $language;
						}
					}
				}
			return $out;
		}

// -------------------------------------------------------------
	function db_down()
	{
		// 503 status might discourage search engines from indexing or caching the error message
		txp_status_header('503 Service Unavailable');
		$error = mysql_error();
		return <<<eod
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<title>Untitled</title>
</head>
<body>
<p align="center" style="margin-top:4em">Database unavailable.</p>
<!-- $error -->
</body>
</html>
eod;
	}

?>