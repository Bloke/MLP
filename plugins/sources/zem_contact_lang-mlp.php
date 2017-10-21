<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'zem_contact_lang-mlp';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '4.6.2.20170316';
$plugin['author'] = 'Netcarver & the TXP Community';
$plugin['author_uri'] = 'http://forum.textpattern.com/viewtopic.php?id=12956';
$plugin['description'] = 'MLP strings plug-in for Zem Contact Reborn';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '0';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '0';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
#
#	Define a (hopefully) unique prefix for our strings...
#
if( !defined( 'ZCRL_PREFIX' ) )
	define( 'ZCRL_PREFIX' , 'zem_crl' );

#
# 	Here are the strings. Note that they all use the single quote format.
# The variable substitutions will be done as the strings are used.
#
global $zem_crl_strings;
$zem_crl_strings = array(
	'checkbox'			=> 'Checkbox',
	'contact'			=> 'Contact',
	'email'				=> 'Email',
	'email_subject'		=> '$var1 > Inquiry',
	'email_thanks'		=> 'Thank you, your message has been sent.',
	'field_missing'		=> 'Required field, &#8220;<strong>$var1</strong>&#8221;, is missing.',
	'form_expired'		=> 'The form has expired, please try again.',
	'form_used' 		=> 'The form was already submitted, please fill out a new form.',
	'general_inquiry'	=> 'General inquiry',
	'invalid_email'		=> '&#8220;<strong>$var1</strong>&#8221; is not a valid email address.',
	'invalid_host'		=> '&#8220;<strong>$var1</strong>&#8221; is not a valid email host.',
	'invalid_utf8' 		=> '“<strong>$var1</strong>” contains invalid UTF-8 characters.',
	'invalid_value'		=> 'Invalid value for &#8220;<strong>$var1</strong>&#8221;, &#8220;<strong>$var2</strong>&#8221; is not one of the available options.',
	'mail_sorry'		=> 'Sorry, unable to send email.',
	'max_warning' 		=> '“<strong>$var1</strong>” must not contain more than $var2 characters.',
	'message'			=> 'Message',
	'min_warning'		=> '&#8220;<strong>$var1</strong>&#8221; must contain at least $var2 characters.',
	'name'				=> 'Name',
	'option'			=> 'Option',
	'radio'				=> 'Radio',
	'receiver'			=> 'Receiver',
	'recipient'			=> 'Recipient',
	'refresh'			=> 'Follow this link if the page does not refresh automatically.',
	'secret' 			=> 'Secret',
	'send'				=> 'Send',
	'send_article'		=> 'Send article',
	'spam'				=> 'We do not accept spam thankyou!',
	'text'				=> 'Text',
	'to'				=> 'No &#8220;<strong>to</strong>&#8221; email address specified.',
	'to_missing'		=> '&#8220;<strong>To</strong>&#8221; email address is missing.',
	'version'			=> '4.0.3.6'
	);

#
#	Register the callback for the enumerate string event.
# If the MLP pack is not present and active this will NOT get called.
#
if (defined('txpinterface'))
        register_callback( 'zem_crl_enumerate_strings' , 'l10n.enumerate_strings' );

#
#	Here's a callback routine used to register the above strings with
# the MLP Pack (if installed).
#
function zem_crl_enumerate_strings($event , $step='' , $pre=0)
	{
	global $zem_crl_strings;
	$r = array	(
				'owner'		=> 'zem_contact_lang-mlp',	#	Name the plugin these strings are for.
				'prefix'	=> ZCRL_PREFIX,				#	Its unique string prefix
				'lang'		=> 'en-gb',					#	The language of the initial strings.
				'event'		=> 'public',				#	public/admin/common = which interface the strings will be loaded into
				'strings'	=> $zem_crl_strings,		#	The strings themselves.
				);
	return $r;
	}

#
#	Here's the local gTxt routine.
#
#	Need to make this fallback to the local array in case this is not being used with the MLP pack.
#
if( @txpinterface=='public' )
	{
	function zem_contact_gTxt( $what , $var1='' , $var2='' )
		{
		global $textarray;
		global $zem_crl_strings;

		#
		#	Build an array of substitutions...
		#
		$args = array();
		if( !empty( $var1 ) )
			$args['$var1'] = $var1;
		if( !empty( $var2 ) )
			$args['$var2'] = $var2;

		#
		#	Prepare the prefixed key for use...
		#
		$key = ZCRL_PREFIX . '-' . $what;
		$key = strtolower($key);

		#
		#	Grab from the global textarray (possibly edited by MLP) if we can...
		#
		if(isset($textarray[$key]))
			{
			$str = $textarray[$key];
			}
		else
			{
			#
			#	Use the non-prefixed key...
			#
			$key = strtolower($what);

			#
			#	Grab from the internal array if possible...
			#
			if( isset( $zem_crl_strings[$key] ) )
				$str = $zem_crl_strings[$key];
			else
				#
				#	Fallback to returning the key if not present...
				#
				$str = $what;
			}
		$str = strtr( $str , $args );
		return $str;
		}
	}
# --- END PLUGIN CODE ---
/*
# --- BEGIN PLUGIN HELP ---

<div style="text-align:center;font-weight:bold;font-size:24px;text-decoration:underline;">Zem Contact Lang</div>

This is a separate language plug-in for use with Zem Contact Reborn. Both plug-ins need to be installed and activated in order to work properly.

Separating the language in this way will enable non-english users to update the main plug-in without affecting their &#8220;localisation&#8221;.

<div id="local" style="text-align:center;font-weight:bold;font-size:24px;text-decoration:underline;">Localisation</div>

Throughout the <code>zem_contact_reborn</code> plug-in, use has been made of a separate <code>gTxt</code> function which you can see in this plug-in&#8217;s code by clicking on the &#8220;Edit&#8221; button.

If you are using the plug-in for a non-english site you can make use of this to localise text outputs for your preferred language.

You should only edit text that appears after the <code>=&gt;</code> sign.

If you have a dual-language site and the languages use separate &#8220;sections&#8221;, you can use the &#60;txp:if&#95;section&#62; tag to enable different translations. An example of this usage is shown in the <strong><a href="http://forum.textpattern.com/viewtopic.php?id=13416">forum thread</a></strong>. Our thanks to Els (doggiez) for this example.

# --- END PLUGIN HELP ---
*/
?>