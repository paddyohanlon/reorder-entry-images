<?php
/**
 * Reorder Entry images
 *
 * @package   ReorderEntryImages
 * @author    Vayu Robins <v@vayu.dk>
 * @license   GPL-2.0+
 * @link      http://vayu.dk/reorder-entry-images/
 * @copyright 2013 Vayu Robins
 */

/**
 * Plugin class.
 *
 * @package ReorderEntryImages
 * @author  Vayu Robins <v@vayu.dk>
 */
class ReorderEntryImages {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	protected $version = '1.0.0';

	/**
	* Unique identifier for your plugin.
	*
	* Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	* match the Text Domain file header in the main plugin file.
	*
	* @since 1.0.0
	*
	* @var string
	*/
	protected $plugin_slug = 'reorder-entry-images';

	/**
	 * Instance of this class.
	 *
	 * @since    1.2.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	* Slug of the plugin screen.
	*
	* @since 1.0.0
	*
	* @var string
	*/
	protected $plugin_screen_hook_suffix = 'reorder-entry-images';

	/**
	 * Entry type to add the metabox to.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $the_post_type = 'page';

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		if( is_admin() ) :
			// Load plugin text domain
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Add the options page and menu item.
			add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

			// Register plugin settings.
			add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );

			// Load admin style sheet and JavaScript.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

			// Get the post type from options settings and save in variable
			$this->the_post_type = get_option( 'rei-options' );

			// Add metabox on the proper metabox hook
			if( is_array( $this->the_post_type ) ) {
				foreach ( $this->the_post_type as $type ) {
					add_action( 'add_meta_boxes_' . $type, array( $this, 'add_image_sortable_box' ) );
				}
			}
			
			// Updates the attachments when saving
			add_filter( 'wp_insert_post_data', array( $this, 'sort_images_meta_save' ), 99, 2 );

		endif;

	}


	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }

		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_{$plugin}" );
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }

		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "deactivate-plugin_{$plugin}" );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		$screen = get_current_screen();

		if ( in_array( $screen->id, $this->the_post_type ) ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), $this->version );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since    1.0.0
	 *
	 * @return   null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		$screen = get_current_screen();
		if ( in_array( $screen->id, $this->the_post_type ) ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), $this->version );
		}

	}

	/**
	* Register the administration menu for this plugin into the WordPress Dashboard menu.
	*
	* @since 1.0.0
	*/
	public function add_plugin_admin_menu() {
		add_options_page(
			__( 'Reorder entry images', $this->plugin_slug ),
			__( 'Reorder images', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);
	}

	/**
	* Render the settings page for this plugin.
	*
	* @since 1.0.0
	*/
	public function display_plugin_admin_page() {
		include_once( 'views/admin.php' );
	}

	/**
	 * Register plugin settings
	 *
	 * @since     1.0.0
	 */
	public function register_plugin_settings() {

		register_setting( 'rei_the_settings_group', 'rei-options' );
		
		add_settings_section( 'rei-section-posttype', __( 'Post type', 'reorder-entry-images' ), array( $this, 'rei_general_settings_callback'), $this->plugin_slug );
		
		add_settings_field( 'rei-field-posttype', __( 'Post type', 'reorder-entry-images' ), array( $this, 'rei_general_settings_field_callback'), $this->plugin_slug, 'rei-section-posttype' );
	}

	/**
	* Render the settings section page for this plugin.
	*
	* @since 1.0.0
	*/
	public function rei_general_settings_callback() {
		echo 'Choose which post type you would like to use the reorder images functionality.';
	}

	/**
	* Render the settings field for this plugin.
	*
	* @since 1.0.0
	*/
	public function rei_general_settings_field_callback() {

		$settings = (array) get_option( 'rei-options' );
		foreach( self::getObjectTypes() as $type ) {
			if( $type->name != 'attachment' ) {
				echo '<label class="inline"><input type="checkbox" name="rei-options['.$type->name.']" value="'.esc_attr($type->name).'" '.checked( $type->name, isset( $settings[$type->name] ) ? $settings[$type->name] : 0, false ).' /> '.esc_html($type->label).'</label>';
			}
		}
		?>
		<span class="description"><?php _e("You can use this builtin-in or custom post types.", 'sort-entry-images'); ?></span>
		<?php
	}


	/**
	 * Add a custom metabox to post, page or cpt, that displays the attachments in a list.
	 *
	 * @since   1.0.0
	 */
	public function add_image_sortable_box() {

		$images = get_children(
			array(
				'post_parent' => get_the_ID(),
				'post_type' => 'attachment',
				'post_mime_type' => 'image'
			)
		);
		if ( $images ) {
			if( is_array( $this->the_post_type ) ) {
				foreach( $this->the_post_type as $type ) {
					add_meta_box(
						'sort-entry-images',
						__( 'Sort your images with drag & drop', 'sort-entry-images' ),
						array( $this, 'add_image_metabox_sorter' ),
						$type,
						'normal',
						'default'
					);
				}
			}
		}
	}

	/**
	 * Gets all attachments and displays them in a sortable list on admin pages.
	 *
	 * @param 	array|object 	$p
	 * @since   1.0.0
	 */
	public function add_image_metabox_sorter( $p ) { 
	
		$thumb_id = get_post_thumbnail_id( get_the_ID() );

		$args = array(
			'order'          => 'ASC',
			'orderby'        => 'menu_order',
			'post_type'      => 'attachment',
			'post_parent'    => get_the_ID(),
			'post_mime_type' => 'image',
			'post_status'    => null,
			'numberposts'    => -1,
			'exclude'		 => $thumb_id // Exclude featured thumbnail
		);

		$attachments = get_posts( $args );

		if( $attachments ) :
			wp_nonce_field( 'custom_images_sort', 'images_sort_nonce' ); ?>

			<div class="imageuploader">
				<div id="attachmentcontainer">
					<?php $i = 0; foreach( $attachments as $attachment ) :
						$attachmentid = $attachment->ID;
						$editorimage = wp_get_attachment_image_src( $attachment->ID, 'thumbnail', false, false);
						$i++;
						?>
						<div class="attachment" id="image-<?php echo $attachment->ID; ?>">
							<div class="image">
								<img width="100" height="auto" src="<?php echo esc_attr( $editorimage[0] ); ?>" />
								<input type="hidden" name="att_id[]" id="att_id" value="<?php echo esc_attr( $attachment->ID ); ?>" />
							</div>
							<div class="title"><?php echo esc_attr( $attachment->post_title ); ?></div>
							<span class="number"><?php echo esc_attr( intval( $i ) ); ?></span>
						</div>
					<?php endforeach; ?>
					<div style="clear: both;"></div>
				</div>      
			</div>

		<?php
		endif;
	}

	/**
	 * Saves the data to the post
	 *
	 * @param 	array 	$data			Sinitized post data
	 * @param 	array 	$_post_vars		Raw post data
	 * @return	$data
	 * @since   1.0.0
	 */
	public function sort_images_meta_save( $data, $_post_vars ) {
		//global $post_ID;
		$post_ID = $_post_vars['ID'];
		
		if( !in_array( $data['post_type'], $this->the_post_type ) || !isset( $_post_vars['images_sort_nonce'] ) ) {
			return $data;
		}

		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $data;
		}

		if( !wp_verify_nonce( $_post_vars['images_sort_nonce'], 'custom_images_sort' ) ) {
			return $data;
		}

		if( !current_user_can( 'edit_post', $post_ID ) ) {
			return $data;
		}

		if( isset( $_post_vars['att_id'] ) ) {
			foreach( $_post_vars['att_id'] as $img_index => $img_id ) {
				$a = array(
					'ID' => $img_id,
					'menu_order' => $img_index
				);
				wp_update_post( $a );
			}
		}
		return $data;
	}

	/**
	 * Use to get post types
	 *
	 * @param string $key 
	 * @return array|object
	 */
	private static function getObjectTypes( $key = '' ) {
		// Get all post types registered.
		$object_types = get_post_types( array('public' => true), 'objects' );
		$object_types = apply_filters( 'rei-post-object-types', $object_types, $key );
		if ( isset($object_types[$key]) ) {
			return $object_types[$key];
		}
		
		return $object_types;
	}
}