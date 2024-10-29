<?php
/*
Plugin Name: Contact Me
Plugin URI: http://www.icprojects.net/ajax-contact-form.html
Description: Light AJAX-ed contact form. Use shortcode [contactme] to add the form to posts/pages.
Version: 1.34
Author: Ivan Churakov
Author URI: http://codecanyon.net/user/ichurakov?ref=ichurakov
*/
define('CM_VERSION', 1.34);
wp_enqueue_script("jquery");

class contactme_class {
	var $options;
	var $themes;
	
	function __construct() {
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('contactme', false, dirname(plugin_basename(__FILE__)).'/languages/');
		}
		$this->themes = array (
			"light" => array("title" => __('Under the Sun', 'contactme'), "css" => "front_light.css"),
			"dark" => array("title" => __('Deep in the Universe', 'contactme'), "css" => "front_dark.css")
		);
		$this->options = array (
			"version" => CM_VERSION,
			"show_donationbox" => "",
			"admin_email" => "alerts@".str_replace("www.", "", $_SERVER["SERVER_NAME"]),
			"from_name" => get_bloginfo("name"),
			"from_email" => "noreply@".str_replace("www.", "", $_SERVER["SERVER_NAME"]),
			"send_confirmation" => "on",
			"confirmation_email_subject" => __('Confirmation e-mail', 'contactme'),
			"confirmation_email_body" => __('Dear {visitor_name}!', 'contactme').PHP_EOL.PHP_EOL.__('By this message we confirm receiving your request. We will contact you as soon as possible.', 'contactme').PHP_EOL.PHP_EOL.__('Thanks,', 'contactme').PHP_EOL.get_bloginfo("name"),
			"theme" => "light",
			"form_title" => __('Please fill in the following form to contact us', 'contactme'),
			"thanksgiving_message" => __('<h1>Form successfully submitted</h1>', 'contactme').PHP_EOL.__('Thank you <strong>{visitor_name}</strong>, your message has been submitted to us.', 'contactme')
		);

		$this->get_settings();
		
		if (is_admin()) {
			wp_enqueue_style('contactme_admin', plugins_url('/css/admin.css?ver='.CM_VERSION, __FILE__));
			if ($this->check_settings() !== true) add_action('admin_notices', array(&$this, 'admin_warning'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('init', array(&$this, 'admin_request_handler'));
			add_action('wp_ajax_contactme_submit', array(&$this, "contactme_submit"));
			add_action('wp_ajax_nopriv_contactme_submit', array(&$this, "contactme_submit"));
		} else {
			wp_enqueue_style('contactme_front', plugins_url('/css/'.$this->themes[$this->options['theme']]["css"].'?ver='.CM_VERSION, __FILE__));
			add_shortcode('contactme', array(&$this, "shortcode_handler"));
		}
	}

	function get_settings() {
		$exists = get_option('contactme_version');
		if ($exists) {
			foreach ($this->options as $key => $value) {
				$this->options[$key] = get_option('contactme_'.$key);
			}
		}
	}

	function update_settings() {
		if (current_user_can('manage_options')) {
			foreach ($this->options as $key => $value) {
				update_option('contactme_'.$key, $value);
			}
		}
	}

	function populate_settings() {
		foreach ($this->options as $key => $value) {
			if (isset($_POST['contactme_'.$key])) {
				$this->options[$key] = stripslashes($_POST['contactme_'.$key]);
			}
		}
	}

	function check_settings() {
		$errors = array();
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->options['admin_email']) || strlen($this->options['admin_email']) == 0) $errors[] = __('E-mail for notifications must be valid e-mail address', 'contactme');
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->options['from_email']) || strlen($this->options['from_email']) == 0) $errors[] = __('Sender e-mail must be valid e-mail address', 'contactme');
		if (strlen($this->options['from_name']) < 3) $errors[] = __('Sender name is too short', 'contactme');
		if ($this->options['send_confirmation'] == 'on') {
			if (strlen($this->options['confirmation_email_subject']) < 3) $errors[] = __('Confirmation e-mail subject must contain at least 3 characters', 'contactme');
			else if (strlen($this->options['confirmation_email_subject']) > 64) $errors[] = __('Confirmation e-mail subject must contain maximum 64 characters', 'contactme');
			if (strlen($this->options['confirmation_email_body']) < 3) $errors[] = __('Confirmation e-mail body must contain at least 3 characters', 'contactme');
		}
		if (strlen($this->options['form_title']) < 3) $errors[] = __('Form title is too short', 'contactme');
		if (strlen($this->options['thanksgiving_message']) < 3) $errors[] = __('Thanksgiving message must contain at least 3 characters', 'contactme');
		if (empty($errors)) return true;
		return $errors;
	}

	function admin_menu() {
		add_options_page(__('Contact Me', 'contactme'), __('Contact Me', 'contactme'), 'manage_options', 'contact-me', array(&$this, 'admin_settings'));
	}

	function admin_settings() {
		global $wpdb;
		$message = "";
		$errors = $this->check_settings();
		if (is_array($errors)) echo '<div class="error"><p>'.__('The following error(s) exists:', 'contactme').'<br />- '.implode('<br />- ', $errors).'</p></div>';
		print ('
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>'.__('Contact Me - Settings', 'contactme').'</h2><br /> 
			'.$message);
		if ($this->options['show_donationbox'] != CM_VERSION) {
			print ('
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="ui-sortable">
						<div class="postbox" style="border: 2px solid green;">
							<div style="float: right; font-size: 13px; font-weight: normal; padding: 7px 10px;" title="'.__('Click to hide this box', 'contactme').'"><a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=contactme_hidedonationbox">'.__('Hide', 'contactme').'</a></div>
							<h3 class="hndle" style="cursor: default; color: green;"><span>'.__('Support further development', 'contactme').'</span></h3>
							<div class="inside">
								'.__('You are happy with this plugin and want to help make it even better? Donate small amount and support further development. All donations are used to improve this plugin!', 'contactme').'
								<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" style="margin: 0px; padding: 0px;">
									<input type="hidden" name="cmd" value="_s-xclick">
									<input type="hidden" name="hosted_button_id" value="NKVRNX9JA5VSG">
									<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
									<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
								</form>
								'.__('Please also check out my premium plugins on <a href="http://codecanyon.net/user/ichurakov/portfolio?ref=ichurakov"><strong>CodeCanyon</strong></a>.').'
							</div>
						</div>
					</div>
				</div>
			</div>');
		}
		print ('
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<h3 class="hndle" style="cursor: default;"><span>'.__('General Settings', 'contactme').'</span></h3>
							<div class="inside">
								<table class="contactme_useroptions">
									<tr>
										<th>'.__('E-mail for notifications:', 'contactme').'</th>
										<td><input type="text" id="contactme_admin_email" name="contactme_admin_email" value="'.htmlspecialchars($this->options['admin_email'], ENT_QUOTES).'" style="width: 98%;"><br /><em>'.__('Please enter e-mail address. All submitted form details are sent to this e-mail address.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Sender name:', 'contactme').'</th>
										<td><input type="text" id="contactme_from_name" name="contactme_from_name" value="'.htmlspecialchars($this->options['from_name'], ENT_QUOTES).'" style="width: 98%;"><br /><em>'.__('Please enter sender name. All confirmation messages are sent using this name as "FROM:" header value.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Sender e-mail:', 'contactme').'</th>
										<td><input type="text" id="contactme_from_email" name="contactme_from_email" value="'.htmlspecialchars($this->options['from_email'], ENT_QUOTES).'" style="width: 98%;"><br /><em>'.__('Please enter sender e-mail. All confirmation messages are sent using this e-mail as "FROM:" header value.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Send Confirmation:', 'contactme').'</th>
										<td><input type="checkbox" id="contactme_send_confirmation" name="contactme_send_confirmation" '.($this->options['send_confirmation'] == "on" ? 'checked="checked"' : '').'" onclick="contactme_handle_confirmation();"> '.__('Send confirmation e-mail', 'contactme').'<br /><em>'.__('Enable sending confirmation e-mail to visitors.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Confirmation e-mail subject:', 'contactme').'</th>
										<td><input type="text" id="contactme_confirmation_email_subject" name="contactme_confirmation_email_subject" value="'.htmlspecialchars($this->options['confirmation_email_subject'], ENT_QUOTES).'" style="width: 98%;"><br /><em>'.__('Every visitor who submitted the form receives confirmation e-mail message by e-mail. This is the subject of the message.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Confirmation e-mail body:', 'contactme').'</th>
										<td><textarea id="contactme_confirmation_email_body" name="contactme_confirmation_email_body" style="width: 98%; height: 120px;">'.htmlspecialchars($this->options['confirmation_email_body'], ENT_QUOTES).'</textarea><br /><em>'.__('This e-mail message is sent to visitors who submitted the form. You can use the following keywords: {visitor_name}, {visitor_email}, {visitor_message}.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Form theme:', 'contactme').'</th>
										<td><select id="contactme_theme" name="contactme_theme">');
		foreach ($this->themes as $key => $value) {
			print ('
											<option value="'.$key.'"'.($key == $this->options['theme'] ? ' selected="selected"' : '').'>'.$value['title'].'</option>');
		}
		print ('								
										</select><br /><em>'.__('Select desired form theme.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Form title:', 'contactme').'</th>
										<td><input type="text" id="contactme_form_title" name="contactme_form_title" value="'.htmlspecialchars($this->options['form_title'], ENT_QUOTES).'" style="width: 98%;"><br /><em>'.__('Please enter form title.', 'contactme').'</em></td>
									</tr>
									<tr>
										<th>'.__('Thanksgiving message:', 'contactme').'</th>
										<td><textarea id="contactme_thanksgiving_message" name="contactme_thanksgiving_message" style="width: 98%; height: 120px;">'.htmlspecialchars($this->options['thanksgiving_message'], ENT_QUOTES).'</textarea><br /><em>'.__('This message is displayed if user successfully submitted the form. You can use the following keywords: {visitor_name}, {visitor_email}. HTML allowed.', 'contactme').'</em></td>
									</tr>
								</table>
								<script type="text/javascript">
									function contactme_handle_confirmation() {
										if (jQuery("#contactme_send_confirmation").attr("checked")) {
											jQuery("#contactme_confirmation_email_subject").removeAttr("readonly");
											jQuery("#contactme_confirmation_email_body").removeAttr("readonly");
										} else {
											jQuery("#contactme_confirmation_email_subject").attr("readonly","readonly"); 
											jQuery("#contactme_confirmation_email_body").attr("readonly","readonly");
										}
									}
									contactme_handle_confirmation();
								</script>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="contactme_update_settings" />
								<input type="submit" class="contactme_button button-primary" name="submit" value="'.__('Update Settings', 'contactme').' »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>
		');
	}

	function admin_request_handler() {
		global $wpdb;
		if (!empty($_POST['ak_action'])) {
			switch($_POST['ak_action']) {
				case 'contactme_update_settings':
					$this->populate_settings();
					if (isset($_POST["contactme_send_confirmation"])) $this->options['send_confirmation'] = "on";
					else $this->options['send_confirmation'] = "off";
					$this->update_settings();
					$errors = $this->check_settings();
					if (!is_array($errors)) header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=contact-me&updated=true');
					else header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=contact-me');
					die();
					break;
				default:
					break;
			}
		}
		if (!empty($_GET['ak_action'])) {
			switch($_GET['ak_action']) {
				case 'contactme_hidedonationbox':
					$this->options['show_donationbox'] = CM_VERSION;
					$this->update_settings();
					header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=contact-me');
					die();
					break;
				default:
					break;
			}
		}
	}

	function admin_warning() {
		echo '
		<div class="updated"><p>'.__('<strong>Contact Me plugin almost ready.</strong> You must do some <a href="admin.php?page=contact-me">settings</a> for it to work.', 'contactme').'</p></div>
		';
	}

	function shortcode_handler($_atts) {
		if ($this->check_settings() === true) {
			$width = intval($_atts["width"]);
			if ($width < 100 || $width > 1000) $width = "";
			$title = $_atts["title"];
			if (empty($title)) $title = htmlspecialchars($this->options['form_title'], ENT_QUOTES);
			$suffix = "_".rand(1000, 9999);
			$form = '
		<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery("#submit'.$suffix.'").click(function(){
					var action = "'.get_bloginfo("wpurl").'/wp-admin/admin-ajax.php";
					jQuery("#submit'.$suffix.'").attr("disabled","disabled").after("<img src=\''.plugins_url('/images/ajax-loader.gif', __FILE__).'\' class=\'contactme_loader\' width=\'16\' height=\'16\'/>");
					jQuery("#message'.$suffix.'").slideUp(750,function() {
						jQuery("#message'.$suffix.'").hide();
						jQuery.post(action, {
								contactme_name: jQuery("#name'.$suffix.'").val(),
								contactme_email: jQuery("#email'.$suffix.'").val(),
								contactme_comments: jQuery("#comments'.$suffix.'").val(),
								action: "contactme_submit"
							},
							function(data){
								jQuery("#message'.$suffix.'").html(data);
								jQuery("#message'.$suffix.'").slideDown("slow");
								jQuery("#contactform'.$suffix.' img.contactme_loader").fadeOut("fast",function(){jQuery(this).remove()});
								jQuery("#submit'.$suffix.'").removeAttr("disabled");
								if(data.match("success") != null) jQuery("#contactform'.$suffix.'").slideUp("slow");
							}
						);
					});
					return false;
				});
			});
		</script>
		<div id="contact'.$suffix.'" class="contactme_container"'.(!empty($width) ? ' style="width: '.$width.'px;"' : '').'>
			<div id="message'.$suffix.'" class="contactme_message"></div>
			<form method="post" action="'.get_bloginfo("wpurl").'" name="contactform" id="contactform'.$suffix.'">
				<fieldset>
					<legend>'.$title.'</legend>
					<label for="name" accesskey="U"><span class="contactme_required">*</span> '.__('Your Name', 'contactme').'</label>
					<input name="name" type="text" id="name'.$suffix.'" size="30" value="" />
					<br />
					<label for="email" accesskey="E"><span class="contactme_required">*</span> '.__('E-mail', 'contactme').'</label>
					<input name="email" type="text" id="email'.$suffix.'" size="30" value="" />
					<br />
					<label for="comments" accesskey="C"><span class="contactme_required">*</span> '.__('Comments', 'contactme').'</label>
					<textarea name="comments" cols="40" rows="6" id="comments'.$suffix.'"></textarea>
					<br />
					<input type="hidden" name="action" id="action'.$suffix.'" value="'.time().'">
					<input type="button" class="contactme_submit" id="submit'.$suffix.'" value="'.__('Submit', 'contactme').'" />
				</fieldset>
			</form>
		</div>';
		} else $form = "";
		return $form;
	}
	
	function contactme_submit() {
		$name = trim($_POST['contactme_name']);
		$email = trim($_POST['contactme_email']);
		$comments = trim($_POST['contactme_comments']);
		if (get_magic_quotes_gpc()) {
			$name = stripslashes($name);
			$email = stripslashes($email);
			$comments = stripslashes($comments);
		}
		$error = '';
		if ($name == '') {
			$error .= '<li>'.__('Your name is required.', 'contactme').'</li>';
		}
		if ($email == '') {
			$error .= '<li>'.__('Your e-mail address is required.', 'contactme').'</li>';
		} else if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email)) {
			$error .= '<li>'.__('You have entered an invalid e-mail address.', 'contactme').'</li>';
		}
		if ($comments == '') {
			$error .= '<li>'.__('You must enter a message to send.', 'contactme').'</li>';
		}

		if ($error != '') {
			echo '<div class="contactme_error_message">'.__('Attention! Please correct the errors below and try again.', 'contactme');
			echo '<ul class="contactme_error_messages">'.$error.'</ul>';
			echo '</div>';
		} else {
			if ($this->options['send_confirmation'] == 'on') {
				$keywords = array("{visitor_name}", "{visitor_email}", "{visitor_message}");
				$vals = array($name, $email, $comments);
				$msg = str_replace($keywords, $vals, $this->options['confirmation_email_body']);
				$mail_headers = "Content-Type: text/plain; charset=utf-8".PHP_EOL;
				$mail_headers .= "From: ".$this->options['from_name']." <".$this->options['from_email'].">".PHP_EOL;
				$mail_headers .= "MIME-Version: 1.0".PHP_EOL;
				wp_mail($email, $this->options['confirmation_email_subject'], $msg, $mail_headers);
			}

			$subject = __('You have been contacted by', 'contactme').' '.$name;
			$msg  = __('You have been contacted by', 'contactme').' '.$name.'. '.__('The message is as follows.', 'contactme').PHP_EOL.PHP_EOL;
			$msg .= $comments.PHP_EOL.PHP_EOL;
			$msg .= __('You can contact', 'contactme').' '.$name.' '.__('via email', 'contactme').' '.$email.'.'.PHP_EOL;
			$msg .= __('Sent via', 'contactme').' '.$_SERVER['HTTP_REFERER'];
			$msg = wordwrap($msg, 70);
			$mail_headers .= "From: ".$name." <".$this->options['from_email'].">".PHP_EOL;
			$mail_headers .= "Reply-To: ".$email.PHP_EOL;
			$mail_headers .= "MIME-Version: 1.0".PHP_EOL;
			$mail_headers .= "Content-type: text/plain; charset=utf-8".PHP_EOL;
			if(wp_mail($this->options['admin_email'], $subject, $msg, $mail_headers)) {
				$keywords = array("{visitor_name}", "{visitor_email}", "\n", "\r");
				$vals = array($name, $email, "<br />", "");
				$thanksgiving_message = str_replace($keywords, $vals, $this->options['thanksgiving_message']);
				echo '
				<fieldset>
				<div class="contactme_success_page">
				'.$thanksgiving_message.'
				</div>
				</fieldset>';
			} else {
				echo '<div class="contactme_error_message">'.__('Internal error occured!', 'contactme').'</div>';
			}
		}
		exit;
	}
}

$contactme = new contactme_class();
?>