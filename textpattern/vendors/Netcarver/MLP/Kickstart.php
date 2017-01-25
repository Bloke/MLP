<?php
/**
 * Instead of patching get_prefs() and adding functions to txplib_db.php,
 * introduce a vendors/MLP folder and bootstrap it.
 * Upgrades via dirty flag can be handled there, as can session starting,
 * overrides, etc.
 *
 * The class loader comes early in the chain, so this is ideal. The only
 * patching required is therefore to the safe_*() calls in txplib_db.php.
 */

namespace Netcarver\MLP;

class Kickstart
{
    // IMPORTANT: Bump this for each release so the dirty flag is set
    protected $l10n_release_version = '4.6.2.20170125';
    protected $l10n_install_version = '';

    /**
     * Perform upgrade if required.
     */
    public function l10n_upgrade()
    {
        global $dbversion, $thisversion, $txp_using_svn;

        if (txpinterface === 'admin') {
            $this->l10n_install_version = get_pref('l10n_version', '', 1);

            if (!$dbversion || ($dbversion != $thisversion) || ($this->l10n_release_version != $this->l10n_install_version) || $txp_using_svn) {
                // Dirty: perform upgrade.
                // Ensure new indexes are present in case of upgrade.
                _l10n_check_index();
                safe_optimize('textpattern');

                // Iterate over the site languages, rebuilding the tables.
                $langs = \MLPLanguageHandler::get_site_langs();

                foreach ($langs as $lang) {
                    _l10n_generate_lang_table($lang);
                    _l10n_generate_localise_table_fields($lang);
                }

                // Update the installed version number
                set_pref( 'l10n_version', $this->l10n_release_version , 'l10n', PREF_HIDDEN );
            }
        }
    }

    /**
     * Start a session if one has not yet started. Otherwise use existing session.
     *
     * @return boolean Success or otherwise.
     */
    public function l10n_session_start()
    {
        if (headers_sent()) {
            if (!isset($_SESSION)) {
                $_SESSION = array();
            }

            return false;
        } elseif (!isset($_SESSION)) {
            session_start();

            return true;
        } else {
            return true;
        }
    }

    /**
     * Check if pack and (optionally) plugins are installed.
     *
     * @param  boolean $check_plugin_active Additionally check if MLP plugins are activated.
     */
    public function l10n_installed($check_plugin_active = false)
    {
        static $result;

        if (isset($result)) {
            return $result;
        }

        $installed = getThing('show tables like \''.PFX.'l10n_articles\'');
        $installed = !empty($installed);
        $active = true;

        if ($installed && $check_plugin_active) {
            $res = safe_field('status', 'txp_plugin', "name='l10n'");
            $active = ($res == 1);

            if (!$active) {
                // When plugin isn't installed in the db or isn't active check the plugin cache folder.
                $cache_dir = safe_field('val', 'txp_prefs', "name='plugin_cache_dir'");
                $active = (count(glob( $cache_dir . '/l10n*.php')) > 0);
            }
        }

        $result = ($installed && $active);

        return $result;
    }

    /**
     * MLP extension: remap tables to include lang columns.
     *
     * @param  string $table Table name to remap
     * @return [type]        [description]
     */
    public function safe_remap_tables($table)
    {
        global $prefs;

        if (isset($prefs['db_remap_tables_func']) && is_callable($prefs['db_remap_tables_func'])) {
            $table = call_user_func($prefs['db_remap_tables_func'], $table);
        }

        return $table;
    }

    /**
     * MLP extension: trim/remap prefs.
     *
     * @param  string $fields Field names to remap
     * @param  string $table  Table name to remap
     * @return [type]         [description]
     */
    public function safe_remap_fields($fields, $table)
    {
        global $prefs;

        if (isset($prefs['db_remap_fields_func']) && is_callable($prefs['db_remap_fields_func'])) {
            $fields = call_user_func($prefs['db_remap_fields_func'], $fields, $table);
        }

        return $fields;
    }

    /**
     * MLP extension: interpret result sets with extra column info in them.
     *
     * @param  string $thing    Field to operate upon
     * @param  string $table    Table name to remap
     * @param  string $where    SQL clause to inject
     * @param  string $results  Results object to return
     * @param  string $is_a_set Whether the table represents a set of strings
     * @return [type]         [description]
     */
    public function safe_process_results($thing, $table, $where, $results, $is_a_set = false)
    {
        global $prefs;

        if (isset($prefs['db_process_result_func']) and is_callable($prefs['db_process_result_func'])) {
            $results = call_user_func($prefs['db_process_result_func'], $thing, $table, $where, $results, $is_a_set);
        }

        return $results;
    }    

}



/*

    function get_prefs()
        {
            global $txp_user, $dbversion, $thisversion, $txp_using_svn, $l10n_release_version;

            $out = array();

            $exception = false;
            $exception_events = array( 'prefs' );   # These txp pages do their own language loading from the $prefs['language'] setting.

            if( isset( $event ) )
                $exception = in_array( $event , $exception_events );

            if( defined('LANG') && !$exception )
                return $out;

            if( (@txpinterface==='admin') or (@txpinterface==='public') ) {
                if( l10n_installed( true ) ) {
                    #   First call and the plugin is installed and active so guess
                    # which language the user is browsing in based on the long session variable.
                    # This will not be set for the first visit to a page but it does reduce the need
                    # for reloading the strings.
                    #
                    # If this guess later proves to be wrong -- for example, on the first call or when
                    # the user switches browse language -- then $textarray will be reloaded.
                    #
                    l10n_session_start();
                    $language = '';
                    if( @txpinterface==='admin' )
                        $language = @$_SESSION['l10n_admin_long_lang'];
                    else
                        $language = @$_SESSION['l10n_long_lang'];

                    if( !empty( $language ) ) {
                        $out['language'] = $language;
                    }
                }
            }

            return $out;
        }



*/
