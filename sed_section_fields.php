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
$plugin['version'] = '0.2' . $revision;
$plugin['author'] = 'Netcarver';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides admin interface field customisation on a per-section basis.';
$plugin['type'] = '1';

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

global $_sed_sf_using_glz_custom_fields;

#
#	Define a unique prefix for our strings (make sure there is no '-' in it!)
#
if( !defined( 'SED_SF_PREFIX' ) )
	define( 'SED_SF_PREFIX' , 'sed_sf' );

#===============================================================================
#	Strings for internationalisation...
#===============================================================================
global $_sed_sf_l18n;
$_sed_sf_l18n = array(
	'write_tab_heading' 	=> 'Write Tab Fields...',
	'hide_cf' 				=> 'Hide "{global_label}" (cf#{cfnum}) on write tab?',
	'hide_section'			=> 'Hide this section from the section list on the write tab?',
	);

function _sed_sf_gtxt( $what , $args=array() )
	{
	global $textarray;
	global $_sed_sf_l18n;

	$key = SED_SF_PREFIX . '-' . $what;
	$key = strtolower($key);

	if(isset($textarray[$key]))
		$str = $textarray[$key];
	else
		{
		$key = strtolower($what);

		if( isset( $_sed_sf_l18n[$key] ) )
			$str = $_sed_sf_l18n[$key];
		else
			$str = $what;
		}
	$str = strtr( $str , $args );
	return $str;
	}

#===============================================================================
#	MLP Registration...
#===============================================================================
register_callback( '_sed_sf_enumerate_strings' , 'l10n.enumerate_strings' );
function _sed_sf_enumerate_strings()
	{
	global $_sed_sf_l18n;
	$r = array	(
				'owner'		=> 'sed_section_fields',
				'prefix'	=> SED_SF_PREFIX,
				'lang'		=> 'en-gb',
				'event'		=> 'admin',
				'strings'	=> $_sed_sf_l18n,
				);
	return $r;
	}


#===============================================================================
#	Admin interface features...
#===============================================================================
if( @txpinterface === 'admin' )
	{
	add_privs('sed_sf' , '1,2,3,4,5,6');

	global $_sed_sf_using_glz_custom_fields;
	$_sed_sf_using_glz_custom_fields = load_plugin('glz_custom_fields');

	register_callback( '_sed_sf_handle_article_pre' ,  'article' , '' , 1 );
	register_callback( '_sed_sf_handle_article_post' , 'article' );
	register_callback( '_sed_sf_section_markup' ,      'section' , '' , 1 );
	register_callback( '_sed_sf_xml_server'     ,      'sed_sf' );

	switch(gps('sed_resources') )
		{
		case 'sed_sf_js':
			_sed_sf_js();
			break;
		default:
			break;
		}
	}

function _sed_sf_make_section_key( $section )
	{
	$key = 'sed_sf_' . $section . '_field_labels';
	return $key;
	}

function _sed_sf_get_max_field_number()
	{
	static $max;

	if( !isset( $max ) )
		{
		if( is_callable( 'glz_custom_fields_MySQL' ) )
			$max = (int)count( glz_custom_fields_MySQL("all") );
		else
			$max = 10;
		}

	return $max;
	}

function _sed_sf_get_cf_char()
	{
	global $_sed_sf_using_glz_custom_fields;

	$c = ( $_sed_sf_using_glz_custom_fields ) ? '_' : '-';

	return $c;
	}



#===============================================================================
#	Routines to handle admin presentation > sections tab...
#===============================================================================
function _sed_sf_inject_section_admin( $page )
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

	$write_tab_header = _sed_sf_gtxt( 'write_tab_heading' );

	$rows = safe_rows_start( '*' , 'txp_section' , "1=1" );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$name  = $row['name'];
			$title = $row['title'];
			$title = strtr( $title , array( "'"=>'&#39;' , '"'=>'&#34;' ) );

			$cf_names = _sed_sf_get_cfviz( $name );

			$f = '<input type="text" name="name" value="' . $name . '" size="20" class="edit" tabindex="1" /></td></tr>'. n.n . '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . gTxt('section_longtitle') . ': </td><td class="noline"><input type="text" name="title" value="' . $title . '" size="20" class="edit" tabindex="1" /></td></tr>';

			$r = n.n.'<tr><td colspan="2">'.$write_tab_header.'</td></tr>'.n;
			$max = _sed_sf_get_max_field_number();
			for( $x = 1; $x <= $max; $x++ )
				{
				$value = $cf_names[ $x ];
				$field_name = 'cf_' . $x . '_set';
				$global_label = $prefs [ 'custom_' . $x . '_set' ];
				$args  = array( '{global_label}'=>$global_label , '{cfnum}'=>$x );
				$label = _sed_sf_gtxt( 'hide_cf', $args );
				if( !empty( $global_label ) )
					{
					#	Only bother showing the show/hide radio buttons if the global field label exists.
					$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . $label . '</td><td class="noline">';
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

function _sed_sf_section_markup( $event , $step )
	{
	if( $step == 'section_save' )
		_sed_sf_update_section_field_data();

	if( $step == '' || $step == 'section_save' )
		ob_start( '_sed_sf_inject_section_admin' );
	}

function _sed_sf_update_section_field_data()
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


	if( 1 )		# Handle custom field visibility...
		{
		#
		#	Build array of submitted values...
		#
		$data = array();
		$max = _sed_sf_get_max_field_number();
		for( $x = 1; $x <= $max; $x++ )
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

	if( 1 )		# Handle section visibility in write tab
		{
		}
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

#===============================================================================
#	Routines to handle admin content > write tab...
#===============================================================================
function _sed_sf_xml_serve_cfvisibility( $event , $step )
	{
	global $prefs;

	$section = gps( 'section' );
	$cf_names = _sed_sf_get_cfviz( $section );

	$r = '';
	$max = _sed_sf_get_max_field_number();
	for( $x=1 ; $x <= $max; $x++)
		{
		$r .= @$cf_names[ $x ];
		if ($x < $max) $r .= ' | ';
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
	global $max;
	$max = _sed_sf_get_max_field_number();
	ob_start( '_sed_sf_inject_into_write' );
	}
function _sed_sf_handle_article_post( $event , $step )
	{
	echo n."<script src='" .hu."textpattern/index.php?sed_resources=sed_sf_js' type='text/javascript'></script>".n;
	}


function _sed_sf_inject_into_write( $page )
	{
	#
	#	Inject section-change event handler
	#
	$f = '</a>]</span><br /><select id="section" ';
	$r = 'onchange="_sed_sf_on_section_change()" ';
	$page = str_replace( $f , $f.$r , $page );

	return $page;
	}



#===============================================================================
#	Javascript resources...
#===============================================================================
function _sed_sf_js()
	{
	$debug = false;
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
		$c = _sed_sf_get_cf_char();
		$max = _sed_sf_get_max_field_number();
		echo <<<js
			var _sed_sf_section_select = null;
			var _sed_sf_last_req       = "";
			var _sed_cf_char           = "$c";
			var _sed_cf_max            = $max;
			var _sed_sf_xml_manager    = false;
			if( window.XMLHttpRequest )
				{
				_sed_sf_xml_manager = new XMLHttpRequest();
				}

			function _sed_sf_add_load_event(func)
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
			_sed_sf_add_load_event( function(){_sed_sf_js_init();} );
			function _sed_sf_js_init()
				{
				if (!document.getElementById)
					{
					return false;
					}
				_sed_sf_section_select = document.getElementById('section');
				_sed_sf_on_section_change();

				// Do what Rob Sables' rss_admin_show_adv_opts does...
				// TODO: Parametirise this, show/hide on a per-section basis!
				toggleDisplay('advanced');
				}
			function _sed_sf_make_xml_req(req,req_receiver)
				{
				if( !_sed_sf_xml_manager || (req_receiver == null) )
					return false;

				if( (_sed_sf_last_req != req) && (req != '') )
					{
					if( _sed_sf_xml_manager && _sed_sf_xml_manager.readyState < 4 )
						{
						_sed_sf_xml_manager.abort();
						}
					if( window.ActiveXObject )
						{
						_sed_sf_xml_manager = new ActiveXObject("Microsoft.XMLHTTP");
						}

					_sed_sf_xml_manager.onreadystatechange = req_receiver;
					_sed_sf_xml_manager.open("GET", req);
					_sed_sf_xml_manager.send(null);
					_sed_sf_last_req = req;
					}
				}
			function _sed_sf_request_section_custom_field_visibility( section )
				{
				var req = "?event=sed_sf&step=get_cfvisibility&section=" + section;
				_sed_sf_make_xml_req( req , _sed_sf_field_vizibility_result_handler );
				}
			function _sed_sf_field_vizibility_result_handler()
				{
				if (_sed_sf_xml_manager.readyState == 4)
					{
					var results = _sed_sf_xml_manager.responseText;
					var cf_viz  = results.split( "|" );

					for( x = 1; x <= _sed_cf_max ; x++ )
						{
						var hide  = cf_viz[ x-1 ];
						var para = 'p:has(label[for=custom' + _sed_cf_char + x + '])';

						hide = _sed_sf_trim( hide );
						if( hide == '1' )
							$(para).hide();
						else
							$(para).show();
						}
					}
				}
			function _sed_sf_trim(term)
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
			function _sed_sf_on_section_change()
				{
				var section = _sed_sf_section_select.value;
				_sed_sf_request_section_custom_field_visibility( section );
				}
js;
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

Introduces section-specific overrides for admin interface fields.

h2(#changelog). Change Log

v0.2

* Knows how to hide glz_custom_fields too.

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
