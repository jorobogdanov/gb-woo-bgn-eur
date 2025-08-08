/**
 * GB WooCommerce BGN + EUR - WooCommerce Blocks Support
 * 
 * This script adds dual currency support to WooCommerce Block-based cart and checkout
 */
(function($) {
    'use strict';
    
    // Main function to process prices in blocks
    function processBlockPrices() {
        // Check if our localized data exists
        if (typeof gbWooBgnEur === 'undefined') {
            return;
        }
        
        // Get settings from localized data
        const currentCurrency = gbWooBgnEur.currency;
        const enableEur = gbWooBgnEur.enableEur;
        const enableBgn = gbWooBgnEur.enableBgn;
        const eurRate = parseFloat(gbWooBgnEur.eurRate);
        const bgnRounding = gbWooBgnEur.bgnRounding;
        
        // Only proceed if we have valid settings
        if (!currentCurrency || !eurRate) {
            return;
        }
        
        // Function to convert BGN to EUR
        function convertToEur(price) {
            return price / eurRate;
        }
        
        // Function to convert EUR to BGN with rounding
        function convertToBgn(price) {
            const rawPrice = price * eurRate;
            
            if (bgnRounding === 'ceil') {
                return Math.ceil(rawPrice);
            } else if (bgnRounding === 'round') {
                return Math.round(rawPrice);
            } else {
                return rawPrice;
            }
        }
        
        // Function to format a number with proper separators
        function formatPrice(price, decimals = 2) {
            return price.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
        }
        
        // Determine which elements need the currency conversion
        function findPriceElements() {
            return [
                // Cart block elements
                '.wp-block-woocommerce-cart .wc-block-components-totals-item__value', // Totals
                '.wp-block-woocommerce-cart .wc-block-components-product-price', // Product prices
                '.wp-block-woocommerce-cart .wc-block-cart-item__price', // Line item prices
                '.wp-block-woocommerce-cart .wc-block-components-totals-footer-item .wc-block-components-totals-item__value', // Order total
                '.wp-block-woocommerce-cart .wc-block-components-formatted-money-amount', // Various formatted amounts
                
                // Checkout block elements
                '.wp-block-woocommerce-checkout .wc-block-components-totals-item__value', // Totals
                '.wp-block-woocommerce-checkout .wc-block-components-product-price', // Product prices
                '.wp-block-woocommerce-checkout .wc-block-components-order-summary-item__total-price', // Order summary items
                '.wp-block-woocommerce-checkout .wc-block-components-formatted-money-amount', // Various formatted amounts
                '.wp-block-woocommerce-checkout .wc-block-components-totals-footer-item-full .wc-block-components-totals-item__value' // Grand total
            ].join(', ');
        }
        
        // Process all price elements in the blocks
        function processPriceElements() {
            const priceElements = findPriceElements();
            
            // Find all price elements within WooCommerce blocks
            $(priceElements).each(function() {
                // Skip if this element already has the dual currency added
                if ($(this).find('.amount-eur').length) {
                    return;
                }
                
                // Skip if a parent already has the dual currency
                if ($(this).parent().find('> .amount-eur').length) {
                    return;
                }
                
                // Skip if this is inside a woocommerce price amount (handled by PHP)
                if ($(this).closest('.woocommerce-Price-amount').length) {
                    return;
                }
                
                // Get the price text and extract the numeric value
                const priceText = $(this).text().trim();
                const priceMatch = priceText.match(/[\d\s,.]+/);
                
                if (!priceMatch) {
                    return;
                }
                
                // Extract and clean the price value
                let numericPrice = priceMatch[0].replace(/\s/g, '').replace(',', '.');
                numericPrice = parseFloat(numericPrice);
                
                if (isNaN(numericPrice)) {
                    return;
                }
                
                // Create the dual currency element
                let dualCurrencyHtml = '';
                
                if (currentCurrency === 'BGN' && enableEur) {
                    const eurPrice = convertToEur(numericPrice);
                    dualCurrencyHtml = `<span class="woocommerce-Price-amount amount amount-eur"> / <bdi>${formatPrice(eurPrice)} €</bdi> </span>`;
                } else if (currentCurrency === 'EUR' && enableBgn) {
                    const bgnPrice = convertToBgn(numericPrice);
                    dualCurrencyHtml = `<span class="woocommerce-Price-amount amount amount-eur"> / <bdi>${formatPrice(bgnPrice)} лв.</bdi> </span>`;
                }
                
                // Append the dual currency element
                if (dualCurrencyHtml) {
                    $(this).append(dualCurrencyHtml);
                }
            });
        }
        
        // Process prices immediately
        processPriceElements();
        
        // Set up a mutation observer to handle dynamically loaded content
        const observer = new MutationObserver(function(mutations) {
            // Process prices when DOM changes
            processPriceElements();
        });
        
        // Start observing the cart and checkout containers for changes
        const containers = document.querySelectorAll('.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout');
        containers.forEach(function(container) {
            observer.observe(container, {
                childList: true,
                subtree: true,
                attributes: false,
                characterData: true
            });
        });
        
        // Also check on Ajax events that might update prices
        $(document.body).on('updated_cart_totals updated_checkout', function() {
            processPriceElements();
        });
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        processBlockPrices();
    });
    
})(jQuery);
