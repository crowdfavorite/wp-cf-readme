<?php
/*
Plugin Name: CF Read Me
Plugin URI: http://crowdfavorite.com
Description: A readme file plugin that translates a <a href="http://daringfireball.net/projects/markdown/syntax">Markdown</a> formatted file in to a readme page. Requires the Crowd Favorite Compatability Plugin to function.
Version: 1.2
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/ 

/** 
 * PLUGIN NOTES:
 * 1. Uses the cf-compat plugin for < WP 2.6 compatability
 * 2. Uses the cf-compat plugin for < PHP 4.3 compatability (for file_get_contents)
 * 3. Requires the cf-compat plugin for is_admin_page function
 * 4. Plugin adds a dashboard sub-menu in WPMU due to bugs in MU's implementation of add_menu_page
 * 5. Set content using the cfreadme_content filter
 */

if (!function_exists('is_admin_page')) {
	function is_admin_page() {
		if (function_exists('is_admin')) {
			return is_admin();
		}
		if (function_exists('check_admin_referer')) {
			return true;
		}
		else {
			return false;
		}
	}
}

function cfreadme_css() {
	echo "
	<style type='text/css'>
		.cf-readme ul, 
		.cf-readme ol {
			margin: 0 0 1em 1.5em;
		}
		.cf-readme dd {
			margin-left:1.5em;
		}
		.cf-readme ul {
			list-style-type: disc;
		}
		.cf-readme li {
			margin-bottom:0;
		}
		.cf-readme li ul,
		.cf-readme li ol {
			margin-bottom:0;
		}
		.cf-readme li ul {
			list-style-type: circle;
		}
		.cf-readme li li ul {
			list-style-type: square;
		}
		.cf-readme ol {
			list-style-type: decimal;
		}
		.cf-readme pre {
			background:#eaeaea;
			border:.5em solid #eaeaea;
			margin-bottom:1em;
			overflow:auto;
		}
		.cf-readme blockquote {
			border-left:.25em solid #eaeaea;
			margin-left:0;
			padding-left:.75em;
		}
	</style>
	";
}
add_action('admin_head', 'cfreadme_css');

/**
 * User level
 * works off of the older user level integers that can be 
 * passed to add_(sub)menu_page funcitons to designate the
 * user access level of the plugin page
 */
$user_level = 2;

/**
 * Add top level admin menu item
 * Top level menu items are a bit convoluted, we neced the plugin to only
 * add the menu item and point to a completely separate functionality page
 */
function cfreadme_menu_items() {
	global $wpmu_version, $user_level;
	// add submenu to dashboard
	if (is_admin_page()) {
		if (is_null($wpmu_version)) {
			add_menu_page('faq','FAQ',$user_level,'cf-faq','cfreadme_show');
		}
		else {
			// wpmu hack for top level menu items, don't like it, nope, not one bit
			//add_menu_page('faq','FAQ',$user_level,'admin.php?page=cf-readme/cf-readme.php','show_readme'); // this doesn't call the function 'show_readme'
			add_submenu_page('index.php','faq','FAQ',$user_level,'cf-faq','cfreadme_show');
		}
	}
}
add_action('admin_menu', 'cfreadme_menu_items');

/**
 * Show appropriate FAQ page
 */
function cfreadme_show() {
	if (!function_exists('Markdown')) {
		require_once(realpath(dirname(__FILE__)).'/markdown/markdown.php');
	}
	$content = apply_filters('cfreadme_content', 'No content set.');
	echo '
<div class="wrap cf-readme">
	'.Markdown($content).'
</div>
	';	
}

?>