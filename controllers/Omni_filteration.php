<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Omni_filteration extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('omni_filteration_model');
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
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
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
     * CLIENT AJAX: Save delivery method (CRITICAL FUNCTION)
     * This is called by JavaScript when dropdown selection changes
     */
    public function save_delivery_method()
    {
        // Allow AJAX calls
        header('Content-Type: application/json');
        
        // Get delivery method from POST
        $delivery_method = $this->input->post('delivery_method');
        
        // IMPORTANT: Get client ID from session
        // If client is logged in via omni_sales, use session
        $client_id = null;
        
        // Try method 1: Standard client login
        if (is_client_logged_in()) {
            $client_id = get_client_user_id();
        }
        // Try method 2: Omni sales session
        elseif ($this->session->userdata('client_user_id')) {
            $client_id = $this->session->userdata('client_user_id');
        }
        // Try method 3: Posted client_id
        elseif ($this->input->post('client_id')) {
            $client_id = $this->input->post('client_id');
        }
        
        // Validate client ID
        if (empty($client_id)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Client not logged in',
                'debug' => [
                    'is_client_logged_in' => is_client_logged_in(),
                    'session_client_id' => $this->session->userdata('client_user_id'),
                    'post_data' => $_POST
                ]
            ]);
            return;
        }
        
        // Validate delivery method
        if (empty($delivery_method)) {
            echo json_encode(['success' => false, 'message' => 'Please select a delivery method']);
            return;
        }
        
        $allowed_methods = ['store_pickup', 'home_delivery'];
        if (!in_array($delivery_method, $allowed_methods)) {
            echo json_encode(['success' => false, 'message' => 'Invalid delivery method']);
            return;
        }
        
        // Delete old entry for this client
        $this->db->where('client_id', $client_id);
        $this->db->delete(db_prefix() . 'omni_student_delivery');
        
        // Save new entry
        $data = [
            'client_id' => $client_id,
            'delivery_method' => $delivery_method
        ];
        
        $insert_id = $this->omni_filteration_model->save_student_delivery($data);
        
        if ($insert_id) {
            $saved_data = $this->omni_filteration_model->get_client_delivery_info($client_id);
            
            log_activity('Omni Filteration: Saved delivery method for Client #' . $client_id . ' - ' . $delivery_method);
            
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
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to save delivery method',
                'debug' => [
                    'client_id' => $client_id,
                    'delivery_method' => $delivery_method
                ]
            ]);
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