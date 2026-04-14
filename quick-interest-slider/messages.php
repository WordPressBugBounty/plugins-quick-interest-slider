<?php

if ( ! defined( 'ABSPATH' ) ) exit;

qis_messages();

function qis_messages() {

	$content = $current = $all = $qis_edit = false;
	$selected = array();
    $allowed_html = qis_allowed_html();

	if ( isset( $_POST['qis_reset_message']) && check_admin_referer("save_qis") ) {
		delete_option('qis_messages');
		qis_admin_notice( esc_html__('All applications have been deleted','quick-interest-slider') . '.' );
	}

	if ( isset($_POST['qis_delete_selected']) && check_admin_referer("save_qis") ) {
		$message = get_option('qis_messages');
		$count = count($message);

		for($i = 0; $i <= $count; $i++) {
			if ( isset($_POST[$i]) && $_POST[$i] === 'checked') {
				unset($message[$i]);
			}
		}

		$message = array_values($message);
		update_option('qis_messages', $message );
		qis_admin_notice( esc_html__('Selected applications have been deleted','quick-interest-slider') . '.' );
	}

	if ( isset($_POST['qis_approve_selected']) && check_admin_referer("save_qis") ) {
		$message = get_option('qis_messages');

		foreach ($message as $key => $value ) {
			if ( isset($_POST[$key]) && $_POST[$key] === 'checked') {
				$message[$key]['confirmed'] = true;
			}
		}

		update_option('qis_messages', $message );
		qis_admin_notice( esc_html__('Selected applications have been approved','quick-interest-slider') . '.' );
	}

	if ( isset($_POST['qis_emaillist']) && check_admin_referer("save_qis") ) {

		$fromemail = get_bloginfo('admin_email');
		$title     = get_bloginfo('name');
		$message   = get_option('qis_messages');

		$content = qis_build_registration_table ($message,'report',null,null);

		$sendtoemail = isset($_POST['sendtoemail']) ? sanitize_email($_POST['sendtoemail']) : '';

		$headers = "From: " . esc_html($title) . " <" . sanitize_email($fromemail) . ">\r\n";
		$headers .= "Content-Type: text/html; charset=\"utf-8\"\r\n";

		wp_mail($sendtoemail, esc_html__('Loan Applications','quick-interest-slider'), $content, $headers);

		qis_admin_notice(
			esc_html__('Application list has been sent to','quick-interest-slider') . ' ' . esc_html($sendtoemail) . '.'
		);
	}

	if ( isset($_POST['qis_update']) && check_admin_referer("save_qis") ) {

		$message = get_option('qis_messages');

		foreach ($_POST['message'] as $id => $row) {
			foreach ($row as $k => $v) {
				$message[$id][$k] = sanitize_text_field($v);
			}
		}

		update_option('qis_messages',$message);
		qis_admin_notice( esc_html__('Applications have been updated','quick-interest-slider') );
	}

	if ( isset($_POST['qis_edit']) && check_admin_referer("save_qis") ) {
		$qis_edit = 'all';
	}

	if ( isset($_POST['qis_edit_selected']) && check_admin_referer("save_qis") ) {
		$qis_edit = 'selected';
		$selected = $_POST;
	}

	$message = get_option('qis_messages');
	$current_user = wp_get_current_user();

	$sendtoemail = !empty($sendtoemail) ? $sendtoemail : $current_user->user_email;

	if(!is_array($message)) $message = array();

	$dashboard = '<div class="wrap">';
	$dashboard .= '<h1>' . esc_html__('Loan Applications','quick-interest-slider') . '</h1>';
	$dashboard .= '<div id="qis-widget">';
	$dashboard .= '<form method="post" id="qis_download_form" action="">';

	$content = qis_build_registration_table ($message,'',$qis_edit,$selected);

	if ($content) {

		$dashboard .= $content;
		$dashboard .= wp_nonce_field("save_qis", '_wpnonce', true, false);

		$dashboard .= '<p>
			<input type="submit" name="qis_reset_message" class="button-secondary"
				value="' . esc_attr__('Delete all applications','quick-interest-slider') . '"
				onclick="return window.confirm(\'' . esc_js('Are you sure you want to delete all the applications?') . '\');"/>

			<input type="submit" name="qis_delete_selected" class="button-secondary"
				value="' . esc_attr__('Delete Selected','quick-interest-slider') . '"
				onclick="return window.confirm(\'' . esc_js('Are you sure you want to delete the selected applications?') . '\');"/>

			<input type="submit" name="qis_approve_selected" class="button-secondary"
				value="' . esc_attr__('Approve Selected','quick-interest-slider') . '"
				onclick="return window.confirm(\'' . esc_js('Are you sure you want to approve the selected applications?') . '\');"/>
		';

		if ($qis_edit) {
			$dashboard .= '<input type="submit" name="qis_update" class="button-primary"
				value="' . esc_attr__('Update Applications','quick-interest-slider') . '" />';
		} else {
			$dashboard .= '<input type="submit" name="qis_edit" class="button-secondary"
				value="' . esc_attr__('Edit Applications','quick-interest-slider') . '" /> <input type="submit" name="qis_edit_selected" class="button-secondary"
				value="' . esc_attr__('Edit Selected','quick-interest-slider') . '" />';
		}

		$dashboard .= ' <input type="submit" name="qis_cancel" class="button-secondary"
			value="' . esc_attr__('Cancel','quick-interest-slider') . '" /></p>';

		$dashboard .= '<p>' . esc_html__('Send applications to this email address','quick-interest-slider') . ':
			<input type="text" name="sendtoemail" value="' . esc_attr($sendtoemail) . '">
			<input type="submit" name="qis_emaillist" class="button-primary"
			value="' . esc_attr__('Email List','quick-interest-slider') . '" />
		</p>';

		$dashboard .= '</form>';

	} else {
		$dashboard .= '<p>' . esc_html__('There are no applications','quick-interest-slider') . '</p>';
	}

	$dashboard .= '</div></div>';

	echo wp_kses($dashboard, $allowed_html);
}