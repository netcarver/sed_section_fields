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
$plugin['version'] = '0.3' . $revision;
$plugin['author'] = 'Netcarver';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides admin interface field customisation on a per-section basis.';
$plugin['type'] = '1';
$plugin['order'] = 5;

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

global $_sed_sf_using_glz_custom_fields;

@require_plugin('sed_plugin_library');

#===============================================================================
#	Strings for internationalisation...
#===============================================================================
global $_sed_sf_l18n;
$_sed_sf_l18n = array(
	'write_tab_heading' 	=> 'Write Tab Fields...',
	'hide_cf' 				=> 'Hide "{global_label}" (cf#{cfnum}) ?',
	'hide_section'			=> 'Treat as a static section?',
	'hide_all_text'			=> 'Hide all?',
	'show_all_text'			=> 'Show all?',
	);


#===============================================================================
#	Admin interface features...
#===============================================================================
if( @txpinterface === 'admin' )
	{
	add_privs('sed_sf' , '1,2,3,4,5,6');
	add_privs('sed_sf.static_sections', '1' );	# which users always see all sections in the write-tab select box

	global $_sed_sf_using_glz_custom_fields;
	$_sed_sf_using_glz_custom_fields = load_plugin('glz_custom_fields');

	register_callback( '_sed_sf_handle_article_pre' ,  'article' , '' , 1 );
	register_callback( '_sed_sf_handle_article_post' , 'article' );
	register_callback( '_sed_sf_handle_section_post' , 'section' );
	register_callback( '_sed_sf_section_markup' ,      'section' , '' , 1 );
	register_callback( '_sed_sf_xml_server'     ,      'sed_sf' );

	switch(gps('sed_resources') )
		{
		case 'sed_sf_write_js':
			_sed_sf_write_js();
			break;

		case 'sed_sf_section_js':
			_sed_sf_section_js();
			break;

		case 'update_data_format': # Only for upgrades from v2 to v3+ of the plugin.
			_sed_sf_upgrade_storage_format();
			$uri = 'http://' . $GLOBALS['siteurl'] . '/textpattern/index.php?event=prefs';
			txp_status_header("302 Found");
			header("Location: $uri");
			exit;
			break;

		default:
			break;
		}
	}

function _sed_sf_get_max_field_number()
	{
	static $max;

	if( !isset( $max ) )
		{
		if( is_callable( 'glz_all_custom_fields' ) )
			{
			$result = glz_all_custom_fields();
			$max = count( $result );

			#
			#	Account for non-consecutive glz_cf's that might have been deleted
			# This is done by reading the index of the last glz_cf...
			#
			$max = (int)substr( $result[$max - 1]['custom_set'], 7, 2 );
			if( $max < 10 )
				$max = 10;
			unset( $result );
			}
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
#	Data access routines...
#===============================================================================
function _sed_sf_make_section_key( $section )
	{
	return 'sed_sf_' . $section . '_field_labels';
	}

function _sed_sf_store_data( $section , $value )
	{
	global $prefs;
	$key = _sed_sf_make_section_key( $section );
	set_pref( doSlash( $key ) , doSlash( $value ) , 'sed_sf' , 2 );
	$prefs[ $key ] = $value;
	}

function _sed_sf_get_data( $section )
	{
	global $prefs;

	$key = _sed_sf_make_section_key( $section );
	return ( isset( $prefs[ $key ] ) ) ? $prefs[ $key ] : '' ;
	}

function _sed_sf_for_each_section_cb( $fn , $fn_data='', $where = "name != 'default'" )
	{
	#	Iterates over the sections, calling the given function to process them.
	if( !is_callable( $fn ) )
		return false;

	$rows = safe_rows_start( '*' , 'txp_section' , $where );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$section = $row['name'];
			call_user_func( $fn , $section , $row , $fn_data );
			}
		}

	return true;
	}

function _sed_sf_upgrade_storage_format()
	{
	function _sed_sf_upgrade_section_data( $section )
		{
		$r = $data = _sed_sf_get_data( $section );
		$data=@unserialize($data);
		if( is_array( $data ) )
			{
			$r = '';
			foreach( $data as $x=>$value )
				$r .= ($value) ? '1' : '0' ;
			$r = 'cf="'.$r.'";';
			}
		_sed_sf_store_data( $section , $r );
		}

	_sed_sf_for_each_section_cb( '_sed_sf_upgrade_section_data' );
	}


#===============================================================================
#	Routines to handle admin presentation > sections tab...
#===============================================================================
function _sed_sf_ps_extract( $section , $key )
	{
	$field_name = 'hide_'.$section.'_'.$key;
	$value = ps( $field_name );
	$d = (empty( $value )) ? '0' : '1';
	return $key.'="'.$d.'";';
	}

function _sed_sf_handle_section_post( $event , $step )
	{
	echo n."<script src='" .hu."textpattern/index.php?sed_resources=sed_sf_section_js' type='text/javascript'></script>".n;
	}

function _sed_sf_inject_section_admin( $page )
	{
	#
	#	Inserts the name text inputs into each sections' edit controls
	# current implementation uses output buffer...
	#
	global $DB , $prefs , $_sed_sf_l18n;

	if( !isset( $DB ) )
		$DB = new db;

	if( !isset( $prefs ) )
		$prefs = get_prefs();

	$mlp = new sed_lib_mlp( 'sed_section_fields' , $_sed_sf_l18n );

	$write_tab_header = $mlp->gTxt( 'write_tab_heading' );

	$rows = safe_rows_start( '*' , 'txp_section' , "name != 'default' order by name" );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$name  = $row['name'];
			$title = $row['title'];
			$title = strtr( $title , array( "'"=>'&#39;' , '"'=>'&#34;' ) );

			$data = _sed_sf_get_data( $name );
			$data_array = sed_lib_extract_name_value_pairs( $data );

			$f = '<input type="text" name="name" value="' . $name . '" size="20" class="edit" tabindex="1" /></td></tr>'. n.n . '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . gTxt('section_longtitle') . ': </td><td class="noline"><input type="text" name="title" value="' . $title . '" size="20" class="edit" tabindex="1" /></td></tr>';

			# Insert custom field visibility controls...
			$cf_names = $data_array['cf'];
			$r = n.n.'<tr><td colspan="2">'.$write_tab_header.'</td></tr>'.n;
			$max = _sed_sf_get_max_field_number();
			$count = 0;
			$showhideall = '';
			for( $x = 1; $x <= $max; $x++ )
				{
				$value = $cf_names[ $x-1 ];
				$field_name = 'cf_' . $x . '_set';
				$global_label = $prefs [ 'custom_' . $x . '_set' ];
				$args  = array( '{global_label}'=>$global_label , '{cfnum}'=>$x );
				$label = $mlp->gTxt( 'hide_cf', $args );
				if( !empty( $global_label ) )
					{
					#	Only bother showing the show/hide radio buttons if the global field label exists.
					$count += 1;
					$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . $label . '</td><td class="noline">';
					$r .= yesnoradio( $name.'_cf_'.$x.'_visible' , $value , '' , $field_name );
					if( $count === 2 )
						{
						$showhideall = '<tr><td colspan="2" class="noline" style="text-align: right; vertical-align: middle;">';
						$showhideall .= '<a onclick="_sed_sf_showhideall(\''.$name.'\',\'0\')">'.$mlp->gTxt('show_all_text').'</a> / <a onclick="_sed_sf_showhideall(\''.$name.'\',\'1\')">'.$mlp->gTxt('hide_all_text').'</a>';
						$showhideall .= '</td></tr>'.n;
						}
					$r .= '</td></tr>'.n;
					}
				}

			$r .= $showhideall.n.'<tr><td colspan="2"></td></tr>'.n;

			# TODO: Insert row to control visibility of this section in the write-tab section selector
			$ss = $data_array['ss'];
			$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">'.$mlp->gTxt('hide_section').'</td><td class="noline">';
			$r .= yesnoradio( 'hide_'.$name.'_ss' , ($ss[0]) ? $ss[0] : '0' );
			$r .= '</td></tr>'.n;

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
	$data = '';

	# renamed section?
	if( $section !== $oldsection )
		{
		$oldkey = doSlash( _sed_sf_make_section_key( $oldsection ) );
		safe_delete('txp_prefs', "`name`='$oldkey'");
		}

	# Handle custom field visibility...
	$d = '';
	$max = _sed_sf_get_max_field_number();
	for( $x = 1; $x <= $max; $x++ )
		{
		$field_name = $section . '_cf_' . $x . '_visible';
		$value = ps( $field_name );
		$d .= (empty( $value )) ? '0' : '1';
		}
	$data .= 'cf="'.$d.'";';

	# Handle static section marker...
	$data .= _sed_sf_ps_extract( $section , 'ss' );

	_sed_sf_store_data( $section , $data );
	}


#===============================================================================
#	Routines to handle admin content > write tab...
#===============================================================================
global $_sed_sf_static_sections;

function _sed_sf_xml_serve_section_data( $event , $step )
	{
	$result  = '';
	$section = gps( 'section' );
	$what    = gps( 'data-id' );

	$raw_data = _sed_sf_get_data( $section );
	$data_array = sed_lib_extract_name_value_pairs( $raw_data );

	if( $what === 'all' || !array_key_exists($what, $data_array) )
		$result = $raw_data;
	else
		$result = $data_array[$what];

	return $result;
	}
function _sed_sf_xml_server( $event , $step )
	{
	while (@ob_end_clean());
	header('Content-Type: text/xml; charset=utf-8');
	header('Cache-Control: private');

	switch( $step )	# step selects among possible content types...
		{
		case 'get_section_data' :
			$r =  _sed_sf_xml_serve_section_data( $event , $step );
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
	echo n."<script src='" .hu."textpattern/index.php?sed_resources=sed_sf_write_js' type='text/javascript'></script>".n;
	}

function _sed_sf_build_static_section_list( $section , $row )
	{
	global $_sed_sf_static_sections;
	$data = _sed_sf_get_data( $section );
	$data_array = sed_lib_extract_name_value_pairs( $data );
	$ss = $data_array['ss'];
	if( $ss[0] == '1' )
		$_sed_sf_static_sections[] = $section;
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
	# Remove static sections from the select box...
	#
	if( !has_privs('sed_sf.static_sections') )
		{
		global $DB , $prefs , $_sed_sf_static_sections;

		if( !isset( $DB ) )
			$DB = new db;

		if( !isset( $prefs ) )
			$prefs = get_prefs();

		$_sed_sf_static_sections = array();
		_sed_sf_for_each_section_cb( '_sed_sf_build_static_section_list' );
		if( count( $_sed_sf_static_sections ) )
			{
			foreach( $_sed_sf_static_sections as $section )
				{
				$f = '<option value="'.$section.'">'.$section.'</option>'.n;
				$page = str_replace( $f , '' , $page );
				}
			}
		}

	return $page;
	}



#===============================================================================
#	Javascript resources...
#===============================================================================
function _sed_sf_js_headers()
	{
	while( @ob_end_clean() );
	header( "Content-Type: text/javascript; charset=utf-8" );
	header( "Expires: ".date("r", time()+3600) );
	header( "Cache-Control: public" );
	}

function _sed_sf_section_js()
	{
	_sed_sf_js_headers();
	$max = _sed_sf_get_max_field_number();

	echo <<<js
	var _sed_cf_max = $max;

	function _sed_sf_showhideall( section , pos )
		{
		for( x = 1; x <= _sed_cf_max ; x++ )
			{
			var name = "input[@name='" + section + '_cf_' + x + "_visible']:nth(" + pos + ")";
			$(name).attr("checked","checked");
			}
		}
js;
	exit();
	}

function _sed_sf_write_js()
	{
	_sed_sf_js_headers();
		$c = _sed_sf_get_cf_char();
		$max = _sed_sf_get_max_field_number();
		echo <<<js
			var _sed_cf_char           = "$c";
			var _sed_cf_max            = $max;
			var _sed_sf =
				{
				on_section_change : function ( section )
				{
					$.get(
						"../textpattern/index.php?event=sed_sf&step=get_section_data&data-id=cf&section=" + section, {},
						function(result)
						{
					for( x = 1; x <= _sed_cf_max ; x++ )
						{
						var hide  = result.substring( x-1 , x );
						var para = 'p:has(label[for=custom' + _sed_cf_char + x + '])';
						if( hide == '1' )
							$(para).hide();
						else
							$(para).show();
						}
							} ,
						'string'
						);
					}
				}

			function _sed_sf_on_section_change()
				{
				_sed_sf.on_section_change( $("#section").val() );
				}

			$(document).ready
				(
				function()
					{
					_sed_sf_on_section_change();
					$("#advanced").show();
					}
				);
js;
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

h2. Upgrading from version 2

If you are updating for the first time from v2 to v3 (or higher) of this plugin then you
will need to upgrade the section_field preferences by following <a href="/textpattern/index.php?sed_resources=update_data_format" rel="nofollow">this link to upgrade the data.</a>

If the link doesn't work for you and you are running on a localhost server configuration, you could try typing the following into your browser to try to force an update...
http://localhost/your-site-name-here/textpattern/index.php?sed_resources=update_data_format

Change 'your-site-name-here' to the name of your local site.


h2(#changelog). Change Log

h3. v0.3

* Adds a "Show all" and "Hide all" link under custom field lists to allow all of
  them to be turned on or off with one click (but still, don't forget to save your change!)
* Now allows sections to be marked as 'static' for exclusion from the write tab's section select list.
* Depends upon sed_plugin_lib for MLP support and compact storage format (thanks Dale.)
* Fixed Dale's 20 custom field limit with glz_custom_fields.
* Using jQuery -- should now work on IE

h3. v0.2

* Knows how to hide glz_custom_fields too.

h3. v0.1

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
