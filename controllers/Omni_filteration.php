<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Omni_filteration extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('omni_filteration_model');
        
        // Register hook to replace ONLY shipping address in invoices (NOT billing)
        hooks()->add_filter('invoice_html_pdf_data', [$this, 'replace_shipping_address_only']);
    }

    /**
     * HOOK: Replace ONLY shipping address with store address (NEVER touch billing)
     * This modifies invoice shipping address before PDF generation
     */
    public function replace_shipping_address_only($invoice_data)
    {
        // Check if invoice exists
        if (!isset($invoice_data['invoice']) || empty($invoice_data['invoice'])) {
            return $invoice_data;
        }
        
        $invoice = $invoice_data['invoice'];
        $client_id = $invoice->clientid;
        
        // Get client's delivery preference
        $delivery = $this->omni_filteration_model->get_student_delivery_by_client($client_id);
        
        // ONLY if store pickup is selected, replace SHIPPING address (NOT billing)
        if ($delivery && $delivery->delivery_method === 'store_pickup') {
            
            // Get store address
            $store_address = $this->omni_filteration_model->get_address();
            
            if ($store_address) {
                // ✅ REPLACE ONLY SHIPPING ADDRESS FIELDS (billing untouched)
                $invoice->shipping_street = $store_address->address;
                $invoice->shipping_city = $store_address->city;
                $invoice->shipping_state = $store_address->state;
                $invoice->shipping_zip = $store_address->pincode;
                $invoice->shipping_country = 102; // India
                
                // ❌ DO NOT TOUCH BILLING ADDRESS
                // $invoice->billing_street = unchanged
                // $invoice->billing_city = unchanged
                // $invoice->billing_state = unchanged
                // $invoice->billing_zip = unchanged
                // $invoice->billing_country = unchanged
                
                // Update the invoice data
                $invoice_data['invoice'] = $invoice;
                
                log_activity('Omni: Replaced ONLY shipping address with store address for invoice #' . $invoice->id);
            }
        }
        
        return $invoice_data;
    }

    /**
     * CLIENT: Display delivery selection page
     */
    public function select_delivery()
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $client_id = get_client_user_id();
        
        $data['store_address'] = $this->omni_filteration_model->get_address();
        $data['client_info'] = $this->omni_filteration_model->get_client_delivery_info($client_id);
        $data['existing_delivery'] = $this->omni_filteration_model->get_student_delivery_by_client($client_id);
        $data['title'] = 'Select Delivery Method';
        
        $this->load->view('select_delivery', $data);
    }

    /**
     * CLIENT AJAX: Get client info
     */
    public function get_client_info()
    {
        header('Content-Type: application/json');
        
        if (!is_client_logged_in()) {
            echo json_encode(['success' => false, 'message' => 'Client not logged in']);
            return;
        }
        
        $client_id = get_client_user_id();
        $client_info = $this->omni_filteration_model->get_client_delivery_info($client_id);
        
        if ($client_info['contact']) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'client_id' => $client_info['client_id'],
                    'contact_id' => $client_info['contact_id'],
                    'school_name' => $client_info['school_name'] ?: 'Not Set',
                    'class_name' => $client_info['class_name'] ?: 'Not Assigned',
                    'contact_name' => $client_info['contact']->firstname . ' ' . $client_info['contact']->lastname
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Primary contact not found']);
        }
    }

    /**
     * CLIENT AJAX: Save delivery method (AUTO-SAVE)
     */
    public function save_delivery_method()
    {
        header('Content-Type: application/json');
        
        if (!is_client_logged_in()) {
            echo json_encode(['success' => false, 'message' => 'Client not logged in']);
            return;
        }
        
        $delivery_method = $this->input->post('delivery_method');
        $client_id = get_client_user_id();
        
        if (empty($delivery_method)) {
            echo json_encode(['success' => false, 'message' => 'Please select a delivery method']);
            return;
        }
        
        $allowed_methods = ['store_pickup', 'home_delivery'];
        if (!in_array($delivery_method, $allowed_methods)) {
            echo json_encode(['success' => false, 'message' => 'Invalid delivery method']);
            return;
        }
        
        // Delete old entry (REPLACE functionality)
        $this->db->where('client_id', $client_id);
        $this->db->delete(db_prefix() . 'omni_student_delivery');
        
        // Save new entry
        $data = ['client_id' => $client_id, 'delivery_method' => $delivery_method];
        $insert_id = $this->omni_filteration_model->save_student_delivery($data);
        
        if ($insert_id) {
            $saved_data = $this->omni_filteration_model->get_client_delivery_info($client_id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Delivery method saved successfully',
                'data' => [
                    'id' => $insert_id,
                    'client_id' => $client_id,
                    'school_name' => $saved_data['school_name'],
                    'class_name' => $saved_data['class_name'],
                    'delivery_method' => $delivery_method
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save delivery method']);
        }
    }

    /**
     * ADMIN: Manage store address
     */
    public function manage_address()
    {
        if (!is_admin()) {
            access_denied('Omni Filteration');
        }

        if ($this->input->post()) {
            $data = [
                'store_name' => $this->input->post('store_name'),
                'address' => $this->input->post('address'),
                'city' => $this->input->post('city'),
                'state' => $this->input->post('state'),
                'pincode' => $this->input->post('pincode'),
                'phone' => $this->input->post('phone')
            ];

            $address_id = $this->input->post('address_id');

            if ($address_id) {
                if ($this->omni_filteration_model->update_address($address_id, $data)) {
                    set_alert('success', 'Store address updated successfully');
                } else {
                    set_alert('danger', 'Failed to update');
                }
            } else {
                if ($this->omni_filteration_model->add_address($data)) {
                    set_alert('success', 'Store address added successfully');
                } else {
                    set_alert('danger', 'Failed to add');
                }
            }

            redirect(admin_url('omni_filteration/manage_address'));
        }

        $data['address'] = $this->omni_filteration_model->get_address();
        $data['title'] = 'Manage Store Address';

        $this->load->view('manage_address', $data);
    }

    /**
     * ADMIN: View all deliveries
     */
    public function view_deliveries()
    {
        if (!is_admin()) {
            access_denied('Omni Filteration');
        }

        $data['deliveries'] = $this->omni_filteration_model->get_all_student_deliveries();
        $data['title'] = 'Student Deliveries';

        $this->load->view('view_deliveries', $data);
    }
}