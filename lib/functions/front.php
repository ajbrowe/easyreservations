<?php
/**
* 	@functions for frontend only
*/

	/**
	*	Returns url of current page before wp can do it
	*/
	function easyreservations_current_page() {
		$pageURL = 'http';
		if(isset($_SERVER["HTTPS"]) AND $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
			$pageURL .= "://";
		if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		}
		return $pageURL;
	}

	/**
	*	Returns formated status
	*
	*	$status = status of reservtion
	*/

	function reservations_status_output($status){ //gives out colored and named stauts

		if($status=="yes") $theStatus= '<b style="color:#009B1C">'.__( 'approved' , 'easyReservations' ).'</b>';
		elseif($status=="no") $theStatus= '<b style="color:#E80000;">'.__( 'rejected' , 'easyReservations' ).'</b>';
		elseif($status=="del") $theStatus= '<b style="color:#E80000;">'.__( 'trashed' , 'easyReservations' ).'</b>';
		elseif($status=="") $theStatus= '<b style="color:#0072E5;">'.__( 'pending' , 'easyReservations' ).'</b>';

		return $theStatus;
	}

	/**
	 *	Check frontend inputs (from a form or User ControlPanel), returns errors or add to DB and send mails
	 *
	 *	$res = array with reservations informations
	 *	$where = 'user-add'/'user-edit'
	*/

	function easyreservations_check_reservation($res, $where) {

		$val_from = strtotime($res['from']);
		$val_fromdate_sql = date("Y-m-d", $val_from);
		$val_fromdat = date("Y-m", $val_from);
		if(!empty($res['to'])){
			$val_to = strtotime($res['to']);
			$val_nights = ( $val_to - $val_from ) / 86400;
		} elseif(!empty($res['nights'])){
			$val_nights = $res['nights'];
			$val_to = $val_from + ($val_nights * 86400 );
		}

		if(!empty($res['offer'])) $val_offer = $res['offer'];
		else $val_offer = 0;

		$val_room = $res['room'];
		$val_name = $res['thename'];
		$val_email = $res['email'];
		$val_country = $res['country'];
		$val_persons = $res['persons'];
		$val_childs = $res['childs'];
		$val_message = $res['message'];
		$val_custom = $res['custom'];
		$val_customp = $res['customp'];
		if(isset($res['old_email'])) $val_oldemail = $res['old_email'];
		$error = "";

		$resource_req = get_post_meta($val_room, 'easy-resource-req', TRUE);
		if(!$resource_req || !is_array($resource_req)) $resource_req = array('nights-min' => 1, 'nights-max' => 0, 'pers-min' => 1, 'pers-max' => 0);

		if($resource_req['pers-min'] > ($val_persons+$val_childs)){
			$error.=  sprintf(__( 'You need to reservate for at least %1$s persons in %2$s' , 'easyReservations' ), $resource_req['pers-min'], __(get_the_title($val_room)) ).'<br>';
		}
		if($resource_req['pers-max'] != 0 && $resource_req['pers-max'] < ($val_persons+$val_childs)){
			$error.=  sprintf(__( 'You can only reservate for %1$s persons in %2$s' , 'easyReservations' ), $resource_req['pers-max'], __(get_the_title($val_room)) ).'<br>';
		}
		if($resource_req['nights-min'] > $val_nights){
			$error.=  sprintf(__( 'You need to reservate for at least %1$s nights in %2$s' , 'easyReservations' ), $resource_req['nights-min'], __(get_the_title($val_room)) ).'<br>';
		}
		if($resource_req['nights-max'] != 0 && $resource_req['nights-max'] < $val_nights){
			$error.=  sprintf(__( 'You can only reservate for %1$s nights in %2$s' , 'easyReservations' ), $resource_req['nights-max'], __(get_the_title($val_room)) ).'<br>';
		}

		if($val_offer > 0){
			$resource_req = get_post_meta($val_offer, 'easy-resource-req', TRUE);
			if(!$resource_req || !is_array($resource_req)) $resource_req = array('nights-min' => 1, 'nights-max' => 0, 'pers-min' => 1, 'pers-max' => 0);

			if($resource_req['pers-min'] > ($val_persons+$val_childs)){
				$error.=  sprintf(__( 'You need to reservate for at least %1$s persons in %2$s' , 'easyReservations' ), $resource_req['pers-min'], __(get_the_title($val_room)) ).'<br>';
			}
			if($resource_req['pers-max'] != 0 && $resource_req['pers-max'] < ($val_persons+$val_childs)){
				$error.=  sprintf(__( 'You can only reservate for %1$s persons in %2$s' , 'easyReservations' ), $resource_req['pers-max'], __(get_the_title($val_room)) ).'<br>';
			}
			if($resource_req['nights-min'] > $val_nights){
				$error.=  sprintf(__( 'You need to reservate for at least %1$s nights in %2$s' , 'easyReservations' ), $resource_req['nights-min'], __(get_the_title($val_room)) ).'<br>';
			}
			if($resource_req['nights-max'] != 0 && $resource_req['nights-max'] < $val_nights){
				$error.=  sprintf(__( 'You can only reservate for %1$s nights in %2$s' , 'easyReservations' ), $resource_req['nights-max'], __(get_the_title($val_room)) ).'<br>';
			}
		}

		if(isset($res['id'])) $val_id = $res['id'];

		if(isset($res['captcha']) && !empty($res['captcha'])){
		
			$captcha = $res['captcha'];

			require_once(WP_PLUGIN_DIR.'/easyreservations/lib/captcha/captcha.php');
			$prefix = $captcha['captcha_prefix'];
			$the_answer_from_respondent = $captcha['captcha_value'];
			$captcha_instance = new ReallySimpleCaptcha();
			$correct = $captcha_instance->check($prefix, $the_answer_from_respondent);
			$captcha_instance->remove($prefix);
			$captcha_instance->cleanup(); // delete all >1h old captchas image & .php file; is the submit a right place for this or should it be in admin?

			if($correct != 1)	$error.=  __( 'Please enter the correct captcha' , 'easyReservations' ).'</b><br>';
		}

		if((strlen($val_name) > 30 OR strlen($val_name) <= 1) OR $val_name == ""){ /* check name */
			$error.=  __( 'Please enter a correct name' , 'easyReservations' ).'<br>';
		}

		if($val_from < time()){ /* check arrival Date */
			$error.=  __( 'The arrival date has to be in future' , 'easyReservations' ).'<br>';
		}

		if($val_to < time()){ /* check departure Date */
			$error.=  __( 'The depature date has to be in future' , 'easyReservations' ).'<br>';
		}

		if($val_to <= $val_from){ /* check difference between arrival and departure date */
			$error.=  __( 'The depature date has to be after the arrival date' , 'easyReservations' ).'<br>';
		}

		$pattern_mail = "/^[a-zA-Z0-9-_.]+@[a-zA-Z0-9-_.]+\.[a-zA-Z]{2,4}$/";
		if(!preg_match($pattern_mail, $val_email) OR $val_email == ""){ /* check email */
			$error.=  __( 'Please enter a correct eMail' , 'easyReservations' ).'<br>'; 
		}

		if (!is_numeric($val_persons) OR $val_persons == '' ){ /* check persons */
			$error.=  __( 'Persons has to be a number' , 'easyReservations' ).'<br>';
		}
		
		$numbererrors=easyreservations_check_avail($val_room, $val_from, 0, $val_nights, $val_offer, 1 ); /* check rooms availability */

		if($numbererrors != '' || $numbererrors > 0){
			$error.= __( 'Isn\'t available at' , 'easyReservations' ).' '.$numbererrors.'<br>';
		}

		$reservation_support_mail = get_option("reservations_support_mail");
		if(is_array($reservation_support_mail)) $adminmail = $reservation_support_mail[0];
		else{ 
			if(preg_match('/[\,]/', $reservation_support_mail)){
				$implode  = implode(',', $reservation_support_mail);
				$adminmail = $implode[0];
			} else $adminmail = $reservation_support_mail;
		}

		if($error == ""){
			global $wpdb;

			if($where == "user-add"){

				$wpdb->query( $wpdb->prepare("INSERT INTO ".$wpdb->prefix ."reservations(name,  email, notes, nights, arrivalDate, dat, room, number, childs, country, special, custom, customp, reservated ) 
				VALUES ('$val_name', '$val_email', '$val_message', '$val_nights', '$val_fromdate_sql', '$val_fromdat', '$val_room', '$val_persons', '$val_childs', '$val_country', '$val_offer', '$val_custom', '$val_customp', NOW() )" ) );

				$newID = mysql_insert_id();
				$error = $newID;
				$priceFunction = easyreservations_price_calculation($newID,'');
				$getThePrice = $priceFunction['price'];
				$thePrice = reservations_format_money($getThePrice, 1);

				if($val_offer != "0"){
					$specialoffer =get_the_title($val_offer); 
				}
				if($val_offer == "0") $specialoffer =  __( 'None' , 'easyReservations' );

				$roomtitle = __(get_the_title($val_room));

				$emailformation=get_option('reservations_email_to_admin');
				$emailformation2=get_option('reservations_email_to_user');

				if($emailformation['active'] == 1) easyreservations_send_mail($emailformation['msg'], $reservation_support_mail, $emailformation['subj'], '', $newID, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, '');
				if($emailformation2['active'] == 1) easyreservations_send_mail($emailformation2['msg'], $val_email, $emailformation2['subj'], '', $newID, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, '');

			} elseif($where == "user-edit"){
			
				$checkSQLedit = "SELECT email, name, arrivalDate, nights, number, childs, country, room, special, approve, notes, custom, customp, price FROM ".$wpdb->prefix ."reservations WHERE id='$val_id' AND email='$val_oldemail' ";
				$checkQuerry = $wpdb->get_results($checkSQLedit ); //or exit(__( 'Wrong ID or eMail' , 'easyReservations' ));

				$beforeArray = array( 'arrivalDate' => $checkQuerry[0]->arrivalDate, 'nights' => $checkQuerry[0]->nights, 'email' => $checkQuerry[0]->email, 'name' => $checkQuerry[0]->name, 'persons' => $checkQuerry[0]->number, 'childs' => $checkQuerry[0]->childs, 'room' => $checkQuerry[0]->room, 'offer' => $checkQuerry[0]->special, 'message' => $checkQuerry[0]->notes, 'custom' => $checkQuerry[0]->custom, 'country' => $checkQuerry[0]->country, 'customp' => $checkQuerry[0]->customp );
				$afterArray = array( 'arrivalDate' => $val_fromdate_sql, 'nights' => $val_nights, 'email' => $val_email, 'name' => $val_name, 'persons' => $val_persons, 'childs' => $val_childs, 'room' =>  $val_room, 'offer' => $val_offer, 'message' => $val_message, 'custom' => $val_custom, 'country' => $val_country, 'customp' => $val_customp );

				$changelog = easyreservations_generate_res_changelog($beforeArray, $afterArray);
				
				if($checkQuerry[0]->nights != $val_nights OR $checkQuerry[0]->arrivalDate != $val_fromdate_sql OR $checkQuerry[0]->number != $val_persons OR $checkQuerry[0]->room != $val_room OR $checkQuerry[0]->special != $val_offer){
					if($checkQuerry[0]->price)
					$explodePrice = explode(";", $checkQuerry[0]->price);
					$newPrice = " price='".$explodePrice[1]."',";
				} else $newPrice = '';

				if(!empty($val_custom))		$customfields =		easyreservations_edit_custom($val_custom,	$val_id, 0, 1, false, 0, 'cstm', 'edit');
				if(!empty($val_customp)) 	$custompfields =	easyreservations_edit_custom($val_customp,	$val_id, 0, 1, false, 1, 'cstm', 'edit');

				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix ."reservations SET arrivalDate='$val_fromdate_sql', nights='$val_nights', name='$val_name', email='$val_email', notes='$val_message', room='$val_room', number='$val_persons', childs='$val_childs', special='$val_offer', dat='$val_fromdat', custom='$customfields', customp='$custompfields', country='$val_country', ".$newPrice." approve='' WHERE id='$val_id' ")) or trigger_error('mySQL-Fehler: '.mysql_error(), E_USER_ERROR);
				$thePrice = easyreservations_get_price($val_id,1 );
				
				if($val_offer != 0) $specialoffer = get_the_title($val_offer);
				else $specialoffer =  __( 'None' , 'easyReservations' );

				$roomtitle = get_the_title($val_room);

				$emailformation=get_option('reservations_email_to_admin');
				$emailformation2=get_option('reservations_email_to_user_edited');
				
				if($checkQuerry[0]->email == $val_email){
					if($emailformation['active'] == 1)		easyreservations_send_mail($emailformation['msg'],		$adminmail,						$emailformation['subj'],		'', $val_id, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, $changelog);
					if($emailformation2['active'] == 1)		easyreservations_send_mail($emailformation2['msg'],	$val_email,						$emailformation2['subj'],	'', $val_id, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, $changelog);
				} else {
					if($emailformation['active'] == 1) 		easyreservations_send_mail($emailformation['msg'],		$adminmail,						$emailformation['subj'],		'', $val_id, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, $changelog);
					if($emailformation2['active'] == 1) 	easyreservations_send_mail($emailformation2['msg'],	$val_email,						$emailformation2['subj'],	'', $val_id, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, $changelog);
					if($emailformation2['active'] == 1) 	easyreservations_send_mail($emailformation2['msg'],	$checkQuerry[0]->email,	$emailformation2['subj'],	'', $val_id, $val_from, $val_to, $val_name, $val_email, $val_nights, $val_persons, $val_childs, $val_country, $roomtitle, $specialoffer, $val_custom, $thePrice, $val_message, $changelog);
				}
			}
		}
		
		return $error;
	}
	
	function easyreservations_generate_form($theForm, $price_action, $validate_action, $isCalendar, $theRoom = 0){
		$theForm = stripslashes($theForm);

		preg_match_all(' /\[.*\]/U', $theForm, $matches);
		$mergearray=array_merge($matches[0], array());
		$edgeoneremove=str_replace('[', '', $mergearray);
		$edgetworemoves=str_replace(']', '', $edgeoneremove);
		$customPrices = 0;
		$roomfield = 0;

		foreach($edgetworemoves as $fields){
			$field=shortcode_parse_atts( $fields);
			if(isset($field['value'])) $value = $field['value'];
			else $value='';
			if(isset($field['style'])) $style = $field['style'];
			else $style='';
			if(isset($field['title'])) $title = $field['title'];
			else $title='';
			if(isset($field['disabled'])) $disabled =  'disabled="disabled"';
			else $disabled='';
			if(isset($field['maxlength'])) $maxlength = $field['maxlength'];
			else $maxlength='';

			if($field[0]=="date-from"){
				if(empty($value)) $value = date(RESERVATIONS_DATE_FORMAT, time());
				else {
					if(preg_match('/\+{1}[1-9]+/i', $value)){
						$cutplus = str_replace('+', '',$value);
						$value = date(RESERVATIONS_DATE_FORMAT, time()+($cutplus*86400));
					}
				}
				$theForm=str_replace('['.$fields.']', '<input id="easy-form-from" type="text" name="from" value="'.$value.'" '.$disabled.' title="'.$title.'" style="'.$style.'" onchange="'.$price_action.$validate_action.'">', $theForm);
			} elseif($field[0]=="date-to"){
				if(empty($value)) $value = date(RESERVATIONS_DATE_FORMAT, time());
				else {
					if(preg_match('/^[\+]{1}[1-9]+$/i', $value)){
						$cutplus = str_replace('+',$value);
						$value = date(RESERVATIONS_DATE_FORMAT, time()+($cutplus*86400));
					}
				}
				$theForm=str_replace('['.$fields.']', '<input id="easy-form-to"  type="text" name="to" value="'.$value.'" '.$disabled.' title="'.$title.'" style="'.$style.'" onchange="'.$price_action.$validate_action.'">', $theForm);
			} elseif($field[0]=="nights"){
				if(isset($field[1])) $number=$field[1]; else $number=31;
				$theForm=preg_replace('/\['.$fields.'\]/', '<select name="nights" '.$disabled.' title="'.$title.'" style="'.$style.'">'.easyReservations_num_options(1,$number, $value).'</select>', $theForm);
			} elseif($field[0]=="persons" || $field[0]=="adults"){
				$start = 1;
				if(isset($field[2])) $end = $field[2]; else $end = 6;
				if(isset($field[3])){ $start = $field[2]; $end = $field[3]; }
				$theForm=preg_replace('/\['.$fields.'\]/', '<select id="easy-form-persons" name="persons" '.$disabled.' style="'.$style.'" title="'.$title.'" onchange="'.$price_action.'">'.easyReservations_num_options($start,$end,$value).'</select>', $theForm);
			} elseif($field[0]=="childs"){
				$start = 0;
				if(isset($field[2])) $end = $field[2]; else $end = 6;
				if(isset($field[3])){ $start = $field[2]; $end = $field[3]; }
				$theForm=preg_replace('/\['.$fields.'\]/', '<select name="childs" '.$disabled.' style="'.$style.'" title="'.$title.'" onchange="'.$price_action.'">'.easyReservations_num_options($start,$end,$value).'</select>', $theForm);
			} elseif($field[0]=="thename"){
				$theForm=preg_replace('/\['.$fields.'\]/', '<input type="text" id="easy-form-thename" name="thename" '.$disabled.' value="'.$value.'" style="'.$style.'" title="'.$title.'" onchange="'.$validate_action.'">', $theForm);
			} elseif($field[0]=="error"){
				if(isset($error)) $form_error=$error;
				else $form_error = '';
				$theForm=preg_replace('/\['.$fields.'\]/', '<div id="showError" class="showError" style="'.$style.'"></div>'.$form_error, $theForm);
			} elseif($field[0]=="email"){
				$theForm=preg_replace('/\['.$fields.'\]/', '<input type="text" id="easy-form-email" name="email" '.$disabled.' value="'.$value.'" title="'.$title.'" style="'.$style.'" onchange="'.$price_action.$validate_action.'">', $theForm);
			} elseif($field[0]=="country"){
				$theForm=str_replace('['.$fields.']', '<select id="easy-form-country" '.$disabled.' title="'.$title.'" name="country">'.easyReservations_country_select($value).'</select>', $theForm);
			} elseif($field[0]=="show_price"){
				$theForm=preg_replace('/\['.$fields.'\]/', '<span class="showPrice" title="'.$title.'" style="'.$style.'">'.__( 'Price' , 'easyReservations' ).': <span id="showPrice" style="font-weight:bold;"><b>0,00</b></span> &'.get_option("reservations_currency").';</span>', $theForm);
			} elseif($field[0]=="message"){
				$theForm=preg_replace('/\['.$fields.'\]/', '<textarea name="message" '.$disabled.' title="'.$title.'" style="'.$style.'">'.$value.'</textarea>', $theForm);
			} elseif($field[0]=="captcha"){
				if(!isset($chaptchaFileAdded)) require_once(WP_PLUGIN_DIR.'/easyreservations//lib/captcha/captcha.php');
				$captcha_instance = new ReallySimpleCaptcha();
				$word = $captcha_instance->generate_random_word();
				$prefix = mt_rand();
				$url = $captcha_instance->generate_image($prefix, $word);

				$theForm=preg_replace('/\['.$fields.'\]/', '<input type="text" title="'.$title.'" name="captcha_value" style="width:40px;'.$style.'" ><img style="vertical-align:middle;margin-top: -5px;" src="'.RESERVATIONS_LIB_DIR.'/captcha/tmp/'.$url.'"><input type="hidden" value="'.$prefix.'" name="captcha_prefix">', $theForm);
			} elseif($field[0]=="hidden"){
				if($field[1]=="room"){
					$roomfield=1;
					$theForm=preg_replace('/\['.$fields.'\]/', '<input type="hidden" name="room" value="'.$field[2].'">', $theForm);
				}  elseif($field[1]=="offer"){
					if(isset($field[2])) $offer_value = $field[2]; else $offer_value = 0;
					$theForm=preg_replace('/\['.$fields.'\]/', '<input type="hidden" name="offer" value="'.$offer_value.'">', $theForm);
				} elseif($field[1]=="from"){
					$theForm=preg_replace('/\['.$fields.'\]/', '<input type="hidden" name="from" value="'.$field[2].'">', $theForm);
				} elseif($field[1]=="to"){
					$theForm=preg_replace('/\['.$fields.'\]/', '<input type="hidden" name="to" value="'.$field[2].'">', $theForm);
				} elseif($field[1]=="persons"){
					$theForm=preg_replace('/\['.$fields.'\]/', '<input type="hidden" name="persons" value="'.$field[2].'">', $theForm);
				} elseif($field[1]=="childs"){
					$theForm=preg_replace('/\['.$fields.'\]/', '<input type="hidden" name="childs" value="'.$field[2].'">', $theForm);
				}
			} elseif($field[0]=="rooms"){	
				$roomfield=1;
				if($isCalendar == true) $calendar_action = "document.CalendarFormular.room.value=this.value;easyreservations_send_calendar('shortcode');"; else $calendar_action = '';
				$theForm=str_replace('['.$fields.']', '<select name="room" id="form_room" '.$disabled.' onChange="'.$calendar_action.$price_action.'">'.reservations_get_room_options($value).'</select>', $theForm);
			} elseif($field[0]=="custom"){
				if(isset($field[3])) $valuefield=str_replace('"', '', $field[3]);
				if($field[1]=="text"){
					$theForm=str_replace('['.$fields.']', '<input title="'.$title.'" style="'.$style.'" '.$disabled.' type="text" name="'.$field[2].'" value="'.$value.'">', $theForm);
				} elseif($field[1]=="textarea"){
					$theForm=str_replace($fields, '<textarea title="'.$title.'" style="'.$style.'" '.$disabled.' name="'.$field[2].'" value="'.$value.'"></textarea>', $theForm);
				} elseif($field[1]=="check"){
					if(isset($field['checked'])) $checked = ' checked="'.$field['checked'].'"'; else $checked = '';
					$theForm=str_replace($fields, '<input type="checkbox" title="'.$title.'" '.$disabled.$checked.' style="'.$style.'" name="'.$field[2].'">', $theForm);
				} elseif($field[1]=="radio"){
					if(preg_match("/^[a-zA-Z0-9_]+$/", $valuefield)){
						$theForm=str_replace($fields, '<input type="radio" title="'.$title.'" '.$disabled.' style="'.$style.'" name="'.$field[2].'" value="'.$valuefield.'"> '.$valuefield, $theForm);
					} elseif(preg_match("/^[a-zA-Z0-9_ \\,\\t]+$/", $valuefield)){
						$valueexplodes=explode(",", $valuefield);
						$custom_radio='';
						foreach($valueexplodes as $value){
							if($value != '') $custom_radio .= '<input type="radio" title="'.$title.'" '.$disabled.' style="'.$style.'" name="'.$field[2].'" value="'.$value.'"> '.$value.'<br>';
						}
						$theForm=str_replace($fields, $custom_radio, $theForm);
					}
				} elseif($field[1]=="select"){
					if(preg_match("/^[0-9]+$/", $valuefield)){
						$theForm=preg_replace('/\['.$fields.'\]/', '<select title="'.$title.'" style="'.$style.'" '.$disabled.'  name="'.$field[2].'">'.easyReservations_num_options(1,$valuefield).'</select>', $theForm);
					} elseif(preg_match("/^[a-zA-Z0-9_]+$/", $valuefield)){
						$theForm=preg_replace('/\['.$fields.'\]/', '<select title="'.$title.'" style="'.$style.'" '.$disabled.'  name="'.$field[2].'"><option value="'.$valuefield.'">'.$field[3].'</option></select>', $theForm);
					} elseif(strstr($valuefield,",")) {
						$valueexplodes=explode(",", $valuefield);
						$custom_select='';
						foreach($valueexplodes as $value){
							if($value != '') $custom_select .= '<option value="'.$value.'">'.$value.'</option>';
						}
						$theForm=str_replace($fields, '<select title="'.$title.'" style="'.$style.'" '.$disabled.' name="'.$field[2].'">'.$custom_select.'</select>', $theForm);
					}
				}
			} elseif($field[0]=="price"){
				$valuefield=str_replace('"', '', $field[3]);
				if(isset($field[4]) && $field[4] == 'pp' ){
					$personfield = 'class="'.$field[4].'"';
					$personfields = ':1';
				} elseif(isset($field[4]) && $field[4] == 'pn'){
					$personfield = 'class="'.$field[4].'"';
					$personfields = ':2';
				} else {
					$personfield = '';
					$personfields = '';
				}
				if($field[1]=="checkbox"){
					$explodeprice=explode(":", $valuefield);
					if(isset($field['checked'])) $checked = 'checked="'.$field['checked'].'"'; else $checked = '';
					$theForm=preg_replace('/\['.$fields.'\]/', '<input title="'.$title.'" style="'.$style.'" '.$disabled.' id="custom_price'.$customPrices.'" '.$personfield.' type="checkbox" '.$checked.' onchange="'.$price_action.'" name="'.$field[2].'" value="'.$explodeprice[0].':'.$explodeprice[1].$personfields.'">', $theForm);
				} elseif($field[1]=="radio"){
					if(preg_match("/^[a-zA-Z0-9_]+$/", $valuefield)){
						$explodeprice=explode(":", $valuefield);
						$theForm=preg_replace('/\['.$fields.'\]/', '<input title="'.$title.'" style="'.$style.'" '.$disabled.' id="custom_price'.$customPrices.'" '.$personfield.' type="radio" onchange="'.$price_action.'" name="'.$field[2].'" value="'.$explodeprice[0].':'.$explodeprice[1].$personfields.'"> '.$explodeprice[0].': '.reservations_format_money($explodeprice[1], 1), $theForm);
					} elseif(strstr($valuefield,",")) {
						$valueexplodes=explode(",", $valuefield);
						$custom_radio = '<pre>';
						foreach($valueexplodes as $value){
							$explodeprice=explode(":", $value);
							if($value != '') $custom_radio .= '<input id="custom_price'.$customPrices.'" '.$disabled.' title="'.$title.'" style="'.$style.'" type="radio" '.$personfield.' name="'.$field[2].'" onchange="'.$price_action.'" value="'.$explodeprice[0].':'.$explodeprice[1].$personfields.'"> '.$explodeprice[0].': '.reservations_format_money($explodeprice[1], 1).'<br>';
						}
						$theForm=preg_replace('/\['.$fields.'\]/', $custom_radio.'</pre>', $theForm);
					}
				} elseif($field[1]=="select"){
					if(preg_match("/^[a-zA-Z0-9_]+$/", $valuefield)){
						$explodeprice=explode(":", $valuefield);
						$theForm=preg_replace('/\['.$fields.'\]/', '<select id="custom_price'.$customPrices.'" '.$personfield.' '.$disabled.' name="'.$field[2].'" title="'.$title.'" style="'.$style.'" onchange="'.$price_action.'"><option value="'.$explodeprice[0].':'.$explodeprice[1].$personfields.'">'.$explodeprice[0].': '.reservations_format_money($explodeprice[1], 1).'</option></select>', $theForm);
					} elseif(preg_match("/^[a-zA-Z0-9].+$/", $valuefield)){
						$valueexplodes=explode(",", $valuefield);
						$custom_select='';
						foreach($valueexplodes as $value){
							$explodeprice=explode(":", $value);
							if($value != '') $custom_select .= '<option value="'.$explodeprice[0].':'.$explodeprice[1].$personfields.'">'.$explodeprice[0].': '.reservations_format_money($explodeprice[1], 1).'</option>';
						}
						$theForm=str_replace($fields, '<select  '.$personfield.' style="'.$style.'" title="'.$title.'" id="custom_price'.$customPrices.'" '.$disabled.' onchange="'.$price_action.'" name="'.$field[2].'">'.$custom_select.'</select>', $theForm);
					}
				}
				$customPrices++;
			} elseif($field[0]=="offers"){
				if($field[1]=="select"){
					if($isCalendar == true) $calendar_action = "document.CalendarFormular.room.value=this.value;easyreservations_send_calendar('shortcode');"; else $calendar_action = '';
					$theForm=preg_replace('/\['.$fields.'\]/', '<select name="offer" id="form_offer" '.$disabled.' title="'.$title.'" style="'.$style.'" title="'.$title.'" onchange="'.$price_action.'"><option value="0">'. __( 'None' , 'easyReservations' ).'</option>'.reservations_get_offer_options().'</select>', $theForm);
				} elseif($field[1]=="box"){
					$comefrom=wp_get_referer(); //Get Refferer for Offer box Style
					$parsedURL = parse_url ($comefrom);
					if(isset($parsedURL['query'])){
						$splitPath = explode ('=', $parsedURL['query']);
						$getlast[] = $splitPath[1];
					} else {
						$splitPath = explode ('/', end($parsedURL));
						$splitPathTry2 = preg_split ('/\//', end($parsedURL), 0, PREG_SPLIT_NO_EMPTY); 
						$buildarray = array($splitPathTry2);
						$getlast=end($buildarray);
					}

					$args=array(
						'name' => end($getlast),
						'post_type' => 'easy-offers',
						'showposts' => 1,
					);
					$special_offer_promt  = '';

					$my_post = get_posts($args);
					if(!empty($my_post)) {
						$theIDs = $my_post[0]->ID;
						$image_id = get_post_thumbnail_id($theIDs);  
						$image_url = wp_get_attachment_image_src($image_id,'large');  
						$image_url = $image_url[0];  
						$desc = get_post_meta($theIDs, 'reservations_short', true);
						$fromto = get_post_meta($theIDs, 'reservations_fromto', true);
						if(strlen(__($desc)) >= 45) { $desc = substr(__($desc),0,45)."..."; }
						$special_offer_promt.='<div id="parent"><div id="child" align="center">';
						$special_offer_promt.='<div align="left" style="width: 324px; border: #ffdc88 solid 1px; vertical-align: middle; background: #fffdeb; padding: 5px 5px 5px 5px; font:12px/18px Arial,serif; border-collapse: collapse;">';
						if(get_post_meta($theIDs, 'reservations_percent', true)!=""){ $special_offer_promt.='<span style="height: 20px; border: 0px; padding: 1px 5px 0 5px; margin: 32px 0 0 -50px; font:14px/18px Arial,serif; font-weight: bold; color: #fff; text-align: right; background: #ba0e01; position: absolute;">'.__(get_post_meta($theIDs, 'reservations_percent', true)).'</span>'; }
						$special_offer_promt.='<img src="'.$image_url.'" style="height:55px; width:55px; border:0px; margin:0px 10px 0px 0px; padding:0px;" class="alignleft"> '.__( 'You\'ve choosen' , 'easyReservations' ).': <b>'.__(get_the_title($theIDs)).'</b><img style="float: right;" src="'.RESERVATIONS_IMAGES_DIR.'/close.png" onClick="'."removeElement('parent','child')".';'.$price_action.'"><br>'.__( 'Available' , 'easyReservations' ).': '.__($fromto[0]).'<br>'.__($desc).'</div>';
						$special_offer_promt.='</div></div><input type="hidden"  name="offer" value="'.$theIDs.'">';
					} else $special_offer_promt.='<input type="hidden" name="offer" value="0">';

					$theForm=preg_replace('/\['.$fields.'\]/', ''.$special_offer_promt.'', $theForm);
				}
			} elseif($field[0]=="submit"){
				if(isset($field[1])) $value=$field[1];
				$theForm=preg_replace('/\['.$fields.'\]/', '<input title="'.$title.'" style="'.$style.'" type="submit" '.$disabled.' value="'.$value.'">', $theForm);
			}
		}

		if($roomfield == 0 && $theRoom > 0) $finalformedgesremoved .= '<input type="hidden" name="room" value="'.$theRoom.'">';
		
		$finalformedgeremove1=str_replace('[', '', $theForm);
		$finalformedgesremoved=str_replace(']', '', $finalformedgeremove1);
		
		return $finalformedgesremoved;

	}
?>