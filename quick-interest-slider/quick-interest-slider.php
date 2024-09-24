<?php
/*
Plugin Name: Quick Interest Slider
Plugin URI: http://loanpaymentplugin.com/
Description: Interest calculator with slider and multiple display options.
Version: 3.1.1
Author: aerin
Author URI: http://quick-plugins.com/
Text Domain: quick-interest-slider
Domain Path: /languages
License: GPLv2 or later

*/

require_once( plugin_dir_path( __FILE__ ) . '/options.php' );
require_once( plugin_dir_path( __FILE__ ) . '/register.php' );

$qis_forms = 0;

add_shortcode('qis', 'qis_loop');
add_shortcode('qis-subscribe', 'qis_subscribe');
add_shortcode('qisprogress', 'qis_show_progress');

add_action('wp_enqueue_scripts', 'qis_scripts');
add_action('init', 'qis_lang_init');
add_action('wp_head', 'qis_head_css');
add_action('template_redirect', 'qis_upgrade_ipn');

add_action('wp_ajax_qis_get_calculator', 'qis_get_calculator');
add_action('wp_ajax_nopriv_qis_get_calculator', 'qis_get_calculator');

add_action('wp_ajax_qis_get_stylesheet', 'qis_get_stylesheet');
add_action('wp_ajax_nopriv_qis_get_stylesheet', 'qis_get_stylesheet');

add_action('wp_ajax_qis_capture_application', 'qis_capture_application');
add_action('wp_ajax_nopriv_qis_capture_application', 'qis_capture_application');

add_action( 'wp_dashboard_setup', 'qis_add_dashboard_widgets' );

add_filter('plugin_action_links', 'qis_plugin_action_links', 10, 2 );

if (is_admin()) require_once( plugin_dir_path( __FILE__ ) . '/settings.php' );


function qis_add_dashboard_widgets() {
	
	$track	= qis_get_track();
	
	if (isset($track) && $track['enabletracking']) {
		wp_add_dashboard_widget(
			'qis_dashboard_widget',							// Widget slug.
			esc_html__( 'Loan Application Tracking', 'quick-interest-slider' ), // Title.
			'qis_dashboard_widget_render'					// Display function.
		);
	}
}

function qis_dashboard_widget_render() {
	
	$track	= qis_get_track();
	
	if ($track) {
		if (!isset($track['completed'])) $track['completed'] = 0;
		if (!isset($track['visitors'])) $track['visitors'] = 0;
		if (!isset($track['opened'])) $track['opened'] = 0;

		echo '<div style="text-align:center;width:33.3%;float:left"><div>Visitors</div>
		<div style="font-size:30px;text-align:center;">'.esc_html($track['visitors']).'</div></div>';
	
		echo '<div style="text-align:center;width:33.3%;float:left"><div>Form Opened</div>
		<div style="font-size:30px;text-align:center;">'.esc_html($track['opened']).'</div></div>';
		
		echo '<div style="text-align:center;width:33.3%;float:left"><div>Completed</div>
		<div style="font-size:30px;text-align:center;">'.esc_html($track['completed']).'</div></div>';
		
		
		echo '<div style="clear:both"></div>';
	
		if ($track['completed'] > 0) {
			$percent = ($track['completed'] / $track['visitors']) * 100;
			$percent = round($percent, 2);
			echo '<div style="text-align:center;">Percentage completed: '.esc_html($percent).'%</div>';
		}
	} else {
		echo '<p>No tracking data available</p>';
	}

}

function qis_get_calculator() {
	
	$return = ['success' => false];
	
	if (isset($_POST['attributes'])) {
		
		// Pass the shortcode attributes to the qis_loop handler
		$data = qis_loop($_POST['attributes']);
		
		$return['data']		= $data;
		$return['success']	= true;
		
	}
	
	echo wp_json_encode($return);
	
	die();
}

function qis_get_stylesheet() {
	$allowed_html = callback_allowed_html();
	if (isset($_POST['form'])) {
		header('content-type: text/css');
		echo wp_kses(qis_generate_css(),$allowed_html);
	}
	die();
}

function qis_block_init() {
	
	if ( !function_exists( 'register_block_type' ) ) {
		return;
	}
	
	$settings	= qis_get_stored_settings(null);
	
	// Register our block editor script.
	wp_register_script(
		'block',
		plugins_url( 'block.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor' )
	);

	// Register our block, and explicitly define the attributes we accept.
	register_block_type(
		'quick-interest-slider/block', array(
		'attributes' => array(
			'calculator'	=> array(
				'type'=> 'string',
				'default'	=> 1
			),
		),
		'editor_script'	=> 'block', // The script name we gave in the wp_register_script() call.
		'render_callback' => 'qis_loop'
		)
	);
}

add_action( 'init', 'qis_block_init' );

function qis_loop($atts) {
	$allowed_html = callback_allowed_html();
	qis_get_stored_upgrade();
	
	// Shortcode Attributes
	$atts = shortcode_atts(array(
		'calculator'		=> '',
		'currency'			=> '',
		'ba'				=> '',
		'primary'			=> '',
		'secondary'			=> '',
		'loanmin'			=> '',
		'loanmax'			=> '',
		'loaninitial'		=> '',
		'loanstep'			=> '',
		'periodslider'		=> '',
		'periodmin'			=> '',
		'periodmax'			=> '',
		'periodinitial' 	=> '',
		'periodstep'		=> '',
		'period'			=> '',
		'interestslider'	=> '',
		'interestselector'	=> '',
		'interestmin'		=> '',
		'interestmax'		=> '',
		'interestinitial'	=> '',
		'intereststep'		=> '',
		'multiplier'		=> '',
		'triggertype'		=> '',
		'trigger'			=> '',
		'outputtotallabel'	=> '',
		'interesttype'		=> '',
		'totallabel'		=> '',
		'primarylabel'		=> '',
		'secondarylabel'	=> '',
		'usebubble'			=> '',
		'repaymentlabel'	=> '',
		'outputtotal'		=> '',
		'outputrepayments'	=> '',
		'outputhelp'		=> '',
		'application'		=> '',
		'repaymentlabel'	=> '',
		'buttons'			=> '',
		'markers'			=> '',
		'processing'		=> '',
		'adminfee'			=> '',
		'adminfeevalue' 	=> '',
		'textinputs'		=> '',
		'interesttype'		=> '',
		'decimals'			=> '',
		'discount'			=> '',
		'applynow'			=> '',
		'fixedaddition' 	=> '',
		'application'		=> '',
		'fields'			=> '',
		'loanlabel'		 	=> '',
		'termlabel'		 	=> '',
		'interestlabel' 	=> '',
		'parttwo'			=> '',
		'usedownpayment'	=> '',
		'float'			 	=> '',
		'percentages'		=> '',
		'usegraph'			=> '',
		'interestdropdown'	=> '',
		'terminterface' 	=> '',
		'use'				=> ''
	),$atts,'quick-interest-slider');
	
	if (isset($_GET['amount']) && $_GET['amount'])	$atts['loaninitial'] = $_GET['amount'];
	if (isset($_GET['term']) && $_GET['term'])		$atts['periodinitial'] = $_GET['term'];
	
	$dropdown = qis_get_stored_dropdown();
	
	$atts['calculatorname'] = $atts['calculator'] ? $dropdown['forms'][$atts['calculator']] : $dropdown['forms']['one'];
	
	if ($atts['use'] == 'dropdown') $dropdown['use'] = true;
	if ($atts['calculator'] == 'one')	$atts['calculator'] = 1;
	if ($atts['calculator'] == 'two')	$atts['calculator'] = 2;
	if ($atts['calculator'] == 'three') $atts['calculator'] = 3;
	if ($atts['calculator'] == 'four')	$atts['calculator'] = 4;
	if ($atts['calculator'] == 'five')	$atts['calculator'] = 5;
	if ($atts['calculator'] == 'six')	$atts['calculator'] = 6;
	if ($atts['calculator'] == 'seven') $atts['calculator'] = 7;
	if ($atts['calculator'] == 'eight') $atts['calculator'] = 8;
	
	// Pro Version filters
	$qppkey = qis_key();
	if (!isset($qppkey['authorised'])) {
		$atts['loanlabel'] = $atts['termlabel'] = $atts['application'] = $atts['applynow'] = $atts['interestslider'] = $atts['intereselector']= $atts['usedownpayment'] = $atts['terminterface'] = false;
		if ($atts['interesttype'] == 'amortization' || $atts['interesttype'] == 'amortisation') $atts['interesttype'] = 'compound';
	}
	
	global $post;
	
	// Apply Now Button
	
	if (!empty($_POST['qisapply'])) {
		$formvalues = qis_check_key($_POST);
		if (isset($_GET['param'])) {
			$formvalues['param'] = $_GET['param'];
		} else {
			$formvalues['param'] = false;
		}
		$settings = qis_get_stored_settings($formvalues['formname']);
		$dropdown = qis_get_stored_dropdown();
		$url = $settings['applynowaction'];
		if ($settings['applynowquery']) {
			$settings['querystructure'] = str_replace('[total]', $_POST['totalamount'], $settings['querystructure']);
			$settings['querystructure'] = str_replace('[amount]', $_POST['loan-amount'], $settings['querystructure']);
			$settings['querystructure'] = str_replace('[term]', $_POST['loan-period'], $settings['querystructure']);
			$settings['querystructure'] = str_replace('[rate]', $_POST['rate'], $settings['querystructure']);
			$settings['querystructure'] = str_replace('[form]', $formvalues['formname'], $settings['querystructure']);
			$settings['querystructure'] = str_replace('[calculator]', $dropdown['forms'][$formvalues['formname']], $settings['querystructure']);
			if ($formvalues['param']) $settings['querystructure'] = str_replace('[param]', $formvalues['param'], $settings['querystructure']);
			$url = $url.$settings['querystructure'];
		}

		echo "<p>".__('Redirecting....','quick-interest-slider')."</p>";
		echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
        die();
		//wp_redirect( $url );
		//exit();

	// Application Form
		
	} elseif (!empty($_POST['qissubmit'])) {
		$formvalues = $_POST;
		$formerrors = array();
		
		if (!qis_verify_form($formvalues, $formerrors)) {
			return qis_display($atts,$formvalues, $formerrors,null);
		} else {
			$formvalues = qis_process_form($formvalues);
			$apply = qis_get_stored_application_messages($formvalues['formname']);
			if ($apply['enable'] || $atts['parttwo']) return qis_display_application($formvalues,array(),'checked');
			else return	qis_display($atts,$formvalues, $formerrors,'registered');
		}
		
	// Part 2 Application
		
	} elseif (!empty($_POST['part2submit'])) {
		$formvalues = $_POST;
		$formerrors = array();
		if (!qis_verify_application($formvalues, $formerrors)) {
			return qis_display_application($formvalues, $formerrors,null);
		} else {
			qis_process_application($formvalues);
			return qis_display_result($formvalues);
		}

	
	} elseif (!isset($_POST['attributes']) && ($dropdown['use'])) {
		
		// Show Dropdown 
		$dd = '<select id="calculators">';
		$one = 1;
		$i = 1;
		$addition = 'selected="selected"';
		
		foreach ($dropdown['forms'] as $key => $name) {
			if ($name) {
				$dd .= '<option value="'.$i.'" '.$addition.'>'.$name.'</option>';
				if ($one++ == 1) $addition = '';
			}
			$i++;
		}
		
		$dd .= "</select>";
		
		$_POST['attributes'] = [];
		
		// Append The Default Calculator
		$dd .= '<div id="calculator-container">';
		$dd .= qis_loop($atts);
		$dd .= "</div>";
		
		return $dd;
	
	} else { // Default Display
		$formnumber = $atts['calculator'];
		$theform = (!$formnumber || $formnumber == 1) ? 1 : $formnumber;
		$settings = qis_get_stored_settings($theform);
		$track = qis_get_track();
		if (isset($track['enabletracking']) && $track['enabletracking']) {
			@$track['visitors']++;
			update_option('qis_track',$track);
		}
		$arr = explode(",",$settings['interestdropdownvalues']);
		//$values = qis_get_stored_register($theform);
		$values['formname'] = $theform;
		$values['interestdropdown'] = $arr[0];
		$digit1 = wp_rand(1,10);
		$digit2 = wp_rand(1,10);
		if( $digit2 >= $digit1 ) {
			$values['thesum'] = "$digit1 + $digit2";
			$values['answer'] = $digit1 + $digit2;
		} else {
			$values['thesum'] = "$digit1 - $digit2";
			$values['answer'] = $digit1 - $digit2;
		}
		return qis_display($atts,$values ,array(),null);
	}
}

function qis_capture_application() {
	
	$track	= qis_get_track();
	
	if ($track['enabletracking']) {
		$track['opened']++;
		update_option('qis_track', $track);
	}
	echo wp_json_encode(['success' => true]);
	die();
}
// Display the form on the page

function qis_display($atts,$formvalues,$formerrors,$registered) {
	
	global $qis_forms;

	$formnumber = $atts['calculator'];
	$theform	= (!$formnumber || $formnumber == 1) ? 1 : $formnumber;
	$settings	= qis_get_stored_settings($theform);
	$style		= qis_get_stored_style();
	$register	= qis_get_stored_register($theform);
	$table		= qis_get_stored_ouputtable();
	$qppkey		= qis_key();
	$floats		 = false;
	
	$qis_forms++;
	
	foreach ($atts as $item => $key) {
		if ($key) {
			$settings[$item] = esc_attr($key);
		}
	}

	if ($atts['terminterface']) {
		
		$settings['terminterface'] = $atts['terminterface'];
		
		if (!in_array(strtolower($atts['terminterface']),['slider','button'])) {
			$settings['periodslider'] = false;
		} else {
			$settings['periodslider'] = 'checked';
		}
	}
		
	if ($atts['periodslider']) $settings['terminterface'] = 'slider';
	
	// Field override
	
	if ($atts['fields']) $formvalues['fields'] = $atts['fields'];
	if ($atts['application']) $register['application'] = 'checked';
	if ($atts['primary']) $settings['triggers'][0]['rate'] = $atts['primary'];
	if ($atts['secondary']) $settings['triggers'][1]['rate'] = $atts['secondary'];
	if ($atts['trigger']) $settings['triggers'][1]['trigger'] = $atts['trigger'];
	if ($atts['repaymentlabel']) $settings['outputrepayments'] = 'true';

	if ($atts['processing']) {
		if (stristr($atts['processing'],'%')) {
			$settings['adminfee'] = true;
			if (preg_match('/^(\d+?\.?\d*)%$/',$atts['processing'],$matches)) {
				$settings['adminfeevalue'] = trim($matches[1],'.');
				$settings['adminfeetype'] = 'percent';
			}
		} else {
			$settings['adminfee'] = true;
			if (is_numeric($atts['processing'])) {
				$settings['adminfeevalue'] = (float) $atts['processing'];
				$settings['adminfeetype'] = 'fixed';
			}
		}
	}
	
	if ($settings['percentages']) {
		$settings['percentarr'] = array();
		$ratesarray = explode(',',$settings['percentages']);
		for ($i=0;$i<count($ratesarray);$i++)
			$settings['percentarr'][$i] = $ratesarray[$i];
	}
	
	$settings['repaymentlabel'] = preg_replace('/{(\w+)}/','[\1]',$settings['repaymentlabel']);

	if ($settings['ba'] == 'before') {
		$settings['cb'] = $settings['currency'];
		$settings['ca'] = ' ';
	} else {
		$settings['ca'] = $settings['currency'];
		$settings['cb'] = ' ';
	}
	
	if ($register['application']) $settings['application'] = true;
	if (!isset($formvalues['loan-amount'])) $formvalues['loan-amount'] = $settings['loaninitial'];
	if (!isset($formvalues['loan-period'])) $formvalues['loan-period'] = $settings['periodinitial'];
	if (!isset($formvalues['loan-interest'])) $formvalues['loan-interest'] = $settings['interestinitial'];
	if (!isset($formvalues['loan-downpayment'])) $formvalues['loan-downpayment'] = $settings['downpaymentinitial'];
	if ($settings['multiplier'] < 1 || $settings['multiplier'] == false) {$settings['multiplier'] = $formvalues['multiplier'] = 1;}

	$settings['singleperiod'] = $settings['singleperiodlabel'] ? $settings['singleperiodlabel'] : $settings['period'];
	$settings['periodlabel'] = $settings['periodlabel'] ? $settings['periodlabel'] : $settings['period'];
	$settings['offset'] = $register['offset'] ? $register['offset'] : 0;

	if ($style['floatoutput']) $atts['float'] = true;
	
	// Normalize values
	
	$outputA = array();
	
	foreach ($settings as $k => $v) {
		$outputA[$k] = $v; 
		
		if (!is_array($v)) {
		
			if (@strtolower($v) == 'checked') $outputA[$k] = true;
		
			if ($v == '') $outputA[$k] = false;
		
			if (@preg_match('/[0-9.]+/',$v)) $outputA[$k] = (float) $v;
		}
	}
	
	if ($settings['nosliderlabel']) {
		$amountmin = qis_separator($settings['loanmin'],$outputA['separator']);
		$amountmax = qis_separator($settings['loanmax'],$outputA['separator']);
		$periodmin = $settings['periodmin'];
		$periodmax = $settings['periodmax'];
		$interestmin = $settings['interestmin'];
		$interestmax = $settings['interestmax'];
		$downpaymentmin = qis_separator($settings['downpaymentmin'],$outputA['separator']);
		$downpaymentmax = qis_separator($settings['downpaymentmax'],$outputA['separator']);
	} else {
		$amountmin = $settings['cb'].qis_separator($settings['loanmin'],$outputA['separator']).$settings['ca'];
		$amountmax = $settings['cb'].qis_separator($settings['loanmax'],$outputA['separator']).$settings['ca'];
		$periodmin = $settings['periodmin'].' '.$settings['singleperiod'];
		$periodmax = $settings['periodmax'].' '.$settings['periodlabel'];
		$interestmin = $settings['interestmin'].'%';
		$interestmax = $settings['interestmax'].'%';
		$downpaymentmin = $settings['cb'].qis_separator($settings['downpaymentmin'],$outputA['separator']).$settings['ca'];
		$downpaymentmax = $settings['cb'].qis_separator($settings['downpaymentmax'],$outputA['separator']).$settings['ca'];
	}

	if ($settings['onlyslidervalue']) {
		$amountmin = $amountmax = $periodmin = $periodmax = '&nbsp;';
	}
	
	// Shortcode Replacements
	
	$dpf = false;
	
	if ($settings['downpaymentfixed']) $dpf = $settings['cb'].$settings['downpaymentfixed'].$settings['ca'];
	if ($settings['downpaymentpercent'] && $dpf) $dpf = $dpf.' and '.$settings['downpaymentpercent'].'%';
	if ($settings['downpaymentpercent'] && !$dpf) $dpf = $settings['downpaymentpercent'].'%';
	
	if (strpos($settings['repaymentlabel'],'[table]') !== false) {
		
		$outputtable = '<table class="outputtable">';

		$sort = explode(",", $table['sort']);
		foreach ($sort as $name) {
			if ($table['use'.$name]) $outputtable .= '<tr><td class="output-caption">'.$table[$name.'caption'].'</td><td class="values-colour output-values">'.$strongon.'['.$name.']'.$strongoff.'</td></tr>';
		}
	
		$outputtable .= '</table>';
	
		$settings['repaymentlabel'] = str_replace('[table]', $outputtable, $settings['repaymentlabel']);
	}
	
	$arr = array('repaymentlabel','outputtotallabel');
	
	foreach ($arr as $item) {
		$settings[$item] = str_replace('[step]', $settings['periodstep'], $settings[$item]);
		$settings[$item] = str_replace('[amount]', '<span class="repayment"></span>', $settings[$item]);
		$settings[$item] = str_replace('[repayment]', '<span class="repayment"></span>', $settings[$item]);
		$settings[$item] = str_replace('[period]', $settings['singleperiod'], $settings[$item]);
		$settings[$item] = str_replace('[rate]', '<span class="interestrate"></span>', $settings[$item]);
		$settings[$item] = str_replace('[dae]', '<span class="dae"></span>', $settings[$item]);
		$settings[$item] = str_replace('[interest]', '<span class="current_interest"></span>', $settings[$item]);
		$settings[$item] = str_replace('[monthlyrate]', '<span class="monthlyrate"></span>', $settings[$item]);
		$settings[$item] = str_replace('[total]', '<span class="final_total"></span>', $settings[$item]);
		$settings[$item] = str_replace('[discount]', '<span class="discount"></span>', $settings[$item]);
		$settings[$item] = str_replace('[principle]', '<span class="principle"></span>', $settings[$item]);
		$settings[$item] = str_replace('[term]', '<span class="term"></span>', $settings[$item]);
		$settings[$item] = str_replace('[processing]', '<span class="processing"></span>', $settings[$item]);
		$settings[$item] = str_replace('[date]', '<span class="repaymentdate"></span>', $settings[$item]);
		$settings[$item] = str_replace('[percentages1]', '<span class="percentages1"></span>', $settings[$item]);
		$settings[$item] = str_replace('[percentages2]', '<span class="percentages2"></span>', $settings[$item]);
		$settings[$item] = str_replace('[percentages3]', '<span class="percentages3"></span>', $settings[$item]);
		$settings[$item] = str_replace('[percentages4]', '<span class="percentages4"></span>', $settings[$item]);
		
		$settings[$item] = str_replace('[primary]', '<span class="generic_primary"></span>', $settings[$item]);
		$settings[$item] = str_replace('[secondary]', '<span class="generic_secondary"></span>', $settings[$item]);
		
		$settings[$item] = str_replace('[weeks]', '<span class="weeks"></span>', $settings[$item]);
		$settings[$item] = str_replace('[years]', '<span class="years"></span>', $settings[$item]);
		$settings[$item] = str_replace('[weekly]', '<span class="weekly"></span>', $settings[$item]);
		$settings[$item] = str_replace('[monthly]', '<span class="monthly"></span>', $settings[$item]);
		$settings[$item] = str_replace('[annual]', '<span class="annual"></span>', $settings[$item]);
		
		if (isset($qppkey['authorised'])) {
			$settings[$item] = str_replace('[downpayment]', '<span class="downpayment"></span>', $settings[$item]);
			$settings[$item] = str_replace('[fixeddownpayment]', $settings['cb'].$settings['downpaymentfixed'].$settings['ca'], $settings[$item]);
			$settings[$item] = str_replace('[downpaymentpercent]', $settings['downpaymentpercent'].'%', $settings[$item]);
			$settings[$item] = str_replace('[mitigated]', '<span class="mitigated"></span>', $settings[$item]);
		}
	}

	$addFloat = '';
	if ($atts['float']) {
		$addFloat = 'qis-add-float';
	}
	
	// Append the currencies to the rates object

	$outputA['currencies'] = array();
	$s_form = ((isset($_POST['submitted_form']))? $_POST['submitted_form']:'N/A');
	
	$i = 1;
	for ($A_i = 0; isset($settings['currency_array'][$A_i]); $A_i++) {
		$outputA['currencies']['c'.$A_i] = $settings['currency_array'][$A_i];
	}

	$newTriggers = [];
	foreach ($outputA['triggers'] as $k => $v) {
		if ($v['rate'] != '') $newTriggers[] = $v;
	}
	
	$outputA['triggers'] = $newTriggers;
	$outputA['graph'] = ['use' => false];
	$outputA['applynowaction'] = $settings['applynowaction'];
	
	$output = '<script type="text/javascript">';
	$output .= 'qis__rates["qis_'.$qis_forms.'"] = '.wp_json_encode($outputA).';';
	$output .= 'qis_form = '.wp_json_encode($s_form).';';
	$output .= '</script>';
	$output .= $floats;
	$output .= '<form action="" class="qis_form '.$style['border'].'" method="POST" id="qis_'.$qis_forms.'" enctype="multipart/form-data">';
	$output .= '<input type="hidden" name="submitted_form" value="qis_'.$qis_forms.'" />';
	
	if ($settings['formheader']) $output .= '<h2>'.$settings['formheader'].'</h2>';
	
	$output .= '<div class="qis-sections qis-float '.$addFloat.'"><div class="qis-inputs qis-float-columns">';
	
	$output .= '<input type="hidden" name="interesttype" value="'.$settings['interesttype'].'" />';
	
	$sort = explode(",", $settings['sort']);
	
	if ($settings['usedownpaymentslider'] != 'checked') {
		$sort = qis_unset($sort,'downpayment');
	}
	
	foreach($sort as $item) {
	
		if ($item == 'amount') {
			$output .= '<div class="range qis-slider-principal">';
			
			$label = false;
	
			// Principal Slider

			if ($settings['loanlabel'] && $settings['sliderlabelposition'] == 'aboveslider') {
				$output .= '<div class="slider-label">'.$settings['loanlabel'];
				if ($settings['loanhelp']) $output .= qis_tooltip($settings['loaninfo']);
				$output .= '</div>';
			}
			
			if ($settings['loanlabel'] && $settings['sliderlabelposition'] == 'beforeoutput') {
				$label = '<span class="sliderlabel">'.$settings['loanlabel'].' </span>';
			}
	
			if ($settings['textinputs'] != 'slider') $oX = '<input type="text" class="output" value="'.$formvalues['loan-amount'].'" />';
			elseif ($settings['outputlimits']) $oX = '<output></output>';
			else $oX = null;
			
			if ($settings['textinputs'] != 'text') {
				
				if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'slidertop') {
					$output .= qis_outputs (true,$amountmin,$amountmax,$oX,$label);
				} elseif ($settings['maxminlimits']) {
					$output .= qis_outputs (false,$amountmin,$amountmax,$oX,$label);
				} else {
					$output .= qis_outputs (false,'&nbsp;','&nbsp;',$oX,$label);
				}
				
				if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'sliderside') {
					$output .= '<div class="qis_buttons">
					<div class="circle-control minus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM184 232c-13.3 0-24 10.7-24 24s10.7 24 24 24H328c13.3 0 24-10.7 24-24s-10.7-24-24-24H184z"/></svg></div>
					<div><input type="range" name="loan-amount" min="'.$settings['loanmin'].'" max="'.$settings['loanmax'].'" value="'.$formvalues['loan-amount'].'" step="'.$settings['loanstep'].'" data-qis></div>
					<div class="circle-control plus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"/></svg></div></div>';
				} else {
					$output .= '<input type="range" name="loan-amount" min="'.$settings['loanmin'].'" max="'.$settings['loanmax'].'" value="'.$formvalues['loan-amount'].'" step="'.$settings['loanstep'].'" data-qis>';
				}
			
				if ($settings['markers']) {
					$output .= qis_markers($settings,$style['handle-size'],$settings['loanmax'],$settings['loanmin'],$settings['loanstep']);
				}

			} else {
				$output .= '<div>'.$oX.'</div>';
				$label = str_replace('[min]',$amountmin,$settings['amounttext']);
				$label = str_replace('[max]',$amountmax,$label);
				$output .= '<div class="textlabel".>'.$label.'</div>';
				$output .= '<div class="hidethis"><input type="range" name="loan-amount" min="'.$settings['loanmin'].'" max="'.$settings['loanmax'].'" value="'.$formvalues['loan-amount'].'" step="'.$settings['loanstep'].'" data-qis></div>';
			}
			
			$output .= '</div>';
		}
		 
		if ($item == 'term') {
			
			$label = false;
	
			// Term Slider
	
			if ($settings['textinputs'] != 'slider') $oX = '<input type="text" class="output" value="'.$formvalues['loan-period'].'" />';
			elseif ($settings['outputlimits']) $oX = '<output></output>';
			else $oX = null;
			
			if ($settings['periodslider']) {
				
				if ($settings['termlabel'] && $settings['sliderlabelposition'] == 'aboveslider') {
					$output .= '<div class="slider-label">'.$settings['termlabel'];
					if ($settings['periodhelp']) $output .= qis_tooltip($settings['periodinfo']);
					$output .= '</div>';
				}
				
				if ($settings['termlabel'] && $settings['sliderlabelposition'] == 'beforeoutput') {
					$label = '<span class="sliderlabel">'.$settings['termlabel'].' </span>';
				}
				
				$output .= '<div class="range qis-slider-term">';
			
					if ($settings['textinputs'] != 'text') {
						
						if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'slidertop') {
							$output .= qis_outputs (true,$periodmin,$periodmax,$oX,$label);
						} elseif ($settings['maxminlimits']) {
							$output .= qis_outputs (false,$periodmin,$periodmax,$oX,$label);
						} else {
							$output .= qis_outputs (false,'&nbsp;','&nbsp;',$oX,$label);
						}
						
						if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'sliderside') {
							$output .= '<div class="qis_buttons">
							<div class="circle-control minus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM184 232c-13.3 0-24 10.7-24 24s10.7 24 24 24H328c13.3 0 24-10.7 24-24s-10.7-24-24-24H184z"/></svg></div>
							<div><input type="range" name="loan-period" min="'.$settings['periodmin'].'" max="'.$settings['periodmax'].'" value="'.$formvalues['loan-period'].'" step="'.$settings['periodstep'].'" data-qis></div>
							<div class="circle-control plus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"/></svg></div></div>';
						} else {
							$output .= '<input type="range" name="loan-period" min="'.$settings['periodmin'].'" max="'.$settings['periodmax'].'" value="'.$formvalues['loan-period'].'" step="'.$settings['periodstep'].'" data-qis>';
						}
						
						if ($settings['markers']) {
							$output .= qis_markers($settings,$style['handle-size'],$settings['periodmax'],$settings['periodmin'],$settings['periodstep']);
						}
						
					} else {
						$output .= '<div>'.$oX.'</div>';
						$label = str_replace('[min]',$periodmin,$settings['termtext']);
						$label = str_replace('[max]',$periodmax,$label);
						$output .= '<div class="textlabel".>'.$label.'</div>';
						$output .= '<div class="hidethis"><input type="range" name="loan-period" min="'.$settings['periodmin'].'" max="'.$settings['periodmax'].'" value="'.$formvalues['loan-period'].'" step="'.$settings['periodstep'].'" data-qis></div>';
					}
					$output .= '</div>';
			} else {
				$output .= '<input type="hidden" name="loan-period" value="'.$formvalues['loan-period'].'">';
			}
				
		}
		
		if ($item == 'downpayment' && $settings['usedownpaymentslider']) {
			
			$output .= '<div class="range qis-slider-downpayment">';
			
			$label = false;
	
			// Downpayment Slider
			if ($settings['downpaymentlabel'] && $settings['sliderlabelposition'] == 'aboveslider') {
				$output .= '<div class="slider-label">'.$settings['downpaymentlabel'];
				if ($settings['downpaymenthelp']) $output .= qis_tooltip($settings['downpaymentinfo']);
				$output .= '</div>';
			}
			
			if ($settings['downpaymentlabel'] && $settings['sliderlabelposition'] == 'beforeoutput') {
				$label = '<span class="sliderlabel">'.$settings['downpaymentlabel'].' </span>';
			}
	
			if ($settings['textinputs'] != 'slider') $oX = '<input type="text" class="output" value="'.$formvalues['loan-downpayment'].'" />';
			elseif ($settings['outputlimits']) $oX = '<output></output>';
			else $oX = null;
	
			if ($settings['textinputs'] != 'text') {
				
				if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'slidertop') {
					$output .= qis_outputs (true,$downpaymentmin,$downpaymentmax,$oX,$label);
				} elseif ($settings['maxminlimits']) {
					$output .= qis_outputs (false,$downpaymentmin,$downpaymentmax,$oX,$label);
				} else {
					$output .= qis_outputs (false,'&nbsp;','&nbsp;',$oX,$label);
				}
				
				if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'sliderside') {
					$output .= '<div class="qis_buttons">
					<div class="circle-control minus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM184 232c-13.3 0-24 10.7-24 24s10.7 24 24 24H328c13.3 0 24-10.7 24-24s-10.7-24-24-24H184z"/></svg></div>
					<div><input type="range" name="loan-downpayment" min="'.$settings['downpaymentmin'].'" max="'.$settings['downpaymentmax'].'" value="'.$formvalues['loan-downpayment'].'" step="'.$settings['downpaymentstep'].'" data-qis></div>
					<div class="circle-control plus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"/></svg></div></div>';
				} else {
					$output .= '<input type="range" name="loan-downpayment" min="'.$settings['downpaymentmin'].'" max="'.$settings['downpaymentmax'].'" value="'.$formvalues['loan-downpayment'].'" step="'.$settings['downpaymentstep'].'" data-qis>';
				}
				
				if ($settings['markers']) {
					$output .= qis_markers($settings,$style['handle-size'],$settings['downpaymentmax'],$settings['downpaymentmin'],$settings['downpaymentstep']);
				}
			
			} else {
				$output .= '<div>'.$oX.'</div>';
				$label = str_replace('[min]',$downpaymentmin,$settings['downpaymenttext']);
				$label = str_replace('[max]',$downpaymentmax,$label);
				$output .= '<div class="textlabel".>'.$label.'</div>';
				$output .= '<div class="hidethis"><input type="range" name="loan-downpayment" min="'.$settings['downpaymentmin'].'" max="'.$settings['downpaymentmax'].'" value="'.$formvalues['loan-downpayment'].'" step="'.$settings['downpaymentstep'].'" data-qis></div>';
			}
			$output .= '</div>';
		}

		if ($item == 'interest') {
			
			$label = false;
		
			// Interest Slider
	
			if ($settings['textinputs'] != 'slider') $oX = '<input type="text" class="output" value="'.$formvalues['loan-interest'].'" />';
			elseif ($settings['outputlimits']) $oX = '<output></output>';
			else $oX = null;
			
			if ($settings['interestslider'] && !$settings['interestselector'] && !$settings['interestdropdown']) {
		
				if ($settings['interestlabel'] && $settings['sliderlabelposition'] == 'aboveslider') {
					$output .= '<div class="slider-label">'.$settings['interestlabel'];
					if ($settings['interesthelp']) $output .= qis_tooltip($settings['interestinfo']);
					$output .= '</div>';
				}
				
				if ($settings['interestlabel'] && $settings['sliderlabelposition'] == 'beforeoutput') {
					$label = '<span class="sliderlabel">'.$settings['interestlabel'].' </span>';
				}
		
				$output .= '<div class="range qis-slider-interest">';
		
				if ($settings['textinputs'] != 'text') {
					
					if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'slidertop') {
						$output .= qis_outputs (true,$interestmin,$interestmax,$oX,$label);
					} elseif ($settings['maxminlimits']) {
						$output .= qis_outputs (false,$interestmin,$interestmax,$oX,$label);
					} else {
						$output .= qis_outputs (false,'&nbsp;','&nbsp;',$oX,$label);
					}
					
					if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'sliderside') {
						$output .= '<div class="qis_buttons">
						<div class="circle-control minus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM184 232c-13.3 0-24 10.7-24 24s10.7 24 24 24H328c13.3 0 24-10.7 24-24s-10.7-24-24-24H184z"/></svg></div>
						<div><input type="range" name="loan-interest" min="'.$settings['interestmin'].'" max="'.$settings['interestmax'].'" value="'.$formvalues['loan-interest'].'" step="'.$settings['intereststep'].'" data-qis></div>
						<div class="circle-control plus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"/></svg></div></div>';
					} else {
						$output .= '<input type="range" name="loan-interest" min="'.$settings['interestmin'].'" max="'.$settings['interestmax'].'" value="'.$formvalues['loan-interest'].'" step="'.$settings['intereststep'].'" data-qis>';
					}
					
					if ($settings['markers']) {
						$output .= qis_markers($settings,$style['handle-size'],$settings['interestmax'],$settings['interestmin'],$settings['intereststep']);
					}
					
				} else {
					$output .= '<div>'.$oX.'</div>';
					$label = str_replace('[min]',$interestmin,$settings['interesttext']);
					$label = str_replace('[max]',$interestmax,$label);
					$output .= '<div class="textlabel".>'.$label.'</div>';
					$output .= '<div class="hidethis"><input type="range" name="loan-interest" min="'.$settings['interestmin'].'" max="'.$settings['interestmax'].'" value="'.$formvalues['loan-interest'].'" step="'.$settings['intereststep'].'" data-qis></div>';
				}
				
				$output .= '</div>';
			}
			
			// Interest Selectors
			if ($settings['interestselector'] && $qppkey['authorised'] && !$settings['interestslider'] && !$settings['interestdropdown']) {
				$output .= '<div class="checkradio"><ul>';
				if ($settings['interestselectorlabel']) $output .= '<li class="label">'.$settings['interestselectorlabel'].':</li>';
				for ($i = 1; $i <= 4; $i++) {
					if ($settings['interestname'.$i]) {
						$checked = $i == 1 ? 'checked' : '';
						$output .= '<li><input type="radio" name="interestselector" value="'.$i.'" '.$checked.' id="interestname'.$i.'"><label for="interestname'.$i.'"><span></span>'.$settings['interestname'.$i].'</label></li>';
					}
				}
				$output .= '</ul></div>';
				$output .= '<div style="clear:both"></div>';
			}
	
			// Interest Dropdown
			if ($settings['interestdropdown'] && $qppkey['authorised'] && !$settings['interestslider'] && !$settings['interestselector']) {
		
				$arr = explode(",",$settings['interestdropdownvalues']);
				$output .= '<div class="qis-register">';
				if ($settings['interestdropdownlabelposition'] == 'paragraph') 
					$output .= '<p>'.$settings['interestdropdownlabel'].'</p>';
					$output .= '<select name="interestdropdown">';
				if ($settings['interestdropdownlabelposition'] == 'include')
					$output .= '<option value="'.preg_replace("/[^0-9.]/", "", $arr[0]).'">' . $settings['interestdropdownlabel'] . '</option>'."\r\t";
				foreach ($arr as $item) {
					$value = preg_replace("/[^0-9.]/", "", $item);
					$selected = $formvalues['interestdropdown'] == $value ? ' selected="selected"' : '';
					$output .= '<option value="' .	$value . '" ' . $selected .'>' .	$item . '</option>'."\r\t";
				}
				$output .= '</select></div>';
			}
		}
		
		// Loan Breakdown Graph
		
		if ($item == 'graph' && !$atts['float']) {
			
			if ($settings['usegraph']) {
				
				if ($settings['graphlabel']) $output .= '<div class="slider-label">'.$settings['graphlabel'].'</div>';
				$output .= '<div class="qisBar">
				<div class="qisBarProgress1" style="background-color:'.$style['graphdownpayment'].'"></div>
				<div class="qisBarProgress2" style="background-color:'.$style['graphprinciple'].'"></div>';
				if ($settings['adminfeewhen'] == 'beforeinterest' && $settings['adminfee']) $output .= '<div class="qisBarProgress4" style="background-color:'.$style['graphprocessing'].'"></div>';
				$output .= '<div class="qisBarProgress3" style="background-color:'.$style['graphinterest'].'"></div>';
				if ($settings['adminfeewhen'] == 'afterinterest' && $settings['adminfee']) $output .= '<div class="qisBarProgress4" style="background-color:'.$style['graphprocessing'].'"></div>';
				$output .= '</div>';

				$output .= '<div id="qis-totalbar"></div>';
				$output .= '<p class="legend">';
				if ($settings['usedownpayment'] || $settings['usedownpaymentslider']) $output .= '<span style="background-color:'.$style['graphdownpayment'].'"></span> '.$settings['graphdownpayment'].' ';
				if ($settings['discount']) $output .= '<span style="background-color:'.$style['graphdiscount'].'"></span> '.$settings['graphdiscount'].' ';
				$output .= '<span style="background-color:'.$style['graphprinciple'].'"></span> '.$settings['graphprinciple'].' ';
				if ($settings['adminfeewhen'] == 'beforeinterest' && $settings['adminfee']) $output .= '<span style="background-color:'.$style['graphprocessing'].'"></span> '.$settings['graphprocessing'].' ';
				$output .= '<span style="background-color:'.$style['graphinterest'].'"></span> '.$settings['graphinterest'].' ';
				if ($settings['adminfeewhen'] == 'afterinterest' && $settings['adminfee']) $output .= '<span style="background-color:'.$style['graphprocessing'].'"></span> '.$settings['graphprocessing'].' ';
				$output .= '</p>';
			}
		}
		
		if ($item == 'repayments' && !$atts['float']) {
			
			// Display output messages
			if ($settings['outputrepayments']) {
				$output .= '<div class="qis-repayments">'.$settings['repaymentlabel'];
				if ($settings['outputhelp']) $output .= qis_tooltip($settings['outputinfo']);
				$output .= '</div>';
			}
		}

		if ($item == 'total' && !$atts['float']) {
			
			if ($settings['outputtotal']) {
				$output .= '<div class="qis-total">'.$settings['outputtotallabel'].'</div>';
			}
		}
			 
		if ($item == 'apply' && !$atts['float']) {
	
			// Apply Now and Application Form
			if ($register['application'] && $qppkey['authorised']) $output .= qis_display_form($formvalues,$formerrors,$registered).'</div>';
			elseif ($settings['applynow'] && $qppkey['authorised']) $output .= '<div class="qis-apply"><a id="applybutton" href="'.$settings['applynowaction'].'" >'.$settings['applynowlabel'].'</a></div>';
		}
	
		$output .= $settings['outputtable'];
	}
	
	// $output .= '</div>';
		
	if ($atts['float']) {

		$output .= '</div><div class="qis-outputs qis-float-columns">';
	
		// Display output messages
	
		if ($settings['outputrepayments']) {
			$output .= '<div class="qis-repayments">'.$settings['repaymentlabel'];
			if ($settings['outputhelp']) $output .= qis_tooltip($settings['outputinfo']);
			$output .= '</div>';
		}
		 
		if ($settings['outputtotal']) {
			$output .= '<div class="qis-total">'.$settings['outputtotallabel'].'</div>';
		}
	
		$output .= $settings['outputtable'];
	
		// Apply Now and Application Form
	
		if ($register['application'] && $qppkey['authorised']) $output .= qis_display_form($formvalues,$formerrors,$registered).'</div>';
		elseif ($settings['applynow'] && $qppkey['authorised']) $output .= '<div class="qis-apply"><a id="applybutton" href="'.$settings['applynowaction'].'" >'.$settings['applynowlabel'].'</a></div>';
		
		// Close .qis-float
		$output .= '</div>';
	}
	
	$output .= '</div>';
	
	$output .= '<input type="hidden" name="repayment" value="'.@$formvalues['repayment'].'" />';
	$output .= '<input type="hidden" name="totalamount" value="'.@$formvalues['totalamount'].'" />';
	$output .= '<input type="hidden" id="formname" name="formname" value="'.$formvalues['formname'].'" />';
	$output .= '<input type="hidden" id="calculatorname" name="calculatorname" value="'.$atts['calculatorname'].'" />';
	$output .= '<input type="hidden" name="rate" value="" />';
	$output .= '<div id="filechecking"><div class="filecheckingcontent"><img src="'.plugin_dir_url( __FILE__ ).'/img/waiting.gif'.'" alt="Loading"></div></div>';

	$output .= '</div></form>';
	return $output;
}

function qis_markers($settings,$handle,$min,$max,$step) {

	//Put together a step total and output a set of step markers
	$inner_value = (float) $min - $max;
	$pps = $inner_value / $step;
	$ppw = 100 / $pps;
							
	if ($settings['buttons'] && $settings['sliderbuttonposition'] == 'sliderside') {
		$output = '<div class="qis_buttons">
		<div></div>
		<div class="qis_slider_markers" style="position: relative; margin-left: '.($handle/2).'px; margin-right: '.($handle/2).'px; border-left: 1px solid black; border-right: 1px solid black; height: 10px">';
		for ($i = 1; $i < $pps; $i++) {
			$output .= '<div class="qis_slider_marker" style="position: absolute; height: 10px; left: '.$ppw * $i.'%; margin-left: -1px; width: 1px; background-color: black;"></div>';
		}

		$output .= '</div>
		<div></div>
		</div>';
	} else {
		$output .= '<div class="qis_slider_markers" style="position: relative; margin-left: '.($handle/2).'px; margin-right: '.($handle/2).'px; border-left: 1px solid black; border-right: 1px solid black; height: 10px">';
		for ($i = 1; $i < $pps; $i++) {
			$output .= '<div class="qis_slider_marker" style="position: absolute; height: 10px; left: '.$ppw * $i.'%; margin-left: -1px; width: 1px; background-color: black;"></div>';
		}
		$output .= '</div>';
	}
	
	return $output;

}

// Display Values
function qis_outputs($buttons,$min,$max,$oX,$label) {

	if ($buttons) {
		$output = '<div class="qis_slideroutputs">
		<div class="column left circle-control minus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM184 232c-13.3 0-24 10.7-24 24s10.7 24 24 24H328c13.3 0 24-10.7 24-24s-10.7-24-24-24H184z"/></svg></div>
		<span class="column center qis-slidercenter">'.$label.$oX.'</span>
		<div class="column right circle-control plus"><svg xmlns="http://www.w3.org/2000/svg" height="25px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"/></svg></div></div>';
	} else {
		$output = '<div class="qis_slideroutputs">';
		$output .= '<span class="column left qis-sliderleft">'.$min.'</span>';
		$output .= '<span class="column center qis-slidercenter">'.$label.$oX.'</span>';
		$output .= '<span class="column right qis-sliderright">'.$max.'</span>';
		$output .= '</div>';
	}
	return $output;
}

function qis_unset($array,$value) {
	
	$newarray = [];
	foreach ($array as $k => $v) {
		if ($v == $value) unset($array[$v]);
		else $newarray[] = $v;
	}
	return $newarray;
}

// Display Tooltip

function qis_tooltip($text) {
	return '<span class="qis_tooltip_toggle"><a href="javascript:void(0);"></a><div class="qis_tooltip_body"><div class="qis_tooltip_content">'.$text.'</div><div class="close"></div></div></span>';
}

// Enqueue Scripts and Styles

function qis_scripts() {
	$style = qis_get_stored_style();
	if (!$style['nostyles']) wp_enqueue_style( 'qis_style',plugins_url('slider.css', __FILE__));
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_script("jquery-effects-core");
	wp_enqueue_script('qis_script',plugins_url('slider.js?v=1.16', __FILE__ ), array( 'jquery' ), false, true );
	wp_enqueue_style ('jquery-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css');
	wp_localize_script('qis_script', 'qis_application', [
		'ajax_url' => admin_url( 'admin-ajax.php' )
	]);
}

// Dashboard Link

function qis_plugin_action_links($links, $file ) {
	if ( $file == plugin_basename( __FILE__ ) ) {
		$qis_links = '<a href="'.get_admin_url().'options-general.php?page=quick-interest-slider-settings">'.__('Settings','quick-interest-slider').'</a>';
		array_unshift( $links, $qis_links );
		}
	return $links;
}

// Build Custom CSS

function qis_generate_css() {
	$style = qis_get_stored_style();
	
	if ($style['nostyles'] || $style['nocustomstyles']) return;
	
	$border = $radius = $background = false;

	//Slider output on small screens
	$smaller = preg_split('#(?<=\d)(?=[a-z%])#i', $style['output-size']);
	$smaller = (floatval($smaller[0])*0.6).$smaller[1];

	// Handle
	$svgsize = $handlesize = preg_split('#(?<=\d)(?=[a-z%])#i', $style['handle-size']);
	$handlesize[0] = $handlesize[0] - $style['handle-thickness']*2;
	if (!isset($handlesize[1])) $handlesize[1] = 'px';
	
	// Slider bar
	$sliderthickness = preg_split('#(?<=\d)(?=[a-z%])#i', $style['slider-thickness']);
	if (!isset($sliderthickness[1])) $sliderthickness[1] = 'px';
	$slideradius = ($sliderthickness[0] / 2).$sliderthickness[1];
	$slidermargin = (1 + ($handlesize[0] - $sliderthickness[0])/2).'px';
	
	// Handle position
	$handletop = $handlesize[0]/2 - $sliderthickness[0]/2 + $style['handle-thickness'];

	// Form Border
	if ($style['border']<>'none') {
		$border = ".qis_form.".$style['border']." {border:".$style['form-border-thickness']."px solid ".$style['form-border-color']."; padding: ".$style['form-padding']."px;border-radius:".$style['form-border-radius']."px;}";
	}
	if ($style['background'] == 'white') {
		$background = "form.qis_form {background:#FFF;}";
	}
	if ($style['background'] == 'color') {
		$background = "form.qis_form {background:".$style['backgroundhex'].";}";
	}
	if ($style['backgroundimage']) {
		$background = "form.qis_form {background: url('".$style['backgroundimage']."');}";
	}
	
	// form width
	$formwidth = preg_split('#(?<=\d)(?=[a-z%])#i', $style['width']);
	if (!isset($formwidth[1])) $formwidth[1] = 'px';
	if ($style['widthtype'] == 'pixel') $width = $formwidth[0].$formwidth[1];
	else $width = '100%';
	
	$data = $border.$radius.$background.'
.qis_form {width:'.$width.';max-width:100%;}
.qis, .qis__fill {width: 100%;height: '.$sliderthickness[0].$sliderthickness[1].';background: '.$style['slider-background'].';border-radius: '.$slideradius.';}
.qis__fill {background: '.$style['slider-revealed'].';border-radius: '.$slideradius.' 0 0 '.$slideradius.';}
.qis__handle {width: '.$handlesize[0].$handlesize[1].';height: '.$handlesize[0].$handlesize[1].';top: -'.$handletop.'px;background: '.$style['handle-background'].';border: '.$style['handle-thickness'].'px solid '.$style['handle-border'].';position: absolute;border-radius:'.$style['handle-corners'].'%;}
.total {font-weight:bold;border-top:1px solid #FFF;margin-top:6px;text-align:left;}
.qis--horizontal {margin: '.$slidermargin.' 0;}
.qis-slidercenter {color:'.$style['slideroutputcolour'].';font-size:'.$style['output-size'].'px;}
.qis-sliderleft, .qis-sliderright {color:'.$style['toplinecolour'].';font-size:'.$style['toplinefont'].'px;}
.slider-label {color:'.$style['slider-label-colour'].';font-size:'.$style['slider-label-size'].'px;margin:'.$style['slider-label-margin'].';}
.qis-interest, .qis-repayments {color:'.$style['interestcolour'].';font-size:'.$style['interestfont'].'px;margin:'.$style['interestmargin'].';}
.qis-total {color:'.$style['totalcolour'].';font-size:'.$style['totalfont'].'px;margin:'.$style['totalmargin'].';}
.qis_tooltip_body {border: '.$style['tooltipborderthickness'].'px solid '.$style['tooltipbordercolour'].'; background-color: '.$style['tooltipbackground'].'; border-radius: '.$style['tooltipcorner'].'px; color: '.$style['tooltipcolour'].';}
.qis_tooltip_content {overflow: hidden; width: 100%; height: 100%;}
.checkradio input[type=radio]:not(old) + label > span{border:3px solid '.$style['handle-border'].'}
.checkradio input[type=radio]:not(old):checked + label > span{background: '.$style['slider-revealed'].';border: 3px solid '.$style['handle-border'].';}
.circle-control svg {fill: '.$style['buttoncolour'].';height:'.$svgsize[0].'px'.';vertical-align:text-bottom;}
.circle-control svg:hover {fill: '.$style['buttonhover'].';}
.qis-outputs {'.$style['floatcustom'].'}
.qis_buttons, .qis_slideroutputs {line-height:'.$style['output-size'].'px;margin-bottom:'.$style['slideroutputmargin'].'px;}
';
	$table = qis_get_stored_ouputtable();
	$right = $table['values-padding'] * 2;
	$strongon = $table['values-strong'] ? '<strong>' : '';
	$strongoff = $table['values-strong'] ? '</strong>' : '';
	$data .= $table['values-colour'] ? '.outputtable td{padding: 0 '.$right.'px '.$table['values-padding'].'px 0;}.values-colour{color:'.$table['values-colour'].'}' : '';
	
$right = 98 - $style['floatpercentage'];
$data .= '.qis-add-float {display:grid;grid-template-columns:'.$style['floatpercentage'].'% '.$right.'%;grid-gap:2%;}
@media only screen and (max-width:'.$style['floatbreakpoint'].'px) {.qis-add-float{display:block;}
.qis-slidercenter {font-size:'.$smaller.'px;}.qis_buttons, .qis_slideroutputs {margin-bottom:'.($style['slideroutputmargin']/2).'px;}
}';
	
	return $data;
}

// Builds Application Form CSS

function qis_register_css () {
	$allowed_html = callback_allowed_html();
	$code=$header=$input=$submitwidth=$paragraph=$submitbutton=$submit='';
	$style = qis_get_register_style();
	$corners = '-webkit-border-radius:'.$style['corners'].'px;border-radius:'.$style['corners'].'px;';
	$input = '.qis-register input[type=text], .qis-register input[type=tel], .qis-register textarea, .qis-register select, .qis_checkbox label, #calculators {color:'.$style['font-colour'].';border:'.$style['input-border'].';background-color:'.$style['inputbackground'].';}.registerradio input[type=radio]:not(old) + label > span{border:'.$style['input-border'].';}';
	$required = '.qis-register input[type=text].required, .qis-register input[type=tel].required, .qis-register textarea.required, .qis-register select.required {border:'.$style['input-required'].'}'; 
	$focus = ".qis-register input:focus, .qis-register textarea:focus {background:".$style['inputfocus'].";}";
	$text = ".qis-register p {color:".$style['font-colour'].";margin: 6px 0 !important;padding: 0 !important;}";
	$error = ".qis-register .error {color:".$style['error-font-colour']." !important;border-color:".$style['error-font-colour']." !important;}";
	$button = ".toggle-qis a {color: ".$style['header-colour'].";height:auto;font-size:1em;margin:0;text-decoration:none;}";
	$submit = "color:".$style['submit-colour'].";background:".$style['submit-background'].";border:".$style['submit-border'].";font-size: inherit;".$corners;
	$submithover = "background:".$style['submit-hover-background'].";";
	$submitbutton = ".qis-register .submit, .toggle-qis a {".$submit."}.qis-register .submit:hover {".$submithover."}";
	$applybutton = ".qis-apply a {color:".$style['submit-colour'].";background:".$style['submit-background'].";border:".$style['submit-border'].";font-size: inherit;".$corners.";}";
	$applybutton .= ".qis-apply a:hover {background:".$style['submit-hover-background'].";}";
	
	$code = ".qis-register {max-width:100%;overflow:hidden;}".$submitbutton.$header.$paragraph.$input.$focus.$required.$text.$error.$applybutton;

	$data = '<style type="text/css" media="screen">'.$code.'</style>';
	echo wp_kses($data,$allowed_html);
}

// Add to Head
function qis_head_css ($atts) {
	$allowed_html = callback_allowed_html();
	$atts = shortcode_atts(array('calculator' => ''),$atts,'quick-interest-slider');
	$data = '<style type="text/css" media="screen">'.qis_generate_css($atts['calculator']).'</style><script type="text/javascript">qis__rates = [];</script>';
	echo wp_kses($data,$allowed_html);
	qis_register_css(); 
}

// GDPR Subscribe/Unsubsribe

function qis_subscribe() {
	$message = get_option('qis_messages');
	
	$auto = qis_get_stored_autoresponder(null);
	if ( isset ($_GET['sub']) ) {
		$ref = $_GET['sub'];
		foreach ($message as $key => $value ) {
			if ($ref == $value['timestamp'] && $value['confirmed'] != true) {
				if ($auto['notification']) qis_send_notification ($value);
				$message[$key]['confirmed'] = true;
				update_option('qis_messages',$message);
				return '<div class="emailresponse">'.$auto['subscribemessage'].'</div>';
			}
		}
		return '<div class="emailresponse">'.$auto['subscribealready'].'</div>';
	}
	if ( isset ($_GET['unsub']) ) {
		$ref = $_GET['unsub'];
		foreach ($message as $key => $value )	{
			if ($ref == $value['timestamp']) {
				unset($value);
				$message = array_values($message);
				update_option('qis_messages',$message);
				return '<div class="emailresponse">'.$auto['unsubscribemessage'].'</div>';
			}
		}
		return '<div class="emailresponse">You have already unsubscribed</div>';
	}
}

// Report of all Applications

function qis_show_progress() {
	
	$content = false;
	
	$progress = qis_get_stored_progress();
	
	if (!empty($_POST['showprogress']) && check_admin_referer("save_qis")) {
		$formvalues = $_POST;
		$formvalues['youremail'] = filter_var($formvalues['youremail'],FILTER_SANITIZE_EMAIL);
		$formvalues['reference'] = htmlentities($formvalues['reference']);
		
		$message = get_option('qis_messages');
		
		foreach ($message as $key) {
			if ($formvalues['youremail'] == $key['youremail'] && $formvalues['reference'] == $key['reference']) {
				$register = qis_get_stored_register(1);
				$content	= '<div class="qis-register">';
				if ($progress['showdetails']) {
					$content .= '<h2>'.$progress['loanlabel'].'</h2>';
					$content .= qis_build_message($key,$register);
				}
				$content .= '<h2>'.$progress['progresslabel'].'</h2>';
				$content .= '<p>';
				$steps = explode(",",$progress['progresssteps']);
				$stop = false;
				if ($progress['rejected'] && end($steps) == $key['progress']) {
					$stop = $progress['currentstep'] = true;
					$progress['highlight'] = $progress['rejectedcolour'];
				}
				foreach ($steps as $item) {
					if ($progress['currentstep']) {
						$background = $item == $key['progress'] ? ' style="background-color:'.$progress['highlight'].';"' : ' style="background-color:'.$progress['background'].';"';
					} else {
						$background = $stop ? ' style="background-color:'.$progress['background'].';"' : ' style="background-color:'.$progress['highlight'].';"';
						if ($item == $key['progress']) $stop = true;	
					}	
					$content .= '<span class="step"'.$background.'>'.$item.'</span>';
				}
				$content .= '</p>';
				$content .= '</div>';
			}
		}
		if ($content) return $content;
		else return '<h2>'.$progress['nothingfound'].'</h2>';
	}
	
	$content .= '<form action="" method="POST" class="qis-register">
	<p>'.$progress['emaillabel'].'<br>
	<input type="email" name="youremail" value=""></p>
	<p>'.$progress['referencelabel'].'<br>
	<input type="text" name="reference" value=""></p>
	<p><input onClick="check();" type="submit" value="'.$progress['submitlabel'].'" class="submit" name="showprogress" /><p>
	<input type="hidden" name="anything" value="'. gmdate('Y-m-d H:i:s').'">
	<div class="validator">Enter the word YES in the box: <input type="text" style="width:3em" name="validator" value=""></div>';
	$content .= wp_nonce_field("save_qis");
	$content .= '</form>';
	
	return $content;
}

// Report of all Applications

function qis_registration_report() {
    $allowed_html = callback_allowed_html();
	$message = get_option('qis_messages');
	ob_start();
	$content ='<div id="qis-widget">
	<h2>'.__('Loan Applications','quick-interest-slider').'</h2>';
	$content .= qis_build_registration_table ($message,'report',null,null);
	$content .='</div>';
	echo wp_kses($content,$allowed_html);
	$output_string=ob_get_contents();
	ob_end_clean();
	return $output_string;
}

// Build the table of registrations

function qis_build_registration_table ($message,$report,$qis_edit,$selected) {
	$register = qis_get_stored_register(1);
	$progress = qis_get_stored_progress();
	$span=$charles=$content='';
	$delete=array();$i=0;
	
	$arr = array('name','email','telephone','message','company','address','number','checks','dropdown','dropdown2','radio','consent');
	
	foreach ($arr as $item) {
		foreach($message as $row) {
			if (isset($row['your'.$item]) && $row['your'.$item]) {
				$register['use'.$item] = true;
			}
		}
	}
	
	$register['yourdropdown']	= $register['dropdownlabel'];
	$register['yourdropdown2'] = $register['dropdown2label'];

	$dashboard = '<table cellspacing="0">
	<tr>
	<th>'.__('Reference', 'quick-interest-slider').'</th>';
	foreach ($arr as $item) {
		if ($register['use'.$item]) $dashboard .= '<th>'.$register['your'.$item].'</th>';
	}
	$dashboard .= '<th>'.__('Amount', 'quick-interest-slider').'</th><th>Period</th>';
	if ($register['useattachment']) $dashboard .= '<th>Attachments</th>';
	$dashboard .= '<th>'.__('Date Sent', 'quick-interest-slider').'</th>';
	if ($progress['enabled']) $dashboard .= '<th>Progress</th>';
	if (!$report) $dashboard .= '<th></th>';

	$dashboard .= '</tr>';

	foreach($message as $value) {
		$span = ($value['reference'] && !$value['confirmed']) ? ' style="font-style:italic;color:#ccc;"' : '';
		$content .= '<tr'.$span.'>
		<td>'.$value['reference'].'</td>';
		foreach ($arr as $item) {
			if ($register['use'.$item]) {
				if (isset($value['yourconsent']) && $value['yourconsent']) $value['yourconsent'] = 'checked';
				$content .= '<td>';
				if ( ($qis_edit == 'selected' && $selected[$i]) || $qis_edit == 'all') $content .= '<input style="width:100%" type="text" value="'.$message[$i]['your'.$item].'" name="message['.$i.'][your'.$item.']">';
				elseif (isset($value['your'.$item])) $content .= $value['your'.$item];
				else $content .= '';
				$content .= '</td>';
			}
		}
		if ( ($qis_edit == 'selected' && $selected[$i]) || $qis_edit == 'all') {
			$content .= '<td><input style="width:100%" type="text" value="'.$message[$i]['loan-amount'].'" name="message['.$i.'][loan-amount]"></td>
			<td><input style="width:100%" type="text" value="'.$message[$i]['loan-period'].'" name="message['.$i.'][loan-period]"></td>';
		} else {
			$content .= '<td>'.$value['loan-amount'].'</td><td>'.$value['loan-period'].'</td>';
		}
		if ($value['yourname']) $charles = 'messages';
		
		/*
		if ($register['useattachment']) {
			$content .= $value['attachment'] ? '<td><a href="'.$value['attachment'].'" target="_blank">View</a></td>' : '<td></td>';
		}
		*/
		
		if ($register['useattachment']) $content .= qis_message_thumbs($value);
		$content .= '<td>'.$value['sentdate'].'</td>';
		
		if ($progress['enabled']) {
			if ( ($qis_edit == 'selected' && $selected[$i]) || $qis_edit == 'all') {
				$content .= '<td>';
				$steps = explode(",",$progress['progresssteps']);
				$content .= '<select name="message['.$i.'][progress]">';
				if ($message[$i]['progress']) $content .= '<option value="'.$message[$i]['progress'].'">'.$message[$i]['progress'].'</option>';
				foreach ($steps as $item) {
					$content .= '<option value="' .	$item . '">' .	$item . '</option>'."\r\t";
				}
				$content .= '</select></div>';
				$content .= '</td>';
			} else {
				$content .= '<td>'.$message[$i]['progress'].'</td>';
			}
		}
		
		if (!$report)	$content .= '<td><input type="checkbox" name="'.$i.'" value="checked" /></td>';
		$content .= '</tr>';
		$i++;
	}	

	$dashboard .= $content.'</table>';
	if ($charles) return $dashboard;
}

// Languages

function qis_lang_init() {
	load_plugin_textdomain( 'quick-interest-slider', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Upgrade IPN function

function qis_upgrade_ipn() {
	$qppkey = qis_key();
	if (!isset($_POST['custom']) || $qppkey['authorised'])
		return;
	$raw_post_data = file_get_contents('php://input');
	$raw_post_array = explode('&', $raw_post_data);
	$myPost = array();
	foreach ($raw_post_array as $keyval) {
		$keyval = explode ('=', $keyval);
		if (count($keyval) == 2)
			$myPost[$keyval[0]] = urldecode($keyval[1]);
	}
	$req = 'cmd=_notify-validate';
	if(function_exists('get_magic_quotes_gpc')) {
		$get_magic_quotes_exists = true;
	}
	foreach ($myPost as $key => $value) {
		if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
			$value = urlencode(stripslashes($value));
		} else {
			$value = urlencode($value);
		}
		$req .= "&$key=$value";
	}

	$ch = curl_init("https://www.paypal.com/cgi-bin/webscr");
	if ($ch == FALSE) {
		return FALSE;
	}

	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

	$res = curl_exec($ch);

	$tokens = explode("\r\n\r\n", trim($res));
	$res = trim(end($tokens));

	if (strcmp ($res, "VERIFIED") == 0 && $qppkey['key'] == $_POST['custom']) {
		$qppkey['authorised'] = 'true';
		update_option('qpp_key',$qppkey);
		$qpp_setup = qp_get_stored_setup();
		$email	= bloginfo('admin_email');
		$headers = "From: Quick Plugins <mail@quick-plugins.com>\r\n"
. "Content-Type: text/html; charset=\"utf-8\"\r\n";
		$message = '<html><p>'.__('Thank you for upgrading. Your authorisation key is','quick-interest-slider').':</p><p>'.$qppkey['key'].'</p></html>';
		wp_mail($email,__('Quick Plugins Authorisation Key','quick-interest-slider'),$message,$headers);
	}
	exit();
}

// Get URL of the current page

function qis_current_page_url() {
	$pageURL = 'http';
	if (!isset($_SERVER['HTTPS'])) $_SERVER['HTTPS'] = '';
	if (!empty($_SERVER["HTTPS"])) {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if (($_SERVER["SERVER_PORT"] != "80") && ($_SERVER['SERVER_PORT'] != '443'))
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	else 
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	return $pageURL;
}

// Changes thousands seperator

function qis_separator($s,$separator) {
	if ($separator == 'none')				return $s;
	else if ($separator == 'apostrophe')	$se = "'";
	else if ($separator == 'dot')		$se = ".";
	else if ($separator == 'comma')		$se = ",";
	else $se = ' ';
	return trim(preg_replace("/(\d)(?=(\d{3})+$)/",'$1'.$se,$s));
}
