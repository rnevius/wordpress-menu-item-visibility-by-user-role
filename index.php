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

    private function __construct() {
        if ( is_admin() ) {
            add_action( 'delete_post', array( $this, 'remove_menu_visibility_meta' ), 1, 3 );
            add_filter( 'wp_edit_nav_menu_walker', array( $this, 'edit_nav_menu_walker' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'custom_field_html' ), 12, 4 );
            add_action( 'wp_update_nav_menu_item', array( $this, 'update_item_meta' ), 10, 3 );
        } else {
            add_filter( 'wp_nav_menu_objects', array( $this, 'check_item_visibility' ), 10, 2 );
        }
    }

    /**
     * Creates or returns a single instance of this class 
     *
     * @return  A single instance of this class
     */
    public static function get_instance() {
        return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
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
    public static function edit_nav_menu_walker( $walker ) {
        if ( class_exists( 'Walker_Nav_Menu_Edit' ) ) {
            require_once( dirname( __FILE__ ) . '/includes/walker-nav-menu-edit.php' );
        }
        return 'Syntarsus_Walker_Nav_Menu_Edit';
    }

    public static function enqueue_scripts() {
        add_thickbox();
    }


    public function custom_field_html( $item_id, $item, $depth, $args ) {
        $item_id = $item->ID;

        // A list of roles for the lightbox
        $all_roles = array_keys(wp_roles()->get_names());
        sort($all_roles);

        // Get the current meta value, if there is one
        $current_value = get_post_meta( $item_id, '_syntarsus_menu_item_visibility', true );
        $current_value = is_array( $current_value ) ? join(', ', $current_value) : $current_value;
        ?>
        <p class="field-visibility description description-wide">
            <label for="syntarsus-edit-menu-item-visibility-<?php echo $item_id; ?>">
                Restrict to Roles 
                <a href="#TB_inline?width=600&height=550&inlineId=syntarsus-edit-menu-item-visibility-help" class="thickbox dashicons dashicons-editor-help" 
                name="Limit Items to User Roles">&nbsp;</a>
            </label>
            
            <input type="text" 
                   class="widefat code" 
                   id="syntarsus-edit-menu-item-visibility-<?php echo $item_id ?>" 
                   name="syntarsus-menu-item-visibility[<?php echo $item_id; ?>]" 
                   value="<?php echo $current_value; ?>" />
        </p>

        <?php // lightbox ?>
        <div id="syntarsus-edit-menu-item-visibility-help" style="display: none;">
            <p>This field can be used to show this menu item only to specific user roles. The default (blank) will show the menu item to all roles.</p>
            <p>The input accepts a comma-delimited list of user roles. (Example: author, contributor).</p>
            <p>The following user roles are active on this site:</p>
            <p><?php echo join(', ', $all_roles); ?></p>
        </div>
    <?php }

    /**
     * Add or update visibility options for a menu item
     */
    public function update_item_meta( $menu_id, $menu_item_db_id, $args ) {
        $input_value = !empty( $_POST['syntarsus-menu-item-visibility'][$menu_item_db_id] ) ?
                       sanitize_text_field($_POST['syntarsus-menu-item-visibility'][$menu_item_db_id]) :
                       false;
        $new_meta_value = $input_value ? array_map( 'trim', explode(',', $input_value) ) : '';
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

        // If the current user is an administrator, show them everything
        if ( in_array('administrator', $current_user_roles) ) {
            return $menu_items;
        }

        $hidden_items = array();

        foreach ( $menu_items as $key => $menu_item ) {
            // Avoid doing a database call if the item's parent is hidden
            if ( in_array($menu_item->menu_item_parent, $hidden_items) ) {
                $hidden_items[] = $menu_item->ID;
                unset($menu_items[$key]);
            }

            $meta_value = get_post_meta( $menu_item->ID, '_syntarsus_menu_item_visibility', true );

            // Remove the item if the current role isn't allowed to view it
            if ( $meta_value && !array_intersect( $meta_value, $current_user_roles ) ) {
                $hidden_items[] = $menu_item->ID;
                unset($menu_items[$key]);
            }
        }

        return self::update_menu_parent_item_classes($menu_items);
    }

    /**
     * Remove the _syntarsus_menu_item_visibility meta when the menu item is removed
     */
    public function remove_menu_visibility_meta( $post_id ) {
        if ( is_nav_menu_item( $post_id ) ) {
            delete_post_meta( $post_id, '_syntarsus_menu_item_visibility' );
        }
    }

    public static function update_menu_parent_item_classes( $menu_items ) {
        $menu_items_with_children = array();

        // Remove parent item class from all items
        // Determine new list of items with children
        foreach ( $menu_items as &$menu_item ) {
            $menu_item->classes = array_diff($menu_item->classes, array('menu-item-has-children'));

            if ( $menu_item->menu_item_parent ) {
                $menu_items_with_children[ $menu_item->menu_item_parent ] = true;
            }
        }

        // Add the menu-item-has-children class to all parent items
        if ( $menu_items_with_children ) {
            foreach ( $menu_items as &$menu_item ) {
                if ( isset( $menu_items_with_children[ $menu_item->ID ] ) ) {
                    $menu_item->classes[] = 'menu-item-has-children';
                }
            }
        }

        return $menu_items;
    }
}
Syntarsus_Menu_Item_Visibility::get_instance();
