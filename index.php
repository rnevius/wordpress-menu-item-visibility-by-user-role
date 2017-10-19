<?php
/**
 * Plugin Name: Menu Item Visibility Control
 * Plugin URI: https://github.com/rnevius/wordpress-menu-item-visibility-by-user-role
 * Description: Hide individual menu items for certain user roles.
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
        if( class_exists( 'Walker_Nav_Menu_Edit' ) ) {
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
        ?>
        <p class="field-visibility description description-wide">
            <label for="edit-menu-item-visibility-<?php echo $item_id; ?>">
                Hide From: 
                <a href="#TB_inline?width=600&height=550&inlineId=edit-menu-item-visibility-help" class="thickbox dashicons dashicons-editor-help" name="Hide Item from User Roles">&nbsp;</a>
            </label>
            
            <input type="text" class="widefat code" id="edit-menu-item-visibility-<?php echo $item_id ?>" name="menu-item-visibility[<?php echo $item_id; ?>]" value="<?php echo esc_html( get_post_meta( $item_id, '_menu_item_visibility', true ) ); ?>" />
        </p>
        <div id="edit-menu-item-visibility-help" style="display: none;">
            <p>This field can be used to hide this menu item from any number of user roles.</p>
            <p>The input accepts a comma-delimited list of user roles. (Example: author, contributor).</p>
            <p>The following user roles are active on this site:</p>
            <p><?php echo join(', ', $roles); ?></p>
        </div>
    <?php }

    public function update_option( $menu_id, $menu_item_db_id, $args ) {
        if( isset( $_POST['menu-item-visibility'][$menu_item_db_id] ) ) {
            $meta_value = get_post_meta( $menu_item_db_id, '_menu_item_visibility', true );
            $new_meta_value = stripcslashes( $_POST['menu-item-visibility'][$menu_item_db_id] );

            if( '' == $new_meta_value ) {
                delete_post_meta( $menu_item_db_id, '_menu_item_visibility', $meta_value );
            } elseif( $meta_value !== $new_meta_value ) {
                update_post_meta( $menu_item_db_id, '_menu_item_visibility', $new_meta_value );
            }
        }
    }

    /**
     * Checks the menu items for their visibility options and
     * removes menu items that are not visible.
     *
     * @return array
     * @since 0.1
     */
    public function visibility_check( $items, $menu, $args ) {
        $hidden_items = array();
        foreach( $items as $key => $item ) {
            $item_parent = get_post_meta( $item->ID, '_menu_item_menu_item_parent', true );
            if( $logic = get_post_meta( $item->ID, '_menu_item_visibility', true ) )
                eval( '$visible = ' . $logic . ';' );
            else
                $visible = true;
            if( ! $visible
                || isset( $hidden_items[$item_parent] ) // also hide the children of invisible items
            ) {
                unset( $items[$key] );
                $hidden_items[$item->ID] = '1';
            }
        }

        return $items;
    }

    /**
     * Remove the _menu_item_visibility meta when the menu item is removed
     *
     * @since 0.2.2
     */
    public function remove_visibility_meta( $post_id ) {
        if( is_nav_menu_item( $post_id ) ) {
            delete_post_meta( $post_id, '_menu_item_visibility' );
        }
    }
}
Syntarsus_Menu_Item_Visibility::get_instance();
