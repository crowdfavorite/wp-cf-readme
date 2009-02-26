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
 * 4. Plugin adds a dashboard sub-menu in WPMU < 2.7 due to bugs in MU's implementation of add_menu_page
 * 5. Set content using the cfreadme_content filter, there is currently no queue for setting dislay order of added items
 * 6. Plugin automatically breaks content sections at H2 elements and creates a TOC based on the H2 content
 * 7. Sections can be linked in to by using /wp-admin/index.php?page=cf-faq#readme-id-of-element where the id of the section to be targeted is "id-of-element"
 *	  Section IDs are generated automatically unless an anchor (<a>) is found as a child of the H2 and that anchor has an ID
 *	  The section ID can be found in the TOC list at the top - to link in from another page just add "readme-" to the front of it to trigger the switch at page load
 * 8. Additional ReadMe pages can be added under the FAQ Menu. See the bottom of this file for example code
 * 9. Plugin options can be modified via the cfreadme_options filter
 */

// ADMIN MENU ITEMS

	/**
	 * Add top level admin menu item
	 * Top level menu items are a bit convoluted, we need the plugin to only
	 * add the menu item and point to a completely separate functionality page
	 *
	 * User level works off of the older user level integers that can be 
	 * passed to add_(sub)menu_page funcitons to designate the user access 
	 * level of the plugin page
	 */
	function cfreadme_menu_items() {
		global $wpmu_version, $wp_version, $opts;
	
		$opts = cfreadme_getopts();

		// add submenu to dashboard
		if (is_admin_page()) {		
			if (is_null($wpmu_version) || version_compare($wpmu_version,'2.7','>=')) {
				add_menu_page($opts['id'],$opts['page_title'],$opts['user_level'],$opts['page_id'],'cfreadme_show');
				if(version_compare($wpmu_version,'2.7','>=')) {
					add_action('admin_init','cfreadme_sort_admin_menu',999);
				}
			}
			else {
				// wpmu hack for top level menu items, don't like it, nope, not one bit
				add_submenu_page('index.php',$opts['id'],$opts['page_title'],$opts['user_level'],$opts['page_id'],'cfreadme_show');
			}
			// load js
			if(is_plugin_page()) {
				wp_enqueue_script('cfreadme-js',trailingslashit(get_bloginfo('url')).'index.php?cf_action=cfreadme_js','jquery','1.0');
			}
		}
	}
	add_action('admin_menu', 'cfreadme_menu_items');

	/**
	 * Sort the admin menu items to force our menu item to be first
	 */
	function cfreadme_sort_admin_menu() {
		global $menu;
		$opts = cfreadme_getopts();
	
		$menu_sep = $dash = $dash_key = null;
		foreach($menu as $key => $menu_item) {
			// grab the dashboard item, find it explicitly in case anyone else has moved it
			if(isset($menu_item[5]) && $menu_item[5] == 'menu-dashboard' && $dash == null) {
				$dash = $menu_item;
				$dash_key = $key;
			}
			// we'll most certainly hit a separator before we hit our menu, clone it
			if($menu_item[4] == 'wp-menu-separator' && $menu_sep == null) {
				$menu_sep = $menu_item;
			}
			// unset the current FAQ position and shove it and a separator on the front of the menu
			if($menu_item[2] == $opts['page_id']) {
				unset($menu[$key],$menu[$dash_key]);
				array_unshift($menu,$dash,$menu_sep,$menu_item);
			}
		}
	}

	/**
	 * Centralized method to get menu options since we need them in multiple places
	 * 
	 * @return array
	 */
	function cfreadme_getopts() {
		global $cfreadme_opts;
		
		if(is_null($cfreadme_opts)) {
			$opts = array(
				'id' => 'faq',
				'page_id' => 'cf-faq',
				'page_title' => 'FAQ',
				'user_level' => 2
			);
			$cfreadme_opts = array_merge($opts,apply_filters('cfreadme_options',$opts));
		}

		return $cfreadme_opts;
	}


// DISPLAY CONTENT

	/**
	 * Show appropriate FAQ page
	 */
	function cfreadme_show() {
		if(!class_exists('Markdown')) {
			require_once(realpath(dirname(__FILE__)).'/markdown/markdown.php');
		}
		$content = apply_filters('cfreadme_content', 'No content set.');
		$html = Markdown($content);
		// modify content to facilitate the creation of a JavaScript TOC
		$html = preg_replace("/(<\/h2>)(.*?)(<h2>|$)/si","$1<div>$2</div>$3",$html);
		echo '
	<div class="wrap cf-readme">
		'.$html.'
	</div>
		';	
	}


// CSS

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
			#readme-tabs li.active {
				list-style-type: none;
			}
			#readme-tabs li.active a {
				font-weight: bold;
				color: #555;
				cursor: default;
			}
		</style>
		";
	}
	add_action('admin_head', 'cfreadme_css');


// JAVASCRIPT

	/**
	 * Javascript for organizing the UI in to tabs based on the H2 tag
	 */
	function cfreadme_javascript() {
		echo '
<script type="text/javascript">
//<[CDATA[
	// find all H2s and make tab sets
	jQuery(function(){
		// make tab ul
		jQuery("<ul>").attr("id","readme-tabs").insertAfter(jQuery(".cf-readme h1"));

		var divcount = 0;
		jQuery(".cf-readme h2").each(function(){
			_this = jQuery(this);
			divcount++;

			// make sure the h2 does not contain a link, if so, use the link text
			if(_this.children("a").length) {
				child = _this.children("a");
				link_text = child.html();
				if(child.attr("id").length) {
					div_id = child.attr("id");
					child.removeAttr("id");
				}
				else {
					div_id = "section-" + divcount;
				}
			}
			else {
				link_text = _this.html();
				div_id = "section-" + divcount;
			}

			// add an li to the tab list
			jQuery("<li>").append(
				jQuery("<a>").attr("href","#"+div_id).html(link_text)
			).appendTo(jQuery("#readme-tabs"));
			// give the trailing div an id and move the h2 inside
			_this.next("div").attr("id",div_id).prepend(_this);
		});

		// make the tab list do neat stuff
		jQuery("#readme-tabs li:first-child").addClass("active");
		jQuery("#readme-tabs li a").each(function(){
			_this = jQuery(this);
			if(_this.parent().attr("class") != "active") {
				jQuery(_this.attr("href")).hide();
			}
		}).click(function(){
			_this = jQuery(this);
			jQuery(_this.attr("href")).show().siblings("div").hide();
			_this.parent().addClass("active").siblings().removeClass("active");
			return false;
		});

		// @TODO - trigger click on tab link if hash present
		view_section = location.hash.replace("#readme-","");
		jQuery("#readme-tabs li a[href=\"#"+view_section+"\"]").click();
	});
//]]>
</script>
		';
	}
	add_action('admin_head','cfreadme_javascript');
	

// COMPATABILITY

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
	
	
// ADDITIONAL FAQ PAGE SAMPLE CODE

	/**
	 * example of adding another admin menu page using the same plugin code
	 * @NOTE - WP 2.6 add_submenu_page must have a specific identifier for the 1st item
	 *		add_submenu_page('cf-faq',$your_faq_id,$your_faq_title,$user_access_level,$your_faq_page_name,'cfreadme_show');
	 * @NOTE - WPMU 2.6
	 * 		add_submenu_page('index.php',$your_faq_id,$your_faq_title,$user_access_level,$your_faq_page_name,'cfreadme_show'); 
	 */
	/*
	function add_another_readme_menu() {
		global $user_level;
		add_submenu_page('cf-faq','my-faq','My FAQ',$user_level,'my-cf-faq','cfreadme_show');
		add_action('cfreadme_content','another_readme_page_content');
	}
	add_action('admin_menu','add_another_readme_menu',999);

	function another_readme_page_content($readme_content) {
		if($_GET['page'] == 'my-cf-faq') {
			$readme_content = file_get_contents('my-faq.txt');
		}
		return $readme_content;
	}
	*/
?>