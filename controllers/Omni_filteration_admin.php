<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Omni Filteration Admin Controller
 * Handles all admin-side operations
 */
class Omni_filteration_admin extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('omni_filteration_model');
    }

    /**
     * ADMIN: Manage store address
     * URL: admin/omni_filteration_admin/manage_address
     */
    public function manage_address()
    {
        if (!has_permission('service_maintenance', '', 'view')) {
            access_denied('omni_filteration');
        }

        // Handle form submission
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
                // Update existing address
                if ($this->omni_filteration_model->update_address($address_id, $data)) {
                    set_alert('success', 'Store address updated successfully');
                } else {
                    set_alert('danger', 'Failed to update store address');
                }
            } else {
                // Add new address
                if ($this->omni_filteration_model->add_address($data)) {
                    set_alert('success', 'Store address added successfully');
                } else {
                    set_alert('danger', 'Failed to add store address');
                }
            }

            redirect(admin_url('omni_filteration_admin/manage_address'));
        }

        // Load data for view
        $data['address'] = $this->omni_filteration_model->get_address();
        $data['title'] = 'Manage Store Address';

        // Load view (same pattern as service_maintenance)
        $this->load->view('manage_address', $data);
    }

    /**
     * ADMIN: View all student deliveries
     * URL: admin/omni_filteration_admin/view_deliveries
     */
    public function view_deliveries()
    {
        if (!has_permission('service_maintenance', '', 'view')) {
            access_denied('omni_filteration');
        }

        $data['deliveries'] = $this->omni_filteration_model->get_all_student_deliveries();
        $data['title'] = 'Student Delivery Preferences';

        // Load view
        $this->load->view('view_deliveries', $data);
    }

    /**
     * ADMIN AJAX: Delete a delivery preference
     */
    public function delete_delivery($id)
    {
        if (!has_permission('service_maintenance', '', 'delete')) {
            access_denied('omni_filteration');
        }

        $this->db->where('id', $id);
        if ($this->db->delete(db_prefix() . 'omni_student_delivery')) {
            set_alert('success', 'Delivery preference deleted');
        } else {
            set_alert('danger', 'Failed to delete');
        }

        redirect(admin_url('omni_filteration_admin/view_deliveries'));
    }
}