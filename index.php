<?php
/**
 * Plugin Name: Menu Item Visibility Control
 * Plugin URI: https://github.com/rnevius/wordpress-menu-item-visibility-by-user-role
 * Description: Limit menu items to specific user roles.
 * Version: 1.0.0
 * Author: Ryan Nevius
 * Author URI: http://ryannevius.com
 * Requires at least: 4.8
 * Tested up to: 4.8
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Syntarsus_Menu_Item_Visibility {

    private static $instance = null;

    /**
     * Creates or returns a single instance of this class 
     *
     * @return  A single instance of this class
     */
    public static function get_instance() {
        return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
    }

    public function __construct() {
        if ( is_admin() ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_filter( 'wp_edit_nav_menu_walker', array( $this, 'edit_nav_menu_walker' ) );
            add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'option' ), 12, 4 );
            add_action( 'wp_update_nav_menu_item', array( $this, 'update_option' ), 10, 3 );
            add_action( 'delete_post', array( $this, 'remove_visibility_meta' ), 1, 3);
        } else {
            add_filter( 'wp_nav_menu_objects', array( $this, 'check_item_visibility' ), 10, 2 );
        }
    }

    /**
     * Custom Walker for menu edit
     *
     * Unfortunately, WordPress does not provide
     * a hook to add custom fields to menu item edit screen. This function
     * defines our custom walker to be used.
     *
     * @return string custom walker
     */
    public function edit_nav_menu_walker( $walker ) {
        if ( class_exists( 'Walker_Nav_Menu_Edit' ) ) {
            require_once( dirname( __FILE__ ) . '/includes/walker-nav-menu-edit.php' );
        }
        return 'Syntarsus_Walker_Nav_Menu_Edit';
    }

    public function enqueue_scripts() {
        add_thickbox();
    }

    public function option( $item_id, $item, $depth, $args ) {
        $item_id = $item->ID;
        $roles = array_keys(wp_roles()->get_names());
        sort($roles);
        $current_value = get_post_meta( $item_id, '_syntarsus_menu_item_visibility', true );
        $current_value = is_array( $current_value ) ? join(', ', $current_value) : $current_value;
        ?>
        <p class="field-visibility description description-wide">
            <label for="syntarsus-edit-menu-item-visibility-<?php echo $item_id; ?>">
                Restrict to Roles 
                <a href="#TB_inline?width=600&height=550&inlineId=syntarsus-edit-menu-item-visibility-help" class="thickbox dashicons dashicons-editor-help" name="Limit Items to User Roles">&nbsp;</a>
            </label>
            
            <input type="text" class="widefat code" id="syntarsus-edit-menu-item-visibility-<?php echo $item_id ?>" name="syntarsus-menu-item-visibility[<?php echo $item_id; ?>]" value="<?php echo $current_value; ?>" />
        </p>
        <div id="syntarsus-edit-menu-item-visibility-help" style="display: none;">
            <p>This field can be used to show this menu item only to specific user roles. The default (blank) will show the menu item to all roles.</p>
            <p>The input accepts a comma-delimited list of user roles. (Example: author, contributor).</p>
            <p>The following user roles are active on this site:</p>
            <p><?php echo join(', ', $roles); ?></p>
        </div>
    <?php }

    public function update_option( $menu_id, $menu_item_db_id, $args ) {
        $input_value = !empty( $_POST['syntarsus-menu-item-visibility'][$menu_item_db_id] ) ?
                       sanitize_text_field($_POST['syntarsus-menu-item-visibility'][$menu_item_db_id]) :
                       false;
        $new_meta_value = $input_value ? array_map( 'trim', explode(',', $input_value) ) : false;
        $saved_meta_value = get_post_meta( $menu_item_db_id, '_syntarsus_menu_item_visibility', true );

        if ( !$new_meta_value && $saved_meta_value ) {
            delete_post_meta( $menu_item_db_id, '_syntarsus_menu_item_visibility', $saved_meta_value );
        } elseif ( $new_meta_value !== $saved_meta_value ) {
            update_post_meta( $menu_item_db_id, '_syntarsus_menu_item_visibility', $new_meta_value );
        }
    }

    /**
     * Checks the menu items for their visibility options and
     * removes menu items that are not visible.
     *
     * @return array
     */
    public function check_item_visibility( $menu_items, $args ) {
        $current_user_roles = wp_get_current_user()->roles;
        if ( in_array('administrator', $current_user_roles) ) {
            return $menu_items;
        }

        $parent_items = array_filter( $menu_items, function($item) {
            return ! $item->menu_item_parent;
        });
        $hidden_parents = array();

        // Start with parent items to reduce child item database calls,
        // when parent items are hidden
        foreach ($parent_items as $key => $menu_item) {
            $meta_value = get_post_meta( $menu_item->ID, '_syntarsus_menu_item_visibility', true );

            if ( $meta_value && !array_intersect( $meta_value, $current_user_roles ) ) {
                $hidden_parents[] = $menu_item->ID;
                unset($menu_items[$key]);
            }
        }

        // Filter a new list of items to remove parents and children
        // hidden from above
        $menu_items = array_filter( $menu_items, function($item) use ($hidden_parents) {
            return !in_array($item->menu_item_parent, $hidden_parents);
        });
        
        foreach ($menu_items as $key => $menu_item) {
            // We're only concerned with child items here,
            // since parents have been looped through above
            if ( $menu_item->menu_item_parent ) {
                $meta_value = get_post_meta( $menu_item->ID, '_syntarsus_menu_item_visibility', true );

                if ( $meta_value && !array_intersect( $meta_value, $current_user_roles ) ) {
                    unset($menu_items[$key]);
                }
            }
        }

        return $menu_items;
    }

    /**
     * Remove the _syntarsus_menu_item_visibility meta when the menu item is removed
     */
    public function remove_visibility_meta( $post_id ) {
        if ( is_nav_menu_item( $post_id ) ) {
            delete_post_meta( $post_id, '_syntarsus_menu_item_visibility' );
        }
    }
}
Syntarsus_Menu_Item_Visibility::get_instance();
