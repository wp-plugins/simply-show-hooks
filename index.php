<?php
/*
Plugin Name: Simply Show Hooks
Plugin URI: http://www.calyxagency.com/#plugins
Description: Simply Show Hooks helps theme or plugin developers to quickly see where all the action and filter hooks are on any WordPress page.
Version: 1.0.0
Author: Stuart O'Brien, cxThemes
Author URI: http://www.calyxagency.com/#plugins
License: GPLv2 or later
Text Domain: simply-show-hooks
Domain Path: /localization/
*/

defined( 'ABSPATH' ) or die( 'No Trespassing!' ); // Security

if ( ! class_exists( 'Simply_Show_Hooks' ) ) :

class Simply_Show_Hooks {
	
	private $status;
	
	private $hooks_collection = array();
	
	/**
	 * Use this to set any tags known to cause problems.
	 * and won't be missed
	 */
	
	private $ignore_hooks;

	/**
	*  Instantiator
	*/
	public static function get_instance() {
		
		static $instance = null;
		
		if ( null === $instance ) {
			$instance = new self();
			$instance->init();
		}
		
		return $instance;
	}
	
	/**
	 * Construct and initialize the main plugin class
	 */
	
	public function __construct() {}
	
	function init() {
		
		// Ignore these hooks know to cause problems
		$this->ignore_hooks = apply_filters( 'ssh_ignore_hooks', array( 'attribute_escape', 'body_class', 'the_post', 'post_edit_form_tag' ) );

		// Translations
		add_action( 'plugins_loaded', array( $this, 'load_translation' ) );
		
		// Init
		add_action( 'init', array( $this, 'wp_init' ) );
		
		if ( ! isset( $this->status ) ) {
			
			if ( !isset( $_COOKIE['ssh_status']) )
				setcookie('ssh_status', 'off', time()+3600*24*100, '/');
			
			if ( isset( $_REQUEST['ssh-hooks']) ) {
				setcookie('ssh_status', $_REQUEST['ssh-hooks'], time()+3600*24*100, '/');
				$this->status = $_REQUEST['ssh-hooks'];
			}
			elseif ( isset( $_COOKIE['ssh_status']) ) {
				$this->status = $_COOKIE['ssh_status'];
			}
			else{
				$this->status = "off";
			}
		}
		
		if ( $this->status == 'show' || $this->status == 'show-nested' ) {
			
			add_action( 'shutdown', array( $this, 'notification_switch' ) );
			add_action( 'all', array( $this, 'build_hooks_collection' ), 1 );
		}
	}
	
	function wp_init() {
		
		// Restrict use to Admins only
		if ( !current_user_can('manage_options') ) { return false; }
		
		// Enqueue Scripts/Styles - in head of admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_script' ) );
		
		// Top Admin Bar
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu'), 90 );
		// Top Admin Bar Styles
		add_action( 'wp_print_styles', array( $this, 'add_builder_edit_button_css' ) );
		add_action( 'admin_print_styles', array( $this, 'add_builder_edit_button_css' ) );
		
		if ( $this->status == 'show' || $this->status == 'show-nested' ) {
			
			//Final hook - render the nested action array
			add_action( 'admin_head', array( $this, 'render_head_hooks'), 100 ); // Back-end - Admin
			add_action( 'wp_head', array( $this, 'render_head_hooks'), 100 ); // Front-end
			add_action( 'login_head', array( $this, 'render_head_hooks'), 100 ); // Login
			add_action( 'customize_controls_print_scripts', array( $this, 'render_head_hooks'), 100 ); // Customizer
		}
	}
	
	/**
	 * Enqueue Scripts
	 */
	
	public function enqueue_script() {
		
		global $wp_scripts, $current_screen;
		
		// Main Styles
		wp_register_style( 'ssh-main-css', plugins_url( basename( plugin_dir_path( __FILE__ ) ) . '/assets/css/ssh-main.css', basename( __FILE__ ) ), '', '1.0.0', 'screen' );
		wp_enqueue_style( 'ssh-main-css' );

		// Main Scripts
		/*
		wp_register_script( 'ssh-main-js', plugins_url( basename( plugin_dir_path( __FILE__ ) ) . '/assets/js/ssh-main.js', basename( __FILE__ ) ), array('jquery') );
		wp_enqueue_script( 'ssh-main-js' );
		wp_localize_script('ssh-main-js', 'ssh-main-js', array(
			'home_url' => get_home_url(),
			'admin_url' => admin_url(),
			'ajaxurl' => admin_url('admin-ajax.php')
		));
		*/
	}
	
	/**
	 * Localization
	 */
	
	public function load_translation() {
		load_plugin_textdomain( 'simply-show-hooks', false, dirname( plugin_basename( __FILE__ ) ) . '/localization/' );
	}
	
	/**
	 * Render Head Hooks
	 */
	function render_head_hooks() {
		
		// Render all the hooks so far
		$this->render_hooks();
		
		// After the header start to write out the hooks as they happen
		add_action( 'all', array( $this, 'hook_all_hooks' ) );
	}
	
	/**
	 * Render all hooks already in the collection
	 */
	function render_hooks() {
		
		foreach ( $this->hooks_collection as $nested_key => $nested_value ) {
			
			$this->render_action( $nested_value[0], $nested_value[1] );
		}
	}
	
	/**
	 * Hook all hooks
	 */
	
	public function hook_all_hooks( $data ) {
		
		global $wp_actions;
		
		if ( isset( $wp_actions[$data] ) && $wp_actions[ $data ] == 1 && ! in_array( $data, $this->ignore_hooks ) ) {
			
			$this->render_action( $data );
		}
	}
	
	/**
	 *
	 * Render action
	 */
	function render_action ( $data, $extra_data = false ) {
		
		global $wp_filter;
		
		// Get all the nested hooks
		$nested_hooks = ( isset( $wp_filter[$data] ) ) ? $wp_filter[$data] : false ;
		
		// Count the number of functions on this hook
		$nested_hooks_count = 0;
		if ( $nested_hooks ) {
			foreach ($nested_hooks as $key => $value) {
				$nested_hooks_count += count($value);
			}
		}
		?>
		<span style="display:none;" class="ssh-action <?php echo ( $nested_hooks ) ? 'ssh-action-has-hooks' : '' ; ?>" >
			<?php
			
			// Main - Write the action hook name.
			echo esc_html( $data );
			
			// @TODO - Caller function testing.
			if ( $extra_data ) {
				foreach ( $extra_data as $extra_data_key => $extra_data_value ) {
					echo '<br>';
					echo $extra_data_value['function'];
				}
			}
			
			// Write the count number if any function are hooked.
			if ( $nested_hooks_count ) {
				?>
				<span class="ssh-action-count">
					<?php echo $nested_hooks_count ?>
				</span>
				<?php
			}
			
			// Write out list of all the function hooked to an action.
			if ( isset( $wp_filter[$data] ) ):
				
				$nested_hooks = $wp_filter[$data];
				
				if ( $nested_hooks ):
					?>
					<ul class="ssh-action-dropdown">
						
						<?php
						foreach ( $nested_hooks as $nested_key => $nested_value ) :
							
							// Show the priority number if the following hooked functions
							?>
							<li class="ssh-priority">
								<span class="ssh-priority-label"><strong><?php _e('Priority', 'simply-show-hooks') ?></strong> <?php echo $nested_key ?></span>
							</li>
							<?php
							
							foreach ( $nested_value as $nested_inner_key => $nested_inner_value ) :
								
								// Show all teh functions hooked to this priority of this hook
								?>
								<li>
									<?php
									if ( $nested_inner_value['function'] && is_array( $nested_inner_value['function'] ) && count( $nested_inner_value['function'] ) > 1 ):
										
										// Hooked function ( of type object->method() )
										?>
										<span class="ssh-function-string">
											<?php
											$classname = false;
											
											if ( is_object( $nested_inner_value['function'][0] ) || is_string( $nested_inner_value['function'][0] ) ) {
												
												if ( is_object( $nested_inner_value['function'][0] ) ) {
													$classname = get_class($nested_inner_value['function'][0]);
												}
												
												if ( is_string( $nested_inner_value['function'][0] ) ) {
													$classname = $nested_inner_value['function'][0];
												}
												
												if ( $classname ) {
													?><?php echo $classname ?>&ndash;&gt;<?php
												}
											}
											?><?php echo $nested_inner_value['function'][1] ?>
										</span>
										<?php
									else :
										
										// Hooked function ( of type function() )
										?>
										<span class="ssh-function-string">
											<?php echo $nested_inner_key ?>
										</span>
										<?php
									endif;
									?>
									
								</li>
								<?php
								
							endforeach;
							
						endforeach;
						?>
						
					</ul>
					<?php
				endif;
				
			endif;
			?>
		</span>
		<?php
	}
	
	/*
	 * Admin Menu top bar
	 */
	function admin_bar_menu( $wp_admin_bar ) {
		
		if ( $this->status=="show" ) {
			
			$title 	= __( 'Hide Hooks' , 'simply-show-hooks' );
			$href 	= "?ssh-hooks=off";
			$css 	= "ssh-hooks-on ssh-hooks-normal";
		}
		else {
			
			$title 	= __( 'Show Hooks' , 'simply-show-hooks' );
			$href 	= "?ssh-hooks=show";
			$css 	= "";
		}
		
		$wp_admin_bar->add_menu(array(
			'title'		=> '<span class="ab-icon"></span><span class="ab-label">' . __( 'Simply Show Hooks' , 'simply-show-hooks' ) . '</span>',
			'id'		=> "ssh-main-menu",
			'parent'	=> false,
			'href'		=> $href,
		));
		
		$wp_admin_bar->add_menu(array(
			'title'		=> $title,
			'id'		=> 'ssh-simply-show-hooks',
			'parent'	=> "ssh-main-menu",
			'href'		=> $href,
			'meta'		=> array( 'class' => $css )
		));
		
		/*
		if ( $this->status=="show-nested" ) {
			
			$title	= "Hide Hooks in Sidebar";
			$href	= "?ssh-hooks=off";
			$css 	= "ssh-hooks-on ssh-hooks-sidebar";
		}
		else {
			
			$title	= "Simply Show Hooks in Sidebar";
			$href	= "?ssh-hooks=show-nested";
			$css 	= "";
		}
		
		$wp_admin_bar->add_menu(array(
			'title'		=> $title,
			'id'		=> 'ssh-show-all-hooks',
			'parent'	=> "ssh-main-menu",
			'href'		=> $href,
			'meta'		=> array( 'class' => $css )
		));
		*/
	}
	
	// Custom css to add icon to admin bar edit button.
	function add_builder_edit_button_css() {
		?>
		<style>
		#wp-admin-bar-ssh-main-menu .ab-icon:before{
			font-family: "dashicons" !important;
			content: "\f323" !important;
			font-size: 16px !important;
		}
		</style>
		<?php
	}

	/*
	 * Notification Switch
	 * Displays notification interface that will alway display
	 * even if the interface is corrupted in other places.
	 */
	function notification_switch( $data ) {
		?>
		<a class="ssh-notification-switch" href="?ssh-hooks=off" >
			<span class="ssh-notification-indicator"></span>
			<?php _e( 'Hide Hooks' , 'simply-show-hooks' ) ?>
		</a>
		<?php
	}
	
	/*
	 * Build a collection of all the hooks to display in one big clump - discarded
	 */
	
	public function build_hooks_collection( $data ) {

		global $wp_actions, $wp_filter, $recent_hooks;
		
		if ( $this->status != "off" ) {
			
			//Get all the nested hooks
			$nested_hooks = ( isset( $wp_filter[$data] ) ) ? $wp_filter[$data] : false ;
			
			//Count the number of functions on this hook
			$nested_hooks_count = 0;
			
			if ( $nested_hooks ) {
				foreach ($nested_hooks as $key => $value) {
					$nested_hooks_count += count($value);
				}
			}
			
			/*
			// Discarded functionality: if the hook was
			// run recently then don't show it again.
			// Better to use the once run or always run theory.
			if ( !isset( $recent_hooks ) ) $recent_hooks = array();
			if ( count( $recent_hooks ) > 10 ) {
				array_shift( $recent_hooks );
			}
			*/
			
			if ( isset( $wp_actions[$data] ) && $wp_actions[$data] == 1 && !in_array( $data, $this->ignore_hooks ) ) {
				
				// @TODO - caller function testing.
				//$callers = debug_backtrace();
				
				$callers = false;
				
				$this->hooks_collection[] = array(
					$data,
					$callers //Array | false
				);
			}
		}
	}

}

Simply_Show_Hooks::get_instance();

endif;
