<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                
                <!-- Page Header -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-map-marker"></i> Manage Store Address
                        </h4>
                        <hr class="hr-panel-heading">
                        
                        <!-- Address Form -->
                        <?php echo form_open(admin_url('omni_filteration/manage_address'), ['id' => 'address-form']); ?>
                        
                        <!-- Hidden ID field for update -->
                        <?php if ($address): ?>
                            <input type="hidden" name="address_id" value="<?php echo $address->id; ?>">
                        <?php endif; ?>
                        
                        <!-- Store Name -->
                        <div class="form-group">
                            <label for="store_name" class="control-label">
                                <span class="text-danger">*</span> Store Name
                            </label>
                            <input 
                                type="text" 
                                id="store_name" 
                                name="store_name" 
                                class="form-control" 
                                value="<?php echo $address ? $address->store_name : ''; ?>"
                                required
                                placeholder="e.g., Main Store, Branch Office"
                            >
                        </div>

                        <!-- Address -->
                        <div class="form-group">
                            <label for="address" class="control-label">
                                <span class="text-danger">*</span> Street Address
                            </label>
                            <textarea 
                                id="address" 
                                name="address" 
                                class="form-control" 
                                rows="3"
                                required
                                placeholder="Enter complete street address"
                            ><?php echo $address ? $address->address : ''; ?></textarea>
                            <small class="help-block">Building number, street name, floor, etc.</small>
                        </div>

                        <!-- City and State (2 columns) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="city" class="control-label">
                                        <span class="text-danger">*</span> City
                                    </label>
                                    <input 
                                        type="text" 
                                        id="city" 
                                        name="city" 
                                        class="form-control" 
                                        value="<?php echo $address ? $address->city : ''; ?>"
                                        required
                                        placeholder="e.g., Bangalore"
                                    >
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="state" class="control-label">
                                        <span class="text-danger">*</span> State
                                    </label>
                                    <input 
                                        type="text" 
                                        id="state" 
                                        name="state" 
                                        class="form-control" 
                                        value="<?php echo $address ? $address->state : ''; ?>"
                                        required
                                        placeholder="e.g., Karnataka"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Pincode and Phone (2 columns) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pincode" class="control-label">
                                        <span class="text-danger">*</span> Pincode / ZIP Code
                                    </label>
                                    <input 
                                        type="text" 
                                        id="pincode" 
                                        name="pincode" 
                                        class="form-control" 
                                        value="<?php echo $address ? $address->pincode : ''; ?>"
                                        required
                                        placeholder="e.g., 560001"
                                        maxlength="10"
                                    >
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="control-label">
                                        Phone Number
                                    </label>
                                    <input 
                                        type="text" 
                                        id="phone" 
                                        name="phone" 
                                        class="form-control" 
                                        value="<?php echo $address ? $address->phone : ''; ?>"
                                        placeholder="e.g., +91-9876543210"
                                    >
                                    <small class="help-block">Optional contact number</small>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-check"></i> 
                                <?php echo $address ? 'Update Address' : 'Add Address'; ?>
                            </button>
                        </div>
                        
                        <?php echo form_close(); ?>
                        
                    </div>
                </div>

                <!-- Current Address Preview (if exists) -->
                <?php if ($address): ?>
                    <div class="panel_s mtop15">
                        <div class="panel-body">
                            <h4 class="no-margin">
                                <i class="fa fa-eye"></i> Current Store Address
                            </h4>
                            <hr class="hr-panel-heading">
                            
                            <div class="alert alert-info">
                                <h4><strong><?php echo $address->store_name; ?></strong></h4>
                                <p class="mbot10">
                                    <?php echo nl2br($address->address); ?>
                                </p>
                                <p class="mbot10">
                                    <i class="fa fa-map-marker"></i> 
                                    <?php echo $address->city; ?>, <?php echo $address->state; ?> - <?php echo $address->pincode; ?>
                                </p>
                                <?php if ($address->phone): ?>
                                    <p class="mbot0">
                                        <i class="fa fa-phone"></i> <?php echo $address->phone; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-muted">
                                <i class="fa fa-info-circle"></i> 
                                This address will be shown to clients when they select "Store Pickup" delivery method.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Form Validation Script -->
<script>
$(document).ready(function() {
    
    // Form validation
    $('#address-form').on('submit', function(e) {
        var isValid = true;
        var errorMessage = '';
        
        // Validate required fields
        if ($.trim($('#store_name').val()) === '') {
            isValid = false;
            errorMessage += 'Store Name is required.\n';
        }
        
        if ($.trim($('#address').val()) === '') {
            isValid = false;
            errorMessage += 'Address is required.\n';
        }
        
        if ($.trim($('#city').val()) === '') {
            isValid = false;
            errorMessage += 'City is required.\n';
        }
        
        if ($.trim($('#state').val()) === '') {
            isValid = false;
            errorMessage += 'State is required.\n';
        }
        
        if ($.trim($('#pincode').val()) === '') {
            isValid = false;
            errorMessage += 'Pincode is required.\n';
        }
        
        // Validate pincode format (alphanumeric, 4-10 chars)
        var pincode = $.trim($('#pincode').val());
        if (pincode && !/^[a-zA-Z0-9]{4,10}$/.test(pincode)) {
            isValid = false;
            errorMessage += 'Invalid pincode format.\n';
        }
        
        // Show validation errors
        if (!isValid) {
            alert(errorMessage);
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Auto-format pincode (uppercase)
    $('#pincode').on('blur', function() {
        $(this).val($(this).val().toUpperCase());
    });
});
</script>

<?php init_tail(); ?>