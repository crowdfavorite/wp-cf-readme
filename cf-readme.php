<?php
/*
Plugin Name: CF Read Me
Plugin URI: http://crowdfavorite.com
Description: A readme file plugin that translates a <a href="http://daringfireball.net/projects/markdown/syntax">Markdown</a> formatted file in to a readme page. Requires the Crowd Favorite Compatability Plugin to function.
Version: 1.5.1
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
 * 9. Plugin options can be modified via the cfreadme_options filter. See the bottom of this file for example code
 * 10. ReadMe content can now be Enqueued using a WP_Dependencies extended class.
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
		global $wpmu_version, $wp_version, $cfreadme_opts;

		$cfreadme_opts = cfreadme_getopts();

		// add submenu to dashboard
		if (is_admin_page()) {
			if (version_compare($wp_version,'2.7','>=')) {
				add_menu_page($cfreadme_opts['id'],$cfreadme_opts['page_title'],$cfreadme_opts['user_level'],$cfreadme_opts['page_id'],'cfreadme_show');
			}
			else {
				// WP 2.6 compat...
				add_submenu_page('index.php',$cfreadme_opts['id'],$cfreadme_opts['page_title'],$cfreadme_opts['user_level'],$cfreadme_opts['page_id'],'cfreadme_show');
			}
			do_action('cf-readme-admin-menu');
		}
	}
	add_action('admin_menu', 'cfreadme_menu_items');

	/**
	 * Centralized method to get menu options since we need them in multiple places
	 *
	 * @return array
	 */
	function cfreadme_getopts() {
		global $cfreadme_opts;

		if(is_null($cfreadme_opts)) {
			$cfreadme_opts = array(
				'id' => 'faq',
				'page_id' => 'cf-faq',
				'page_title' => 'FAQ',
				'user_level' => 2
			);
			$cfreadme_opts = apply_filters('cfreadme_options',$cfreadme_opts);
		}

		return $cfreadme_opts;
	}


// DISPLAY CONTENT

	/**
	 * Show appropriate FAQ page
	 */
	function cfreadme_show() {
		global $cfreadme;

		if(!class_exists('Markdown')) {
			require_once(realpath(dirname(__FILE__)).'/markdown/markdown.php');
		}
		
		// set default content
		$content = '<h1>'.get_bloginfo('name')." FAQ</h1>\n\n";
		$content = apply_filters('cfreadme_pre_content',$content);
		
		// pull enqueued items if any
		if(is_a($cfreadme,'CF_ReadMe')) {
			$content .= $cfreadme->get_contents();
		}
		
		// apply filters
		$content = apply_filters('cfreadme_content', $content);
		$html = Markdown($content);
		
		// modify content to facilitate the creation of a JavaScript TOC
		$html = preg_replace("/(<\/h2>)(.*?)(<h2>|$)/si","$1<div>$2</div>$3",$html);
		echo '
	<div id="cf-readme" class="wrap cf-readme">
		'.$html.'
	</div>
		';
	}


// ENQUEUE README

	if(class_exists('WP_Dependencies')) {
		/**
		 * Add a readme function to the queue.
		 * Functions added must return their readme content, not echo.
		 * Dependencies will not only dictate order, but wether the item shows at all
		 *
		 * @TODO: create ordering without dependencies
		 *
		 * @param string $handle - unique ID of the script being added 
		 * @param string $src - funtion that returns the readme content
		 * @param array $deps - IDs of any items that need to be shown before this readme
		 * @param int $priority - priority of output
		 */
		function cfreadme_enqueue($handle,$src,$deps=array(),$priority=5) {
			global $cfreadme;
			if(!is_a($cfreadme,'CF_Readme')) {
				$cfreadme = new CF_Readme;
			}
		
			// if deps was passed as a string then compensate
			if(!is_array($deps)) {
				$deps = array($deps);
			}
		
			$cfreadme->add($handle,$src,$deps);
			$cfreadme->enqueue($handle);
			$cfreadme->set_priority($handle,$priority);
		}
	
		/**
		 * Register some readme content.
		 * Adds readme content to the class, but the content won't show
		 * unless another readme addition lists it as a dependency, in which
		 * case this script will be added and shown first.
		 *
		 * @param string $handle - unique ID of the script being added 
		 * @param string $src - funtion that returns the readme content
		 * @param array $deps - IDs of any items that need to be shown before this readme
		 */
		function cfreadme_register($handle,$src,$deps=array()) {
			global $cfreadme;
			if(!is_a($cfreadme,'CF_Readme')) {
				$cfreadme = new CF_Readme;
			}

			$cfreadme->add($handle,$src,$deps);		
		}
	
		/**
		 * Remove a readme from the queue
		 *
		 * @param string $handle 
		 */
		function cfreadme_deregister($handle) {
			global $cfreadme;
			if(!is_a($cfreadme,'CF_Readme')) {
				$cfreadme = new CF_Readme;
			}

			$cfreadme->remove($handle);
		}

		/**
		 * CF_Readme
		 * A class that extends the WordPress WP_Dependencies class to 
		 * provide an accessible means of adding readme content as well
		 * as provide for a means of basic ordering by enabling a plugin
		 * to define any other items that must come before it.
		 *
		 * Use the accessor functions above to use this functionality
		 * @todo add priority ordering
		 *
		 * @uses WP_Dependencies
		 */
		class CF_Readme extends WP_Dependencies {

			var $priorities;
			var $content;
			
			/**
			 * Construct
			 */
			function __construct() {
				$this->content = '';
			}
			
			/**
			 * PHP4 compat
			 */
			function CF_Readme() {
				$this->__construct();
			}
			
			/**
			 * Build the contents of the total ReadMe output
			 *
			 * @param bool/array $handles 
			 * @return string html
			 */
			function get_contents($handles = false) {
				$this->order_by_priority();
				$this->do_items($handles);
				return $this->content;
			}

			/**
			 * concatenate the readme in to the content var
			 *
			 * @param string $handle 
			 */
			function do_item($handle) {
				$func = $this->registered[$handle]->src;
				if(function_exists($func)) {
					$this->content .= "\n".$func()."\n";
				}
			}
			
			/**
			 * build an array of priorities for later ordering of output
			 *
			 * @param string $handle 
			 * @param int $priority 
			 * @return bool
			 */
			function set_priority($handle,$priority) {
				return $this->priorities[$handle] = $priority;
			}
			
			/**
			 * Order the queue by priority
			 * Dependencies still take precendence over priortiy, so items dependent
			 * upon another item will come AFTER their dependency
			 */
			function order_by_priority() {
				uasort($this->queue,array($this,'order_by_callback'));
			}
			
				/**
				 * Comparison function for priority ordering
				 *
				 * @param string $a - readme handle
				 * @param string $b - readme handle
				 * @return int
				 */
				function order_by_callback($a,$b) {
					return $this->priorities[$a] < $this->priorities[$b] ? -1 : 1;
				}
		}
		
	} // end if(class_exists('WP_Dependencies'))
	
// CSS

	function cfreadme_css() {
		// global $plugin_page;
		// $opts = cfreadme_getopts();
		// if($plugin_page != $opts['page_id']) { return; }
		if(!is_plugin_page()) { return; }
		
		echo "
		<style type='text/css'>
			#cf-readme {
				width: 90%;
			}
			#cf-readme .cfreadme-intro {
				margin: 15px 0;
			}
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
			.cf-readme #readme-tabs {
				list-style-type: none;
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
			.cf-readme img {
				background: white;
				border: 1px solid gray;
				padding: 5px;
			}
			#readme-tabs li.active {
				list-style-type: circle;
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
	jQuery(function($) {
		$("#menu-dashboard")
			.after($("#toplevel_page_cf-faq"))
			.after($("#adminmenu li.wp-menu-separator:first").clone(true));
	});

		';
		
		// get out if we're not on the plugin page
		if(!is_plugin_page()) { 
			echo '
//]]>
</script>			
			';
			return; 
		}
		
		echo '

	// find all H2s and make tab sets
	jQuery(function(){
		function cf_js_sanitize(string) {
			return string.replace(/[^\w]+/g,"-").replace(/[^[:allnum:]]/g,"").toLowerCase();
		}
		
		// allow for a div.cfreadme-intro right after the H1 to preceed the nav list
		var listafter = jQuery(".cf-readme h1");
		if (jQuery(".cf-readme h1 + div.cfreadme-intro").length > 0) {
			listafter = jQuery(".cf-readme h1 + div.cfreadme-intro");
		}

		// make tab ul		
		tabs = jQuery("<ul>").attr("id","readme-tabs").insertAfter(listafter);

		var divcount = 0;
		jQuery(".cf-readme h2").each(function() {
			_this = jQuery(this);
			divcount++;
			
			// make sure the h2 does not contain a link, if so, use the link text
			if (_this.children("a").length) {
				child = _this.children("a");
				link_text = child.html();
				if(child.attr("id").length) {
					div_id = child.attr("id");
					child.removeAttr("id");
				}
				else {
					div_id = cf_js_sanitize(link_text);
				}
			}
			else {
				link_text = _this.html();
				div_id = cf_js_sanitize(link_text);
			}
			
			// build link and add to TOC
			// built a bit janky for IE compatability
			jQuery("<a class=\"readme-tab-link\">"+link_text+"</a>").attr("href","#cfs-"+div_id).appendTo("<li>").parent().appendTo(tabs);
						
			// give the trailing div an id and move the h2 inside
			_this.next("div").attr("id",div_id).prepend(_this);
			
			// return to top link
			jQuery("<a>Top</a>").attr("href","#cf-readme").appendTo("<p>").parent().appendTo(_this.parent());
		});

		// make the tab list do neat stuff
		tabs.find("li:first-child").addClass("active");
		tabs.find("li a").each(function() {
			_this = jQuery(this);
			if (_this.parent().attr("class") != "active") {
				jQuery(_this.attr("href").replace("cfs-","")).hide();
			}
		}).click(function() {
			_this = jQuery(this);
			jQuery(_this.attr("href").replace("cfs-","")).show().siblings("div").hide();
			_this.parent().addClass("active").siblings().removeClass("active");
			window.location.hash = _this.attr("href");
			return false;
		});
		
		// grant links who start with a hash the functionality to click the appropriate tab-link item
		jQuery("#cf-readme a[href^=\'#\']").not(".readme-tab-link").click(function() {
			jQuery("#readme-tabs a[href=\'" + jQuery(this).attr("href") + "\']").click();
			return false;
		});

		// trigger click on tab link if hash present
		if (window.location.hash.length) {
			tabs.find("li a[href^="+window.location.hash+"]").click();
		}
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

	/**
	 * Example of modifying the FAQ menu item options
	 */
	/*
	function set_faq_menu_options($options) {
		$options['page_title'] = 'My FAQs';
		return $options;
	}
	add_action('cfreadme_options','set_faq_menu_options');
	*/
?>