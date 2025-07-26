<?php
/**
 * Plugin Name:       Secure Login Encryptor
 * Description:       Encrypts login credentials on the frontend and decrypts them on the backend using a unique key pair.
 * Version:           1.0.0
 * Author:            Lungdsuo Mozhui
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       secure-login-encryptor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Secure_Login_Encryptor {

  
    private static $_instance = null;

    
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->setup_hooks();
        }
        return self::$_instance;
    }

   
    private function setup_hooks() {
        register_activation_hook( __FILE__, [ $this, 'generate_keys_on_activation' ] );

        add_action( 'login_enqueue_scripts', [ $this, 'enqueue_login_scripts' ] );

        add_filter( 'authenticate', [ $this, 'decrypt_credentials_before_auth' ], 20, 3 );
    }

    
    public function generate_keys_on_activation() {
        if ( ! get_option( 'sle_private_key' ) && ! get_option( 'sle_public_key' ) ) {
            $config = [
                "digest_alg" => "sha512",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];

            $res = openssl_pkey_new( $config );

            openssl_pkey_export( $res, $private_key );

            $public_key_details = openssl_pkey_get_details( $res );
            $public_key = $public_key_details["key"];

            update_option( 'sle_private_key', $private_key );
            update_option( 'sle_public_key', $public_key );
        }
    }

   
    public function enqueue_login_scripts() {
        wp_enqueue_script( 'jsencrypt', plugin_dir_url( __FILE__ ) . 'jsencrypt.min.js', [], '3.2.1', true );

        wp_enqueue_script( 'sle-login-script', plugin_dir_url( __FILE__ ) . 'secure-login-script.js', [ 'jquery', 'jsencrypt' ], '1.1.0', true );

        $public_key = get_option( 'sle_public_key' );
        wp_localize_script( 'sle-login-script', 'sle_login_data', [
            'public_key' => $public_key,
        ] );
    }

 
    public function decrypt_credentials_before_auth( $user, $username, $password ) {
        if ( isset( $_POST['sle_encrypted'] ) && $_POST['sle_encrypted'] === 'true' ) {
            $private_key = get_option( 'sle_private_key' );

            if ( ! $private_key ) {
                return new WP_Error( 'sle_no_key', __( '<strong>ERROR</strong>: The server is missing its decryption key.', 'secure-login-encryptor' ) );
            }

            $encrypted_user = base64_decode( $_POST['log'] );
            $encrypted_pass = base64_decode( $_POST['pwd'] );
            
            $decrypted_user = '';
            $decrypted_pass = '';

            openssl_private_decrypt( $encrypted_user, $decrypted_user, $private_key );
            openssl_private_decrypt( $encrypted_pass, $decrypted_pass, $private_key );
            
            if ( empty( $decrypted_user ) || empty( $decrypted_pass ) ) {
                return new WP_Error( 'sle_decryption_failed', __( '<strong>ERROR</strong>: Could not decrypt credentials.', 'secure-login-encryptor' ) );
            }

            return wp_authenticate_username_password( null, $decrypted_user, $decrypted_pass );
        }

        return $user;
    }
}


function sle_run_plugin() {
    return Secure_Login_Encryptor::instance();
}
sle_run_plugin();