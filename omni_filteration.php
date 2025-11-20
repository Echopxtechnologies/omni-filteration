<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Omni Filteration
Description: Store pickup and delivery management with invoice integration
Version: 1.5
Author: EchoPx
*/

define('OMNI_FILTERATION_MODULE_NAME', 'omni_filteration');

// ================================================================
// MODULE ACTIVATION / DEACTIVATION HOOKS
// ================================================================

register_activation_hook(OMNI_FILTERATION_MODULE_NAME, 'omni_filteration_activation_hook');
register_deactivation_hook(OMNI_FILTERATION_MODULE_NAME, 'omni_filteration_deactivation_hook');

function omni_filteration_activation_hook()
{
    require(__DIR__ . '/install.php');
}

function omni_filteration_deactivation_hook()
{
    log_activity('Omni Filteration: Module deactivated');
}

// ================================================================
// REGISTER ADMIN MENU
// ================================================================

hooks()->add_action('admin_init', 'omni_filteration_init_menu');

function omni_filteration_init_menu()
{
    $CI = &get_instance();
    
    if (is_admin()) {
        $CI->app_menu->add_setup_menu_item('omni-store-address', [
            'name'     => 'Store Address',
            'href'     => admin_url('omni_filteration/manage_address'),
            'position' => 35,
            'icon'     => 'fa fa-map-marker'
        ]);
    }
}

// ================================================================
// INJECT DELIVERY METHOD DROPDOWN BEFORE VOUCHER INPUT
// ================================================================

hooks()->add_action('app_customers_head', 'omni_filteration_inject_dropdown');

function omni_filteration_inject_dropdown()
{
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'omni_sales') === false) {
        return;
    }
    
    // Get store address from database
    $CI = &get_instance();
    $store_address = $CI->db->get(db_prefix() . 'omni_store_address')->row();
    
    ?>
    <style>
        /* Delivery Method Dropdown */
        .delivery-method-wrapper {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .delivery-method-wrapper label {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            margin-bottom: 10px;
            display: block;
        }
        
        .delivery-method-wrapper label .required {
            color: #e74c3c;
            margin-left: 3px;
        }
        
        .delivery-method-wrapper select {
            width: 100%;
            padding: 12px 15px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: white;
            color: #333;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .delivery-method-wrapper select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .delivery-method-wrapper select:hover {
            border-color: #4CAF50;
        }
        
        .delivery-method-icon {
            margin-right: 8px;
            color: #4CAF50;
        }
        
        @media (max-width: 768px) {
            .delivery-method-wrapper {
                padding: 15px;
            }
            
            .delivery-method-wrapper label {
                font-size: 14px;
            }
            
            .delivery-method-wrapper select {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
    </style>
    
    <script>
        var OMNI_STORE_ADDRESS = <?php echo json_encode($store_address); ?>;
        var OMNI_AJAX_URL = '<?php echo site_url("omni_filteration/save_delivery_method"); ?>';
        
        (function() {
            'use strict';
            
            console.log('Omni Filteration: Starting initialization...');
            
            // Store original shipping HTML globally
            window.omniOriginalShippingHTML = null;
            window.omniOriginalShippingParent = null;
            
            function injectDeliveryDropdown() {
                if (document.querySelector('.delivery-method-wrapper')) {
                    console.log('Omni Filteration: Dropdown already exists, skipping...');
                    return true;
                }
                
                var voucherInput = document.querySelector('input[name="voucher"]');
                
                if (!voucherInput) {
                    voucherInput = document.querySelector('input[name="voucher"].form-control');
                }
                
                if (!voucherInput) {
                    var allInputs = document.querySelectorAll('input.form-control');
                    for (var i = 0; i < allInputs.length; i++) {
                        var input = allInputs[i];
                        var placeholder = input.getAttribute('placeholder') || '';
                        var label = input.previousElementSibling;
                        var labelText = label ? label.textContent.toLowerCase() : '';
                        
                        if (placeholder.toLowerCase().indexOf('voucher') !== -1 || 
                            labelText.indexOf('voucher') !== -1) {
                            voucherInput = input;
                            console.log('Omni Filteration: Found voucher input by placeholder/label');
                            break;
                        }
                    }
                }
                
                if (!voucherInput) {
                    console.log('Omni Filteration: Voucher input not found, will retry...');
                    return false;
                }
                
                var targetElement = voucherInput.parentElement;
                
                if (targetElement.classList.contains('col-md-12') || 
                    targetElement.classList.contains('col-md-6')) {
                    targetElement = voucherInput.closest('.col-md-12, .col-md-6, .form-group');
                }
                
                if (!targetElement) {
                    targetElement = voucherInput;
                }
                
                var dropdownHTML = `
                    <div class="delivery-method-wrapper" id="delivery-method-container">
                        <label for="delivery_method">
                            <i class="fa fa-truck delivery-method-icon"></i>
                            Delivery Method
                            <span class="required">*</span>
                        </label>
                        <select id="delivery_method" name="delivery_method" class="form-control" required>
                            <option value="">-- Select Delivery Method --</option>
                            <option value="home_delivery">🏠 Home Delivery</option>
                            <option value="store_pickup">🏪 Store Pickup</option>
                        </select>
                    </div>
                `;
                
                targetElement.insertAdjacentHTML('beforebegin', dropdownHTML);
                
                console.log('Omni Filteration: Delivery dropdown injected successfully');
                
                setupDropdownListener();
                
                return true;
            }
            
            function setupDropdownListener() {
                var dropdown = document.getElementById('delivery_method');
                
                if (!dropdown) {
                    console.log('Omni Filteration: Dropdown not found for event listener');
                    return;
                }
                
                dropdown.addEventListener('change', function() {
                    var selectedMethod = this.value;
                    console.log('Omni Filteration: Delivery method selected:', selectedMethod);
                    
                    // Store in localStorage
                    if (typeof Storage !== 'undefined') {
                        localStorage.setItem('omni_delivery_method', selectedMethod);
                    }
                    
                    // Handle address display based on selection
                    handleAddressDisplay(selectedMethod);
                    
                    // Save to database
                    if (selectedMethod === 'store_pickup' || selectedMethod === 'home_delivery') {
                        saveDeliveryMethodToDatabase(selectedMethod);
                    }
                    
                    // Store in hidden input
                    var existingInput = document.getElementById('hidden_delivery_method');
                    if (existingInput) {
                        existingInput.value = selectedMethod;
                    } else {
                        var hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.id = 'hidden_delivery_method';
                        hiddenInput.name = 'delivery_method';
                        hiddenInput.value = selectedMethod;
                        
                        var form = dropdown.closest('form');
                        if (form) {
                            form.appendChild(hiddenInput);
                        } else {
                            document.body.appendChild(hiddenInput);
                        }
                    }
                    
                    // Visual feedback
                    dropdown.style.borderColor = '#4CAF50';
                    dropdown.style.backgroundColor = '#f0fff4';
                    
                    setTimeout(function() {
                        dropdown.style.borderColor = '';
                        dropdown.style.backgroundColor = '';
                    }, 1000);
                });
                
                // Restore previous selection
                if (typeof Storage !== 'undefined') {
                    var savedMethod = localStorage.getItem('omni_delivery_method');
                    if (savedMethod) {
                        dropdown.value = savedMethod;
                        console.log('Omni Filteration: Restored saved delivery method:', savedMethod);
                        handleAddressDisplay(savedMethod);
                    }
                }
            }
            
            /**
             * Handle address display - REPLACES shipping section with store address
             */
            function handleAddressDisplay(method) {
                var shippingSection = findShippingAddressSection();
                
                if (!shippingSection) {
                    console.log('Omni Filteration: Shipping section not found');
                    return;
                }
                
                if (method === 'store_pickup') {
                    // REPLACE shipping section with store address
                    replaceShippingWithStore(shippingSection);
                    console.log('Omni Filteration: Replaced shipping with store address');
                    
                } else if (method === 'home_delivery') {
                    // RESTORE original shipping section
                    restoreOriginalShipping();
                    console.log('Omni Filteration: Restored original shipping address');
                }
            }
            
            /**
             * Find shipping address section
             */
            function findShippingAddressSection() {
                // Try multiple methods to find shipping section
                
                // Method 1: Look for "Shipping Address" heading
                var headings = document.querySelectorAll('h4, h3, .panel-heading, strong');
                for (var i = 0; i < headings.length; i++) {
                    var heading = headings[i];
                    var text = heading.textContent.trim().toLowerCase();
                    
                    if (text.indexOf('shipping') !== -1 && text.indexOf('address') !== -1) {
                        var section = heading.closest('.col-md-4, .col-md-6, .panel, div[class*="col-"]');
                        if (section) {
                            console.log('Omni Filteration: Found shipping section by heading');
                            return section;
                        }
                    }
                }
                
                // Method 2: Look for shipping icon
                var icons = document.querySelectorAll('.fa-truck, .fa-shipping-fast');
                for (var j = 0; j < icons.length; j++) {
                    var icon = icons[j];
                    var section = icon.closest('.col-md-4, .col-md-6, .panel, div[class*="col-"]');
                    if (section) {
                        console.log('Omni Filteration: Found shipping section by icon');
                        return section;
                    }
                }
                
                // Method 3: Look for elements with "shipping" in class name
                var shippingElements = document.querySelectorAll('[class*="shipping"]');
                if (shippingElements.length > 0) {
                    var section = shippingElements[0].closest('.col-md-4, .col-md-6, .panel, div[class*="col-"]');
                    if (section) {
                        console.log('Omni Filteration: Found shipping section by class');
                        return section;
                    }
                }
                
                console.log('Omni Filteration: Shipping section not found');
                return null;
            }
            
            /**
             * Replace shipping section with store address - SAME STYLE AS BILLING
             */
            function replaceShippingWithStore(shippingSection) {
                if (!OMNI_STORE_ADDRESS) {
                    console.log('Omni Filteration: Store address not configured');
                    return;
                }
                
                // Store original shipping HTML (only once)
                if (!window.omniOriginalShippingHTML) {
                    window.omniOriginalShippingHTML = shippingSection.outerHTML;
                    window.omniOriginalShippingParent = shippingSection.parentNode;
                    console.log('Omni Filteration: Stored original shipping HTML');
                }
                
                // Get the class names from original shipping section to maintain same styling
                var originalClasses = shippingSection.className || 'col-md-4';
                
                // Create store address HTML - EXACT SAME STYLE AS BILLING ADDRESS
                var storeAddressHTML = `
                    <div class="${originalClasses}" id="omni-store-pickup-address">
                        <h4>
                            <i class="fa fa-shopping-bag"></i> Store Address
                        </h4>
                        <address>
                            ${OMNI_STORE_ADDRESS.store_name}<br>
                            ${OMNI_STORE_ADDRESS.phone ? OMNI_STORE_ADDRESS.phone + '<br>' : ''}
                            ${OMNI_STORE_ADDRESS.address}<br>
                            ${OMNI_STORE_ADDRESS.city}, ${OMNI_STORE_ADDRESS.state},<br>
                            ${OMNI_STORE_ADDRESS.pincode ? OMNI_STORE_ADDRESS.pincode + '.' : ''}<br>
                            ${OMNI_STORE_ADDRESS.city} ${OMNI_STORE_ADDRESS.state}<br>
                            IN ${OMNI_STORE_ADDRESS.pincode}
                        </address>
                    </div>
                `;
                
                // Replace shipping section with store address
                shippingSection.outerHTML = storeAddressHTML;
            }
            
            /**
             * Restore original shipping section
             */
            function restoreOriginalShipping() {
                var storeAddressReplacement = document.getElementById('omni-store-pickup-address');
                
                if (storeAddressReplacement && window.omniOriginalShippingHTML) {
                    storeAddressReplacement.outerHTML = window.omniOriginalShippingHTML;
                    console.log('Omni Filteration: Restored original shipping section');
                }
            }
            
            /**
             * Save delivery method to database
             */
            function saveDeliveryMethodToDatabase(method) {
                console.log('Omni Filteration: Saving to database...');
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', OMNI_AJAX_URL, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                console.log('Omni Filteration: Delivery method saved successfully');
                                console.log('Saved data:', response.data);
                            } else {
                                console.error('Omni Filteration: Failed to save:', response.message);
                            }
                        } catch (e) {
                            console.error('Omni Filteration: Invalid response format');
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Omni Filteration: AJAX request failed');
                };
                
                xhr.send('delivery_method=' + encodeURIComponent(method));
            }
            
            /**
             * Form validation
             */
            function setupFormValidation() {
                var forms = document.querySelectorAll('form');
                
                forms.forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        var dropdown = document.getElementById('delivery_method');
                        
                        if (dropdown && !dropdown.value) {
                            e.preventDefault();
                            dropdown.style.borderColor = '#e74c3c';
                            dropdown.focus();
                            alert('Please select a delivery method before proceeding.');
                            return false;
                        }
                    });
                });
            }
            
            /**
             * Initialize delivery dropdown
             */
            function initDeliveryDropdown() {
                var success = injectDeliveryDropdown();
                if (success) {
                    setupFormValidation();
                }
            }
            
            // Initialize on different events
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDeliveryDropdown);
            } else {
                initDeliveryDropdown();
            }
            
            // Retry with delays (for dynamically loaded content)
            setTimeout(initDeliveryDropdown, 500);
            setTimeout(initDeliveryDropdown, 1000);
            setTimeout(initDeliveryDropdown, 1500);
            setTimeout(initDeliveryDropdown, 2500);
            
            // Watch for DOM changes
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    var shouldRun = false;
                    
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    if (node.tagName === 'INPUT' && node.name === 'voucher') {
                                        shouldRun = true;
                                    }
                                    if (node.querySelector && node.querySelector('input[name="voucher"]')) {
                                        shouldRun = true;
                                    }
                                }
                            });
                        }
                    });
                    
                    if (shouldRun && !document.querySelector('.delivery-method-wrapper')) {
                        setTimeout(initDeliveryDropdown, 200);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            
            console.log('Omni Filteration: Initialization complete');
        })();
    </script>
    <?php
}