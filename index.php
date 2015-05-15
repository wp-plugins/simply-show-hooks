<?php
/*
Plugin Name: Simply Show Hooks
Plugin URI: http://www.calyxagency.com/#plugins
Description: Simply Show Hooks helps theme or plugin developers to quickly see where all the action and filter hooks are on any WordPress page.
Version: 1.1.1
Contributors: stuartobrien, cxthemes, obox
Author URI: http://www.calyxagency.com/#plugins
License: GPLv2 or later
Text Domain: simply-show-hooks
Domain Path: /localization/
*/

defined( 'ABSPATH' ) or die( 'No Trespassing!' ); // Security

if ( ! class_exists( 'Simply_Show_Hooks' ) ) :

class Simply_Show_Hooks {
	
	private $status;
	
	private $all_hooks = array();
	
	private $recent_hooks = array();
	
	private $ignore_hooks = array();
	
	private $doing = 'collect';
	

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
		
		// Use this to set any tags known to cause display problems.
		// Will be display in sidebar.
		$this->ignore_hooks = apply_filters( 'ssh_ignore_hooks', array(
			'attribute_escape',
			'body_class',
			'the_post',
			'post_edit_form_tag',
			//'gettext',
		) );

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
				$this->status = 'off';
			}
		}
		
		if ( $this->status == 'show-action-hooks' || $this->status == 'show-filter-hooks' ) {
			
			add_filter( 'all', array( $this, 'hook_all_hooks' ), 100 );
			add_action( 'shutdown', array( $this, 'notification_switch' ) );
			
			add_action( 'shutdown', array( $this, 'filter_hooks_panel' ) );
		}
	}
	
	/*
	 * Admin Menu top bar
	 */
	function admin_bar_menu( $wp_admin_bar ) {
		
		if ( 'show-action-hooks' == $this->status ) {
			
			$title 	= __( 'Hide Action Hooks' , 'simply-show-hooks' );
			$href 	= '?ssh-hooks=off';
			$css 	= 'ssh-hooks-on ssh-hooks-normal';
		}
		else {
			
			$title 	= __( 'Show Action Hooks' , 'simply-show-hooks' );
			$href 	= '?ssh-hooks=show-action-hooks';
			$css 	= '';
		}
		
		$wp_admin_bar->add_menu(array(
			'title'		=> '<span class="ab-icon"></span><span class="ab-label">' . __( 'Simply Show Hooks' , 'simply-show-hooks' ) . '</span>',
			'id'		=> 'ssh-main-menu',
			'parent'	=> false,
			'href'		=> $href,
		));
		
		$wp_admin_bar->add_menu(array(
			'title'		=> $title,
			'id'		=> 'ssh-simply-show-hooks',
			'parent'	=> 'ssh-main-menu',
			'href'		=> $href,
			'meta'		=> array( 'class' => $css )
		));
		
		
		if ( $this->status=="show-filter-hooks" ) {
			
			$title	= 'Hide Action & Filter Hooks';
			$href	= '?ssh-hooks=off';
			$css 	= 'ssh-hooks-on ssh-hooks-sidebar';
		}
		else {
			
			$title	= 'Show Action & Filter Hooks';
			$href	= '?ssh-hooks=show-filter-hooks';
			$css 	= '';
		}
		
		$wp_admin_bar->add_menu(array(
			'title'		=> $title,
			'id'		=> 'ssh-show-all-hooks',
			'parent'	=> 'ssh-main-menu',
			'href'		=> $href,
			'meta'		=> array( 'class' => $css )
		));
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
	function notification_switch() {
		?>
		<a class="ssh-notification-switch" href="?ssh-hooks=off" >
			<span class="ssh-notification-indicator"></span>
			<?php echo 'Hide Hooks' ; // _e( 'Hide Hooks' , 'simply-show-hooks' ) ?>
		</a>
		<?php
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
		
		if ( $this->status == 'show-action-hooks' || $this->status == 'show-filter-hooks' ) {
			
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
		wp_register_style( 'ssh-main-css', plugins_url( basename( plugin_dir_path( __FILE__ ) ) . '/assets/css/ssh-main.css', basename( __FILE__ ) ), '', '1.1.0', 'screen' );
		wp_enqueue_style( 'ssh-main-css' );

		// Main Scripts
		/*
		wp_register_script( 'ssh-main-js', plugins_url( basename( plugin_dir_path( __FILE__ ) ) . '/assets/js/ssh-main.js', basename( __FILE__ ) ), array('jquery'), '1.1.0' );
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
		
		// Add header marker to hooks collection
		// $this->all_hooks[] = array( 'End Header. Start Body', false, 'marker' );
		
		// Change to doing 'write' which will write the hook as it happens
		$this->doing = 'write';
	}
	
	/**
	 * Render all hooks already in the collection
	 */
	function render_hooks() {
		
		foreach ( $this->all_hooks as $nested_value ) {
			
			if ( 'action' == $nested_value['type'] ) {
				
				$this->render_action( $nested_value );
			}
		}
	}
	
	/**
	 * Hook all hooks
	 */
	
	public function hook_all_hooks( $hook ) {
		
		global $wp_actions, $wp_filter;
		
		if( !in_array( $hook, $this->recent_hooks ) ) {
			
			if ( isset( $wp_actions[$hook] ) ) {
				
				// Action
				$this->all_hooks[] = array(
					'ID'       => $hook,
					'callback' => false,
					'type'     => 'action',
				);
			}
			else {
				
				// Filter
				$this->all_hooks[] = array(
					'ID'       => $hook,
					'callback' => false,
					'type'     => 'filter',
				);
			}
		}
		
		//if ( isset( $wp_actions[$hook] ) && $wp_actions[$hook] == 1 && !in_array( $hook, $this->ignore_hooks ) ) {
		//if (  ( isset( $wp_actions[$hook] ) || isset( $wp_filter[$hook] ) ) && !in_array( $hook, $this->ignore_hooks ) ) {
		if ( isset( $wp_actions[$hook] ) && !in_array( $hook, $this->recent_hooks ) && !in_array( $hook, $this->ignore_hooks ) ) {
			
			// @TODO - caller function testing.
			$callers = false; // @param $callers Array | false for debug_backtrace()
			
			if ( 'write' == $this->doing ) {
				$this->render_action( end( $this->all_hooks ) );
			}
		}
		else{
			//s('(skiped-hook!)');
			//$this->render_action( $hook );
		}
		
		// Discarded functionality: if the hook was 
		// run recently then don't show it again.
		// Better to use the once run or always run theory.
		
		$this->recent_hooks[] = $hook;
		
		if ( count( $this->recent_hooks ) > 100 ) {
			array_shift( $this->recent_hooks );
		}	
	}
	
	/**
	 *
	 * Render action
	 */
	function render_action( $args = array() ) {
		
		global $wp_filter;
		
		// Get all the nested hooks
		$nested_hooks = ( isset( $wp_filter[ $args['ID'] ] ) ) ? $wp_filter[ $args['ID'] ] : false ;
		
		// Count the number of functions on this hook
		$nested_hooks_count = 0;
		if ( $nested_hooks ) {
			foreach ($nested_hooks as $key => $value) {
				$nested_hooks_count += count($value);
			}
		}
		?>
		<span style="display:none;" class="ssh-hook ssh-hook-<?php echo $args['type'] ?> <?php echo ( $nested_hooks ) ? 'ssh-hook-has-hooks' : '' ; ?>" >
			
			<?php
			if ( 'action' == $args['type'] ) {
				?>
				<span class="ssh-hook-type ssh-hook-type">A</span>
				<?php
			}
			else if ( 'filter' == $args['type'] ) {
				?>
				<span class="ssh-hook-type ssh-hook-type">F</span>
				<?php
			}
			?>
			
			<?php
			
			// Main - Write the action hook name.
			//echo esc_html( $args['ID'] );
			echo $args['ID'];
			
			// @TODO - Caller function testing.
			if ( isset( $extra_data[1] ) && FALSE !== $extra_data[1] ) {
				foreach ( $extra_data as $extra_data_key => $extra_data_value ) {
					echo '<br />';
					echo $extra_data_value['function'];
				}
			}
			
			// Write the count number if any function are hooked.
			if ( $nested_hooks_count ) {
				?>
				<span class="ssh-hook-count">
					<?php echo $nested_hooks_count ?>
				</span>
				<?php
			}
			
			// Write out list of all the function hooked to an action.
			if ( isset( $wp_filter[$args['ID']] ) ):
				
				$nested_hooks = $wp_filter[$args['ID']];
				
				if ( $nested_hooks ):
					?>
					<ul class="ssh-hook-dropdown">
						
						<li class="ssh-hook-heading">
							<strong><?php echo $args['type'] ?>:</strong> <?php echo $args['ID']; ?>
						</li>
						
						<?php
						foreach ( $nested_hooks as $nested_key => $nested_value ) :
							
							// Show the priority number if the following hooked functions
							?>
							<li class="ssh-priority">
								<span class="ssh-priority-label"><strong><?php echo 'Priority:'; /* _e('Priority', 'simply-show-hooks') */ ?></strong> <?php echo $nested_key ?></span>
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
	 * Filter Hooks Panel
	 */
	function filter_hooks_panel() {
		
		global $wp_filter, $wp_actions;
		
		?>
		<div class="ssh-nested-hooks-block <?php echo ( 'show-filter-hooks' == $this->status ) ? 'ssh-active' : '' ; ?> ">
			<?php
			foreach ( $this->all_hooks as $va_nested_value ) {
				
				if ( 'action' == $va_nested_value['type'] || 'filter' == $va_nested_value['type'] ) {
					$this->render_action( $va_nested_value );
				}
				else{
					?>
					<div class="ssh-collection-divider">
						<?php echo $va_nested_value['ID'] ?>
					</div>
					<?php
				}
				
				/*
				?>
				<div class="va-action">
					<?php echo $va_nested_value ?>
				</div>
				<?php
				*/
			}
			?>
		</div>
		<?php
	}

}

Simply_Show_Hooks::get_instance();

endif;
