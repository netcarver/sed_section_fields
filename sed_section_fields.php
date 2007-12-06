<?php

$plugin['revision'] = '$LastChangedRevision$';

$revision = @$plugin['revision'];
if( !empty( $revision ) )
	{
	$parts = explode( ' ' , trim( $revision , '$' ) );
	$revision = $parts[1];
	if( !empty( $revision ) )
		$revision = '.' . $revision;
	}

$plugin['name'] = 'sed_section_fields';
$plugin['version'] = '0.1' . $revision;
$plugin['author'] = 'Stephen Dickinson';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides per-section custom field labels';
$plugin['type'] = '1';

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

if( @txpinterface === 'admin' )
	{
	register_callback( '_sed_sf_handle_article_pre' ,  'article' , '' , 1 );
	register_callback( '_sed_sf_handle_article_post' , 'article' );
	register_callback( '_sed_sf_insert_cfnames' ,      'section' , '' , 1 );
	register_callback( '_sed_sf_xml_server'     ,      'sed_sf' );
	}

switch(gps('sed_resources'))
	{
	case 'sed_sf_js':
		_sed_sf_js();
		break;
	default:
		break;
	}

function _sed_sf_make_section_key( $section )
	{
	$key = 'sed_sf_' . $section . '_field_labels';
	return $key;
	}


function _sed_sf_insert_cfnames_into_sections( $page )
	{
	#
	#	Inserts the name text inputs into each sections' edit controls
	# current implementation uses output buffer...
	#
	global $DB , $prefs;

	if( !isset( $DB ) )
		$DB = new db;

	if( !isset( $prefs ) )
		$prefs = get_prefs();

	$rows = safe_rows_start( '*' , 'txp_section' , "1=1" );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$name  = $row['name'];
			$title = $row['title'];

			$cf_names = _sed_sf_get_cfviz( $name );

			$f = '<input type="text" name="name" value="' . $name . '" size="20" class="edit" tabindex="1" /></td></tr>'. n.n . '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . gTxt('section_longtitle') . ': </td><td class="noline"><input type="text" name="title" value="' . $title . '" size="20" class="edit" tabindex="1" /></td></tr>';

			$r = n.n.'<tr><td colspan="2">Write Tab Fields...</td></tr>'.n;
			for( $x = 1; $x <= 10; $x++ )
				{
				$value = $cf_names[ $x ];
				$field_name = 'cf_' . $x . '_set';
				$global_label = $prefs [ 'custom_' . $x . '_set' ];
				if( !empty( $global_label ) )
					{
					#	Only bother showing the show/hide radio buttons if the global field label exists.
					$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">Hide "' . $global_label . '" (cf#'. $x .') on write tab? </td><td class="noline">';
					$r .= yesnoradio( $name.'_cf_'.$x.'_visible' , $value , '' , $field_name );
					$r .= '</td></tr>'.n;
					}
				}

			# TODO: Insert row to control visibility of this section in the write-tab section selector
			#$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">Hide this section on write tab? </td><td class="noline">';
			#$r .= yesnoradio( 'hide_'.$name.'_from_list' , '0' );
			#$r .= '</td></tr>'.n;

			$r .= n.'<tr><td colspan="2"></td></tr>'.n.n;
			$page = str_replace( $f , $f.$r , $page );
			}
		}

	return $page;
	}

function _sed_sf_insert_cfnames( $event , $step )
	{
	if( $step == 'section_save' )
		_sed_sf_update_cfnames();

	if( $step == '' || $step == 'section_save' )
		ob_start( '_sed_sf_insert_cfnames_into_sections' );
	}

function _sed_sf_update_cfnames()
	{
	#
	#	Stores the custom-field labels for the section being saved
	#
	global $prefs;

	$section    = gps( 'name' );
	$oldsection = gps( 'old_name' );

	if( $section !== $oldsection )	# renamed section?
		{
		$oldkey = doSlash( _sed_sf_make_section_key( $oldsection ) );
		safe_delete('txp_prefs', "`name`='$oldkey'");
		}

	#
	#	Build array of submitted values...
	#
	$data = array();
	for( $x = 1; $x <= 10; $x++ )
		{
		$field_name = $section . '_cf_' . $x . '_visible';
		$value = ps( $field_name );
		if( !empty( $value ) )
			$data[$x] = $value;
		else
			$data[$x] = '0';
		}

	#
	#	Store the data...
	#
	$key = doSlash( _sed_sf_make_section_key( $section ) );
	doArray( $data , 'doSlash' );
	$value = serialize( $data );
	set_pref( $key , $value , 'sed_sf' , 2 );
	$prefs[ $key ] = $value;
	}

function _sed_sf_get_cfviz( $section )
	{
	#
	#	Given the name of a section, will grab the labels for the custom fields
	# as an array 'number' => 'label'
	#
	global $prefs;

	$key = _sed_sf_make_section_key( $section );
	if( isset( $prefs[ $key ] ) )
		$results = unserialize( $prefs[ $key ] );
	else
		$results = array();

	return $results;
	}

function _sed_sf_xml_serve_cfvisibility( $event , $step )
	{
	global $prefs;

	$section = gps( 'section' );
	$cf_names = _sed_sf_get_cfviz( $section );

	$r = '';
	for( $x=1 ; $x <= 10; $x++)
		{
		$label = @$cf_names[ $x ];
		$r .= $label;
		if ($x < 10)
			$r .= ' | ';
		}
	return $r;
	}
function _sed_sf_xml_server( $event , $step )
	{
	while (@ob_end_clean());
	header('Content-Type: text/xml; charset=utf-8');
	header('Cache-Control: private');

	switch( $step )	# step selects among possible content types...
		{
		case 'get_cfvisibility' :
			$r =  _sed_sf_xml_serve_cfvisibility( $event , $step );
			break;
		default:
			$r = '';
			break;
		}

	echo $r;
	exit;
	}

function _sed_sf_handle_article_pre( $event , $step )
	{
	ob_start( '_sed_sf_inject_into_write' );
	}
function _sed_sf_handle_article_post( $event , $step )
	{
	echo n."<script src='" .hu."?sed_resources=sed_sf_js' type='text/javascript'></script>".n;

	#
	# QUESTION: Is it possible to grab the output buffer and insert the elements
	# we need right here -- without using an output buffer handler?
	#
	}


function _sed_sf_inject_into_write( $page )
	{
	#
	#	Inject section-change event handler
	#
	$f = '</a>]</span><br /><select id="section" ';
	$r = 'onchange="_sed_sf_on_section_change()" ';
	$page = str_replace( $f , $f.$r , $page );

	#
	#	Inject markup into custom field controls so the javascript can access
	# them easily...
	#
	for( $x = 1 ; $x <= 10 ; $x++ )
		{
		$f = '<p><label for="custom-' . $x .  '">';
		$r = '<p id="custom-' . $x . '-para"><label for="custom-' . $x .  '" id="custom-'. $x . '-label">';
		$page = str_replace( $f , $r , $page );
		}

	return $page;
	}


function _sed_sf_js()
	{
	$debug = true;
	while( @ob_end_clean() );
	header( "Content-Type: text/javascript; charset=utf-8" );
	header( "Expires: ".date("r", time()+3600) );
	header( "Cache-Control: public" );
	if( $debug )
		{
		readfile( dirname(__FILE__) . '/sed_sf.js' );
		}
	else
		{
#		echo <<<js
#HELLO!
#js;
		}
	exit();
	}

# --- END PLUGIN CODE ---

/*
# --- BEGIN PLUGIN CSS ---
	<style type="text/css">
	div#sed_sf_help td { vertical-align:top; }
	div#sed_sf_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
	div#sed_sf_help code.sed_code_tag { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
	div#sed_sf_help a:link, div#sed_sf_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
	div#sed_sf_help a:hover, div#sed_sf_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
	div#sed_sf_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
	div#sed_sf_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
	div#sed_sf_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	div#sed_sf_help ul ul { font-size:85%; }
	div#sed_sf_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	</style>
# --- END PLUGIN CSS ---
# --- BEGIN PLUGIN HELP ---
<div id="sed_sf_help">

h1(#top). SED Section Fields Help.

Introduces section-specific named overrides for custom fields.

h2(#changelog). Change Log

v0.1

* Use the presentations > section tab to choose which custom fields to hide for any
article in that section.
* When you write or edit an article, your per-section custom fields preferences will
appear.
* If you change the section of an article and then edit it, the new section's
fields will appear (or disappear) as appropriate to the section.

 <span style="float:right"><a href="#top" title="Jump to the top">top</a></span>

</div>
# --- END PLUGIN HELP ---
*/
?>
