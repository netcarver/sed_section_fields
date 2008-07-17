<?php

$plugin['name'] = 'sed_section_fields';
$plugin['version'] = '0.4';
$plugin['author'] = 'Netcarver';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides admin interface field customisation on a per-section basis.';
$plugin['type'] = '1';
$plugin['order'] = 5;

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

require_plugin('sed_plugin_library');

if( !defined('sed_sf_prefix') )
	define( 'sed_sf_prefix' , 'sed_sf' );

#===============================================================================
#	Admin interface features...
#===============================================================================
if( @txpinterface === 'admin' )
	{
	add_privs('sed_sf', '1,2,3,4,5,6');
	add_privs('sed_sf.static_sections', '1' );	# which users always see all sections in the write-tab select box

	global $_sed_sf_using_glz_custom_fields , $prefs, $textarray , $_sed_sf_l18n , $sed_sf_prefs , $_sed_sf_field_keys , $event , $step;

	#===========================================================================
	#	Strings for internationalisation...
	#===========================================================================
	$_sed_sf_l18n = array(
		'write_tab_heading' 	=> 'Write Tab Fields...',
		'hide_cf' 				=> '{global_label} (#{cfnum})',
		'hide_section'			=> 'Hide from non-publishers?',
		'hide_all_text'			=> 'Hide all?',
		'show_all_text'			=> 'Show all?',
		'alter_section_tab'		=> 'Alter Presentation > Section tab?',
		'filter_label'			=> 'Filter&#8230;',
		'filter_limit'			=> 'Show section index filter after how many sections?',
		'hide'					=> 'Hide',
		'show'					=> 'Show',
		);
	$mlp = new sed_lib_mlp( 'sed_section_fields' , $_sed_sf_l18n , '' , 'admin' );

	#===========================================================================
	#	Plugin preferences...
	#===========================================================================
	$sed_sf_prefs = array
		(
		'alter_section_tab'	=> array( 'type'=>'yesnoradio' , 'val'=>'0' ) ,
		'filter_limit' 		=> array( 'type'=>'text_input' , 'val'=>'18' ) ,
		);

	#===========================================================================
	#	Shorthand for storage in prefs...
	#===========================================================================
	$_sed_sf_field_keys = array(
		'kw'=>'keywords' , 'of'=>'override-form' , 'ai'=>'article-image' , 'uot'=>'url-title'
		);

	#===========================================================================
	#	glz_custom_fields present or not?
	#===========================================================================
	$_sed_sf_using_glz_custom_fields = load_plugin('glz_custom_fields');

	#===========================================================================
	#	Textpattern event handlers...
	#===========================================================================
	register_callback( '_sed_sf_handle_article_pre' ,  'article' , '' , 1 );
	register_callback( '_sed_sf_handle_article_post' , 'article' );
	register_callback( '_sed_sf_handle_section_post' , 'section' );
	register_callback( '_sed_sf_section_markup' ,      'section' , '' , 1 );
	register_callback( '_sed_sf_xml_server'     ,      'sed_sf' );
	register_callback( '_sed_sf_handle_prefs_pre' , 'prefs' , 'advanced_prefs' , 1 );

	#===========================================================================
	#	Serve resource requests...
	#===========================================================================
	switch(gps('sed_resources') )
		{
		case 'sed_sf_write_js':
			require_privs( 'article' );
			_sed_sf_write_js();
			break;

		case 'sed_sf_section_js':
			require_privs( 'section' );
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
	static $c = false;
	
	if( false === $c )
		{
		#	glz_custom_fields present or not?
		global $_sed_sf_using_glz_custom_fields;

		$c = '-';
		
		if( $_sed_sf_using_glz_custom_fields )
			{
			#	Now have to check for the change in markup from 1.1.3 to 1.2!			
			global $plugins_ver;
			$glz_ver = @$plugins_ver['glz_custom_fields'];
			
			if( version_compare( $glz_ver , '1.2' , '<') ) 
				$c = '_';
			}
		}
	
	#echo br , 'Cf character [' , $c , ']';
	return $c;
	}


#===============================================================================
#	Data access routines...
#===============================================================================
function _sed_sf_prefix_key($key)
	{
	return sed_sf_prefix.'-'.$key;
	}
function _sed_sf_install_pref($key,$value,$type)
	{
	global $prefs , $textarray , $_sed_sf_l18n;
	$k = _sed_sf_prefix_key( $key );
	if( !array_key_exists( $k , $prefs ) )
		{
		set_pref( $k , $value , sed_sf_prefix , 1 , $type );
		$prefs[$k] = $value;
		}
	# Insert the preference strings for non-mlp sites...
	if( !array_key_exists( $k , $textarray ) )
		$textarray[$k] = $_sed_sf_l18n[$key];
	}
function _sed_sf_remove_prefs()
	{
	safe_delete( 'txp_prefs' , "`event`='".sed_sf_prefix."'" );
	}
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


function _sed_sf_handle_prefs_pre( $event , $step )
	{
	global $prefs, $sed_sf_prefs;

	if( version_compare( $prefs['version'] , '4.0.6' , '>=' ) )
		{
		if( !isset(@$prefs[_sed_sf_prefix_key('filter_limit')]) )
			{
			foreach( $sed_sf_prefs as $key=>$data )
				_sed_sf_install_pref( $key , $data['val'] , $data['type'] );
			}
		}
	else
		_sed_sf_remove_prefs();	
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
	echo n."<script src='/textpattern/index.php?sed_resources=sed_sf_section_js' type='text/javascript'></script>".n;
	_sed_sf_css();
	}

function _sed_sf_showhide_radio($field, $var, $tabindex = '', $id = '')
	{
	global $mlp;

	$vals = array
		(
		'0' => $mlp->gTxt('show'),
		'1' => $mlp->gTxt('hide')
		);

	return sed_lib_radio_set( $vals , $field , $var , $tabindex , $id );
	}

function _sed_sf_inject_section_admin( $page )
	{
	#
	#	Inserts the name text inputs into each sections' edit controls
	# current implementation uses output buffer...
	#
	global $DB , $prefs , $_sed_sf_l18n , $step , $mlp;

	if( !isset( $DB ) )
		$DB = new db;

	if( !isset( $prefs ) )
		$prefs = get_prefs();

	$mlp = new sed_lib_mlp( 'sed_section_fields' , $_sed_sf_l18n , '' , 'admin' );

	$write_tab_header = $mlp->gTxt( 'write_tab_heading' );
	$section_index = '';

	$rows = safe_rows_start( '*' , 'txp_section' , "name != 'default' order by name" );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$name  = $row['name'];
			$title = $row['title'];
			$title = strtr( $title , array( "'"=>'&#39;' , '"'=>'&#34;' ) );

			# Build the list of sections for the section-tab index
			$section_index .= '<li id="sed_section-'.$name.'"><a href="#section-'.$name.'" class="sed_sf_hide_all_but_one">'.$name.'</a></li>';

			$data = _sed_sf_get_data( $name );
			$data_array = sed_lib_extract_name_value_pairs( $data );

			$f = '<input type="text" name="name" value="' . $name . '" size="20" class="edit" tabindex="1" /></td></tr>'. n.n . '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . gTxt('section_longtitle') . ': </td><td class="noline"><input type="text" name="title" value="' . $title . '" size="20" class="edit" tabindex="1" /></td></tr>';

			# Insert custom field visibility controls...
			$cf_names = @$data_array['cf'];
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
					$r .= _sed_sf_showhide_radio( $name.'_cf_'.$x.'_visible' , $value , '' , $field_name );
					if( $count === 2 )
						{
						$showhideall = '<tr><td colspan="2" class="noline" style="text-align: right; vertical-align: middle;">';
						$showhideall .= '<a class="sed_sf_normal" onclick="_sed_sf_showhideall(\''.$name.'\',\'0\')">'.$mlp->gTxt('show_all_text').'</a> / <a class="sed_sf_normal" onclick="_sed_sf_showhideall(\''.$name.'\',\'1\')">'.$mlp->gTxt('hide_all_text').'</a>';
						$showhideall .= '</td></tr>'.n;
						}
					$r .= '</td></tr>'.n;
					}
				}

			$r .= $showhideall.n.'<tr><td colspan="2"></td></tr>'.n;

			# Insert row to control visibility of this section in the write-tab section selector
			$ss = @$data_array['ss'];
			$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">'.$mlp->gTxt('hide_section').'</td><td class="noline">';
			$r .= _sed_sf_showhide_radio( 'hide_'.$name.'_ss' , ($ss[0]) ? $ss[0] : '0' );
			$r .= '</td></tr>'.n;

			$r .= n.'<tr><td colspan="2"></td></tr>'.n.n;
			$page = str_replace( $f , $f.$r , $page );
			}

		#
		#	Insert a JS variable holding the index of sections...
		#
		$newsection = '';
		if( $step == 'section_create' || $step == 'section_save' )
			$newsection = ps('name');

		$filter = '';
		$limit = @$prefs[ _sed_sf_prefix_key('filter_limit') ];
		if( !is_numeric( $limit ) )
			$limit = 18;
		if( $c >= $limit )
			$filter = '<label for="sed_sf_section_index_filter">'.$mlp->gTxt('filter_label').'</label><br /><input id="sed_sf_section_index_filter" type="text" class="edit" />';

		$section_index =	'<div id="sed_sf_section_index_div">'.
							'<form id="sed_sf_filter_form">'.$filter.'</form>'.
						 	'<ol id="sed_sf_section_index" class="sed_sf_section_index">'.
							'<li  id="sed_section-default"><a href="#section-default" class="sed_sf_hide_all_but_one">default</a></li>'.
							$section_index.
							'</ol>'.
							'</div>';
		$section_index = str_replace('"', '\"', $section_index);
		$r = '<script type=\'text/javascript\'> var sed_sf_new_section = "#section-'.$newsection.'"; var sed_sf_section_index = "'.$section_index.'";</script>';
		$f = "<script src='/textpattern/index.php?sed_resources=sed_sf_section_js' type='text/javascript'></script>";
		$page = str_replace( $f , $r.n.$f , $page );
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

	$save_as = $section = gps( 'name' );
	$oldsection = gps( 'old_name' );
	$data = '';

	# renamed section?
	if( $section != $oldsection )
		{
		$oldkey = doSlash( _sed_sf_make_section_key( $oldsection ) );
		safe_delete('txp_prefs', "`name`='$oldkey'");
		if( $oldsection != '' )
			$section = $oldsection;
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

	_sed_sf_store_data( $save_as , $data );
	}


#===============================================================================
#	Routines to handle admin content > write tab...
#===============================================================================
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
	header('Content-Type: text/plain; charset=utf-8');
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
	echo n."<script src='/textpattern/index.php?sed_resources=sed_sf_write_js' type='text/javascript'></script>".n;
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
#	CSS resources...
#===============================================================================
function _sed_sf_css()
	{
	echo <<<css
		<style>
		div#sed_sf_section_index_div {
		float: left;
		margin: 2em;
		margin-top: 0;
		border-right: 1px solid #ccc;
		padding: 20px 20px 20px 0;
		}
		div#sed_sf_section_index_div ul , div#sed_sf_section_index_div ol {
		margin: 2em 0;
		}
		form#sed_sf_filter_form {
		margin-top: 1em;
		}
		.sed_sf_normal {
		cursor: default;
		}
		</style>
css;
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
	global $prefs;

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

	#	Old versions can't handle the re-arranged screen!
	if( version_compare( $prefs['version'] , '4.0.6' , '<' ) )
		exit();

	if( @$prefs[_sed_sf_prefix_key('alter_section_tab')] == '1' )
	echo <<<js
	/*
	Idea based on "hide all except one" jQuery code by Charles Stuart...
	*/
	function sed_sf_hide_all_but_one(el)
		{
		$('table#list>tbody>tr').hide();
		$('table#list tr' + el).show();
		}

	function sed_sf_filter_list( list , filter )
		{
		if( filter == '' )
			list.find('li').show();
		else
			{
			list.find('li').hide();
			var select = "li[@id^='sed_section-"+filter+"']";
			list = list.find( select );
			list.show();
			}
		}

	$(document).ready
		(
		function()
			{
			// Insert index of sections...
			$("#list").before( sed_sf_section_index );

			// Insert an #section-default id into the row containing the Default form...
			var row = $('table#list>tbody>tr:nth-child(2)');
			row.attr( "id" , "section-default" );

			var replace_point = $('#sed_sf_filter_form');

			// Move the h1 and create form from the table to the index...
			var source = $('table#list>tbody>tr:first>td:first');
			replace_point.before( source.html() );

			// Filter the list every time the filter text is updated...
			$('input#sed_sf_section_index_filter').keyup
				(
				function()
					{
					var filter = $('input#sed_sf_section_index_filter').val();
					var index = $('ol#sed_sf_section_index');
					sed_sf_filter_list( index , filter );
					}
				);

			// Add click handlers that show only that section's row..
			$('a.sed_sf_hide_all_but_one').click
				(
				function()
					{
					var href = $(this).attr('href');
					sed_sf_hide_all_but_one(href);
					}
				);

			//	Setup initial state of the section table...
			if( sed_sf_new_section == '#section-' )	// New section
				sed_sf_new_section = window.location.hash;
			if( sed_sf_new_section == '' )				// No section so show default
				sed_sf_new_section = '#section-default';
			sed_sf_hide_all_but_one(sed_sf_new_section);
			//window.scrollTo(0,0); // Doesn't align exactly on save but gives access to nav withouth scrolling up
			window.location.hash = sed_sf_new_section;
			}
		);
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

If the link doesn't work for you and you are running on a localhost server configuration, you can *try* typing the following into your browser to try to force an update...
http://localhost/your-site-name-here/textpattern/index.php?sed_resources=update_data_format

Change 'your-site-name-here' to the name of your local site.


h2(#changelog). Change Log

h3. v0.4

* Adds new layout for "Presentation > Section" tab. You can turn the new layout on and off from the "Admin > Prefs > Advanced" page. Look for the *sed_sf* preferences towards the bottom of the screen.
* Adds a "live" filter to the section index on the new section tab. *NB* This will only appear once the limit specified in "Admin > Prefs > Advanced > sed_sf" is exceeded.
* Bugfix: Error console/IE errors due to a text/xml header being sent for text/plain data.
* Bugfix: PHP notices (treated as errors in some setups) stop the section tab working.

h3. v0.3

* Adds a "Show all" and "Hide all" link under custom field lists to allow all of them to be turned on or off with one click (don't forget to save your change!)
* Now allows sections to be marked as 'static' for exclusion from the write tab's section select list *for non-publishers*.
* Depends upon sed_plugin_lib for MLP support and compact storage format (thanks Dale.)
* Bugfix: Removed limit of 20 custom fields with glz_custom_fields (thanks Dale.)
* Bugfix: Creating new sections now shows the custom controls.
* Bugfix: Renaming a section now preserves existing sed_sf data.
* Using jQuery -- should now work on IE.

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