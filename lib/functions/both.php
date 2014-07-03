<?php
	/**
	* 	@functions for admin and frontend 
	*/

	function easyreservation_resource_init() {
		$labels = array(
			'name' => _x('Resources', 'easyReservations'),
			'singular_name' => _x('Resource', 'easyReservations'),
			'add_new' => _x('Add Resource', 'easyReservations'),
			'add_new_item' => __('Add New Resource', 'easyReservations'),
			'edit_item' => __('Edit Resource', 'easyReservations'),
			'new_item' => __('New Resource', 'easyReservations'),
			'all_items' => __('All Resources', 'easyReservations'),
			'view_item' => __('View Resource', 'easyReservations'),
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => array('slug' => 'resource'), 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => true,
			'capability_type' => 'post',
			'has_archive' => false, 
			'hierarchical' => true,
			'menu_position' => null,
			'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields', 'categorys', 'page-attributes' )
		); 
		register_post_type('easy-rooms',$args);
		register_post_type('easy-offers');
	}
	add_action( 'init', 'easyreservation_resource_init' );

	function easy_init_sessions() {
		if (!session_id()) {
			session_start();
		}
	}
	add_action('init', 'easy_init_sessions');

	function easyreservations_admin_bar() {
		global $wp_admin_bar;

		$pending_reservations_cnt = easyreservations_get_pending();
		if($pending_reservations_cnt != 0) $pending = '<span class="ab-label">'.$pending_reservations_cnt.'</span>';
		else $pending = '';

		$wp_admin_bar->add_node( array(
			'id' => 'reservations',
			'title' => '<span class="er-adminbar-icon"></span>'.$pending,
			'href' => admin_url( 'admin.php?page=reservations#pending'),
			'meta' => array('class' => 'er-adminbar-item')
		) );
		$wp_admin_bar->add_node( array(
			'parent' => 'reservations',
			'id' => 'reservations-new',
			'title' => 'New',
			'href' => admin_url( 'admin.php?page=reservations&add'),
		) );
		$wp_admin_bar->add_node( array(
			'parent' => 'reservations',
			'id' => 'reservations-pending',
			'title' => 'Pending',
			'href' => admin_url( 'admin.php?page=reservations#pending'),
		) );
		$wp_admin_bar->add_node( array(
			'parent' => 'reservations',
			'id' => 'reservations-nurrent',
			'title' => 'Current',
			'href' => admin_url( 'admin.php?page=reservations#current'),
		) );
	}

	add_action( 'admin_bar_menu', 'easyreservations_admin_bar', 999 );

	/**
	* Format string into money
	*
	* @since 1.3
	*
	* @param int $amout amount of money to format
	* @param int 1 = currency sign | 0 = without
	* @return string formated money
	*/

	function easyreservations_format_money($amount, $mode=0, $dig = 2){
		if($amount == '') $amount = 0;
		$reservations_settings = get_option("reservations_settings");
		$currency_settings = $reservations_settings['currency'];
		if(!is_array($currency_settings)) $currency_settings = array('sign' => $currency_settings, 'place' => 0, 'whitespace' => 1, 'divider1' => '.', 'divider2' => ',', 'decimal' => 1);

		if($amount < 0 || substr($amount,0,1) == '-'){
			$amount = substr($amount, 1);
			$add = '-';
		} else $add = '';

		if($currency_settings['decimal'] == 1) $dig = 2;
		else $dig = 0;
		
		$money = $add.number_format((float)$amount,$dig,$currency_settings['divider2'],$currency_settings['divider1']);
		
		if($mode == 1){
			if($currency_settings['whitespace'] == 1) $white = ' ';
			else $white = '';

			if($currency_settings['place'] == 0) $money = $money.$white.'&'.$currency_settings['sign'].';';
			else $money = '&'.$currency_settings['sign'].';'.$white.$money;
		}

		return $money;
	}

	/**
	*	Repair incorrect input, checks if string can be a price (money) -> returns the price or error
	*
	*	$price = a string to check
	*/

	function easyreservations_check_price($price){
		$newPrice = str_replace(",", ".", $price);
		return ( preg_match("/^[\-]{0,1}[0-9]+[\.]?[0-9]*$/", $newPrice)) ? $newPrice : false;
	}
	
	function easyreservations_load_resources($interval = false){
      global $the_rooms_array;
		if(empty($the_rooms_array)) $the_rooms_array = easyreservations_get_rooms();
		if($interval){
			global $the_rooms_intervals_array;
			if(empty($the_rooms_intervals_array)) $the_rooms_intervals_array = easyreservations_get_rooms_intervals();
		}
	}

	function easyreservations_get_rooms($content=false, $check=false, $user = false){
		global $wpdb;
		if($content) $con = ", post_content"; else $con = "";

		$rooms = $wpdb->get_results("SELECT ID, post_title, menu_order $con FROM ".$wpdb->prefix ."posts WHERE post_type='easy-rooms' AND post_status!='auto-draft' ORDER BY menu_order ASC");
		
		if(function_exists('icl_object_id')){
			$blog_current_lang = false;          
			$blog_lang = get_option('WPLANG');
			if($blog_lang){
				$exp = explode('_',$blog_lang);
				$blog_current_lang = $exp[0];
			}
			if(!$blog_current_lang && defined('WPLANG') && WPLANG != ''){
				$blog_lang = WPLANG;
				$exp = explode('_',$blog_lang);
				$blog_current_lang = $exp[0];
			}
			if(!$blog_current_lang){
				$blog_current_lang = 'en';
			}

			foreach ($rooms as $key => $id){
				$xlat = icl_object_id($id->ID,'easy-rooms', false, $blog_current_lang);
				if(is_null($xlat) || $id->ID !== $xlat){
					unset($rooms[$key]);
					continue;
				}
				$xlat2 = icl_object_id($id->ID,'easy-rooms', false);
				if(!is_null($xlat) && !empty($xlat2) && $xlat != $xlat2 && is_numeric($xlat2)){
					$new_room = $wpdb->get_results("SELECT post_title $con FROM ".$wpdb->prefix ."posts WHERE ID='$xlat2' AND post_type='easy-rooms' AND post_status!='auto-draft' ORDER BY menu_order ASC");
					if($content) $rooms[$key]->post_content = $new_room[0]->post_content;
					$rooms[$key]->post_title = $new_room[0]->post_title;
				}
			}
		}

		foreach($rooms as $key => $room){
			$rooms[$room->ID] = $room;
			unset($rooms[$key]);
			if($check){
				$get_role = get_post_meta($room->ID, 'easy-resource-permission', true);
				if(!empty($get_role) && ((!$user && !current_user_can($get_role)) || ($user && !user_can($user, $get_role))) ) unset($rooms[$room->ID]);
			}
		}

		return $rooms;
	}

	$the_rooms_array = '';

	function easyreservations_resource_options($selected='', $check=0, $exclude= '', $addslashes = false){
		$rooms = easyreservations_get_rooms(0, $check);
		$rooms_options='';
		foreach( $rooms as $room ){
			if(empty($exclude) || !in_array($room->ID, $exclude)){
				if($addslashes) $room->post_title = addslashes($room->post_title);
				if(!empty($selected) && $selected == $room->ID) $select = ' selected="selected"'; else $select = "";
				$rooms_options .= '<option value="'.$room->ID.'"'.$select.'>'.__($room->post_title).'</option>';
			}
		}
		return $rooms_options;
	}

	function easyreservations_get_rooms_intervals(){
		global $the_rooms_array;
		$rooms = $the_rooms_array;
		$room_intervals = '';
		if(!empty($rooms)){
			foreach( $rooms as $room ){
				$meta = get_post_meta($room->ID, 'easy-resource-interval', TRUE);
				if($meta) $room_intervals[$room->ID] = $meta;
				else $room_intervals[$room->ID] = 86400;
			}
		}
		return $room_intervals;
	}
	
	$the_rooms_intervals_array = '';

	function easyreservations_interval_infos($interval= 0, $mode = 0, $singular = 0){
		if($interval == 3600){
			$string = _n('hour', 'hours', $singular, 'easyReservations');
		} elseif($interval == 86400){
			$string = _n('night', 'nights', $singular, 'easyReservations');
		} elseif($interval == 604800){
			$string = _n('week', 'weeks', $singular, 'easyReservations');
		} else $string = _n('time', 'times', $singular, 'easyReservations');

		return $string;
	}

	function easyreservations_get_interval($interval = 0, $resourceID = 0, $mode = 0){
		if($interval == 0) $interval = get_post_meta($resourceID, 'easy-resource-interval', TRUE);
		if($mode == 0) return $interval;
		else {
			if($interval == 3600) return 3600;
			else return 86400;
		}
	}

	function easyreservations_get_nights($interval, $arrival, $departure, $mode = 1){
		$number = ($departure-$arrival) / easyreservations_get_interval($interval, 0,  $mode);
		$significance = 0.01;
		return ( is_numeric($number)) ? (ceil(ceil($number/$significance)*$significance)) : false;
	}
	/**
	*	Returns changelog
	*
	*	$beforeArray = array of reservation before editation
	*	$afterArray = array of reservation after editation
	*/

	function easyreservations_generate_res_changelog($beforeArray, $afterArray){
    easyreservations_load_resources();
    global $the_rooms_array;
		$changelog = '';

		if($beforeArray['arrival'] != $afterArray['arrival']){
			$changelog .= __('The arrival date was edited' , 'easyReservations' ).': '.date(RESERVATIONS_DATE_FORMAT, strtotime($beforeArray['arrival'])).' => '.date(RESERVATIONS_DATE_FORMAT, strtotime($afterArray['arrival'])).'<br>';
		}

		if($beforeArray['departure'] != $afterArray['departure']){
			$changelog .= __('The departure date was edited' , 'easyReservations' ).': '.date(RESERVATIONS_DATE_FORMAT_SHOW, strtotime($beforeArray['departure'])).' => '.date(RESERVATIONS_DATE_FORMAT_SHOW, strtotime($afterArray['departure'])).'<br>';
		}

		if($beforeArray['name'] != $afterArray['name']){
			$changelog .= __('The name was edited' , 'easyReservations' ).': '.$beforeArray['name'].' => '.$afterArray['name'].'<br>';
		}

		if($beforeArray['email'] != $afterArray['email']){
			$changelog .= __('The email was edited' , 'easyReservations' ).': '.$beforeArray['email'].' => '.$afterArray['email'].'<br>';
		}

		if($beforeArray['persons'] != $afterArray['persons']){
			$changelog .= __('The amount of persons was edited' , 'easyReservations' ).': '.$beforeArray['persons'].' => '.$afterArray['persons'].'<br>';
		}

		if($beforeArray['childs'] != $afterArray['childs']){
			$changelog .= __('The amount of childs was edited' , 'easyReservations' ).': '.$beforeArray['childs'].' => '.$afterArray['childs'].'<br>';
		}

		if($beforeArray['country'] != $afterArray['country']){
			$changelog .= __('The country was edited' , 'easyReservations' ).': '.$beforeArray['country'].' => '.$afterArray['country'].'<br>';
		}

		if($beforeArray['room'] != $afterArray['room']){
			$changelog .= __('The room was edited' , 'easyReservations' ).': '.__($the_rooms_array[$beforeArray['room']]->post_title).' => '.__($the_rooms_array[$afterArray['room']]->post_title).'<br>';
		}

		if($beforeArray['message'] != $afterArray['message']){
			$changelog .= __('The message was edited' , 'easyReservations' ).'<br>';
		}

		if($beforeArray['custom'] != $afterArray['custom']){
			$changelog .= __('Custom fields got edited', 'easyReservations' ).'<br>';
		}

		if(isset($beforeArray['customp']) && $beforeArray['customp'] != $afterArray['customp']){
			$changelog .= __('Prices  got edited' , 'easyReservations' ).'<br>';
		}

		return $changelog;
	}

	/**
	*	Returns an array of all countrys
	*
	*/
	function easyReservations_country_array(){

		return array( 'AF'=>'Afghanistan', 'AL'=>'Albania', 'DZ'=>'Algeria', 'AS'=>'American Samoa', 'AD'=>'Andorra', 'AO'=>'Angola', 'AI'=>'Anguilla', 'AQ'=>'Antarctica', 'AG'=>'Antigua And Barbuda', 'AR'=>'Argentina', 'AM'=>'Armenia', 'AW'=>'Aruba', 'AU'=>'Australia', 'AT'=>'Austria', 'AZ'=>'Azerbaijan', 'BS'=>'Bahamas', 'BH'=>'Bahrain', 'BD'=>'Bangladesh', 'BB'=>'Barbados', 'BY'=>'Belarus', 'BE'=>'Belgium', 'BZ'=>'Belize', 'BJ'=>'Benin', 'BM'=>'Bermuda', 'BT'=>'Bhutan', 'BO'=>'Bolivia', 'BA'=>'Bosnia And Herzegovina', 'BW'=>'Botswana', 'BV'=>'Bouvet Island', 'BR'=>'Brazil', 'IO'=>'British Indian Ocean Territory', 'BN'=>'Brunei', 'BG'=>'Bulgaria', 'BF'=>'Burkina Faso', 'BI'=>'Burundi', 'KH'=>'Cambodia', 'CM'=>'Cameroon', 'CA'=>'Canada', 'CV'=>'Cape Verde', 'KY'=>'Cayman Islands', 'CF'=>'Central African Republic', 'TD'=>'Chad', 'CL'=>'Chile', 'CN'=>'China', 'CX'=>'Christmas Island', 'CC'=>'Cocos (Keeling) Islands', 'CO'=>'Colombia', 'KM'=>'Comoros', 'CG'=>'Congo', 'CK'=>'Cook Islands', 'CR'=>'Costa Rica', 'CI'=>'Cote D\'Ivorie (Ivory Coast)', 'HR'=>'Croatia (Hrvatska)', 'CU'=>'Cuba', 'CY'=>'Cyprus', 'CZ'=>'Czech Republic', 'CD'=>'Democratic Republic Of Congo (Zaire)', 'DK'=>'Denmark', 'DJ'=>'Djibouti', 'DM'=>'Dominica', 'DO'=>'Dominican Republic', 'TP'=>'East Timor', 'EC'=>'Ecuador', 'EG'=>'Egypt', 'SV'=>'El Salvador', 'GQ'=>'Equatorial Guinea', 'ER'=>'Eritrea', 'EE'=>'Estonia', 'ET'=>'Ethiopia', 'FK'=>'Falkland Islands (Malvinas)', 'FO'=>'Faroe Islands', 'FJ'=>'Fiji', 'FI'=>'Finland', 'FR'=>'France', 'FX'=>'France, Metropolitan', 'GF'=>'French Guinea', 'PF'=>'French Polynesia', 'TF'=>'French Southern Territories', 'GA'=>'Gabon', 'GM'=>'Gambia', 'GE'=>'Georgia', 'DE'=>'Germany', 'GH'=>'Ghana', 'GI'=>'Gibraltar', 'GR'=>'Greece', 'GL'=>'Greenland', 'GD'=>'Grenada', 'GP'=>'Guadeloupe', 'GU'=>'Guam', 'GT'=>'Guatemala', 'GN'=>'Guinea', 'GW'=>'Guinea-Bissau', 'GY'=>'Guyana', 'HT'=>'Haiti', 'HM'=>'Heard And McDonald Islands', 'HN'=>'Honduras', 'HK'=>'Hong Kong', 'HU'=>'Hungary', 'IS'=>'Iceland', 'IN'=>'India', 'ID'=>'Indonesia', 'IR'=>'Iran', 'IQ'=>'Iraq', 'IE'=>'Ireland', 'IL'=>'Israel', 'IT'=>'Italy', 'JM'=>'Jamaica', 'JP'=>'Japan', 'JO'=>'Jordan', 'KZ'=>'Kazakhstan', 'KE'=>'Kenya', 'KI'=>'Kiribati', 'KW'=>'Kuwait', 'KG'=>'Kyrgyzstan', 'LA'=>'Laos', 'LV'=>'Latvia', 'LB'=>'Lebanon', 'LS'=>'Lesotho', 'LR'=>'Liberia', 'LY'=>'Libya', 'LI'=>'Liechtenstein', 'LT'=>'Lithuania', 'LU'=>'Luxembourg', 'MO'=>'Macau', 'MK'=>'Macedonia', 'MG'=>'Madagascar', 'MW'=>'Malawi', 'MY'=>'Malaysia', 'MV'=>'Maldives', 'ML'=>'Mali', 'MT'=>'Malta', 'MH'=>'Marshall Islands', 'MQ'=>'Martinique', 'MR'=>'Mauritania', 'MU'=>'Mauritius', 'YT'=>'Mayotte', 'MX'=>'Mexico', 'FM'=>'Micronesia', 'MD'=>'Moldova', 'MC'=>'Monaco', 'MN'=>'Mongolia', 'MS'=>'Montserrat', 'MA'=>'Morocco', 'MZ'=>'Mozambique', 'MM'=>'Myanmar (Burma)', 'NA'=>'Namibia', 'NR'=>'Nauru', 'NP'=>'Nepal', 'NL'=>'Netherlands', 'AN'=>'Netherlands Antilles', 'NC'=>'New Caledonia', 'NZ'=>'New Zealand', 'NI'=>'Nicaragua', 'NE'=>'Niger', 'NG'=>'Nigeria', 'NU'=>'Niue', 'NF'=>'Norfolk Island', 'KP'=>'North Korea', 'MP'=>'Northern Mariana Islands', 'NO'=>'Norway', 'OM'=>'Oman', 'PK'=>'Pakistan', 'PW'=>'Palau', 'PA'=>'Panama', 'PG'=>'Papua New Guinea', 'PY'=>'Paraguay', 'PE'=>'Peru', 'PH'=>'Philippines', 'PN'=>'Pitcairn', 'PL'=>'Poland', 'PT'=>'Portugal', 'PR'=>'Puerto Rico', 'QA'=>'Qatar', 'RE'=>'Reunion', 'RO'=>'Romania', 'RU'=>'Russia', 'RW'=>'Rwanda', 'SH'=>'Saint Helena', 'KN'=>'Saint Kitts And Nevis', 'LC'=>'Saint Lucia', 'PM'=>'Saint Pierre And Miquelon', 'VC'=>'Saint Vincent And The Grenadines', 'SM'=>'San Marino', 'ST'=>'Sao Tome And Principe', 'SA'=>'Saudi Arabia', 'SN'=>'Senegal', 'SC'=>'Seychelles', 'SL'=>'Sierra Leone', 'SG'=>'Singapore', 'SK'=>'Slovak Republic', 'SI'=>'Slovenia', 'SB'=>'Solomon Islands', 'SO'=>'Somalia', 'ZA'=>'South Africa', 'GS'=>'South Georgia And South Sandwich Islands', 'KR'=>'South Korea', 'ES'=>'Spain', 'LK'=>'Sri Lanka', 'SD'=>'Sudan', 'SR'=>'Suriname', 'SJ'=>'Svalbard And Jan Mayen', 'SZ'=>'Swaziland', 'SE'=>'Sweden', 'CH'=>'Switzerland', 'SY'=>'Syria', 'TW'=>'Taiwan', 'TJ'=>'Tajikistan', 'TZ'=>'Tanzania', 'TH'=>'Thailand', 'TG'=>'Togo', 'TK'=>'Tokelau', 'TO'=>'Tonga', 'TT'=>'Trinidad And Tobago', 'TN'=>'Tunisia', 'TR'=>'Turkey', 'TM'=>'Turkmenistan', 'TC'=>'Turks And Caicos Islands', 'TV'=>'Tuvalu', 'UG'=>'Uganda', 'UA'=>'Ukraine', 'AE'=>'United Arab Emirates', 'UK'=>'United Kingdom', 'US'=>'United States', 'UM'=>'United States Minor Outlying Islands', 'UY'=>'Uruguay', 'UZ'=>'Uzbekistan', 'VU'=>'Vanuatu', 'VA'=>'Vatican City (Holy See)', 'VE'=>'Venezuela', 'VN'=>'Vietnam', 'VG'=>'Virgin Islands (British)', 'VI'=>'Virgin Islands (US)', 'WF'=>'Wallis And Futuna Islands', 'EH'=>'Western Sahara', 'WS'=>'Western Samoa', 'YE'=>'Yemen', 'YU'=>'Yugoslavia', 'ZM'=>'Zambia', 'ZW'=>'Zimbabwe' );

	}
	/**
	*	Returns options for a country select
	*
	*	$sel = (optional) selected country
	*/
	function easyreservations_country_options($sel = ''){

		$countryArray = easyReservations_country_array();
		$country_options = '';
		foreach($countryArray as $short => $country){
			if($short == $sel) $select = ' selected';
			else $select = "";
			$country_options .= '<option value="'.$short.'"'.$select.'>'.htmlentities($country,ENT_QUOTES).'</options>';
		}

		return $country_options;
	}
	/**
	*	Returns full name of a country
	*
	*	$country = Index of country
	*/

	function easyreservations_country_name($country){

		if(!empty($country)){
			$countryArray = easyReservations_country_array();
			return $countryArray[$country];
		}
	}

	/**
	* Return numbered options for selects
	*
	*	$start = first number of options
	*	$end = last number of options
	*	$sel = (optional) selected option
	*/

    function easyreservations_num_options($start,$end,$sel=''){
		$return = '';
		for($num = (int) $start; $num <= $end; $num++){
			$numdisplay = $num;
			if(!empty($sel) && $num == $sel ) $isel = 'selected="selected"'; else $isel = '';
			if(strlen($start) == strlen($end) && $start < 10 && $end > 9 && $num < 10){
				$numdisplay = '0'.$num;
			}
			$return .= '<option value="'.$num.'" '.$isel.'>'.$numdisplay.'</option>';
		}
		return $return;
	}
	
	function easyreservations_shortcode_parser($content, $usepattern = false, $define = false){
		if($usepattern){
			$pattern = '\\[';						 // Opening bracket
			if($define){
				$pattern.= '(\\[?)'					 // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
					.	'('.$define.')';					 // 2: Shortcode name
			}
			$pattern .= '\\b'                        // Word boundary
					. '('                                // 3: Unroll the loop: Inside the opening shortcode tag
					.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
					.     '(?:'
					.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
					.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
					.     ')*?'
					. ')'
					. '(?:'
					.     '(\\/)'                        // 4: Self closing tag ...
					.     '\\]'                          // ... and closing bracket
					. '|'
					.     '\\]'                          // Closing bracket
					.     '(?:'
					.         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
					.             '[^\\[]*+'             // Not an opening bracket
					.             '(?:'
					.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
					.                 '[^\\[]*+'         // Not an opening bracket
					.             ')*+'
					.         ')'
					.         '\\[\\/\\2\\]'             // Closing shortcode tag
					.     ')?'
					. ')'
					. '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
			preg_match_all( '/'. $pattern .'/s', $content, $match);
			if($define) $return = $match[3];
			else $return = $match[1];
			$return = array_merge($return, array());
		} else {
			preg_match_all( '/\[.*\]/U', $content, $match);
			$return = $match[0];
		}
		$return = str_replace(array('[',']'), '', $return);

		return $return;
	}

	function easyreservations_check_val(){
		if(has_action( 'er_mod_inst', 'easyreservations_add_module_notice') && strlen((string) easyreservations_add_module_notice(true)) == 280) return true;
		else return false;
	}

	function easyreservations_generate_hidden_fields($array, $id = false){
		if($array){
			$return = '';
			$idstr = '';
			foreach($array as $key => $value){
				if($id) $idstr = ' id="'.$key.'" ';
				$return .= '<input type="hidden" name="'.$key.'" value="'.$value.'" '.$idstr.'>';
			}
			return $return;
		}
		return false;
	}

	function easyreservations_send_calendar_callback(){
		global $reservations_settings;
		check_ajax_referer( 'easy-calendar', 'security' );

		$atts = (array) $_POST['atts'];
		$pers = 1; $child = 0; $resev = 0;
		if(isset($_POST['persons'])) $pers = $_POST['persons'];
		if(isset($_POST['childs'])) $child = $_POST['childs'];
		if(isset($_POST['reservated'])) $resev = $_POST['reservated'];
		if(isset($reservations_settings['mergeres'])){
			if(is_array($reservations_settings['mergeres']) && isset($reservations_settings['mergeres']['merge']) && $reservations_settings['mergeres']['merge'] > 0) $room_count = $reservations_settings['mergeres']['merge'];
			elseif(is_numeric($reservations_settings['mergeres']) && $reservations_settings['mergeres'] > 0) $room_count  = $reservations_settings['mergeres'];
		}
		if(!isset($room_count)){
			$room_count = get_post_meta($_POST['room'], 'roomcount', true);
			if(is_array($room_count)){
				$room_count = $room_count[0];
			}
		}
		$month_names = easyreservations_get_date_name(1);
		$day_names = easyreservations_get_date_name(0,2);
		if($atts['req'] == 1) $requirements = get_post_meta($_POST['room'], 'easy-resource-req', TRUE);
		if(isset($_POST['where']) && $_POST['where'] == "widget"){
			$onClick = "easyreservations_send_calendar('widget');";
			$formular = "widget_formular";
			$where = 'widget';
		} else {
			$onClick = "easyreservations_send_calendar('shortcode');";
			$formular = "CalendarFormular";
			$where = 'shortcode';
		}
		$divider = 1;
		$monthes = 1;

		if(isset($atts['monthes']) && $where == 'shortcode' && preg_match('/^[0-9]+x{1}[0-9]+$/i', $atts['monthes'])){
			$explode_monthes = explode('x', $atts['monthes']);
			$monthes = $explode_monthes[0] * $explode_monthes[1];
			$divider = $explode_monthes[0];
		}

		if(function_exists('easyreservations_generate_multical') && $where == 'shortcode' && $monthes != 1) $timenows = easyreservations_generate_multical($_POST['date'], $monthes);
		else $timenows=array(strtotime("+".$_POST['date']." month", strtotime(date("01.m.Y", time()) )));

		if(!isset($timenows[1])) $month = $month_names[date("n", $timenows[0])-1].' '.date("Y", $timenows[0]);
		else {
			$anf =  $timenows[0];
			$end = $timenows[count($timenows)-1];
			if(date("Y", $anf) == date("Y", $end) ){
				$month=$month_names[date("n", $anf)-1].' - '.$month_names[date("n", $end)-1].' '.date("Y", $anf);
			} else {
				$month=$month_names[date("n", $anf)-1].' '.date("y", $anf).' - '.$month_names[date("n", $end)-1].' '.date("y", $end);
			}
		}

		echo '<table class="calendar-table" cellpadding="0" cellspacing="0">';
			echo '<thead>';
				echo '<tr class="calendarheader">';
					echo '<th class="calendar-header-month-prev" onClick="easyClick = 0;document.'.$formular.'.date.value='.($_POST['date']-$atts['interval']).';'.$onClick.'">'.__('prev', 'easyReservations').'</th>';
					echo '<th colspan="5" class="calendar-header-show-month">'.$month.'</th>';
					echo '<th class="calendar-header-month-next" onClick="document.'.$formular.'.date.value='.($_POST['date']+$atts['interval']).';'.$onClick.'">'.__('next', 'easyReservations').'</th>';
				echo '</tr>';
				echo '</thead>';
				echo '<tbody style="text-align:center;white-space:nowrap;padding:0px">';
					echo '<tr>';
					echo '<td colspan="7" style="white-space:nowrap;padding:0px;margin:0px">';
		if(count($timenows) > 1){
			$atts['width'] = ((float) $atts['width']) / $divider;
			$percent = 100 / $divider;
		} else $percent = 100;
		$rand = rand(1,999);
		$month_count=0;
		foreach($timenows as $timenow){
			$month_count++;
			$diff=1;
			$setet=0;
			$yearnow=date("Y", $timenow);
			$monthnow=date("m", $timenow);
			$key = $yearnow.$monthnow;
			$num = cal_days_in_month(CAL_GREGORIAN, $monthnow, $yearnow); // 31

			if($monthnow-1 <= 0){
				$monthnowFix=13;
				$yearnowFix=$yearnow-1;
			} else {
				$monthnowFix=$monthnow;
				$yearnowFix=$yearnow;
			}

			$num2 = cal_days_in_month(CAL_GREGORIAN, $monthnowFix-1, $yearnowFix); // 31
			if(count($timenows) > 1 && $divider % 2 != 0) $thewidth = ($atts['width']-0.33).'%';
			else $thewidth = $percent.'%';
			if($month_count % $divider == 0) $float = '';
			else $float = 'float:left';
			echo '<table class="calendar-direct-table '.str_replace(':left', '', $float).'" style="width:'.$thewidth.';margin:0px;'.$float.'">';
				echo '<thead>';
				if($atts['header'] == 1){
					echo '<tr>';
						echo '<th class="calendar-header-month" colspan="7">'.$month_names[date("n", $timenow)-1].'</th>';
					echo '</tr>';
				}
					echo '<tr>';
						echo '<th class="calendar-header-cell">'.$day_names[0].'</th>';
						echo '<th class="calendar-header-cell">'.$day_names[1].'</th>';
						echo '<th class="calendar-header-cell">'.$day_names[2].'</th>';
						echo '<th class="calendar-header-cell">'.$day_names[3].'</th>';
						echo '<th class="calendar-header-cell">'.$day_names[4].'</th>';
						echo '<th class="calendar-header-cell">'.$day_names[5].'</th>';
						echo '<th class="calendar-header-cell">'.$day_names[6].'</th>';
					echo '</tr>';
				echo '</thead>';
				echo '<tbody style="text-align:center;padding;0px;margin:0px">';
			$rowcount=0;
			while($diff <= $num){
				$dateofeachday=strtotime($diff.'.'.$monthnow.'.'.$yearnow);
				$dayindex=date("N", $dateofeachday);
				if($setet==0 || $setet==7 || $setet==14 || $setet==21 || $setet==28 || $setet==35){ echo '<tr style="text-align:center">'; $rowcount++; }
				if($setet==0 && $diff==1 && $dayindex != "1"){ 
					echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2).'</span></td>'; $setet++; 
					if($setet==1 && $diff==1 && $dayindex != "2"){ 
						echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2+$setet).'</span></td>'; $setet++; 
						if($setet==2 && $diff==1 && $dayindex != "3"){ 
						echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2+$setet).'</span></td>'; $setet++;
							if($setet==3 && $diff==1 && $dayindex != "4"){ 
							echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2+$setet).'</span></td>'; $setet++; 
								if($setet==4 && $diff==1 && $dayindex != "5"){ 
								echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2+$setet).'</span></td>'; $setet++;
									if($setet==5 && $diff==1 && $dayindex != "6"){
									echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2+$setet).'</span></td>'; $setet++;
										if($setet==6 && $diff==1 && $dayindex != "7"){
										echo '<td class="calendar-cell calendar-cell-last"><span>'.($num2-$dayindex+2+$setet).'</span></td>'; $setet++; 
										}
									}
								}
							}
						}
					}
				}

				$res = new Reservation(false, array('email' => 'mail@test.com', 'arrival' => $dateofeachday+86400-1, 'departure' =>  $dateofeachday,'resource' => (int) $_POST['room'], 'adults' => $pers, 'childs' => $child,'reservated' => time()-($resev*86400)), false);
				try {
					$res->admin = false;
					if($atts['price'] > 0){
						$res->Calculate();
						if($atts['price'] == 1 || $atts['price'] == 2){ $explode = explode('.', $res->price); $res->price = $explode[0]; }
						if($atts['price'] == 1) $formated_price = $res->price.'&'.RESERVATIONS_CURRENCY.';';
						elseif($atts['price'] == 2) $formated_price = $res->price;
						elseif($atts['price'] == 3) $formated_price = easyreservations_format_money($res->price, 1);
						elseif($atts['price'] == 4) $formated_price = easyreservations_format_money($res->price);
						elseif($atts['price'] == 5) $formated_price = '&'.RESERVATIONS_CURRENCY.';'.' '.$res->price;
						$final_price = '<span class="calendar-cell-price">'.$formated_price.'</b>';
					} else $final_price = '';

					if(date("d.m.Y", $dateofeachday) == date("d.m.Y", time())) $todayClass=" today";
					else $todayClass="";

					$avail = $res->checkAvailability(3);
					if(floor($avail) >= $room_count) $backgroundtd=" calendar-cell-full";
					elseif(floor($avail) > 0) $backgroundtd=" calendar-cell-occupied";
					else $backgroundtd=" calendar-cell-empty";

					if(round($avail) >= $room_count) $backgroundtd.=" calendar-cell-full2";
					elseif(round($avail) > 0) $backgroundtd.=" calendar-cell-occupied2";
					else $backgroundtd.=" calendar-cell-empty2";

					if($avail  == 0.51) $backgroundtd.=" calendar-cell-halfstart";
					elseif($avail == 0.5) $backgroundtd.=" calendar-cell-halfend";

					if(isset($atts['style']) && $atts['style'] == 3 && $diff < 10) $show = '0'.$diff;
					else $show = $diff;

					if($dateofeachday > time() && $atts['select'] > 0) $onclick = 'onclick="easyreservations_click_calendar(this,\''.date(RESERVATIONS_DATE_FORMAT, $dateofeachday).'\', \''.$rand.'\', \''.$key.'\');"'; else $onclick ='style="cursor:default"';
					if($atts['req'] == 1 && $requirements && ((isset($requirements['start-on']) && is_array($requirements['start-on']) && $requirements['start-on'] != 0) || (isset($requirements['end-on']) && is_array($requirements['end-on']) && $requirements['end-on'] != 0))){
						$das = true;
						if(isset($requirements['start-on']) && is_array($requirements['start-on']) && $requirements['start-on'] != 0 && !in_array(date("N", $dateofeachday), $requirements['start-on'])){
							$backgroundtd.= " reqstartdisabled reqdisabled";
							$das = false;
						} 
						if(isset($requirements['end-on']) && is_array($requirements['end-on']) && $requirements['end-on'] != 0 && !in_array(date("N", $dateofeachday), $requirements['end-on'])){
							$backgroundtd.= " reqenddisabled";
							$das = false;
						}
						if($das) $backgroundtd.= " notreqdisabled";
					}
					echo '<td class="calendar-cell'.$todayClass.$backgroundtd.'" '.$onclick.' id="easy-cal-'.$rand.'-'.$diff.'-'.$key.'" axis="'.$diff.'">'.$show.''.$final_price.'</td>'; $setet++; $diff++;
					if($setet==0 || $setet==7 || $setet==14 || $setet==21 || $setet==28) echo '</tr>';
					$res->destroy();
				} catch(easyException $e){
					return false;
				}
			}

			if(!empty($final_price)) $final_price =  '<span class="calendar-cell-price">&nbsp;</b>';

			if(($diff-1==$num && $setet/7 != $rowcount) || $setet < 36){
				if($divider == 1) $calc=($rowcount*7)-($setet+1);
				else $calc=42-($setet+1);
				for($countits=0; $countits < $calc+1; $countits++){
					if($countits==0) $fix = " calendar-cell-lastfixer"; else $fix ="";
					if($setet+$countits==35){ echo '</tr><tr>'; $setet++; }
					echo '<td class="calendar-cell calendar-cell-last'.$fix.'"><div>&nbsp;</div><span>'.($countits+1).'</span>'.$final_price.'</td>';
				}
			}

			echo '</tr></tbody></table>';
		}

		echo '</td></tr></tbody></table>';
		exit;
	}

	/**
	 *	Callback for the price calculation (here it fakes a reservation and send it to calculation)
	 *
	*/

	function easyreservations_send_form_callback(){
		if(isset($_POST['delete'])){
			if(!empty($_POST['delete'])){
				if(isset($_POST['cancel'])){
					$explode = array($_POST['cancel']);
				} else {
					$explode = explode(',', $_POST['delete']);
					unset($explode[count($explode)]);
				}
				
				foreach($explode as $id){
					if(is_numeric($id)){
						$res = new Reservation((int) $id);
						$res->deleteReservation();
					}
				}
			}
		} else {
			easyreservations_load_resources(true);
			if (!wp_verify_nonce($_POST['easynonce'], 'easy-user-add' )) die('Security check <a href="'.$_SERVER['referer_url'].'">('.__( 'Back' , 'easyReservations' ).')</a>' );
			global $the_rooms_intervals_array, $current_user;
			$error = '';
			if(isset($_POST['formname']))$theForm = stripslashes(get_option('reservations_form_'.$_POST['formname']));
			else $theForm = stripslashes(get_option("reservations_form"));
			if(empty($theForm)) $theForm = stripslashes(get_option("reservations_form"));

			if(isset($_POST['captcha_value'])) $captcha = array( 'captcha_prefix' => $_POST['captcha_prefix'], 'captcha_value' => $_POST['captcha_value'] );
			else $captcha ="";
			if(isset($_POST['thename'])) $name_form=$_POST['thename'];
			else $name_form = "";
			if(isset($_POST['from'])) $arrival = strtotime($_POST['from']);
			else $arrival = time();
			if(isset($_POST['persons'])) $persons=$_POST['persons'];
			else $persons = 1;
			if(isset($_POST['email'])) $email=$_POST['email'];
			else $email = "";
			if(isset($_POST['childs'])) $childs=$_POST['childs'];
			else $childs = 0;
			if(isset($_POST['to'])) $departure = strtotime($_POST['to']);
			else $departure = $arrival + $the_rooms_intervals_array[$_POST['easyroom']];
			if(isset($_POST['nights'])) $departure = $arrival+((int) $_POST['nights'] * $the_rooms_intervals_array[$_POST['easyroom']]);
			if(isset($_POST['country'])) $country=$_POST['country'];
			else $country = "";
			if(isset($_POST['easyroom'])) $room = $_POST['easyroom'];
			else $room = false;

			$arrivalplus = 0;
			if(isset($_POST['date-from-hour'])) $arrivalplus += (int) $_POST['date-from-hour'] * 60;
			else $arrivalplus += 12*60;
			if(isset($_POST['date-from-min'])) $arrivalplus += (int) $_POST['date-from-min'];
			if($arrivalplus > 0) $arrivalplus = $arrivalplus * 60;
			$departureplus = 0;
			if(isset($_POST['date-to-hour'])) $departureplus += (int) $_POST['date-to-hour'] * 60;
			else $departureplus += 12*60;
			if(isset($_POST['date-to-min'])) $departureplus += (int) $_POST['date-to-min'];
			if($departureplus > 0) $departureplus = $departureplus*60;
			$arrival += $arrivalplus;
			$departure += $departureplus;
			$custom_form='';
			$custom_price='';
			$tags = easyreservations_shortcode_parser($theForm, true);
			if(isset($_POST['captcha']) && !empty($_POST['captcha'])){
				$captcha = $_POST['captcha'];
				require_once(WP_PLUGIN_DIR.'/easyreservations/lib/captcha/captcha.php');
				$prefix = $captcha['captcha_prefix'];
				$the_answer_from_respondent = $captcha['captcha_value'];
				$captcha_instance = new ReallySimpleCaptcha();
				$correct = $captcha_instance->check($prefix, $the_answer_from_respondent);
				$captcha_instance->cleanup(); // delete all >1h old captchas image & .php file; is the submit a right place for this or should it be in admin?
				if($correct != 1)	$error.=  '<li><label for="easy-form-captcha">'.__( 'Please enter the correct captcha' , 'easyReservations' ).'</label></li>';
			}

			foreach($tags as $fields){
				$field=shortcode_parse_atts( $fields);
				if($field[0]=="custom"){
					if(isset($_POST['easy-custom-'.$field[2]]) && !empty($_POST['easy-custom-'.$field[2]])){
						$custom_form[] = array( 'type' => 'cstm', 'mode' => 'edit', 'title' => $field[2], 'value' => $_POST['easy-custom-'.$field[2]]);
					} else {
						if(end($field)  == "*") $error.= '<li>'.sprintf(__( '%s is required', 'easyReservations'), ucfirst($field[2])).'</li>'; 
					}
				} elseif($field[0]=="price"){
					if(isset($_POST['custom_price'.$field[2]])){
						$explodeprice = explode(":",$_POST['custom_price'.$field[2]]);
						if(end($field) == 'pp') $theprice = $explodeprice[1] * ($persons+$childs);
						elseif(end($field)  == 'pn') $theprice = $explodeprice[1] * easyreservations_get_nights($the_rooms_intervals_array[$room], $arrival,$departure);
						elseif(end($field)  == 'pb') $theprice = $explodeprice[1] * easyreservations_get_nights($the_rooms_intervals_array[$room], $arrival,$departure) * ($persons+$childs);
						else $theprice = $explodeprice[1];
						$custom_price[] = array( 'type' => 'cstm', 'mode' => 'edit', 'title' => $field[2], 'value' => $explodeprice[0], 'amount' => $theprice );
					}
				}
			}

			$current_user = wp_get_current_user();
			$array = array('name' => $name_form, 'email' => $email, 'arrival' => $arrival,'departure' => $departure,'resource' => (int) $room,'resourcenumber' => 0,'country' => $country, 'adults' => $persons, 'custom' => maybe_unserialize($custom_form),'prices' => maybe_unserialize($custom_price),'childs' => $childs,'reservated' => date('Y-m-d H:i:s', time()),'status' => '','user' => $current_user->ID);

			if(isset($_POST['edit'])){
				$res = new Reservation((int) $_POST['edit'], $array, false);
				try {
					$res->admin = false;
					$theID = $res->editReservation();
					$res->Calculate();
					if(!$theID) echo json_encode(array($res->id, round($res->price,2)));
					else echo 'error';
				} catch(Exception $e){
					echo '<li><label>'.$e->getMessage().'</label></li>';
          exit;
				}
			} else {
				$res = new Reservation(false, $array, false);
				try {
					$res->admin = false;
					if(isset($_POST['coupon'])) $res = apply_filters('easy-add-res-ajax', $res);
					$save = $res->coupon;
					$res->fake = false;
					$res->coupon = false;
					$theID = $res->addReservation();
					if($theID){
						foreach($theID as $key => $terror){
							if($key%2==0) $error.=  '<li><labe for="'.$terror.'">';
							else $error .= $terror.'</label></li>';
						}
					}
				} catch(easyException $e){
					$error.=  '<li><label>'.$e->getMessage().'</label></li>';
				}
				if(!empty($error)) echo $error;
				else {
					if(isset($_POST['submit'])){
						$prices = 0;
						$finalform = '';
						$atts = (array) $_POST['atts'];
						$ids = (array) $_POST['ids'];
						if(!empty($ids)){
							foreach($ids as $id){
								$new = new Reservation((int) $id);
                try{
                  $new->Calculate();
                  $new->sendMail( 'reservations_email_to_admin', false);
                  $new->sendMail( 'reservations_email_to_user', $new->email);
                  $prices += $new->price;
                } catch(Exception $e){
                  echo '<li><label>'.$e->getMessage().'</label></li>';
                  exit;
                }
							}
							$res->Calculate();
							$prices += $res->price;
							$ids[]=$res->id;
							$payment = $ids;
						} else {
							$res->Calculate();
							$prices = $res->price;
							$payment = $res;
						}
						$prices = round($prices,2);
            try {
						  $res->sendMail( 'reservations_email_to_admin', false);
						  $res->sendMail( 'reservations_email_to_user', $res->email);
            } catch(Exception $e){
              echo '<li><label>'.$e->getMessage().'</label></li>';
              exit;
            }

						if(empty($error) && isset($arrival)){
							if(!empty($atts['submit'])) $finalform.= '<div class="easy_form_success"><b class="easy_submit">'.$atts['submit'].'!</b>';
							if(!empty($atts['subsubmit'])) $finalform.= '<span class="easy_subsubmit">'.$atts['subsubmit'].'</span>';
							if($atts['price'] == 1) $finalform.= '<span class="easy_show_price_submit">'.__('Price','easyReservations').': <b>'.easyreservations_format_money($prices, 1).'</b></span>';
							if(function_exists('easyreservation_generate_payment_form') && $atts['payment'] > 0){
								$finalform .= easyreservation_generate_payment_form($payment, $prices, ($atts['payment'] == 2) ? true : false, (is_numeric($atts['discount']) && $atts['discount'] < 100) ? $atts['discount'] : false);
							}
							$finalform.='</div>';
						}
						echo json_encode(array($res->id, round($res->price,2), $finalform));
					} else {
						$res->Calculate();
						echo json_encode(array($res->id, round($res->price,2)));
					}
				}
			}
		}
		exit;
	}

	/**
	 *	Callback for the price calculation (here it fakes a reservation and send it to calculation)
	 *
	*/

	function easyreservations_send_price_callback(){
		easyreservations_load_resources(true);
		check_ajax_referer( 'easy-price', 'security' );
		global $the_rooms_intervals_array;
		$room = $_POST['room'];
		$val_from = strtotime($_POST['from']) + (int) $_POST['fromplus'] ;
		if(!empty($_POST['to'])){
			$val_to = strtotime($_POST['to']) + (int) $_POST['toplus'] ;
		} else {
			$val_to = strtotime(date("d.m.Y", $val_from)) + ($_POST['nights'] * $the_rooms_intervals_array[$room])  + (int) $_POST['toplus'];
		}
		if(!empty($_POST['email'])) $email = $_POST['email'];
		else $email = "test@test.de";
		if(!empty($_POST['persons'])) $persons = $_POST['persons'];
		else $persons = 1;

		if(isset($_POST['customp'])){
			$customp = str_replace("!", "&", $_POST['customp']);
		} else $customp = '';
		
		if(isset($_POST['reserved']) && !empty($_POST['reserved'])) $reserved = $_POST['reserved'];
		else $reserved = time();

		if(isset($_POST['childs']) && !empty($_POST['childs'])) $childs = $_POST['childs'];
		else $childs = 0;

		if(isset($_POST['coupon'])) $coupon = $_POST['coupon'];
		else $coupon = '';
		
		$res = new Reservation(false, array('name' => 'abv', 'email' => $email, 'arrival' => $val_from,'departure' => $val_to,'resource' => (int) $room, 'adults' => (int) $persons, 'childs' => $childs, 'status' => '', 'prices' => (float) $customp, 'coupon' => $coupon, 'reservated' => $reserved), false);
		try {
			$res->Calculate();
			if(isset($_POST['priceper']) && !empty($_POST['priceper'])){
				if($_POST['priceper'] == 'unit'){
					$res->price = round($res->price/$res->times,2);
				} elseif($_POST['priceper'] == 'person' || $_POST['priceper'] == 'pers'){
					$res->price = round($res->price/($res->adults+$res->childs),2);
				} elseif($_POST['priceper'] == 'both'){
					$res->price = round($res->price/($res->adults+$res->childs)/$res->times,2);
				}
			}
			echo json_encode(array(easyreservations_format_money($res->price,1), round($res->price,2)));
		} catch(easyException $e){
			echo 'Error:'. $e->getMessage();
		}
		exit;
	}

	/**
	 *	Callback for the ajax validation (here it checks the values)
	 *
	*/

	function easyreservations_send_validate_callback(){
		check_ajax_referer( 'easy-price', 'security' );
		easyreservations_load_resources(true);
		global $the_rooms_intervals_array;
		$mode = $_POST['mode'];
		
		$val_room = $_POST['room'];
		$val_from = strtotime($_POST['from']) + (int) $_POST['fromplus'];
		if(!empty($_POST['to'])){
			$val_to = strtotime($_POST['to']) + (int) $_POST['toplus'];
			$field = 'easy-form-to';
		} else {
			$val_to = strtotime(date("d.m.Y", $val_from)) + ($_POST['nights'] * $the_rooms_intervals_array[$val_room])  + (int) $_POST['toplus'];
			$field = 'easy-form-units';
		}
		if(isset($_POST['id']) && !empty($_POST['id'])) $id = $_POST['id'];
		else $id = false;
		$error = "";

		$res = new Reservation($id, array('name' =>  $_POST['thename'], 'email' => $_POST['email'], 'arrival' => $val_from,'departure' => $val_to,'resource' => (int) $_POST['room'], 'adults' => (int) $_POST['persons'], 'childs' => (int) $_POST['childs'],'reservated' => time(),'status' => ''), false);
		try {
			$res->admin = false;
			$error = $res->Validate($mode);
		} catch(easyException $e){
			$error[] = '';
			$error[] = $e->getMessage();
		}

		if($mode == 'send'){
			$explode_customs = explode(',', substr($_POST['customs'],0,-1));
			foreach($explode_customs as $cstm){
				if(!empty($cstm)){
					$error[] = $cstm;
					$error[] =  sprintf(__( '%1$s is required' , 'easyReservations' ), ucfirst(str_replace('easy-custom-req-', '', $cstm)));
				}
			}
			if($_POST['captcha'] != 'x!'){
				if(empty($_POST['captcha'])){
					$error[] = 'easy-form-captcha';
					$error[] =  __( 'Captcha is required' , 'easyReservations' );
				} elseif(strlen($_POST['captcha']) != 4){
					$error[] = 'easy-form-captcha';
					$error[] =  __( 'Enter correct captcha' , 'easyReservations' );
				} else {
					require_once(WP_PLUGIN_DIR.'/easyreservations/lib/captcha/captcha.php');
					$captcha_instance = new easy_ReallySimpleCaptcha();
					$correct = $captcha_instance->check($_POST['captcha_prefix'], $_POST['captcha']);
					$captcha_instance->cleanup();
					if($correct != 1){
						$error[] = 'easy-form-captcha';
						$error[] =  __( 'Enter correct captcha' , 'easyReservations' );
					}
				}
			}
		}

		if( $error != '' ){
			header( "Content-Type: application/json" );
			echo json_encode($error);
		} else echo true;

		exit;
	}

	function easyreservations_register_scripts(){
		wp_register_script('easyreservations_send_calendar', WP_PLUGIN_URL.'/easyreservations/js/ajax/send_calendar.js' , array( "jquery" ), RESERVATIONS_VERSION);
		wp_register_script('easyreservations_send_price', WP_PLUGIN_URL.'/easyreservations/js/ajax/send_price.js' , array( "jquery" ), RESERVATIONS_VERSION);
		wp_register_script('easyreservations_send_validate', WP_PLUGIN_URL.'/easyreservations/js/ajax/send_validate.js' , array( "jquery" ), RESERVATIONS_VERSION);
		wp_register_script('easyreservations_send_form', WP_PLUGIN_URL . '/easyreservations/js/ajax/form.js', array( "jquery" ), RESERVATIONS_VERSION);
		easyreservations_load_resources(true);
		global $the_rooms_intervals_array;

		$lang = '';
		if(function_exists('icl_object_id')) $lang = '?lang=' . ICL_LANGUAGE_CODE;
		elseif(function_exists('qtrans_getLanguage')) $lang = '?lang=' . qtrans_getLanguage();

		wp_localize_script( 'easyreservations_send_calendar', 'easyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php'.$lang ), 'plugin_url' => WP_PLUGIN_URL, 'interval' => json_encode($the_rooms_intervals_array) ) );
		wp_localize_script( 'easyreservations_send_price', 'easyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php'.$lang ), 'plugin_url' => WP_PLUGIN_URL, 'interval' => json_encode($the_rooms_intervals_array) ) );
		wp_localize_script( 'easyreservations_send_validate', 'easyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php'.$lang ), 'plugin_url' => WP_PLUGIN_URL, 'interval' => json_encode($the_rooms_intervals_array) ) );
		$reservations_settings = get_option("reservations_settings");
		$reservations_currency = $reservations_settings['currency'];
		if(!is_array($reservations_currency)) $reservations_currency = array('sign' => $reservations_currency, 'place' => 0, 'whitespace' => 1, 'divider1' => '.', 'divider2' => ',', 'decimal' => 1);
		wp_localize_script( 'easyreservations_send_form', 'easyDate', array( 'ajaxurl' => admin_url( 'admin-ajax.php'.$lang ), 'currency' => $reservations_currency,  'easydateformat' => RESERVATIONS_DATE_FORMAT, 'interval' => json_encode($the_rooms_intervals_array) ) );

		wp_register_style('easy-frontend', WP_PLUGIN_URL . '/easyreservations/css/frontend.css', array(), RESERVATIONS_VERSION); // widget form style
		if(file_exists(WP_PLUGIN_DIR . '/easyreservations/css/custom/form.css')) wp_register_style('easy-form-custom', WP_PLUGIN_URL . '/easyreservations/css/custom/form.css', array(), RESERVATIONS_VERSION); // custom form style override
		wp_register_style('easy-form-little', WP_PLUGIN_URL . '/easyreservations/css/forms/form_little.css', array(), RESERVATIONS_VERSION); // widget form style
		wp_register_style('easy-form-none', WP_PLUGIN_URL . '/easyreservations/css/forms/form_none.css', array(), RESERVATIONS_VERSION);
		wp_register_style('easy-form-blue', WP_PLUGIN_URL . '/easyreservations/css/forms/form_blue.css', array(), RESERVATIONS_VERSION);

		if(file_exists(WP_PLUGIN_DIR . '/easyreservations/css/custom/calendar.css')) wp_register_style('easy-cal-custom', WP_PLUGIN_URL . '/easyreservations/css/custom/calendar.css', array(), RESERVATIONS_VERSION); // custom form style override
		wp_register_style('easy-cal-1', WP_PLUGIN_URL . '/easyreservations/css/calendar/style_1.css', array(), RESERVATIONS_VERSION);
		wp_register_style('easy-cal-2', WP_PLUGIN_URL . '/easyreservations/css/calendar/style_2.css', array(), RESERVATIONS_VERSION);

		if(file_exists(WP_PLUGIN_DIR . '/easyreservations/css/custom/datepicker.css')) $form1 = 'custom/datepicker.css'; else $form1 = 'jquery-ui.css';
		wp_register_style('datestyle', WP_PLUGIN_URL . '/easyreservations/css/'.$form1, array(), RESERVATIONS_VERSION);
	}
	
	function easyreservations_register_datepicker_style_normal(){
		if(file_exists(WP_PLUGIN_DIR . '/easyreservations/css/custom/datepicker.css')) $form1 = 'custom/datepicker.css'; else $form1 = 'jquery-ui.css';
		wp_register_style('datestyle', WP_PLUGIN_URL . '/easyreservations/css/'.$form1, array(), RESERVATIONS_VERSION);
	}

	add_action('admin_enqueue_scripts', 'easyreservations_register_datepicker_style_normal');
	add_action('wp_enqueue_scripts', 'easyreservations_register_scripts');

	add_action('wp_ajax_easyreservations_send_calendar', 'easyreservations_send_calendar_callback');
	add_action('wp_ajax_nopriv_easyreservations_send_calendar', 'easyreservations_send_calendar_callback');
	
	add_action('wp_ajax_easyreservations_send_form', 'easyreservations_send_form_callback');
	add_action('wp_ajax_nopriv_easyreservations_send_form', 'easyreservations_send_form_callback');
	
	add_action('wp_ajax_easyreservations_send_price', 'easyreservations_send_price_callback');
	add_action('wp_ajax_nopriv_easyreservations_send_price', 'easyreservations_send_price_callback');
	
	add_action('wp_ajax_easyreservations_send_validate', 'easyreservations_send_validate_callback');
	add_action('wp_ajax_nopriv_easyreservations_send_validate', 'easyreservations_send_validate_callback');

	function easyreservations_get_roomname($number, $room, $roomnames = ''){
		$number = $number - 1;
		if(empty($number) && $number < 0) return $number;
		if(empty($roomnames)) $roomnames = get_post_meta($room, 'easy-resource-roomnames', TRUE);
		if(isset($roomnames[$number]) && !empty($roomnames[$number])) return $roomnames[$number];
		else return $number+1;
	}

	/**
	 * Get day or month names
	 * 
	 * @since 1.8
	 * 
	 * @param int $interval 0 for days - 1 for monthes
	 * @param int $substr number of characters to display 0=full
	 * @param int $date number of day/or month to retutn just that string
	 * @return array/string with name of date
	 */

	function easyreservations_get_date_name($interval = 0, $substr = 0, $date = false, $addslashes = false){
		$name = '';
		if($interval == 0){
			$name[] = __( 'Monday' , 'easyReservations' );
			$name[] = __( 'Tuesday' , 'easyReservations' );
			$name[] = __( 'Wednesday' , 'easyReservations' );
			$name[] = __( 'Thursday' , 'easyReservations' );
			$name[] = __( 'Friday' , 'easyReservations' );
			$name[] = __( 'Saturday' , 'easyReservations' );
			$name[] = __( 'Sunday' , 'easyReservations' );
		} else {
			$name[] = __( 'January' , 'easyReservations' );
			$name[] = __( 'February' , 'easyReservations' );
			$name[] = __( 'March' , 'easyReservations' );
			$name[] = __( 'April' , 'easyReservations' );
			$name[] = __( 'May' , 'easyReservations' );
			$name[] = __( 'June' , 'easyReservations' );
			$name[] = __( 'July' , 'easyReservations' );
			$name[] = __( 'August' , 'easyReservations' );
			$name[] = __( 'September' , 'easyReservations' );
			$name[] = __( 'October' , 'easyReservations' );
			$name[] = __( 'November' , 'easyReservations' );
			$name[] = __( 'December' , 'easyReservations' );
		}

		if($substr > 0 && function_exists('mb_internal_encoding')) mb_internal_encoding("UTF-8");
		foreach($name as $key => $day){
			if($substr > 0 && function_exists('mb_substr')) $name[$key] = mb_substr($day, 0, $substr);
			elseif($substr > 0) $name[$key] = substr($day, 0, $substr);
			if($addslashes) $name[$key] = addslashes($name[$key]);
		}

		if($date !== false) return $name[$date];
		else return $name;
	}
	
	function easyreservations_add_icons_stylesheet() {
		if(is_user_logged_in()){?><style type="text/css">
			.er-adminbar-item .er-adminbar-icon {background-image: url('<?php echo RESERVATIONS_URL; ?>images/toolbar.png');background-repeat: no-repeat;float: left;height: 16px !important;margin-top: 6px !important;margin-right: 1px !important;position: absolute; width: 16px !important;}
			.hover .er-adminbar-icon {background-image: url('<?php echo RESERVATIONS_URL; ?>images/toolbar_hover.png'); }</style><?php 
		}
	}

	add_action('wp_print_styles', 'easyreservations_add_icons_stylesheet');
	add_action('admin_print_styles', 'easyreservations_add_icons_stylesheet');
	
	/**
	 * Print jQuery Code for Datepicker
	 * @param int $type 0 for standard 1 for frontend
	 */	
	function easyreservations_build_datepicker($type, $instances, $trans = false, $search = false){
		if(function_exists('mb_internal_encoding')){
			mb_internal_encoding("UTF-8");
			$function = 'mb_substr';
		} else $function = 'substr';
		
		$daysnames = easyreservations_get_date_name(0,0,false,true);
		$daynames = '["'.$daysnames[6].'", "'.$daysnames[0].'", "'.$daysnames[1].'", "'.$daysnames[2].'", "'.$daysnames[3].'", "'.$daysnames[4].'", "'.$daysnames[5].'"]';
		$daynamesshort = '["'.$function($daysnames[6],0, 3).'","'.$function($daysnames[0],0, 3).'","'.$function($daysnames[1],0, 3).'","'.$function($daysnames[2],0, 3).'","'.$function($daysnames[3],0, 3).'","'.$function($daysnames[4],0, 3).'","'.$function($daysnames[5],0, 3).'"]';
		$daynamesmin = '["'.$function($daysnames[6],0, 2).'","'.$function($daysnames[0],0, 2).'","'.$function($daysnames[1],0, 2).'","'.$function($daysnames[2],0, 2).'","'.$function($daysnames[3],0, 2).'","'.$function($daysnames[4],0, 2).'","'.$function($daysnames[5],0, 2).'"]';
		$monthes = easyreservations_get_date_name(1,0,false,true);
		$monthnames =  '["'.$monthes[0].'","'.$monthes[1].'","'.$monthes[2].'","'.$monthes[3].'","'.$monthes[4].'","'.$monthes[5].'","'.$monthes[6].'","'.$monthes[7].'","'.$monthes[8].'","'.$monthes[9].'","'.$monthes[10].'","'.$monthes[11].'"]';
		$monthnamesshort =  '["'.$function($monthes[0],0,3).'","'.$function($monthes[1],0,3).'","'.$function($monthes[2],0,3).'","'.$function($monthes[3],0,3).'","'.$function($monthes[4],0,3).'","'.$function($monthes[5],0,3).'","'.$function($monthes[6],0,3).'","'.$function($monthes[7],0,3).'","'.$function($monthes[8],0,3).'","'.$function($monthes[9],0,3).'","'.$function($monthes[10],0,3).'","'.$function($monthes[11],0,3).'"]';
		$translations = <<<EOF
dayNames: $daynames,
			dayNamesShort: $daynamesshort,
			dayNamesMin: $daynamesmin,
			monthNames: $monthnames,
			monthNamesShort: $monthnamesshort,
EOF;
		
		if($search) $search = 1;
		else $search = 2;

		if($trans === true) return $translations;
		elseif($trans) $format = $trans;
		else $format = RESERVATIONS_DATE_FORMAT;

		$jquery = '';
		if(isset($instances[1])) foreach($instances as $instance) $jquery .= '#'.$instance.',';
		else $jquery = '#'.$instances;
		$jquery = substr($jquery, 0, -1);

		if($format == 'Y/m/d') $dateformat = 'yy/mm/dd';
		elseif($format == 'm/d/Y') $dateformat = 'mm/dd/yy';
		elseif($format == 'd-m-Y') $dateformat = 'dd-mm-yy';
		elseif($format == 'Y-m-d') $dateformat = 'yy-mm-dd';
		elseif($format == 'd.m.Y') $dateformat = 'dd.mm.yy';

		if($type == 0){
			$datepicker = <<<EOF
		<script type="text/javascript">
			jQuery(document).ready(function(){
				var dates = jQuery( "$jquery" ).datepicker({
					dateFormat: '$dateformat',
					minDate: 0,
					beforeShowDay: function(date){
						if($search == 2 && window.easydisabledays && document.easyFrontendFormular.easyroom){
							return easydisabledays(date,document.easyFrontendFormular.easyroom.value);
						} else {
							return [true];
						}
					},
					$translations
					firstDay: 1,
					onSelect: function( selectedDate ){
						if(this.id == '$instances[0]'){
							var option = this.id == "$instances[0]" ? "minDate" : "maxDate",
							instance = jQuery( this ).data( "datepicker" ),
							date = jQuery.datepicker.parseDate( instance.settings.dateFormat ||	jQuery.datepicker._defaults.dateFormat,	selectedDate, instance.settings );
							date.setDate(date.getDate()+1);
							dates.not( this ).datepicker( "option", option, date );
						}
						if(window.easyreservations_send_validate) easyreservations_send_validate();
						if(window.easyreservations_send_price) easyreservations_send_price();		
					}
				});
			});
		</script>
EOF;
		} else {
			$datepicker = <<<EOF
		<script type="text/javascript">
			jQuery(document).ready(function(){
				var dates = jQuery( "$jquery" ).datepicker({
					$translations
					dateFormat: '$dateformat',
					firstDay: 1
				});
			});
		</script>
EOF;
		}
		echo $datepicker;
	}
	
	function easyreservations_generate_restrict($identifier_array){
		$return = '';
		foreach($identifier_array as $identifier) $return .= easyreservations_restrict_inputs((is_array($identifier) ? $identifier[0] : $identifier), (isset($identifier[1]) ? $identifier[1] : false));
		if(!empty($return)) $return = '<script type="text/javascript">'.$return.'</script>';
		echo $return;
	}

	function easyreservations_restrict_inputs($identifier,$percent = false){
		if($percent) $percent = ' || (e.shiftKey && e.keyCode == 53)';
		else $percent = '';
		return 'jQuery(\''.$identifier.'\').keydown(function(e){if(e.keyCode == 46 || e.keyCode == 8 || e.keyCode == 45 || (e.keyCode == 190 && !e.shiftKey) || (e.keyCode == 109 && !e.shiftKey) || (e.keyCode == 189 && !e.shiftKey)'.$percent.') return; else if(e.shiftKey || (e.keyCode < 48 || e.keyCode > 57) && (e.keyCode < 96 || e.keyCode > 105 )) {e.preventDefault();}});';
	}

?>
