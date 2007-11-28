<?php



$plugin['revision'] = '$LastChangedRevision$';

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
$plugin['author'] = 'Stephen Dickinson';
$plugin['author_uri'] = 'http://txp-plugins.netcarving.com';
$plugin['description'] = 'Provides per-section custom field labels';
$plugin['type'] = '1';

@include_once('../zem_tpl.php');

if (0) {
?>
<!-- CSS SECTION
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
-->
<!-- HELP SECTION
# --- BEGIN PLUGIN HELP ---
<div id="sed_sf_help">

h1(#top). SED Section Fields Help.

Introduces section-specific named overrides for custom fields.

h2(#changelog). Change Log

v0.1

* Use the presentations > section tab to setup custom field names for any article
in that section.
* When you write or edit an article, your per-section custom fields will appear
-- possibly overriding the site-wide labels.
* If you change the section of an article and then edit it, the new sections
labels will appear.

 <span style="float:right"><a href="#top" title="Jump to the top">top</a></span>

</div>
# --- END PLUGIN HELP ---
-->
<?php
}
# --- BEGIN PLUGIN CODE ---

if( @txpinterface === 'admin' )
	{
	register_callback( '_sed_sf_handle_article' , 'article' , '' , 1 );
	register_callback( '_sed_sf_insert_cfnames' , 'section' , '' , 1 );
	register_callback( '_sed_sf_xml_server'     , 'sed_sf' );
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
	global $DB;

	if( !isset( $DB ) )
		$DB = new db;

	$rows = safe_rows_start( '*' , 'txp_section' , "1=1" );
	$c = @mysql_num_rows($rows);
	if( $rows && $c > 0 )
		{
		while( $row = nextRow($rows) )
			{
			$name  = $row['name'];
			$title = $row['title'];

			$cf_names = _sed_sf_get_cfnames( $name );

			$f = '<input type="text" name="name" value="' . $name . '" size="20" class="edit" tabindex="1" /></td></tr>'. n.n . '<tr><td class="noline" style="text-align: right; vertical-align: middle;">' . gTxt('section_longtitle') . ': </td><td class="noline"><input type="text" name="title" value="' . $title . '" size="20" class="edit" tabindex="1" /></td></tr>';

			$r = '';
			for( $x = 1; $x <= 10; $x++ )
				{
				$value = $cf_names[ $x ];
				$field_name = 'cf_' . $x . '_set';
				$r .= '<tr><td class="noline" style="text-align: right; vertical-align: middle;">Label for custom field #' . $x . ': </td><td class="noline">';
				$r .= '<input name="' . $field_name . '" value="'.$value.'" size="20" class="edit" type="text" >';
				$r .= '</td></tr>'.n;
				}
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
		$field_name = 'cf_' . $x . '_set';
		$value = ps( $field_name );
		if( isset( $value ) )
			$data[$x] = $value;
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

function _sed_sf_get_cfnames( $section )
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

function _sed_sf_xml_serve_cfnames( $event , $step )
	{
	global $prefs;

	$section = gps( 'section' );
	$cf_names = _sed_sf_get_cfnames( $section );

	$r = '';
	for( $x=1 ; $x <= 10; $x++)
		{
		$label = @$cf_names[ $x ];
		if( empty($label) )
			{
			#
			#	If no section-level label defined then use the default label for
			# this field.
			#
			$def_label = 'custom_'.$x.'_set';
			$label = $prefs[ $def_label ];
			}
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
		case 'get_cfnames' :
			$r =  _sed_sf_xml_serve_cfnames( $event , $step );
			break;
		default:
			$r = '';
			break;
		}

	echo $r;
	exit;
	}

function _sed_sf_handle_article( $event , $step )
	{
	#
	#	Makes sure all the custom fields are always present on the write-tab page.
	#	Javascript running on the page will take care of hiding un-used fields
	# and renaming used fields.
	#
	global $prefs;

	for( $x = 1; $x < 11; $x++ )
		{
		$item = 'custom_' . $x . '_set';
		$prefs[ $item ] = $x;
		}

	#
	#	Insert our javascript handler for section changes...
	#
	ob_start( '_sed_sf_inject_js' );
	}


function _sed_sf_inject_js( $page )
	{
	#
	#	This output buffer processing routine injects our javascript into the
	# write tab head area.
	#
	$sed_sf_jscript = <<<end_js
	var _sed_sf_section_select = null;
	var _sed_sf_last_req       = "";
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
function _sed_sf_request_section_custom_field_names( section )
	{
	var req = "?event=sed_sf&step=get_cfnames&section=" + section;
	_sed_sf_make_xml_req( req , _sed_sf_field_name_result_handler );
	}
function _sed_sf_field_name_result_handler()
	{
	if (_sed_sf_xml_manager.readyState == 4)
		{
		var results = _sed_sf_xml_manager.responseText;

		var cf_names = results.split( "|" );

		for( x = 1; x <= 10 ; x++ )
			{
			var text  = cf_names[ x-1 ];
			var label = document.getElementById('custom-' + x + '-label' );
			var para  = document.getElementById('custom-' + x + '-para' );

			text = _sed_sf_trim( text );

			if( text.length > 0 )
				{
				label.innerHTML = text;
				para.style.display=""
				}
			else
				para.style.display="none"
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
	_sed_sf_request_section_custom_field_names( section );
	}
end_js;

	#
	#	Inject javascript
	#
	$f = '<script type="text/javascript" src="jquery.js"></script>';
	$r = '<script type="text/javascript">' . $sed_sf_jscript . '</script>';
	$page = str_replace( $f , $f.n.$r.n , $page );

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

# --- END PLUGIN CODE ---

?>
