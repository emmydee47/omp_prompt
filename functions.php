<?php
/**
 * ompv1 functions and definitions
 *
 * @link    https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ompv1
 */

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function ompv1_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'ompv1_content_width', 1400 );
}

add_action( 'after_setup_theme', 'ompv1_content_width', 0 );

if ( ! function_exists( 'ompv1_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 */
	function ompv1_setup() {
		// Make theme available for translation.
		load_theme_textdomain( 'ompv1', get_template_directory() . '/languages' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Let WordPress manage the document title.
		add_theme_support( 'title-tag' );

		// Enable support for Post Thumbnails on posts and pages.
		add_theme_support( 'post-thumbnails' );

		// Enable support for common post formats
		add_theme_support( 'post-formats', array( 'gallery', 'video' ) );

		// Register menu locations
		register_nav_menus( array(
			'primary'   => esc_html__( 'Primary Menu', 'ompv1' ),
			'secondary' => esc_html__( 'Secondary Menu', 'ompv1' ),
			'topbar'    => esc_html__( 'Topbar Menu', 'ompv1' ),
			'hamburger' => esc_html__( 'Full Screen Menu', 'ompv1' ),
			'socials'   => esc_html__( 'Socials Menu', 'ompv1' ),
			'blog'      => esc_html__( 'Blog Header Menu', 'ompv1' ),
			'footer'    => esc_html__( 'Footer Menu', 'ompv1' ),
			'mobile'    => esc_html__( 'Mobile Menu', 'ompv1' ),
		) );

		// Switch default core markup for search form, comment form, and comments to output valid HTML5.
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		) );

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );

		// Add support for full and wide align images.
		add_theme_support( 'align-wide' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );

		// Enqueue editor styles.
		add_editor_style( array( 'css/style-editor.css', ompv1_fonts_url() ) );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for font sizes
		add_theme_support( 'editor-font-sizes', array(
			array(
				'name'      => __( 'Small', 'ompv1' ),
				'shortName' => __( 'S', 'ompv1' ),
				'size'      => 12,
				'slug'      => 'small'
			),
			array(
				'name'      => __( 'Normal', 'ompv1' ),
				'shortName' => __( 'N', 'ompv1' ),
				'size'      => 18,
				'slug'      => 'normal'
			),
			array(
				'name'      => __( 'Medium', 'ompv1' ),
				'shortName' => __( 'M', 'ompv1' ),
				'size'      => 24,
				'slug'      => 'medium'
			),
			array(
				'name'      => __( 'Large', 'ompv1' ),
				'shortName' => __( 'L', 'ompv1' ),
				'size'      => 40,
				'slug'      => 'large'
			),
			array(
				'name'      => __( 'Huge', 'ompv1' ),
				'shortName' => __( 'XL', 'ompv1' ),
				'size'      => 64,
				'slug'      => 'huge'
			),
		) );

		// Add image sizes.
		set_post_thumbnail_size( 360, 210, true );
		add_image_size( 'ompv1-post-thumbnail-medium', 580, 400, true );
		add_image_size( 'ompv1-post-thumbnail-large', 750, 420, true );
		add_image_size( 'ompv1-post-thumbnail-navigation', 100, 68, true );
		add_image_size( 'ompv1-post-thumbnail-shortcode', 450, 300, true );
	}
endif;
add_action( 'after_setup_theme', 'ompv1_setup' );

/**
 * Setup theme instances
 */
function ompv1_init() {
	if ( is_admin() ) {
		ompv1_Term_Edit::instance();
	}
}

add_action( 'init', 'ompv1_init', 20 );

/**
 * Register widget areas.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function ompv1_widgets_init() {
	register_sidebar( array(
		'name'          => esc_html__( 'Blog Sidebar', 'ompv1' ),
		'id'            => 'blog-sidebar',
		'description'   => esc_html__( 'Add widgets here in order to display them on blog pages', 'ompv1' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Shop Sidebar', 'ompv1' ),
		'id'            => 'shop-sidebar',
		'description'   => esc_html__( 'Add widgets here in order to display on shop pages', 'ompv1' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Off Screen Sidebar', 'ompv1' ),
		'id'            => 'off-screen',
		'description'   => esc_html__( 'Add widgets here in order to display inside off-screen panel of hamburger icon', 'ompv1' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	for ( $i = 1; $i < 5; $i++ ) {
		register_sidebar( array(
			'name'          => sprintf( esc_html__( 'Footer Sidebar %s', 'ompv1' ), $i ),
			'id'            => 'footer-' . $i,
			'description'   => esc_html__( 'Add widgets here in order to display on footer', 'ompv1' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="widget-title">',
			'after_title'   => '</h4>',
		) );
	}
}

add_action( 'widgets_init', 'ompv1_widgets_init' );


/**
 * Custom functions for this theme.
 */
require get_template_directory() . '/inc/functions/options.php';
require get_template_directory() . '/inc/functions/layout.php';
require get_template_directory() . '/inc/functions/style.php';
require get_template_directory() . '/inc/functions/header.php';
require get_template_directory() . '/inc/functions/menus.php';
require get_template_directory() . '/inc/functions/post.php';
require get_template_directory() . '/inc/functions/shop.php';
require get_template_directory() . '/inc/functions/footer.php';
require get_template_directory() . '/inc/functions/misc.php';

/**
 * Custom functions that act in the frontend.
 */
require get_template_directory() . '/inc/frontend/frontend.php';
require get_template_directory() . '/inc/frontend/header.php';
require get_template_directory() . '/inc/frontend/menus.php';
require get_template_directory() . '/inc/frontend/entry.php';
require get_template_directory() . '/inc/frontend/widgets.php';
require get_template_directory() . '/inc/frontend/footer.php';
require get_template_directory() . '/inc/frontend/maintenance.php';
require get_template_directory() . '/inc/frontend/mobile.php';

/**
 * Custom functions that act in the backend.
 */
if ( is_admin() ) {
	require get_template_directory() . '/inc/admin/plugins.php';
	require get_template_directory() . '/inc/admin/meta-boxes.php';
	require get_template_directory() . '/inc/admin/term.php';
	require get_template_directory() . '/inc/admin/editor.php';
	require get_template_directory() . '/inc/admin/widgets.php';
	require get_template_directory() . '/inc/admin/ajax.php';
}

/**
 * Load WooCommerce compatibility file.
 */
if ( class_exists( 'WooCommerce' ) ) {
	require get_template_directory() . '/inc/woocommerce.php';
}

/**
 * Load WooCommerce compatibility file.
 */
if ( get_option( 'ompv1_portfolio' ) ) {
	require get_template_directory() . '/inc/portfolio.php';
}

/**
 * Customizer additions.
 */
if ( class_exists( 'Kirki' ) ) {
	require get_template_directory() . '/inc/customizer.php';
}

add_action( 'woocommerce_before_checkout_billing_form', function() {
	$company_details = get_customer_details('grouped');
	echo '<p class="form-row form-row-wide address-field" id="customer_p"><label for="billing_customer">Customer</label><span class="woocommerce-input-wrapper"><select name="billing_customer" tabindex="-1" class="country_to_state country_select select2-hidden-accessible" id="billing_customer" aria-hidden="true" autocomplete="customer">';
	echo'<option value="" selected>Select customer code…</option>';
	foreach($company_details as $company_detail){
		
		echo'<option data-first_name="'.$company_detail->firstname.'" data-lastname="'.$company_detail->lastname.'"
		data-company="'.$company_detail->company_name.'" data-street_address="'.$company_detail->street_address.'"
		data-city="'.$company_detail->city.'" data-postal_code="'.$company_detail->postal_code.'"
		data-country="'.$company_detail->country.'"  data-state="'.$company_detail->state.'" 
		data-phone="'.$company_detail->phone.'"  data-email="'.$company_detail->email_address.'" 
		 value="'.$company_detail->company_code.'">'.$company_detail->company_code.'-'.ucfirst($company_detail->company_name).'</option>';
	}
	echo '</select></span></p>';
});

add_action( 'woocommerce_before_checkout_shipping_form', function() {
	echo '<p class="form-row form-row-wide address-field" id="customer_p2"><label for="billing_customer2">Customer</label><span class="woocommerce-input-wrapper"><select name="billing_customer2" tabindex="-1" class="country_to_state country_select select2-hidden-accessible" id="billing_customer2" aria-hidden="true" autocomplete="customer"><option value="">Select customer code…</option><option value="Customer Code 1">Customer Code 1</option><option value="Customer Code 2">Customer Code 2</option><option value="Customer Code 3">Customer Code 3</option></select></span></p>';
});


add_action('woocommerce_after_checkout_form', 'debounce_add_jscript_checkout');

function debounce_add_jscript_checkout() {
	wp_enqueue_script('main', get_template_directory_uri() . '/js/custom.js', false, false, true);
}

add_action('wp_ajax_nopriv_get_customer_details', 'get_customer_details');
add_action('wp_ajax_get_customer_details', 'get_customer_details');

add_filter( 'woocommerce_default_address_fields', 'customising_checkout_fields', 1000, 1 );
function customising_checkout_fields( $address_fields ) {
    $address_fields['first_name']['required'] = false;
    $address_fields['last_name']['required'] = false;
    $address_fields['company']['required'] = false;
    $address_fields['country']['required'] = false;
    $address_fields['city']['required'] = false;
    $address_fields['state']['required'] = false;
	$address_fields['postcode']['required'] = false;
	$address_fields['address_1']['required'] = false;
	$address_fields['address_2']['required'] = false;
	// $address_fields['phone']['required'] = false;
	// $address_fields['email']['required'] = false;

    return $address_fields;
}

add_filter( 'woocommerce_billing_fields', 'wc_optional_billing_fields', 10, 1 );
function wc_optional_billing_fields( $address_fields ) {
$address_fields['billing_phone']['required'] = false;
$address_fields['billing_email']['required'] = false;
$address_fields['billing_address_1']['required'] = false;
$address_fields['billing_address_2']['required'] = false;
return $address_fields;
}

function get_customer_details($type="all")
{
	global $wpdb;
	$qry = "SELECT * FROM ompecom_shipto GROUP BY company_code";

	if($type!="grouped"){
	$qry = "SELECT * FROM ompecom_shipto";	
	}

	$results = $wpdb->get_results( 
						$wpdb->prepare($qry)
					);
	 if($type!="grouped"){
	 	echo json_encode($results);
	 }
	return $results;
}


