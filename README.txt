# CF ReadMe

The CF ReadMe plugin adds the ability to conglomerate helpful documentation in for users and retain the documentation where it belongs: with the plugin that it describes. Plugin developers can hook in the the ReadMe enqueue functions to add any content to the ReadMe page. The content is run through a Markdown filter before display, but full HTML is preserved if provided.


## Adding content

Adding content to the ReadMe requires 2 functions and an Action. One function to enqueue the content for inclusion and another function to deliver the actual content for display. Below is an example of adding content:

	// README HANDLING
		add_action('admin_init','my_add_readme');

		/**
		 * Enqueue the readme function
		 */
		function my_add_readme() {
			if(function_exists('cfreadme_enqueue')) {
				cfreadme_enqueue('my-plugin','my_readme');
			}
		}
	
		/**
		 * return the contents of the links readme file
		 *
		 * @return string
		 */
		function my_readme() {
			$file = realpath(dirname(__FILE__)).'/README.txt';
			if(is_file($file) && is_readable($file)) {
				$markdown = file_get_contents($file);
				return $markdown;
			}
			return null;
		}

Images may be included with the plugin for display. In your readme content format the images with paths relative to the plugin's images folder, then modify the function that includes the content to pre-process the image links to accommodate the plugin location. This allows images to be delivered with the plugin and not be hard coded to a specific site.

	/**
	 * return the contents of the links readme file
	 * replace the image urls with full paths to this plugin install
	 *
	 * @return string
	 */
	function my_readme() {
		$file = realpath(dirname(__FILE__)).'/README.txt';
		if(is_file($file) && is_readable($file)) {
			$markdown = file_get_contents($file);
			// process images
			$markdown = preg_replace('|!\[(.*?)\]\((.*?)\)|','![$1]('.WP_PLUGIN_URL.'/my-plugin/$2)',$markdown);
			return $markdown;
		}
		return null;
	}

Multiple ReadMe content entries can be added by a single plugin by adding in each block of content with a unique handle.


## Dependencies

ReadMe content can be made dependent upon other readme content being available. To add content that is dependent upon other added content, enqueue the content with that other content's handle listed as a dependency.
	
	cfreadme_enqueue('my-plugin','my_readme',array('other-plugin-handle'));
	
With this option enabled '`my-plugin`' will not appear unless '`other-plugin-handle`' is enqueued. '`my-plugin`' will be displayed AFTER '`other-plugin-handle`' in the TOC.


## Content Formatting Conventions

The plugin is designed to work with MarkDown formatted content, though it is not required. Full HTML is supported.

The Plugin will build a Table of Contents based on `<h2>` elements in the page. Each content area between `<h2>` elements will be designated as a content block and shown/hidden according to click actions on the Table of Contents. The `<h2>` text is used to create the TOC link text. Content divs are automatically assigned IDs.


## Linking to ReadMe Content

The plugin allows content to be directly linked to and displayed by using its ID as the URL Hash. While this functionality exists and works it should not be relied upon as in the future the ids will be replaced with something that won't change when new items are added/removed from the list.

## Multiple ReadMe Pages

TBD - not officially supported, but can be done.