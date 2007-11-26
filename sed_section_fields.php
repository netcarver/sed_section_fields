<?php

$plugin['revision'] = '$LastChangedRevision: 5 $';

$revision = @$plugin['revision'];
if( !empty( $revision ) )
	{
	$parts = explode( ' ' , trim( $revision , '$' ) );
	$revision = $parts[1];
	if( !empty( $revision ) )
		$revision = ' (r' . $revision . ')';
	}

$plugin['name'] = 'sed_section_fields';
$plugin['version'] = '0.1' . $revision;
$plugin['author'] = 'netcarver';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides per-section custom field labels';
$plugin['type'] = 1;

@include_once('../zem_tpl.php');

if (0) {
?>
<!-- CSS SECTION
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
div#sed_help td { vertical-align:top; }
div#sed_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
div#sed_help code.sed_code_tag { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
div#sed_help a:link, div#sed_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
div#sed_help a:hover, div#sed_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
div#sed_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
div#sed_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
div#sed_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
</style>
# --- END PLUGIN CSS ---
-->
<!-- HELP SECTION
# --- BEGIN PLUGIN HELP ---
<div id="sed_help">

h1(#top). sed_section_fields

|_. Copyright 2007 Stephen Dickinson. |

h2(#tag). The @sed_hello@ tag.

This is the only tag in this plugin.

|_. Attribute    |_. Default Value |_. Status   |_. Description |
| 'name'       | 'Fred'     | Optional | The name you want to display. |

*Emphasized items* have been added or changed since the last release.
-Struck out items- have been removed since the last release.

h2(#changelog). Change Log

v0.1 Features of the new template & compiler...&#8230;

* This plugin template has a special section for storing the Help section's CSS. This section doesn't get pulled through the textile mangler, it simply gets appended at the head of your help section so you can style it properly.
* The replacement template compiler 'zem_tpl.php' also takes care of checking if a client-only plugin references admin only features and stops the compilation if it does. *Why?* If the plugin is accessing admin-side resources, it should be marked as an admin side plugin, not a client-only plugin. This saves wasted time and effort if you happen to compile and install a plugin that you expect to work on the admin side but doesn't.


</div>
# --- END PLUGIN HELP ---
-->
<?php
}
# --- BEGIN PLUGIN CODE ---

if( 'admin' == @txpinterface )
	{
	}


	# --- END PLUGIN CODE ---
?>
