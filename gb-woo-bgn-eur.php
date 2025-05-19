<?php
/*
Plugin Name: GB WooCommerce BGN + EUR
Plugin URI: https://gbogdanov.com
Description: Двойно показване на цените в BGN и EUR за WooCommerce магазини
Version: 1.0
Author: Georgi Bogdanov
Author URI: https://gbogdanov.com
*/

if ( !defined('ABSPATH') ) { 
    die;
}

// Връща фиксирания курс на еврото спрямо лева (1 EUR = 1.95583 BGN)
function get_eur_rate() {
    $eur_rate_option = get_option( 'eur_exchange_rate' );
    if ( !empty( $eur_rate_option ) ) {
        $eur_rate = floatval( $eur_rate_option );
    } else {
        $eur_rate = 1.95583;
    }
    return $eur_rate;
}

// Конвертира низ със стойност в число с плаваща запетая
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

// Преобразува цена от BGN в EUR с фиксирания курс
function gb_convert_to_eur( $price ) {
    $rate = get_eur_rate(); // get_eur_rate() already returns a float
    $new_price = priceToFloat( $price ) / $rate;
    return number_format( $new_price, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
}

// Преобразува цена от EUR в BGN
function gb_convert_to_bgn( $price ) {
    return number_format( priceToFloat( $price ) * get_eur_rate(), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
}

// Добавя втора валута (EUR или BGN) към показаната цена в магазина
add_filter( 'wc_price', 'gb_custom_price_format', 9999, 3 );
function gb_custom_price_format( $formatted_price, $price, $args ) {

    global $product;
    if ( $product instanceof WC_Product &&
         get_option( 'hide_sale_eur_price' ) === 'yes' &&
         $product->is_on_sale() ) {

        // Get the regular price of the product
        $regular_price_from_product = $product->get_regular_price();

        // Standardize both prices to the WooCommerce number of decimals before float conversion and comparison.
        // This helps ensure that subtle differences in float representation or decimal places don't cause comparison failures.
        $price_input_standardized = wc_format_decimal( $price, wc_get_price_decimals() );
        $regular_price_object_standardized = wc_format_decimal( $regular_price_from_product, wc_get_price_decimals() );
		
        // Check if the price being formatted is the regular price (which will be the 'old' price)
        if ( !empty( $regular_price_from_product ) && // Check original regular price for emptiness
             is_numeric( $price_input_standardized ) && 
             is_numeric( $regular_price_object_standardized ) && 
             abs( (float)$price_input_standardized - (float)$regular_price_object_standardized ) < 0.00001 ) {
             return $formatted_price; // Do not add dual currency for the regular price if product is on sale and option is checked
        }
    }
 
    $current_currency = get_woocommerce_currency();
	
    $currency_map = [
        'BGN' => ['convert' => 'gb_convert_to_eur', 'symbol' => '€'],
        'EUR' => ['convert' => 'gb_convert_to_bgn', 'symbol' => 'лв.']
    ];
    
    if ( isset( $currency_map[$current_currency] ) ) {
        $converted_price = call_user_func( $currency_map[$current_currency]['convert'], $price );
        $symbol = $currency_map[$current_currency]['symbol'];
        return $formatted_price . "<span class=\"woocommerce-Price-amount amount amount-eur\"> <bdi>( $converted_price $symbol )</bdi> </span>";
    }
    
    return $formatted_price;
}
 
// Запазва оригиналното форматиране на тотала в поръчките
add_filter( 'woocommerce_get_formatted_order_total', 'filter_woocommerce_get_formatted_order_total', 10, 2 ); 
add_filter( 'woocommerce_order_shipping_to_display', 'filter_woocommerce_get_formatted_order_total', 10, 2 ); 
function filter_woocommerce_get_formatted_order_total( $formatted_total, $order ) { 
    return $formatted_total; 
}

// Запазва оригиналното форматиране на междинната сума в поръчките
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
    $sections['dual_prices_notices'] = __( 'Display Dual Prices' );
    return $sections;
}

add_filter( 'woocommerce_get_settings_products' , 'dual_prices_get_settings' , 10, 2 );
function dual_prices_get_settings( $settings, $current_section ) {
	
	if( 'dual_prices_notices' == $current_section ) {
		$custom_settings = array();

        $custom_settings[] =  array(
			'name' => __( 'Двойно показване на цените' ),
			'type' => 'title',
			'desc' => __( 'Опции за показване на използвания обменен курс на страницата на продукта, в архива, количката и при плащане' ),
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
		$custom_settings[] =  array(
			'name' => __( 'Продуктова страница' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс на страницата на продукта'),
			'id'	=> 'enable_product_info',
			'default'  => ''
		);
		$custom_settings[] =  array(
			'name' => __( 'Страницата на магазина/категории/архивни страници' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс на страницата на магазина/архива'),
			'id'	=> 'enable_archive_info'
		);
		$custom_settings[] =  array(
			'name' => __( 'Количка' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс на страницата на количката'),
			'id'	=> 'enable_cart_info'
		);
		$custom_settings[] =  array(
			'name' => __( 'Плащане' ),
			'type' => 'textarea',
			'css' => 'height:100px;',
			'desc' => __( 'Например. Можете да копирате този код в полето по-долу <br><code>&lt;p&gt;&lt;strong&gt;Всички плащания ще бъдат извършени в евро&lt;/strong&gt;&lt;/p&gt;&lt;p&gt;&lt;small&gt;Сумата в куни се получава чрез конвертиране на цената по фиксирания обменен курс на Българската национална банка &lt;br&gt; 1 евро = 1.95583 лева'),
			//'desc_tip' => true,
			'id'	=> 'show_checkout_dual_info'
		);
		$custom_settings[] =  array(
			'name' => __( 'Имейл за успешна поръчка' ),
			'type' => 'checkbox',
			'desc' => __( 'Показване на информация за фиксирания валутен курс в имейла за поръчка'),
			'id'	=> 'enable_email_info'
		);
		$custom_settings[] =  array(
			'name' => __( 'Не показвай евро цена при промоция', 'gb-woo-bgn-eur' ),
			'type' => 'checkbox',
			'desc' => __( 'Ако е отметнато, няма да се показва евро еквивалентът на промоционалната цена.', 'gb-woo-bgn-eur' ),
			'id'   => 'hide_sale_eur_price',
			'default' => 'no'
		);

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
		'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=dual_prices_notices' ) ) . '">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
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
   		echo '<span class="rate_product_page"><span class="meta-label">Курс:</span> 1 EUR = ' . get_eur_rate() . ' BGN</span>';
   	}
}

//store page, categories, archives
add_action( 'woocommerce_after_shop_loop_item' , 'dual_price_shop_loop', 5 );
function dual_price_shop_loop() {
	if ( get_option('enable_archive_info') === 'yes' ) {
   		echo '<span class="rate_archive_page"><small>1 EUR = ' . get_eur_rate() . ' BGN</small></span>';
   	}
}

// shopping cart page
add_action( 'woocommerce_proceed_to_checkout' , 'dual_price_cart_page', 1 );
function dual_price_cart_page() {
	if ( get_option('enable_cart_info') === 'yes' ) {
   		echo '<span class="rate_cart_page" style="display: block;text-align: center;"><small>КУРС: 1 EUR = ' . get_eur_rate() . ' BGN</small></span>';
   	}
}

/* Checkout messages */
add_action( 'woocommerce_review_order_before_payment', 'gb_notice_shipping', 5 );
function gb_notice_shipping() {
  $dual_price_checkout = get_option( 'show_checkout_dual_info' );
  
  	if ( !empty( $dual_price_checkout ) ) {
  		echo '<div class="foreign-currency-checkout woocommerce-info">' . $dual_price_checkout . '</div>';
   	}
}

/* Email message */
add_filter( 'woocommerce_get_order_item_totals', 'add_rate_row_email', 10, 2 );
function add_rate_row_email( $total_rows, $myorder_obj ) {
	$email_info = get_option( 'enable_email_info' );
 	if ( !empty( $email_info ) ) {
		$total_rows['used_rate'] = array(
			'label' => __( 'Фиксиран валутен курс:', 'woocommerce' ),
			'value'   => '1 € = ' . get_eur_rate() . ' лв.'
		);
	}

	return $total_rows;
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