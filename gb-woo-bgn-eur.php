<?php
/*
Plugin Name: GB BGN + EUR for WooCommerce 
Plugin URI: https://gbogdanov.com/bg/product/dvoyno-pokazvane-na-tsenite-v-bgn-i-eur/
Description: Двойно показване на цените в BGN и EUR за WooCommerce магазини
Version: 1.3
Author: Georgi Bogdanov
Author URI: https://gbogdanov.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gb-woo-bgn-eur
*/

/* Register and enqueue the plugin's assets if enabled */
function gb_woo_bgn_eur_enqueue_styles() {
    // Check if CSS is enabled in settings
    if ( get_option( 'enable_gb_woo_css', 'no' ) === 'yes' ) {
        wp_enqueue_style( 'gb-woo-bgn-eur-styles', plugin_dir_url(__FILE__) . 'assets/gb-woo-bgn-eur.css', array(), '1.0.0' );
    }

    // Only add the block support JS and CSS if Gutenberg blocks support is enabled and we're on a page that might contain cart or checkout blocks
    if ( get_option( 'enable_gutenberg_blocks', 'no' ) === 'yes' && ( is_cart() || is_checkout() || gb_has_block('woocommerce/cart') || gb_has_block('woocommerce/checkout') ) ) {
        // Get current currency and settings
        $current_currency = get_woocommerce_currency();
        $enabled_eur = get_option( 'enable_eur_display', 'yes' ) === 'yes';
        $enabled_bgn = get_option( 'enable_bgn_display', 'yes' ) === 'yes';
        $eur_rate = get_eur_rate();
        $bgn_rounding = get_option( 'bgn_rounding', '' );
        
        // Prepare data for JS
        $script_data = array(
            'currency' => $current_currency,
            'enableEur' => $enabled_eur,
            'enableBgn' => $enabled_bgn,
            'eurRate' => $eur_rate,
            'bgnRounding' => $bgn_rounding
        );
        
        // Add inline script to handle currency conversion in blocks
        wp_enqueue_script(
            'gb-woo-blocks-support',
            plugin_dir_url(__FILE__) . 'assets/gb-woo-blocks.js',
            array('jquery'),
            '1.0.0',
            true
        );
        wp_localize_script('gb-woo-blocks-support', 'gbWooBgnEur', $script_data);

		wp_enqueue_style(
			'gb-woo-blocks-support',
			plugin_dir_url(__FILE__) . 'assets/gb-woo-blocks.css',
			array(),
			'1.0.0'
		);
    }
}
add_action('wp_enqueue_scripts', 'gb_woo_bgn_eur_enqueue_styles');

/* Check if the current page contains a specific block */
function gb_has_block( $block_name ) {
    if ( function_exists( 'has_block' ) ) {
        // Use WordPress native function if available
        return has_block( $block_name );
    } else {
        // Fallback for older WordPress versions
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_blocks( $post->post_content ) ) {
            return false !== strpos( $post->post_content, '<!-- wp:' . $block_name . ' ' );
        }
        return false;
    }
}

if ( !defined('ABSPATH') ) { 
    die;
}

/* Returns the fixed euro course (1 EUR = 1.95583 BGN) */
function get_eur_rate() {
    $eur_rate_option = get_option( 'eur_exchange_rate' );
    if ( !empty( $eur_rate_option ) ) {
        $eur_rate = floatval( $eur_rate_option );
    } else {
        $eur_rate = 1.95583;
    }
    return $eur_rate;
}

/* Convert string with value to float */
function priceToFloat( $s ) {
    $decimal_separator = wc_get_price_decimal_separator();
    $thousand_separator = wc_get_price_thousand_separator();

    // Remove thousand separators
    $s = str_replace( $thousand_separator, '', $s );
    // Replace decimal separator with a dot
    $s = str_replace( $decimal_separator, '.', $s );
    // Remove everything except numbers and dot "."
    $s = preg_replace( "/[^0-9\.]/", "", $s );

    return (float) $s;
}

/* Convert price from BGN to EUR with fixed rate */
function gb_convert_to_eur( $price ) {
    $rate = get_eur_rate(); // get_eur_rate() already returns a float
    $new_price = priceToFloat( $price ) / $rate;
    return number_format( $new_price, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
}

/* Convert price from EUR to BGN with rounding option */
function gb_convert_to_bgn( $price ) {
    $rate = get_eur_rate(); // get_eur_rate() already returns a float
    $new_price_unrounded = priceToFloat( $price ) * $rate;

	if ( get_option( 'bgn_rounding' ) === 'ceil' ) {
		$new_price = ceil ( $new_price_unrounded );
	} elseif ( get_option( 'bgn_rounding' ) === 'round' ) {
		$new_price = round ( $new_price_unrounded );
	} else {
    	$new_price = $new_price_unrounded;
	}
	
    return number_format( $new_price, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
}

/* Global variable to track email context */
global $gb_is_in_email;
$gb_is_in_email = false;

/* Detect if we're in an email context */
function gb_is_email_context() {
    global $gb_is_in_email;
    
    // Check our global flag first
    if ( $gb_is_in_email ) {
        return true;
    }
    
    // Check if we're in a WooCommerce email using multiple methods
    if ( did_action( 'woocommerce_email_before_order_table' ) && !did_action( 'woocommerce_email_after_order_table' ) ) {
        return true;
    }
    
    // Check if we're in email header/footer context
    if ( did_action( 'woocommerce_email_header' ) && !did_action( 'woocommerce_email_footer' ) ) {
        return true;
    }
    
    // Check for email-specific actions
    if ( doing_action('woocommerce_email_order_details') || 
         doing_action('woocommerce_email_order_meta') ||
         doing_action('woocommerce_order_item_meta_end') ) {
        return true;
    }
    
    return false;
}

/* Add second currency (EUR or BGN) to the displayed price in the store */
add_filter( 'wc_price', 'gb_custom_price_format', 9999, 3 );
function gb_custom_price_format( $formatted_price, $price, $args ) {
	
	global $product;
    
    if ( $product instanceof WC_Product && 'yes' === get_option( 'hide_sale_eur_price' ) && $product->is_on_sale() ) {

        $price_input_standardized = wc_format_decimal( $price, wc_get_price_decimals() );
        $match_regular_price      = false;

        if ( $product->is_type( 'variable' ) ) {
            // Check against all variation regular prices
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) {
                    continue;
                }

                $regular_price_variation = wc_format_decimal( $variation->get_regular_price(), wc_get_price_decimals() );
                if ( is_numeric( $regular_price_variation ) && abs( (float) $price_input_standardized - (float) $regular_price_variation ) < 0.00001 ) {
                    $match_regular_price = true;
                    break;
                }
            }
        } else {
            // Simple or other product types
            $regular_price_object_standardized = wc_format_decimal( $product->get_regular_price(), wc_get_price_decimals() );
            if ( is_numeric( $regular_price_object_standardized ) && abs( (float) $price_input_standardized - (float) $regular_price_object_standardized ) < 0.00001 ) {
                $match_regular_price = true;
            }
        }

        // If matched, return without adding secondary currency
        if ( $match_regular_price ) {
            return $formatted_price;
        }
    }
 
    $current_currency = get_woocommerce_currency();
    $is_email = gb_is_email_context();
    
    if( $current_currency == 'BGN' && get_option( 'enable_eur_display', 'yes' ) === 'yes' ) {
    	$price_eur = gb_convert_to_eur($price);
    	
    	if ($is_email) {
    	    // For emails: display EUR price on a new line below
    	    $formatted_price_eur = "<span class=\"woocommerce-Price-amount amount amount-eur\"> / <bdi>$price_eur €</bdi> </span>";
    	} else {
    	    // For regular pages: display inline (your current format)
    	    $formatted_price_eur = "<span class=\"woocommerce-Price-amount amount amount-eur\"> / <bdi>$price_eur <span class='woocommerce-Price-currencySymbol'>€</span></bdi> </span>";
    	}
		
		return $formatted_price . $formatted_price_eur;

	} elseif( $current_currency == 'EUR' && get_option( 'enable_bgn_display', 'yes' ) === 'yes' ) {
    	$price_bgn = gb_convert_to_bgn( $price );
    	
    	if ($is_email) {
    	    // For emails: display BGN price on a new line below
    	    $formatted_price_bgn = "<span class=\"woocommerce-Price-amount amount amount-bgn\"> / <bdi>$price_bgn лв.</bdi> </span>";
    	} else {
    	    // For regular pages: display inline (your current format)
    	    $formatted_price_bgn = "<span class=\"woocommerce-Price-amount amount amount-bgn\"> / <bdi>$price_bgn <span class='woocommerce-Price-currencySymbol'>лв.</span></bdi> </span>";
    	}
		
		return $formatted_price . $formatted_price_bgn;

	} else {
    	return $formatted_price;
    }
}
 
/* Save original total formatting in orders */
add_filter( 'woocommerce_get_formatted_order_total', 'filter_woocommerce_get_formatted_order_total', 10, 2 ); 
add_filter( 'woocommerce_order_shipping_to_display', 'filter_woocommerce_get_formatted_order_total', 10, 2 ); 
function filter_woocommerce_get_formatted_order_total( $formatted_total, $order ) { 
    return $formatted_total; 
}

/* Save original subtotal formatting in orders */
add_filter( 'woocommerce_order_subtotal_to_display', 'filter_woocommerce_order_subtotal_to_display', 10, 3 ); 
function filter_woocommerce_order_subtotal_to_display( $subtotal, $compound, $order ) {
    return filter_woocommerce_get_formatted_order_total( $subtotal, $order );
}

function original_wc_price( $price, $args = array() ) {
    $args = apply_filters(
		'wc_price_args', wp_parse_args(
			$args, array(
			  	'ex_tax_label'       => false,
			  	'currency'           => '',
			  	'decimal_separator'  => wc_get_price_decimal_separator(),
			  	'thousand_separator' => wc_get_price_thousand_separator(),
			  	'decimals'           => wc_get_price_decimals(),
			  	'price_format'       => get_woocommerce_price_format(),
			)
		)
    );
  
    $unformatted_price = $price;
    $negative          = $price < 0;
    $price             = apply_filters( 'raw_woocommerce_price', floatval( $negative ? $price * -1 : $price ) );
    $price             = apply_filters( 'formatted_woocommerce_price', number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] );
  
    if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $args['decimals'] > 0 ) {
      	$price = wc_trim_zeros( $price );
    }
  
    $formatted_price = ( $negative ? '-' : '' ) . sprintf( $args['price_format'], '<span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol( $args['currency'] ) . '</span>', $price );
    $return          = '<span class="woocommerce-Price-amount amount">' . $formatted_price . '</span>';
  
    if ( $args['ex_tax_label'] && wc_tax_enabled() ) {
      	$return .= ' <small class="woocommerce-Price-taxLabel tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
    }
  
    return $return;
}
  
/* Admin options */
add_filter( 'woocommerce_get_sections_products' , 'dual_prices_settings_tab' );
function dual_prices_settings_tab( $sections ){
    $sections['dual_prices_notices'] = __( 'Display Dual Prices', 'gb-woo-bgn-eur' );
    return $sections;
}

add_filter( 'woocommerce_get_settings_products' , 'dual_prices_get_settings' , 10, 2 );
function dual_prices_get_settings( $settings, $current_section ) {
	
	if( 'dual_prices_notices' == $current_section ) {
		$custom_settings = array();

        $custom_settings[] =  array(
			'name' => __( 'Двойно показване на цените', 'gb-woo-bgn-eur' ),
			'type' => 'title',
			'desc' => __( 'Опции за показване на използвания обменен курс на страницата на продукта, в архива, количката и при плащане', 'gb-woo-bgn-eur' ),
			'id'   => 'double_price_options' 
		);
		$custom_settings[] = array(
			'name'     => __( 'Курс на еврото спрямо лева', 'gb-woo-bgn-eur' ),
			'desc_tip' => __( 'Въведете курс на еврото спрямо лева, който желате да се използва.', 'gb-woo-bgn-eur' ),
			'id'       => 'eur_exchange_rate',
			'type'     => 'text',
			'desc'     => __( 'Например, 1.95583', 'gb-woo-bgn-eur' ),
			'default'  => '1.95583',
		);
        $custom_settings[] = array(
            'name' => __( 'Показване на цена в ЕВРО', 'gb-woo-bgn-eur' ),
            'desc' => __( 'Показване на цена в ЕВРО, когато валутата на магазина е BGN', 'gb-woo-bgn-eur' ),
            'id'   => 'enable_eur_display',
            'type' => 'checkbox',
            'default' => 'yes',
        );
        $custom_settings[] = array(
            'name' => __( 'Показване на цена в ЛЕВА', 'gb-woo-bgn-eur' ),
            'desc' => __( 'Показване на цена в ЛЕВА, когато валутата на магазина е EUR', 'gb-woo-bgn-eur' ),
            'id'   => 'enable_bgn_display',
            'type' => 'checkbox',
            'default' => 'yes',
        );
        $custom_settings[] = array(
            'name' => __( 'Използване на CSS стиловете', 'gb-woo-bgn-eur' ),
            'desc' => __( 'Включване на вградените CSS стилове за второстепенната валута', 'gb-woo-bgn-eur' ),
            'id'   => 'enable_gb_woo_css',
            'type' => 'checkbox',
            'default' => 'no',
        );
        $custom_settings[] = array(
            'name' => __( 'Използване на CSS стиловете за имейлите', 'gb-woo-bgn-eur' ),
            'desc' => __( 'Включване на вградените CSS стилове за второстепенната валута в имейлите', 'gb-woo-bgn-eur' ),
            'id'   => 'enable_gb_woo_email_css',
            'type' => 'checkbox',
            'default' => 'no',
        );
        $custom_settings[] = array(
            'name' => __( 'Gutenberg блокове', 'gb-woo-bgn-eur' ),
            'desc' => __( 'Поддръжка за двойно показване на цените в Gutenberg блоковете за количка и плащане', 'gb-woo-bgn-eur' ),
            'id'   => 'enable_gutenberg_blocks',
            'type' => 'checkbox',
            'default' => 'no',
        );
		$custom_settings[] =  array(
			'name' => __( 'Продуктова страница', 'gb-woo-bgn-eur' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс на страницата на продукта', 'gb-woo-bgn-eur' ),
			'id'	=> 'enable_product_info',
			'default'  => ''
		);
		$custom_settings[] =  array(
			'name' => __( 'Страницата на магазина/категории/архивни страници', 'gb-woo-bgn-eur' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс на страницата на магазина/архива', 'gb-woo-bgn-eur' ),
			'id'	=> 'enable_archive_info'
		);
		$custom_settings[] =  array(
			'name' => __( 'Количка', 'gb-woo-bgn-eur' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс на страницата на количката', 'gb-woo-bgn-eur' ),
			'id'	=> 'enable_cart_info'
		);
		// $custom_settings[] =  array(
		// 	'name' => __( 'Плащане', 'gb-woo-bgn-eur ),
		// 	'type' => 'textarea',
		// 	'css' => 'height:100px;',
		// 	'desc' => __( 'Например. Можете да копирате този код в полето по-долу <br><code>&lt;p&gt;&lt;strong&gt;Всички плащания ще бъдат извършени в евро&lt;/strong&gt;&lt;/p&gt;&lt;p&gt;&lt;small&gt;Сумата в куни се получава чрез конвертиране на цената по фиксирания обменен курс на Българската национална банка &lt;br&gt; 1 евро = 1.95583 лева', 'gb-woo-bgn-eur' ),
		// 	//'desc_tip' => true,
		// 	'id'	=> 'show_checkout_dual_info'
		// );
		$custom_settings[] =  array(
			'name' => __( 'Имейл за успешна поръчка', 'gb-woo-bgn-eur' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс в имейла за поръчка', 'gb-woo-bgn-eur' ),
			'id'	=> 'enable_email_info'
		);
		$custom_settings[] =  array(
			'name' => __( 'Не показвай евро цена при промоция', 'gb-woo-bgn-eur' ),
			'type' => 'checkbox',
			'desc' => __( 'Ако е отметнато, няма да се показва евро еквивалентът на промоционалната цена.', 'gb-woo-bgn-eur' ),
			'id'   => 'hide_sale_eur_price',
			'default' => 'no'
		);
		// $custom_settings[] =  array(
		// 	'name' => __( 'Закръгляне на цените в лв.', 'gb-woo-bgn-eur' ),
		// 	'type' => 'select',
		// 	'desc' => __( 'Изберете, ако сте променили цените от лв. в евро и искате да закръглите информационната цена до лв. кръгъл: 99.01->99.00 или 99.50->100.00 таван: 99.01 лв. или 99,50 лв. -> 100,00 лв.', 'gb-woo-bgn-eur' ),
		// 	'id'	=> 'bgn_rounding',
		// 	'options' => array(
		// 		'none' => __( 'Не закръгляй', 'gb-woo-bgn-eur' ),
		// 		'round' => __( 'Закръгляване нагоре или надолу до най-близкото цяло число', 'gb-woo-bgn-eur' ),
		// 		'ceil' => __( 'Закръгляване нагоре', 'gb-woo-bgn-eur' ),
		// 	)
		// );
		$custom_settings[] =  array( 
			'type' => 'sectionend', 
			'id' => 'dual_display_options'
		);

		return $custom_settings;
		
    } else {
        return $settings;
    }

}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dual_prices_action_links' );
function dual_prices_action_links( $links ) {
	$settings = array(
		'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=dual_prices_notices' ) ) . '">' . esc_html__( 'Settings', 'gb-woo-bgn-eur' ) . '</a>',
	);

	return array_merge( $settings, $links );
}

/* Override email template  */
function gb_eur_plugin_path() {
  	return untrailingslashit( plugin_dir_path( __FILE__ ) );
}

add_filter( 'woocommerce_locate_template', 'gb_woocommerce_locate_template', 10, 3 );
function gb_woocommerce_locate_template( $template, $template_name, $template_path ) {
	global $woocommerce;

	$_template = $template;

	if ( ! $template_path ) $template_path = $woocommerce->template_url;

	$plugin_path  = gb_eur_plugin_path() . '/woocommerce/';

	// Look within passed path within the theme - this is priority
	$template = locate_template(
		array(
		  	$template_path . $template_name,
		  	$template_name
		)
	);
	
	if ( ! $template && file_exists( $plugin_path . $template_name ) )
	$template = $plugin_path . $template_name;

	if ( ! $template )
	$template = $_template;

	return $template;
}

/* View the exchange rate used */
//Product page
//add_action( 'woocommerce_after_add_to_cart_button' , 'dual_price_after_add_to_cart', 5 );
add_action( 'woocommerce_product_meta_start' , 'dual_price_single_product', 10 );
function dual_price_single_product() {
	if ( get_option('enable_product_info') === 'yes' ) {
   		echo '<span class="rate_product_page"><span class="meta-label">Курс:</span> 1 EUR = ' . esc_html( get_eur_rate() ) . ' BGN</span>';
   	}
}

//store page, categories, archives
add_action( 'woocommerce_after_shop_loop_item' , 'dual_price_shop_loop', 5 );
function dual_price_shop_loop() {
	if ( get_option('enable_archive_info') === 'yes' ) {
   		echo '<span class="rate_archive_page"><small>1 EUR = ' . esc_html( get_eur_rate() ) . ' BGN</small></span>';
   	}
}

// shopping cart page
add_action( 'woocommerce_proceed_to_checkout' , 'dual_price_cart_page', 1 );
function dual_price_cart_page() {
	if ( get_option('enable_cart_info') === 'yes' ) {
   		echo '<span class="rate_cart_page" style="display: block;text-align: center;"><small>КУРС: 1 EUR = ' . esc_html( get_eur_rate() ) . ' BGN</small></span>';
   	}
}

/* Checkout messages */
add_action( 'woocommerce_review_order_before_payment', 'gb_notice_shipping', 5 );
function gb_notice_shipping() {
  $dual_price_checkout = get_option( 'show_checkout_dual_info' );
  
  	if ( !empty( $dual_price_checkout ) ) {
  		echo '<div class="foreign-currency-checkout woocommerce-info">' . esc_html( $dual_price_checkout ) . '</div>';
   	}
}

/* Email message */
add_filter( 'woocommerce_get_order_item_totals', 'add_rate_row_email', 10, 2 );
function add_rate_row_email( $total_rows, $myorder_obj ) {
	$email_info = get_option( 'enable_email_info' );
 	if ( !empty( $email_info ) ) {
		$total_rows['used_rate'] = array(
			'label' => __( 'Фиксиран валутен курс:', 'gb-woo-bgn-eur' ),
			'value'   => '1 € = ' . get_eur_rate() . ' лв.'
		);
	}

	return $total_rows;
}

add_filter( 'woocommerce_email_styles', 'gb_add_css_to_emails', 9999, 2 );
function gb_add_css_to_emails( $css, $email ) { 
   $css .= '
      	#body_content .email-order-details .order-totals-total td {
			font-size: 16px !important;
		}
   ';
   return $css;
}

/* Don't show dual prices in admin */
add_action('admin_head', 'gb_custom_admin_css');
function gb_custom_admin_css() {
	$current_currency = get_woocommerce_currency();
	
	if ( $current_currency == 'BGN' ) {
		echo '<style>
				.amount-eur {
					display: none;
				}
		  	</style>';
  	}
}