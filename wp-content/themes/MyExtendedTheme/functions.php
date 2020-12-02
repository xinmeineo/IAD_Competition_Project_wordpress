<?php
if ( ! class_exists( 'Limit_Login_Attempts' ) ) {
class Limit_Login_Attempts {
var $failed_login_limit = 6; //Number of authentification allowed
var $lockout_duration = 1800; // Time = 1800/60 = 30min
var $transient_name = 'attempted_login'; //Transient used 
public function __construct() {
add_filter( 'authenticate', array( $this, 'check_attempted_login' ), 30, 6 
 );
add_action( 'wp_login_failed', array( $this, 'login_failed' ), 10, 1 );
}
public function check_attempted_login( $user, $username, $password ) {
if ( get_transient( $this->transient_name ) ) {
$datas = get_transient( $this->transient_name );
if ( $datas['tried'] >= $this->failed_login_limit ) {
$until = get_option( '_transient_timeout_' . $this->transient_name );
$time = $this->when( $until );
return new WP_Error( 'too_many_tried', sprintf( __( '<strong> ERROR</strong>: You have reached authentification limit, you will be able to try again in %1$s.' ) , $time ) );
} 
}
return $user; 
}
public function login_failed( $username ) {
if ( get_transient( $this->transient_name ) ) {
$datas = get_transient( $this->transient_name );
$datas['tried']++;
if ( $datas['tried'] <= $this->failed_login_limit )
set_transient( $this->transient_name, $datas , $this->lockout_duration );
} else {
$datas = array(
'tried' => 1
);
set_transient( $this->transient_name, $datas , $this->lockout_duration );
}
}
private function when( $time ) {
if ( ! $time ) 
return;
$right_now = time();
$diff = abs( $right_now - $time );
$second = 1;
$minute = $second * 60;
$hour = $minute * 60;
$day = $hour * 24;
if ( $diff < $minute )
return floor( $diff / $second ) . ' secondes';
if ( $diff < $minute * 2 )
return "about 1 minute ago";
if ( $diff < $hour )
return floor( $diff / $minute ) . ' minutes';
if ( $diff < $hour * 2 )
return 'about 1 hour';
return floor( $diff / $hour ) . ' hours';
}
}
}
new Limit_Login_Attempts();


function disable_emojis() {
remove_action( 'wp_head', 'print_emoji_detection_script', 7 ); remove_action( 'admin_print_scripts', 'print_emoji_detection_script' ); remove_action( 'wp_print_styles', 'print_emoji_styles' ); remove_action( 'admin_print_styles', 'print_emoji_styles' ); remove_filter( 'the_content_feed', 'wp_staticize_emoji' ); remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'tiny_mce_plugins', 'disable_emojis_tinymce' );
}
add_action( 'init', 'disable_emojis' );

function disable_embeds_code_init() {
remove_action( 'rest_api_init', 'wp_oembed_register_route' );
add_filter( 'embed_oembed_discover', '__return_false' );
remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 ); remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
add_filter( 'tiny_mce_plugins', 'disable_embeds_tiny_mce_plugin' ); add_filter( 'rewrite_rules_array', 'disable_embeds_rewrites' ); remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
}
add_action( 'init', 'disable_embeds_code_init', 9999 ); 
function disable_embeds_tiny_mce_plugin($plugins) {
return array_diff( $plugins, array('wpembed') ); 
}
function disable_embeds_rewrites ($rules) {
foreach($rules as $rule => $rewrite) {
if(false !== strpos($rewrite, 'embed=true')) { 
unset($rules[$rule]);
} 
}
return $rules; 
}

function _remove_script_version ( $src ){
$parts = explode( '?', $src );
return $parts[0];
}
add_filter( 'script_loader_src', '_remove_script_version', 15, 1 ); add_filter( 'style_loader_src', '_remove_script_version', 15, 1 );

function remove_error_styles(){ 
wp_dequeue_style('storefront-fonts');
}
add_action( 'wp_enqueue_scripts', 'remove_error_styles', 999);