<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($title) ? $title : 'Select Delivery'; ?></title>
    <link href="<?php echo base_url('assets/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo base_url('assets/plugins/font-awesome/css/font-awesome.min.css'); ?>" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .radio label { font-size: 16px; padding: 15px; display: block; cursor: pointer; }
        .radio label:hover { background: #f9f9f9; }
    </style>
</head>
<body>

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-shipping-fast"></i> <?php echo $title; ?>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <?php if ($existing_delivery): ?>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> Current Selection: 
                            <strong><?php echo ucwords(str_replace('_', ' ', $existing_delivery->delivery_method)); ?></strong>
                            <br>School: <strong><?php echo $existing_delivery->school_name; ?></strong>
                            <br>Class: <strong><?php echo $existing_delivery->class_name; ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- Client Info Display -->
                    <div class="well" id="client-info-display" style="display:none;">
                        <h4><i class="fa fa-user"></i> Your Information</h4>
                        <p><strong>School Name:</strong> <span id="display-school-name">Loading...</span></p>
                        <p><strong>Class Name:</strong> <span id="display-class-name">Loading...</span></p>
                    </div>

                    <!-- Delivery Method Selection -->
                    <div class="form-group">
                        <label><strong>Select Delivery Method:</strong></label>
                        
                        <div class="radio">
                            <label>
                                <input type="radio" name="delivery_method" value="store_pickup" id="store_pickup" <?php echo ($existing_delivery && $existing_delivery->delivery_method == 'store_pickup') ? 'checked' : ''; ?>>
                                <strong>Store Pickup</strong>
                                <p class="text-muted" style="margin-left: 20px;">Pick up from our store location</p>
                            </label>
                        </div>

                        <div class="radio">
                            <label>
                                <input type="radio" name="delivery_method" value="home_delivery" id="home_delivery" <?php echo ($existing_delivery && $existing_delivery->delivery_method == 'home_delivery') ? 'checked' : ''; ?>>
                                <strong>Home Delivery</strong>
                                <p class="text-muted" style="margin-left: 20px;">We'll deliver to your address</p>
                            </label>
                        </div>
                    </div>

                    <!-- Store Address (shown when store_pickup selected) -->
                    <div id="store-address-section" style="display:none;" class="alert alert-success">
                        <h4><i class="fa fa-map-marker"></i> Store Pickup Address</h4>
                        <?php if ($store_address): ?>
                            <p><strong><?php echo $store_address->store_name; ?></strong></p>
                            <p><?php echo nl2br($store_address->address); ?></p>
                            <p><?php echo $store_address->city; ?>, <?php echo $store_address->state; ?> - <?php echo $store_address->pincode; ?></p>
                            <?php if ($store_address->phone): ?>
                                <p><i class="fa fa-phone"></i> <?php echo $store_address->phone; ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Saving Indicator -->
                    <div id="saving-indicator" style="display:none;" class="alert alert-warning">
                        <i class="fa fa-spinner fa-spin"></i> Saving your selection...
                    </div>

                    <!-- Result Message -->
                    <div id="result-message" style="display:none;" class="mtop15"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url('assets/plugins/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/bootstrap.min.js'); ?>"></script>
<script>
$(document).ready(function() {
    
    // Load client info on page load
    loadClientInfo();
    
    // Show store address if store pickup is already selected
    if ($('#store_pickup').is(':checked')) {
        $('#store-address-section').show();
        $('#client-info-display').show();
    }
    
    // AUTO-SAVE when radio button changes
    $('input[name="delivery_method"]').on('change', function() {
        var deliveryMethod = $(this).val();
        
        // Show/hide store address
        if (deliveryMethod === 'store_pickup') {
            $('#store-address-section').slideDown();
            $('#client-info-display').slideDown();
        } else {
            $('#store-address-section').slideUp();
        }
        
        // Auto-save immediately
        saveDeliveryMethod(deliveryMethod);
    });
    
    // Load client information
    function loadClientInfo() {
        $.ajax({
            url: '<?php echo site_url("omni_filteration/get_client_info"); ?>',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#display-school-name').text(response.data.school_name);
                    $('#display-class-name').text(response.data.class_name);
                }
            }
        });
    }
    
    // Auto-save delivery method
    function saveDeliveryMethod(deliveryMethod) {
        $('#saving-indicator').slideDown();
        $('#result-message').slideUp();
        
        $.ajax({
            url: '<?php echo site_url("omni_filteration/save_delivery_method"); ?>',
            type: 'POST',
            data: {
                delivery_method: deliveryMethod,
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                $('#saving-indicator').slideUp();
                
                if (response.success) {
                    $('#result-message')
                        .removeClass('alert-danger')
                        .addClass('alert alert-success')
                        .html('<i class="fa fa-check-circle"></i> ' + response.message + 
                              '<br><strong>School:</strong> ' + response.data.school_name + 
                              '<br><strong>Class:</strong> ' + response.data.class_name)
                        .slideDown();
                    
                    console.log('Saved:', response.data);
                } else {
                    $('#result-message')
                        .removeClass('alert-success')
                        .addClass('alert alert-danger')
                        .html('<i class="fa fa-times-circle"></i> ' + response.message)
                        .slideDown();
                }
            },
            error: function() {
                $('#saving-indicator').slideUp();
                $('#result-message')
                    .removeClass('alert-success')
                    .addClass('alert alert-danger')
                    .html('<i class="fa fa-times-circle"></i> Error saving')
                    .slideDown();
            }
        });
    }
});
</script>

</body>
</html>