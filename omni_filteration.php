<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Omni Filteration
Description: Store pickup and delivery management with invoice integration
Version: 3.4
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
    log_activity('Omni Filteration: Module activated successfully');
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
    
    // if (is_admin()) {
    //     // Add menu under Setup
    //     $CI->app_menu->add_setup_menu_item('omni-store-address', [
    //         'name'     => 'Store Address',
    //         'href'     => admin_url('omni_filteration_admin/manage_address'), // Updated URL
    //         'position' => 35,
    //         'icon'     => 'fa fa-map-marker'
    //     ]);
        
    //     // Add menu under Utilities (optional)
    //     $CI->app_menu->add_setup_menu_item('omni-deliveries', [
    //         'name'     => 'Student Deliveries',
    //         'href'     => admin_url('omni_filteration_admin/view_deliveries'), // Updated URL
    //         'position' => 36,
    //         'icon'     => 'fa fa-truck'
    //     ]);
    // }
}

// ================================================================
// OVERRIDE SHIPPING LABEL AND ADDRESS FOR STORE PICKUP
// ================================================================

hooks()->add_action('before_render_invoice_template', 'omni_modify_invoice_shipping_label', 10, 1);

function omni_modify_invoice_shipping_label($invoice_id)
{
    $CI = &get_instance();
    
    if (empty($invoice_id)) {
        return;
    }
    
    if (!$CI->load->is_loaded('invoices_model')) {
        $CI->load->model('invoices_model');
    }
    
    $invoice = $CI->invoices_model->get($invoice_id);
    
    if (!$invoice || empty($invoice->clientid)) {
        return;
    }
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($invoice->clientid);
    
    if ($delivery && $delivery->delivery_method === 'store_pickup') {
        // Store this in a global variable for use in templates
        $GLOBALS['omni_is_store_pickup'] = true;
    }
}

// ================================================================
// MODIFY LANGUAGE STRING FOR SHIPPING ADDRESS
// ================================================================

hooks()->add_filter('before_return_language_line', 'omni_change_shipping_to_pickup_label', 10, 2);

function omni_change_shipping_to_pickup_label($label, $key)
{
    // Check if this is a store pickup order
    if (isset($GLOBALS['omni_is_store_pickup']) && $GLOBALS['omni_is_store_pickup'] === true) {
        
        // Change shipping-related labels to pickup
        if ($key === 'shipping_address' || $key === 'ship_to') {
            return 'Pickup at';
        }
        
        if ($key === 'shipping_street') {
            return 'Store Address';
        }
        
        if ($key === 'shipping_city') {
            return 'City';
        }
        
        if ($key === 'shipping_state') {
            return 'State';
        }
        
        if ($key === 'shipping_zip') {
            return 'Pincode';
        }
        
        if ($key === 'shipping_country') {
            return 'Country';
        }
        
        if ($key === 'show_shipping_on_invoice') {
            return 'Show pickup location on invoice';
        }
    }
    
    return $label;
}

// ================================================================
// HIDE BILLING ADDRESS IN ORDER OVERVIEW PAGE
// ================================================================

hooks()->add_action('app_customers_head', 'omni_hide_billing_address_in_overview');

function omni_hide_billing_address_in_overview()
{
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Only apply on view_overview page
    if (strpos($current_url, 'view_overview') === false && 
        strpos($current_url, 'omni_sales_client/view_overview') === false) {
        return;
    }
    
    ?>
    <style>
        /* Hide billing address section in order overview page */
        .billing-address-section,
        .panel-body .col-md-6:has(h4:contains("Billing Address")),
        .panel-body .col-md-6:has(strong:contains("Billing Address")),
        div[class*="col-"]:has(h4:contains("Billing Address")),
        div[class*="col-"]:has(strong:contains("Billing Address")) {
            display: none !important;
            visibility: hidden !important;
        }
    </style>
    
    <script>
    (function() {
        'use strict';
        
        console.log('🔒 Omni: Hiding billing address in order overview');
        
        function waitForDOM(callback) {
            if (document.body && document.readyState !== 'loading') {
                callback();
            } else {
                setTimeout(function() { waitForDOM(callback); }, 100);
            }
        }
        
        function hideBillingAddress() {
            if (!document.body) {
                return false;
            }
            
            var headings = document.querySelectorAll('h4, h3, h5, strong, b, label');
            var billingSection = null;
            
            for (var i = 0; i < headings.length; i++) {
                var heading = headings[i];
                var text = heading.textContent.trim().toLowerCase();
                
                if (text.indexOf('billing') !== -1 && text.indexOf('address') !== -1) {
                    billingSection = heading.closest('.col-md-6, .col-md-4, .col-sm-6, div[class*="col-"]');
                    
                    if (!billingSection) {
                        billingSection = heading.parentElement;
                    }
                    
                    if (billingSection) {
                        billingSection.style.display = 'none';
                        billingSection.style.visibility = 'hidden';
                        billingSection.classList.add('omni-hidden-billing');
                        console.log('   ✅ Hidden billing address section');
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        waitForDOM(function() {
            hideBillingAddress();
            setTimeout(hideBillingAddress, 300);
            setTimeout(hideBillingAddress, 600);
            setTimeout(hideBillingAddress, 1000);
            setTimeout(hideBillingAddress, 1500);
        });
    })();
    </script>
    <?php
}

// ================================================================
// INJECT DELIVERY METHOD NEXT TO ADDRESSES (WITH REPLACEMENT LOGIC)
// ================================================================

hooks()->add_action('app_customers_head', 'omni_filteration_inject_dropdown');

function omni_filteration_inject_dropdown()
{
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'omni_sales') === false) {
        return;
    }
    
    $CI = &get_instance();
    $store_address = $CI->db->get(db_prefix() . 'omni_store_address')->row();
    
    ?>
    <style>
        /* Delivery method box styling */
        .omni-delivery-method-box {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .omni-delivery-method-box h4 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .omni-delivery-method-box .form-group {
            margin-bottom: 15px;
        }
        
        .omni-delivery-method-box label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
        }
        
        .omni-delivery-method-box label .required {
            color: #e74c3c;
            margin-left: 3px;
        }
        
        .omni-delivery-method-box select {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: white;
            color: #333;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .omni-delivery-method-box select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .omni-delivery-method-box select:hover {
            border-color: #4CAF50;
        }
        
        .delivery-method-icon {
            margin-right: 8px;
            color: #4CAF50;
        }
    </style>
    
    <script>
        var OMNI_STORE_ADDRESS = <?php echo json_encode($store_address); ?>;
        var OMNI_AJAX_URL = '<?php echo site_url("omni_filteration/save_delivery_method"); ?>';
        var OMNI_CLIENT_ID = <?php echo is_client_logged_in() ? get_client_user_id() : '0'; ?>;
        
        (function() {
            'use strict';
            
            console.log('📦 Omni: Initializing delivery method dropdown');
            
            window.omniOriginalShippingHTML = null;
            window.omniOriginalShippingParent = null;
            
            function injectDeliveryDropdown() {
                if (document.querySelector('.omni-delivery-method-box')) {
                    console.log('   Already injected');
                    return true;
                }
                
                var storeAddressSection = null;
                var headings = document.querySelectorAll('h4, h3, h5, strong');
                
                for (var i = 0; i < headings.length; i++) {
                    var text = headings[i].textContent.trim().toLowerCase();
                    
                    if ((text.indexOf('store') !== -1 || text.indexOf('shipping')) && text.indexOf('address') !== -1) {
                        storeAddressSection = headings[i].closest('.col-md-6, .col-md-4, .col-sm-6, div[class*="col-"]');
                        console.log('   ✓ Found address section');
                        break;
                    }
                }
                
                if (!storeAddressSection) {
                    for (var j = 0; j < headings.length; j++) {
                        var text2 = headings[j].textContent.trim().toLowerCase();
                        
                        if (text2.indexOf('customer') !== -1 && text2.indexOf('detail') !== -1) {
                            storeAddressSection = headings[j].closest('.col-md-6, .col-md-4, .col-sm-6, div[class*="col-"]');
                            console.log('   ✓ Found Customer details section');
                            break;
                        }
                    }
                }
                
                if (!storeAddressSection) {
                    console.log('   ❌ Could not find insertion point');
                    return false;
                }
                
                var colClass = storeAddressSection.className.match(/col-md-\d+|col-sm-\d+/);
                var columnClass = colClass ? colClass[0] : 'col-md-6';
                
                var deliveryMethodHTML = `
                    <div class="${columnClass} omni-delivery-column">
                        <div class="omni-delivery-method-box">
                            <h4>
                                <i class="fa fa-truck delivery-method-icon"></i>
                                Delivery Method
                            </h4>
                            <div class="form-group">
                                <label for="delivery_method">
                                    Select Method
                                    <span class="required">*</span>
                                </label>
                                <select id="delivery_method" name="delivery_method" class="form-control" required>
                                    <option value="">-- Select --</option>
                                    <option value="home_delivery">🏠 Home Delivery</option>
                                    <option value="store_pickup">🏪 Store Pickup</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
                
                storeAddressSection.insertAdjacentHTML('afterend', deliveryMethodHTML);
                console.log('   ✅ Delivery method box inserted');
                
                setupDropdownListener();
                
                return true;
            }
            
            function setupDropdownListener() {
                var dropdown = document.getElementById('delivery_method');
                
                if (!dropdown) {
                    return;
                }
                
                dropdown.addEventListener('change', function() {
                    var selectedMethod = this.value;
                    
                    console.log('   Selected:', selectedMethod);
                    
                    if (typeof Storage !== 'undefined') {
                        localStorage.setItem('omni_delivery_method', selectedMethod);
                    }
                    
                    handleAddressDisplay(selectedMethod);
                    
                    if (selectedMethod === 'store_pickup' || selectedMethod === 'home_delivery') {
                        saveDeliveryMethodToDatabase(selectedMethod);
                    }
                    
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
                    
                    dropdown.style.borderColor = '#4CAF50';
                    dropdown.style.backgroundColor = '#f0fff4';
                    
                    setTimeout(function() {
                        dropdown.style.borderColor = '';
                        dropdown.style.backgroundColor = '';
                    }, 1000);
                });
                
                if (typeof Storage !== 'undefined') {
                    var savedMethod = localStorage.getItem('omni_delivery_method');
                    if (savedMethod) {
                        dropdown.value = savedMethod;
                        handleAddressDisplay(savedMethod);
                        
                        setTimeout(function() {
                            saveDeliveryMethodToDatabase(savedMethod);
                        }, 1000);
                    }
                }
            }
            
            function handleAddressDisplay(method) {
                console.log('   Handling address display for:', method);
                
                var shippingSection = findShippingAddressSection();
                
                if (!shippingSection) {
                    console.log('   ⚠ Shipping section not found');
                    return;
                }
                
                if (method === 'store_pickup') {
                    replaceShippingWithStore(shippingSection);
                } else if (method === 'home_delivery') {
                    restoreOriginalShipping();
                }
            }
            
            function findShippingAddressSection() {
                var headings = document.querySelectorAll('h4, h3, .panel-heading, strong');
                
                for (var i = 0; i < headings.length; i++) {
                    var heading = headings[i];
                    var text = heading.textContent.trim().toLowerCase();
                    
                    if (text.indexOf('shipping') !== -1 && text.indexOf('address') !== -1) {
                        var section = heading.closest('.col-md-4, .col-md-6, .panel, div[class*="col-"]');
                        if (section) {
                            console.log('   ✓ Found shipping address section');
                            return section;
                        }
                    }
                }
                
                var icons = document.querySelectorAll('.fa-truck, .fa-shipping-fast');
                for (var j = 0; j < icons.length; j++) {
                    var icon = icons[j];
                    var section = icon.closest('.col-md-4, .col-md-6, .panel, div[class*="col-"]');
                    if (section) {
                        console.log('   ✓ Found shipping section via icon');
                        return section;
                    }
                }
                
                return null;
            }
            
            function replaceShippingWithStore(shippingSection) {
                if (!OMNI_STORE_ADDRESS) {
                    console.log('   ⚠ No store address configured');
                    return;
                }
                
                if (!window.omniOriginalShippingHTML) {
                    window.omniOriginalShippingHTML = shippingSection.outerHTML;
                    window.omniOriginalShippingParent = shippingSection.parentNode;
                    console.log('   ✓ Saved original shipping HTML');
                }
                
                var originalClasses = shippingSection.className || 'col-md-4';
                
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
                
                shippingSection.outerHTML = storeAddressHTML;
                console.log('   ✅ Replaced shipping with store address');
            }
            
            function restoreOriginalShipping() {
                var storeAddressReplacement = document.getElementById('omni-store-pickup-address');
                
                if (storeAddressReplacement && window.omniOriginalShippingHTML) {
                    storeAddressReplacement.outerHTML = window.omniOriginalShippingHTML;
                    console.log('   ✅ Restored original shipping address');
                }
            }
            
            function saveDeliveryMethodToDatabase(method) {
                if (!OMNI_CLIENT_ID || OMNI_CLIENT_ID == 0) {
                    return;
                }
                
                var formData = new FormData();
                formData.append('delivery_method', method);
                formData.append('client_id', OMNI_CLIENT_ID);
                
                var csrfToken = document.querySelector('input[name="csrf_token_name"]');
                if (csrfToken) {
                    formData.append(csrfToken.name, csrfToken.value);
                }
                
                fetch(OMNI_AJAX_URL, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        console.log('   ✅ Delivery method saved to database');
                    }
                })
                .catch(function(error) {
                    console.error('   ❌ AJAX error:', error);
                });
            }
            
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
            
            function waitForDOM(callback) {
                if (document.body && document.readyState !== 'loading') {
                    callback();
                } else {
                    setTimeout(function() { waitForDOM(callback); }, 100);
                }
            }
            
            function initDeliveryDropdown() {
                var success = injectDeliveryDropdown();
                if (success) {
                    setupFormValidation();
                }
            }
            
            waitForDOM(function() {
                initDeliveryDropdown();
                
                setTimeout(initDeliveryDropdown, 500);
                setTimeout(initDeliveryDropdown, 1000);
                setTimeout(initDeliveryDropdown, 1500);
            });
        })();
    </script>
    <?php
}

// ================================================================
// ADD SCHOOL NAME & CLASS NAME BELOW PERSON NAME IN BILL TO
// ================================================================

hooks()->add_action('app_admin_head', 'omni_inject_school_class_info');
hooks()->add_action('app_customers_head', 'omni_inject_school_class_info');

function omni_inject_school_class_info()
{
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'invoice') === false && strpos($current_url, 'invoicehtml') === false) {
        return;
    }
    
    $CI = &get_instance();
    
    $invoice_id = null;
    if (preg_match('/invoice\/(\d+)/', $current_url, $matches)) {
        $invoice_id = $matches[1];
    } elseif (preg_match('/invoicehtml\/(\d+)/', $current_url, $matches)) {
        $invoice_id = $matches[1];
    } elseif ($CI->input->get('id')) {
        $invoice_id = $CI->input->get('id');
    }
    
    if (!$invoice_id) {
        return;
    }
    
    $CI->db->select('clientid');
    $CI->db->where('id', $invoice_id);
    $invoice = $CI->db->get(db_prefix() . 'invoices')->row();
    
    if (!$invoice) {
        return;
    }
    
    $CI->db->select('school_name, class_name');
    $CI->db->where('client_id', $invoice->clientid);
    $delivery_info = $CI->db->get(db_prefix() . 'omni_student_delivery')->row();
    
    if (!$delivery_info || empty($delivery_info->school_name)) {
        return;
    }
    
    $school_name = htmlspecialchars($delivery_info->school_name);
    $class_name = htmlspecialchars($delivery_info->class_name);
    
    ?>
    <style>
        .omni-school-info {
            font-size: 14px !important;
            color: #555 !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.5 !important;
            font-weight: normal !important;
        }
        
        .omni-school-name,
        .omni-class-name {
            color: #555 !important;
            font-size: 14px !important;
            font-weight: normal !important;
            display: inline !important;
        }
        
        .omni-separator {
            display: inline !important;
            margin: 0 5px !important;
            color: #999 !important;
        }
        
        @media print {
            .omni-school-info,
            .omni-school-name,
            .omni-class-name {
                font-size: 13px !important;
            }
        }
    </style>
    
    <script>
    (function() {
        'use strict';
        
        var schoolName = <?php echo json_encode($school_name); ?>;
        var className = <?php echo json_encode($class_name); ?>;
        
        console.log('🏫 Omni: Injecting school info');
        
        function waitForDOM(callback) {
            if (document.body && document.readyState !== 'loading') {
                callback();
            } else {
                setTimeout(function() { waitForDOM(callback); }, 100);
            }
        }
        
        function injectSchoolClassInfo() {
            if (!document.body) {
                return false;
            }
            
            if (document.querySelector('.omni-school-info')) {
                return true;
            }
            
            var billToHeading = null;
            var headings = document.querySelectorAll('h4, h3, strong, b');
            
            for (var i = 0; i < headings.length; i++) {
                var text = headings[i].textContent.trim().toLowerCase();
                if (text === 'bill to' || text === 'bill to:') {
                    billToHeading = headings[i];
                    break;
                }
            }
            
            var billToAddress = null;
            
            if (billToHeading) {
                var currentElement = billToHeading;
                while (currentElement && !billToAddress) {
                    currentElement = currentElement.nextElementSibling;
                    if (currentElement && currentElement.tagName === 'ADDRESS') {
                        billToAddress = currentElement;
                        break;
                    }
                }
            }
            
            if (!billToAddress) {
                var addresses = document.querySelectorAll('address');
                
                if (addresses.length >= 2) {
                    billToAddress = addresses[1];
                } else if (addresses.length === 1) {
                    billToAddress = addresses[0];
                } else {
                    return false;
                }
            }
            
            var firstBr = billToAddress.querySelector('br');
            
            if (firstBr) {
                var schoolSpan = document.createElement('span');
                schoolSpan.className = 'omni-school-info';
                schoolSpan.innerHTML = '<span class="omni-school-name">' + schoolName + '</span>' +
                                      '<span class="omni-separator"> - </span>' +
                                      '<span class="omni-class-name">' + className + '</span>';
                
                var newBr = document.createElement('br');
                
                var nextNode = firstBr.nextSibling;
                firstBr.parentNode.insertBefore(newBr, nextNode);
                firstBr.parentNode.insertBefore(schoolSpan, newBr);
                
                console.log('   ✅ School info injected');
                return true;
            }
            
            return false;
        }
        
        waitForDOM(function() {
            injectSchoolClassInfo();
            setTimeout(injectSchoolClassInfo, 500);
            setTimeout(injectSchoolClassInfo, 1000);
            setTimeout(injectSchoolClassInfo, 2000);
        });
    })();
    </script>
    <?php
}

// ================================================================
// CHANGE "SHIP TO" TO "PICKUP AT" IN CLIENT-SIDE INVOICE VIEW
// ================================================================

hooks()->add_action('app_admin_head', 'omni_change_ship_to_pickup_at_label');
hooks()->add_action('app_customers_head', 'omni_change_ship_to_pickup_at_label');

function omni_change_ship_to_pickup_at_label()
{
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'invoice') === false && strpos($current_url, 'invoicehtml') === false) {
        return;
    }
    
    $CI = &get_instance();
    
    $invoice_id = null;
    if (preg_match('/invoice\/(\d+)/', $current_url, $matches)) {
        $invoice_id = $matches[1];
    } elseif (preg_match('/invoicehtml\/(\d+)/', $current_url, $matches)) {
        $invoice_id = $matches[1];
    } elseif ($CI->input->get('id')) {
        $invoice_id = $CI->input->get('id');
    }
    
    if (!$invoice_id) {
        return;
    }
    
    $CI->db->select('clientid');
    $CI->db->where('id', $invoice_id);
    $invoice = $CI->db->get(db_prefix() . 'invoices')->row();
    
    if (!$invoice) {
        return;
    }
    
    // Check if this is a store pickup order
    $CI->db->where('client_id', $invoice->clientid);
    $CI->db->where('delivery_method', 'store_pickup');
    $delivery = $CI->db->get(db_prefix() . 'omni_student_delivery')->row();
    
    if (!$delivery) {
        return; // Not store pickup
    }
    
    ?>
    <script>
    (function() {
        'use strict';
        
        console.log('🚚 OMNI: Store Pickup - Changing "Ship to" to "Pickup at"');
        
        function waitForDOM(callback) {
            if (document.body && document.readyState !== 'loading') {
                callback();
            } else {
                setTimeout(function() { waitForDOM(callback); }, 100);
            }
        }
        
        function changeLabels() {
            if (!document.body) {
                return false;
            }
            
            var changed = false;
            
            // Find all headings
            var headings = document.querySelectorAll('h4, h3, h5, strong, b, label');
            
            for (var i = 0; i < headings.length; i++) {
                var heading = headings[i];
                var text = heading.textContent.trim();
                
                // Match "Ship to" or "Ship To" (case insensitive) but NOT "Bill to"
                if (/^ship\s+to$/i.test(text) && !/bill/i.test(text)) {
                    console.log('   ✓ Found "Ship to" heading');
                    heading.textContent = 'Pickup at';
                    changed = true;
                }
                
                // Also check for "Shipping Address"
                if (/shipping\s+address/i.test(text)) {
                    console.log('   ✓ Found "Shipping Address"');
                    heading.textContent = heading.textContent.replace(/shipping\s+address/gi, 'Pickup at');
                    changed = true;
                }
            }
            
            if (changed) {
                console.log('   ✅ SUCCESS: Labels changed');
            } else {
                console.log('   ⚠ "Ship to" heading not found');
            }
            
            return changed;
        }
        
        waitForDOM(function() {
            changeLabels();
            
            setTimeout(changeLabels, 300);
            setTimeout(changeLabels, 600);
            setTimeout(changeLabels, 1000);
            setTimeout(changeLabels, 1500);
            setTimeout(changeLabels, 2000);
        });
    })();
    </script>
    <?php
}

// ================================================================
// INVOICE HOOKS
// ================================================================

hooks()->add_action('omni_sales_after_invoice_added', 'omni_update_shipping_after_order_invoice', 10, 1);

function omni_update_shipping_after_order_invoice($order_id)
{
    $CI = &get_instance();
    
    if (empty($order_id) || !is_numeric($order_id)) {
        return;
    }
    
    if (!$CI->load->is_loaded('omni_sales_model')) {
        $CI->load->model('omni_sales/omni_sales_model');
    }
    
    $cart = $CI->omni_sales_model->get_cart($order_id);
    
    if (!$cart || empty($cart->userid)) {
        return;
    }
    
    $client_id = $cart->userid;
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($client_id);
    
    if (!$delivery || $delivery->delivery_method !== 'store_pickup') {
        return;
    }
    
    $store_address = $CI->omni_filteration_model->get_address();
    
    if (!$store_address) {
        return;
    }
    
    $CI->db->select('id, clientid');
    $CI->db->where('clientid', $client_id);
    $CI->db->order_by('id', 'DESC');
    $CI->db->limit(1);
    $invoice = $CI->db->get(db_prefix() . 'invoices')->row();
    
    if (!$invoice) {
        return;
    }
    
    $update_data = [
        'shipping_street' => $store_address->address,
        'shipping_city' => $store_address->city,
        'shipping_state' => $store_address->state,
        'shipping_zip' => $store_address->pincode,
        'shipping_country' => 102
    ];
    
    $CI->db->where('id', $invoice->id);
    $CI->db->update(db_prefix() . 'invoices', $update_data);
    
    if ($CI->db->affected_rows() > 0) {
        log_activity('Omni Filteration: Updated shipping for Invoice #' . $invoice->id);
    }
}

hooks()->add_filter('invoice_html_pdf_data', 'omni_replace_invoice_shipping_data', 10, 1);

function omni_replace_invoice_shipping_data($invoice_data)
{
    $CI = &get_instance();
    
    $invoice = null;
    $is_wrapped = false;
    
    if (is_object($invoice_data) && isset($invoice_data->id) && isset($invoice_data->clientid)) {
        $invoice = $invoice_data;
    } elseif (is_array($invoice_data) && isset($invoice_data['invoice'])) {
        $invoice = $invoice_data['invoice'];
        $is_wrapped = true;
    } elseif (is_object($invoice_data) && isset($invoice_data->invoice)) {
        $invoice = $invoice_data->invoice;
        $is_wrapped = true;
    }
    
    if (!$invoice || empty($invoice->clientid)) {
        return $invoice_data;
    }
    
    $client_id = $invoice->clientid;
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($client_id);
    
    if ($delivery && $delivery->delivery_method === 'store_pickup') {
        
        $store_address = $CI->omni_filteration_model->get_address();
        
        if ($store_address) {
            
            $invoice->shipping_street = $store_address->address;
            $invoice->shipping_city = $store_address->city;
            $invoice->shipping_state = $store_address->state;
            $invoice->shipping_zip = $store_address->pincode;
            $invoice->shipping_country = 102;
            
            if (property_exists($invoice, 'shipping_country_name')) {
                $invoice->shipping_country_name = 'India';
            }
        }
    }
    
    if ($is_wrapped) {
        if (is_array($invoice_data)) {
            $invoice_data['invoice'] = $invoice;
        } else {
            $invoice_data->invoice = $invoice;
        }
        return $invoice_data;
    }
    
    return $invoice;
}

hooks()->add_action('after_invoice_added', 'omni_update_shipping_in_database', 10, 1);
hooks()->add_action('after_invoice_updated', 'omni_update_shipping_in_database', 10, 1);

function omni_update_shipping_in_database($invoice_id)
{
    $CI = &get_instance();
    
    if (empty($invoice_id) || !is_numeric($invoice_id)) {
        return;
    }
    
    if (!$CI->load->is_loaded('invoices_model')) {
        $CI->load->model('invoices_model');
    }
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $invoice = $CI->invoices_model->get($invoice_id);
    
    if (!$invoice || empty($invoice->clientid)) {
        return;
    }
    
    $client_id = $invoice->clientid;
    $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($client_id);
    
    if ($delivery && $delivery->delivery_method === 'store_pickup') {
        
        $store_address = $CI->omni_filteration_model->get_address();
        
        if ($store_address) {
            
            $update_data = [
                'shipping_street' => $store_address->address,
                'shipping_city' => $store_address->city,
                'shipping_state' => $store_address->state,
                'shipping_zip' => $store_address->pincode,
                'shipping_country' => 102
            ];
            
            $CI->db->where('id', $invoice_id);
            $CI->db->update(db_prefix() . 'invoices', $update_data);
        }
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================

function omni_is_store_pickup($client_id)
{
    if (empty($client_id) || !is_numeric($client_id)) {
        return false;
    }
    
    $CI = &get_instance();
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($client_id);
    
    return ($delivery && $delivery->delivery_method === 'store_pickup');
}

function omni_get_store_address()
{
    $CI = &get_instance();
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    return $CI->omni_filteration_model->get_address();
}

function omni_get_client_delivery_info($client_id)
{
    if (empty($client_id) || !is_numeric($client_id)) {
        return null;
    }
    
    $CI = &get_instance();
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    return $CI->omni_filteration_model->get_client_delivery_info($client_id);
}

function omni_has_delivery_preference($client_id)
{
    if (empty($client_id) || !is_numeric($client_id)) {
        return false;
    }
    
    $CI = &get_instance();
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($client_id);
    
    return !empty($delivery);
}

function omni_get_store_pickup_count()
{
    $CI = &get_instance();
    
    $CI->db->where('delivery_method', 'store_pickup');
    return $CI->db->count_all_results(db_prefix() . 'omni_student_delivery');
}

function omni_get_home_delivery_count()
{
    $CI = &get_instance();
    
    $CI->db->where('delivery_method', 'home_delivery');
    return $CI->db->count_all_results(db_prefix() . 'omni_student_delivery');
}

function omni_sync_all_invoices()
{
    $CI = &get_instance();
    
    if (!$CI->load->is_loaded('invoices_model')) {
        $CI->load->model('invoices_model');
    }
    
    if (!$CI->load->is_loaded('omni_filteration_model')) {
        $CI->load->model('omni_filteration/omni_filteration_model');
    }
    
    $CI->db->select('id, clientid');
    $invoices = $CI->db->get(db_prefix() . 'invoices')->result();
    
    $updated_count = 0;
    $skipped_count = 0;
    
    foreach ($invoices as $invoice) {
        
        $delivery = $CI->omni_filteration_model->get_student_delivery_by_client($invoice->clientid);
        
        if ($delivery && $delivery->delivery_method === 'store_pickup') {
            
            $store_address = $CI->omni_filteration_model->get_address();
            
            if ($store_address) {
                
                $update_data = [
                    'shipping_street' => $store_address->address,
                    'shipping_city' => $store_address->city,
                    'shipping_state' => $store_address->state,
                    'shipping_zip' => $store_address->pincode,
                    'shipping_country' => 102
                ];
                
                $CI->db->where('id', $invoice->id);
                $CI->db->update(db_prefix() . 'invoices', $update_data);
                
                if ($CI->db->affected_rows() > 0) {
                    $updated_count++;
                }
            }
        } else {
            $skipped_count++;
        }
    }
    
    log_activity('Omni Filteration: Bulk sync - Updated: ' . $updated_count . ', Skipped: ' . $skipped_count);
    
    return [
        'success' => true,
        'updated' => $updated_count,
        'skipped' => $skipped_count,
        'total' => count($invoices)
    ];
}

// ================================================================
// MODULE INITIALIZATION
// ================================================================

log_activity('Omni Filteration Module: Loaded successfully (v3.4)');