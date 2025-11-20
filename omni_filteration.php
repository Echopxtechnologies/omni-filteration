<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Omni Filteration
Description: Store pickup and delivery management with invoice integration
Version: 2.3
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
        var OMNI_CLIENT_ID = <?php echo is_client_logged_in() ? get_client_user_id() : '0'; ?>;
        
        (function() {
            'use strict';
            
            console.log('Omni Filteration: Starting initialization...');
            console.log('AJAX URL:', OMNI_AJAX_URL);
            console.log('Client ID:', OMNI_CLIENT_ID);
            
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
                        console.log('Omni Filteration: Saved to localStorage');
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
                        
                        // Also save to database on page load
                        setTimeout(function() {
                            saveDeliveryMethodToDatabase(savedMethod);
                        }, 1000);
                    }
                }
            }
            
            function handleAddressDisplay(method) {
                var shippingSection = findShippingAddressSection();
                
                if (!shippingSection) {
                    console.log('Omni Filteration: Shipping section not found');
                    return;
                }
                
                if (method === 'store_pickup') {
                    replaceShippingWithStore(shippingSection);
                    console.log('Omni Filteration: Replaced shipping with store address');
                } else if (method === 'home_delivery') {
                    restoreOriginalShipping();
                    console.log('Omni Filteration: Restored original shipping address');
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
                            console.log('Omni Filteration: Found shipping section by heading');
                            return section;
                        }
                    }
                }
                
                var icons = document.querySelectorAll('.fa-truck, .fa-shipping-fast');
                for (var j = 0; j < icons.length; j++) {
                    var icon = icons[j];
                    var section = icon.closest('.col-md-4, .col-md-6, .panel, div[class*="col-"]');
                    if (section) {
                        console.log('Omni Filteration: Found shipping section by icon');
                        return section;
                    }
                }
                
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
            
            function replaceShippingWithStore(shippingSection) {
                if (!OMNI_STORE_ADDRESS) {
                    console.log('Omni Filteration: Store address not configured');
                    return;
                }
                
                if (!window.omniOriginalShippingHTML) {
                    window.omniOriginalShippingHTML = shippingSection.outerHTML;
                    window.omniOriginalShippingParent = shippingSection.parentNode;
                    console.log('Omni Filteration: Stored original shipping HTML');
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
            }
            
            function restoreOriginalShipping() {
                var storeAddressReplacement = document.getElementById('omni-store-pickup-address');
                
                if (storeAddressReplacement && window.omniOriginalShippingHTML) {
                    storeAddressReplacement.outerHTML = window.omniOriginalShippingHTML;
                    console.log('Omni Filteration: Restored original shipping section');
                }
            }
            
            function saveDeliveryMethodToDatabase(method) {
                console.log('Omni Filteration: Attempting to save to database...');
                console.log('Method:', method);
                console.log('Client ID:', OMNI_CLIENT_ID);
                console.log('URL:', OMNI_AJAX_URL);
                
                if (!OMNI_CLIENT_ID || OMNI_CLIENT_ID == 0) {
                    console.error('Omni Filteration: No client ID available');
                    return;
                }
                
                var formData = new FormData();
                formData.append('delivery_method', method);
                formData.append('client_id', OMNI_CLIENT_ID);
                
                var csrfToken = document.querySelector('input[name="csrf_token_name"]');
                if (csrfToken) {
                    formData.append(csrfToken.name, csrfToken.value);
                }
                
                console.log('Omni Filteration: Sending AJAX request...');
                
                fetch(OMNI_AJAX_URL, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    console.log('Omni Filteration: Response status:', response.status);
                    return response.json();
                })
                .then(function(data) {
                    console.log('Omni Filteration: Response data:', data);
                    
                    if (data.success) {
                        console.log('✅ Omni Filteration: Delivery method saved successfully to database');
                        console.log('Saved data:', data.data);
                    } else {
                        console.error('❌ Omni Filteration: Failed to save:', data.message);
                        if (data.debug) {
                            console.error('Debug info:', data.debug);
                        }
                    }
                })
                .catch(function(error) {
                    console.error('❌ Omni Filteration: AJAX error:', error);
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
            
            function initDeliveryDropdown() {
                var success = injectDeliveryDropdown();
                if (success) {
                    setupFormValidation();
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDeliveryDropdown);
            } else {
                initDeliveryDropdown();
            }
            
            setTimeout(initDeliveryDropdown, 500);
            setTimeout(initDeliveryDropdown, 1000);
            setTimeout(initDeliveryDropdown, 1500);
            
            if (window.MutationObserver && document.body) {
                var observer = new MutationObserver(function(mutations) {
                    var shouldRun = false;
                    
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    if (node.tagName === 'INPUT' && node.getAttribute && node.getAttribute('name') === 'voucher') {
                                        shouldRun = true;
                                    }
                                    if (node.querySelector) {
                                        var voucherNode = node.querySelector('input[name="voucher"]');
                                        if (voucherNode) {
                                            shouldRun = true;
                                        }
                                    }
                                }
                            });
                        }
                    });
                    
                    if (shouldRun && !document.querySelector('.delivery-method-wrapper')) {
                        setTimeout(initDeliveryDropdown, 200);
                    }
                });
                
                try {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                    console.log('Omni Filteration: MutationObserver initialized');
                } catch (e) {
                    console.error('Omni Filteration: Failed to initialize observer:', e);
                }
            }
            
            console.log('Omni Filteration: Initialization complete');
        })();
    </script>
    <?php
}

// ================================================================
// CHANGE "SHIP TO" LABEL TO "PICKUP AT" FOR STORE PICKUP
// ================================================================

hooks()->add_action('app_admin_head', 'omni_inject_invoice_label_changer');
hooks()->add_action('app_customers_head', 'omni_inject_invoice_label_changer');

function omni_inject_invoice_label_changer()
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
    
    $CI->db->where('client_id', $invoice->clientid);
    $CI->db->where('delivery_method', 'store_pickup');
    $delivery = $CI->db->get(db_prefix() . 'omni_student_delivery')->row();
    
    if (!$delivery) {
        return;
    }
    
    ?>
    <script>
    (function() {
        'use strict';
        
        function changeShipToLabel() {
            var walker = document.createTreeWalker(
                document.body,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            var node;
            var nodesToReplace = [];
            
            while (node = walker.nextNode()) {
                if (node.nodeValue && 
                    (node.nodeValue.indexOf('Ship to') !== -1 || 
                     node.nodeValue.indexOf('Ship To') !== -1 ||
                     node.nodeValue.indexOf('SHIP TO') !== -1)) {
                    nodesToReplace.push(node);
                }
            }
            
            nodesToReplace.forEach(function(node) {
                node.nodeValue = node.nodeValue
                    .replace(/Ship to/gi, 'Pickup at')
                    .replace(/Ship To/g, 'Pickup At')
                    .replace(/SHIP TO/g, 'PICKUP AT');
            });
            
            console.log('Omni Filteration: Changed ' + nodesToReplace.length + ' "Ship to" labels to "Pickup at"');
        }
        
        changeShipToLabel();
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', changeShipToLabel);
        }
        
        setTimeout(changeShipToLabel, 500);
        setTimeout(changeShipToLabel, 1000);
    })();
    </script>
    <?php
}
// ================================================================
// ADD SCHOOL NAME & CLASS NAME BELOW COMPANY NAME IN BILL TO
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
    
    $CI->db->select('school_name, class_name, delivery_method');
    $CI->db->where('client_id', $invoice->clientid);
    $delivery_info = $CI->db->get(db_prefix() . 'omni_student_delivery')->row();
    
    if (!$delivery_info || empty($delivery_info->school_name)) {
        return;
    }
    
    $school_name = htmlspecialchars($delivery_info->school_name);
    $class_name = htmlspecialchars($delivery_info->class_name);
    
    ?>
    <style>
        /* Uniform styling for school and class name */
        .omni-school-info {
            font-size: 14px !important;
            color: #333 !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.4 !important;
            font-weight: normal !important;
        }
        
        .omni-school-name {
            color: #333 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            display: inline !important;
        }
        
        .omni-class-name {
            color: #333 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            display: inline !important;
            margin-left: 5px !important;
        }
        
        .omni-separator {
            display: inline !important;
            margin: 0 5px !important;
            color: #999 !important;
        }
        
        @media print {
            .omni-school-info {
                font-size: 13px !important;
            }
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
        
        console.log('=== OMNI SCHOOL INFO DEBUG ===');
        console.log('School Name:', schoolName);
        console.log('Class Name:', className);
        
        function injectSchoolClassInfo() {
            console.log('Attempting to inject school/class info...');
            
            // Check if already injected
            if (document.querySelector('.omni-school-info')) {
                console.log('Already injected - skipping');
                return true;
            }
            
            // Find all address elements
            var addresses = document.querySelectorAll('address');
            console.log('Found', addresses.length, 'address elements');
            
            if (addresses.length === 0) {
                console.log('No address elements found');
                return false;
            }
            
            // First address is usually "Bill To"
            var billToAddress = addresses[0];
            console.log('Using first address element for Bill To');
            
            // Method 1: Insert after first <br> tag
            var firstBr = billToAddress.querySelector('br');
            
            if (firstBr) {
                console.log('Found first <br> tag - inserting after it');
                
                // Create the school info HTML - all on ONE LINE
                var schoolDiv = document.createElement('span');
                schoolDiv.className = 'omni-school-info';
                
                var schoolSpan = document.createElement('span');
                schoolSpan.className = 'omni-school-name';
                schoolSpan.textContent = schoolName;
                schoolDiv.appendChild(schoolSpan);
                
                if (className && className !== '') {
                    // Add separator
                    var separator = document.createElement('span');
                    separator.className = 'omni-separator';
                    separator.textContent = '-';
                    schoolDiv.appendChild(separator);
                    
                    // Add class name
                    var classSpan = document.createElement('span');
                    classSpan.className = 'omni-class-name';
                    classSpan.textContent = className;
                    schoolDiv.appendChild(classSpan);
                }
                
                schoolDiv.appendChild(document.createElement('br'));
                
                // Insert after first BR
                firstBr.parentNode.insertBefore(schoolDiv, firstBr.nextSibling);
                
                console.log('✅ School info injected successfully using BR method');
                return true;
            }
            
            // Method 2: Parse innerHTML and inject
            console.log('No <br> found, trying innerHTML method');
            
            var html = billToAddress.innerHTML;
            var parts = html.split('<br>');
            
            if (parts.length < 2) {
                parts = html.split('<br/>');
            }
            if (parts.length < 2) {
                parts = html.split('<br />');
            }
            
            console.log('Split into', parts.length, 'parts');
            
            if (parts.length >= 2) {
                // Insert school info after first part (company name)
                var schoolInfo = '<span class="omni-school-info">';
                schoolInfo += '<span class="omni-school-name">' + schoolName + '</span>';
                if (className && className !== '') {
                    schoolInfo += '<span class="omni-separator"> - </span>';
                    schoolInfo += '<span class="omni-class-name">' + className + '</span>';
                }
                schoolInfo += '</span>';
                
                parts.splice(1, 0, schoolInfo);
                billToAddress.innerHTML = parts.join('<br>');
                
                console.log('✅ School info injected successfully using innerHTML method');
                return true;
            }
            
            console.log('❌ Could not inject - no suitable insertion point found');
            return false;
        }
        
        // Run immediately
        injectSchoolClassInfo();
        
        // Run after page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Running on DOMContentLoaded');
                injectSchoolClassInfo();
            });
        }
        
        // Run with delays
        setTimeout(function() {
            console.log('Running after 500ms');
            injectSchoolClassInfo();
        }, 500);
        
        setTimeout(function() {
            console.log('Running after 1000ms');
            injectSchoolClassInfo();
        }, 1000);
        
        setTimeout(function() {
            console.log('Running after 2000ms');
            injectSchoolClassInfo();
        }, 2000);
        
        console.log('=== END OMNI SCHOOL INFO DEBUG ===');
    })();
    </script>
    <?php
}

// ================================================================
// HOOK INTO OMNI SALES INVOICE CREATION
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
        log_activity('Omni Filteration: Could not find invoice for Order #' . $order_id);
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
        log_activity('Omni Filteration: Updated shipping address for Invoice #' . $invoice->id . ' (Order #' . $order_id . ') - Store Pickup');
    }
}

// ================================================================
// INVOICE SHIPPING ADDRESS REPLACEMENT FOR VIEWING
// ================================================================

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

// ================================================================
// SECONDARY HOOKS
// ================================================================

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
            
            if ($CI->db->affected_rows() > 0) {
                log_activity('Omni Filteration: Updated shipping address for Invoice #' . $invoice_id . ' (Standard)');
            }
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
    
    log_activity('Omni Filteration: Bulk sync completed - Updated: ' . $updated_count . ', Skipped: ' . $skipped_count);
    
    return [
        'success' => true,
        'updated' => $updated_count,
        'skipped' => $skipped_count,
        'total' => count($invoices)
    ];
}

// ================================================================
// MODULE INITIALIZATION COMPLETE
// ================================================================

log_activity('Omni Filteration Module: Loaded successfully (v2.3)');