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
    public $loop_size = 100;
    public $description = 'Update sessions reports';
    public $title = 'Zume Training Migrator';

    /**
     * #2 EDIT QUERY
     */
    public function source_query() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT user_id, meta_value FROM wp_usermeta WHERE meta_key LIKE 'zume_group_%';"
            , ARRAY_A );
    }

    /**
     * #3 EDIT TASK TO EXECUTE
     */
    public function run_task( $result ) {
        $session = maybe_unserialize( $result['meta_value'] );
        $users = [];
        $users[] = $result['user_id'];
        if ( isset( $session['coleaders'] ) && ! empty( $session['coleaders'] ) ) {
            foreach( $session['coleaders'] as $coleader_email ) {
                $u = get_user_by( 'email', $coleader_email );
                if ( $u ) {
                    $users[] = $u->ID;
                }
            }
        }
        foreach( $users as $user_id ) {
            $list = $this->list();
            foreach( $list as $item ) {
                if ( !empty( $session[$item['key']] ) ) {
                    $report = $this->get_array( $session, $item['number'], $item['key'], $user_id );
                    $report['hash'] = hash( 'sha256', maybe_serialize( $report ) );
                    $report['timestamp'] = time();
                    $duplicate_found = $this->check_dup($report['hash']);
                    if ( ! $duplicate_found ) {
                        $this->insert( $report );
                    }
                }
            }
        }

    }
    public function check_dup( $hash ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    `id`
                FROM
                    wp_dt_reports
                WHERE hash = %s AND hash IS NOT NULL;",
                $hash
            )
        );
    }
    public function list() {
        return [
            [
                'number' => '1',
                'key' => 'session_1_complete',
            ],
            [
                'number' => '2',
                'key' => 'session_2_complete',
            ],
            [
                'number' => '3',
                'key' => 'session_3_complete',
            ],
            [
                'number' => '4',
                'key' => 'session_4_complete',
            ],
            [
                'number' => '5',
                'key' => 'session_5_complete',
            ],
            [
                'number' => '6',
                'key' => 'session_6_complete',
            ],
            [
                'number' => '7',
                'key' => 'session_7_complete',
            ],
            [
                'number' => '8',
                'key' => 'session_8_complete',
            ],
            [
                'number' => '9',
                'key' => 'session_9_complete',
            ],
            [
                'number' => '10',
                'key' => 'session_10_complete',
            ],
        ];
    }
    public function get_array( $session, $number, $session_label, $user_id ) {
        return [
            'user_id' => $user_id,
            'parent_id' => null,
            'post_id' => null,
            'post_type' => null,
            'type' => 'zume_session',
            'subtype' => $number,
            'payload' => null,
            'value' => 0,
            'lng' => $session['location_grid_meta']['lng'] ?? null,
            'lat' => $session['location_grid_meta']['lat'] ?? null,
            'level' => $session['location_grid_meta']['level'] ?? null,
            'label' => $session['location_grid_meta']['label'] ?? null,
            'grid_id' => $session['location_grid_meta']['grid_id'] ?? null,
            'time_begin' => null,
            'time_end' => strtotime( $session[$session_label] )
        ];
    }
    public function insert( $report ) {
        global $wpdb;
        return $wpdb->insert( 'wp_dt_reports', $report,
            [
                '%d', // user_id
                '%d', // parent_id
                '%d', // post_id
                '%s', // post_type
                '%s', // type
                '%s', // subtype
                '%s', // payload
                '%d', // value
                '%f', // lng
                '%f', // lat
                '%s', // level
                '%s', // label
                '%d', // grid_id
                '%d', // time_begin
                '%d', // time_end
                '%s', // timestamp
                '%s', // hash
            ] );
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