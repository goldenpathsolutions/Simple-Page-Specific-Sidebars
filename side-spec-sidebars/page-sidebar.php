<?php

/**
 *  Plugin Name: Simple Page Specific Sidebars
 *  Plugin URI: http://wordpress.org/extend/plugins/page-specific-sidebars/
 *  Description: Add a sidebar to any specific page by creating a widget area on demand.
 *  Author: IvyCat Web Services
 *  Author URI: http://www.ivycat.com
 *  Version: 2.13
 *  License: GPLv3
 **/

/**
------------------------------------------------------------------------
Copyright 2011 IvyCat, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/

if( !defined( 'DGSIDEBAR_DIR' ) ) define( 'DGSIDEBAR_DIR', dirname( __FILE__ ) ) ;
if( !defined( 'DGSIDEBAR_URL' ) ) define( 'DGSIDEBAR_URL', str_replace( ABSPATH, site_url( '/' ), DGSIDEBAR_DIR ) ) ;

class DGPageSidebarCustom{
    
    protected $home_id;
    protected $widget_name;
    
    public function __construct(){
        self::set_opts();
        add_filter( 'plugin_action_links_'. plugin_basename( __FILE__ ), array( &$this, 'plugin_action_links' ), 10, 4 );
        add_action( 'widgets_init', array( &$this, 'build_sidebars' ) );
        add_action( 'admin_init', array( &$this, 'add_page_meta_box' ) );
        add_action( 'save_post' , array( &$this, 'save_custom_page_meta' ) );
        add_filter( 'sidebars_widgets', array( &$this, 'hijack_sidebar' ) );
        add_action('admin_menu', array(&$this, 'options_page_init') );
    }
    
/**
* Initialize the options page
*/
    public function options_page_init(){
        if( !current_user_can( 'administrator' ) ) return;
        $hooks = array();
		$hooks[] = add_options_page( __( 'Page Sidebar Settings' ), __( 'Page Sidebar Settings' ), 'read', 'page-sidebar-settings', array( $this, 'option_page' ) );
         foreach( $hooks as $hook ) add_action( "admin_print_styles-{$hook}", array($this, 'load_assets' ) );
    }
    
    public function load_assets(){
         wp_enqueue_style( 'page-spec-sidebar-css', DGSIDEBAR_URL . '/assets/page_spec_styles.css' );
         wp_enqueue_script( 'page-spec-sidebar-js', DGSIDEBAR_URL . '/assets/page-spec_scripts.js' );
    }
    
    public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
        if ( is_plugin_active( $plugin_file ) )
            $actions[] = '<a href="' . admin_url('options-general.php?page=page-sidebar-settings') . '">' . __( 'Settings & Help', 'page-sidebar-settings' ) . '</a>';
        return $actions;
    }
  
/**
*  Display Options page / Save Options on submit
*/
    public function option_page(){
        if( $_SERVER['REQUEST_METHOD'] == 'POST' ) self::page_sidebar_settings_save();
		global $wp_registered_sidebars;
        require_once 'assets/page-sidebar-options-view.php';
    }
    
    public function page_sidebar_settings_save(){
        update_option( 'page_sidebar_home_id', trim( $_POST['home_page_id'] ) );
        update_option( 'page_sidebar_widget_name', trim( $_POST['primary_sidebar_slug'] )  );
        self::set_opts();
    }
    
    public function set_opts(){
        $this->home_pg_id = ( $home = get_option( 'page_sidebar_home_id' ) ) ? $home : self::home_pg_id();
        $this->widget_name = ( $widget = get_option( 'page_sidebar_widget_name' ) ) ? trim( $widget ) : 'sidebar-1';
    }
    
    public function add_page_meta_box(){
        $location = apply_filters( 'page_sidebar_location', 'side' );
        $priority = apply_filters( 'page_sidebar_priority', 'high' );
        add_meta_box(
            'custompageopt',
            'Custom Page Options',
            array( &$this, 'custom_page_meta' ),
            'page',
            $location ,
            $priority
        );
    }
    
    public function custom_page_meta(){
        global $post;
        $is_custom = get_post_meta( $post->ID, 'is_custom', true );
        $add2chk = ( get_post_meta( $post->ID, 'add2sidebar', true ) ) ? 'checked="checked"' : '';
        $checked = ($is_custom == 'y') ? ' checked="checked"' : '' ;
        ?>
            <div class="group">
                <ul>
                    <li>
                        <strong>Has Custom Sidebar: </strong>
                    </li>
                    <li>
                        <input id="iscustom" type="checkbox" name="is-custom" value="y"<?php echo $checked; ?>/>
                        <label for="iscustom">Yes </label>
                    </li>
                    <li class="add-replace">
                        <input type="checkbox" id="addrplce" name="add2sidebar" value="add2chk"<?php echo $add2chk; ?>/>
                        <label for="addrplce">Add to sidebar rather than replace: <label>
                    </li>
                </ul>
            </div>
        <?php
    }
    
    
    public function save_custom_page_meta(){
        if ( defined('DOING_AJAX') ) return;
        global $post;
        update_post_meta( $post->ID, 'is_custom', $_POST['is-custom'] );
        update_post_meta( $post->ID, 'add2sidebar', $_POST['add2sidebar'] );
    }
    
    public function build_sidebars(){
        $pages = self::get_pages();
        $stop = count( $pages );
        $count = 0;
        foreach( $pages as $page ): $count++; 
        if( $count <= $stop ){
            $args = array(
	        'name'          => __( $page->post_title ),
	        'id'            => 'page-sidebar-'. $page->ID ,
	        'description'   => '',
		    'before_widget' => '<div id="%1$s" class="widget %2$s widget-%2$s"><div class="widget-wrap widget-inside">',
		    'after_widget' => '</div></div>',
		    'before_title' => '<h3 class="widget-title">',
		    'after_title' => '</h3>' );
	        
	       register_sidebar( $args ); 
	    }
	    endforeach;
    }
     
    public function hijack_sidebar( $sidebars=array() ){
        global $wp_registered_widgets, $wp_registered_sidebars, $_wp_sidebars_widgets, $post;
        $sidebar_title = apply_filters( 'page-sidebar-title' , $this->widget_name );
        if( !is_page() || is_admin() ) return $sidebars;
        $is_custom = get_post_meta( $post->ID, 'is_custom', true );
        $add2sidebar = get_post_meta( $post->ID, 'add2sidebar', true );
        if( $is_custom != 'y'){
            return $sidebars;
        }else{
            
            $sidebar_term = ( is_front_page() ) ? 'page-sidebar-' . self::home_pg_id() : 'page-sidebar-' . $post->ID;
            $sidebars_widgets = $_wp_sidebars_widgets;
            
            if( !array_key_exists( $sidebar_term, $sidebars_widgets) || count($_wp_sidebars_widgets[$sidebar_term]) < 1 ){
                return $sidebars; 
            }else{
                if( $sidebars_widgets['array_version'] != 3  ) return $sidebars;
                if( $add2sidebar ){ 
                    $sidebars[$sidebar_title] = ( is_array( $_wp_sidebars_widgets[$sidebar_term] ) ) ? array_merge( (array)$sidebars[$sidebar_title], $_wp_sidebars_widgets[$sidebar_term] ) : $sidebars[$sidebar_title];
                }else{
                   $sidebars[$sidebar_title] = $_wp_sidebars_widgets[$sidebar_term]; 
                }
                return $sidebars;
            }
        }
    }
    
    protected function fprint_r($array){
        printf('<pre>%s</pre>', print_r($array, 1));
    }
    
    /**
     *  Often times the Homepage has posts on it, dictated by the template.  For some reason, this sets the post->ID for the page as the last post listed on the page
     *  which keeps this plugin from inserting the correct sidebar.  This is the fix.
     */
    protected function home_pg_id(){
        $home_slug = 'home';
        $home_slug = apply_filters( 'page-sidebar-homeslug', $home_slug );
        $pg = get_page_by_path( $home_slug );
        return $pg->ID;
    }
    
    protected function get_pages(){
        $pages = get_posts( array( 'post_type'=>'page', 'numberposts'=>-1, 'orderby'=>'post_title', "meta_key"=>"is_custom", "meta_value"=>"y" ) );
        return $pages;
    }
} new DGPageSidebarCustom();
