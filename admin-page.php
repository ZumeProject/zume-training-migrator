<?php
/**
 * Plugin Name: Zume Training Migrator
 * Plugin URI: https://github.com/DiscipleTools/disciple-tools-one-page-extension
 * Description: One page extension of Disciple Tools
 * Version:  0.1.0
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/DiscipleTools/disciple-tools-one-page-extension
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.3
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

if ( ! function_exists( 'zume_training_migrator' ) ) {
    function zume_training_migrator() {
        return Zume_Training_Migrator::instance();
    }
}
add_action( 'after_setup_theme', 'zume_training_migrator' );

class Zume_Training_Migrator {


    /**
     * #1 SET VARIABLES
     */
    public $loop_size = 500;
    public $description = 'Update all the pieces pages to a new post type zume_pages.';
    public $title = 'Zume Training Migrator';

    /**
     * #2 EDIT QUERY
     */
    public function source_query() {
        global $wpdb;

        $wpdb->get_results("DELETE FROM wp_posts WHERE post_type = 'iwp_log';");

        $wpdb->get_results(
            "UPDATE wp_posts SET post_type = 'zume_pieces' WHERE ID IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_wp_page_template' and meta_value = 'template-zume-pieces-page.php');"
            , ARRAY_A );

        $wpdb->get_results(
            "UPDATE wp_posts SET post_type = 'zume_page'  WHERE post_type = 'page'"
            , ARRAY_A );

        return [];
    }

    /**
     * #3 EDIT TASK TO EXECUTE
     */
    public function run_task( $result ) {

        /**
         * PROCESS TASK
         */

    }





    /******************************************************************************************************************
     * PAGE SUPPORTS
     ******************************************************************************************************************/
    public function run_loop( $step ){
        $results = $this->source_query();
        $total_count = count( $results );
        $loop_count = 0;
        $processed_count = 0;
        foreach( $results as $index => $result ) {
            $loop_count++;
            if ( $loop_count < $step ) {
                continue;
            }
            $processed_count++;
            $this->run_task( $result );
            if ( $processed_count > $this->loop_size ) {
                break;
            }
        }
        if ( $loop_count >= $total_count  ) {
            return;
        }
        ?>
        <tr>
            <td><img src="<?php echo esc_url( plugin_dir_url(__FILE__) ) ?>/spinner.svg" width="30px" alt="spinner" /></td>
        </tr>
        <script type="text/javascript">
            function nextpage() {
                location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token ) ?>&loop=true&step=<?php echo esc_attr( $loop_count ) ?>&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
            }
            setTimeout( "nextpage()", 1500 );
        </script>
        <?php
    }
    public function admin_page() {
        if ( !current_user_can( $this->permissions ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr><th>Zúme Training Migrator</th></tr>
            <tr><th>DESCRIPTION: <?php echo $this->description ?></th></tr>
            <tr>
                <th><p style="max-width:450px"></p>
                    <p><a class="button" id="upgrade_button" href="<?php echo esc_url( trailingslashit( admin_url() ) ) ?>admin.php?page=<?php echo esc_attr( $this->token ) ?>&loop=true" disabled="true">Upgrade</a></p>
                </th>
            </tr>
            </thead>
            <tbody>
            <?php
            /* disable button */
            if ( ! isset( $_GET['loop'] ) ) {
                ?>
                <script>
                    jQuery(document).ready(function(){
                        jQuery('#upgrade_button').removeAttr('disabled')
                    })

                </script>
                <?php
            }
            /* Start loop & add spinner */
            if ( isset( $_GET['loop'] ) && ! isset( $_GET['step'] ) ) {
                ?>
                <tr>
                    <td><img src="<?php echo esc_url( get_theme_file_uri() ) ?>/spinner.svg" width="30px" alt="spinner" /></td>
                </tr>
                <script type="text/javascript">
                    function nextpage() {
                        location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token )  ?>&loop=true&step=0&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
                    }
                    setTimeout( "nextpage()", 1500 );
                </script>
                <?php
            }

            /* Loop */
            if ( isset( $_GET['loop'], $_GET['step'], $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'loop'.get_current_user_id() ) ) {
                $step = sanitize_text_field( wp_unslash( $_GET['step'] ) );
                $this->run_loop( $step );
            }

            ?>
            </tbody>
        </table>
        <?php
    }
    public $token = 'zume_training_migrator';
    public $permissions = 'manage_options';
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    public function __construct() {
        if ( is_admin() ) {
            add_action( "admin_menu", array( $this, "register_menu" ) );
        }
    }
    public function register_menu() {
        add_menu_page( 'Zume Migrator', 'Zume Migrator', $this->permissions, $this->token, [ $this, 'admin_page' ], 'dashicons-editor-customchar', 10 );
    }
    public function __toString() {
        return $this->token;
    }
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
    }
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
    }
    public function __call( $method = '', $args = array() ) {
        // @codingStandardsIgnoreLine
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
        unset( $method, $args );
        return null;
    }
}