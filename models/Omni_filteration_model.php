<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Omni_filteration_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get store address (first record)
     * @return object|null
     */
    public function get_address()
    {
        return $this->db->get(db_prefix() . 'omni_store_address')->row();
    }

    /**
     * Add new address
     * @param array $data
     * @return int Insert ID
     */
    public function add_address($data)
    {
        $this->db->insert(db_prefix() . 'omni_store_address', $data);
        return $this->db->insert_id();
    }

    /**
     * Update address
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_address($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'omni_store_address', $data);
    }

    /**
     * Get primary contact with school name (title) for a client
     * @param int $client_id
     * @return object|null
     */
    public function get_client_primary_contact($client_id)
    {
        $this->db->select('id, title, firstname, lastname, email, phonenumber');
        $this->db->where('userid', $client_id);
        $this->db->where('is_primary', 1);
        return $this->db->get(db_prefix() . 'contacts')->row();
    }

    /**
     * Get customer group name (class name) for a client
     * @param int $client_id
     * @return string|null
     */
    public function get_client_group_name($client_id)
    {
        $this->db->select('cg.name as group_name');
        $this->db->from(db_prefix() . 'customer_groups cu_grp');
        $this->db->join(db_prefix() . 'customers_groups cg', 'cg.id = cu_grp.groupid', 'left');
        $this->db->where('cu_grp.customer_id', $client_id);
        $this->db->limit(1);
        
        $result = $this->db->get()->row();
        
        return $result ? $result->group_name : null;
    }

    /**
     * Get complete client delivery information
     * @param int $client_id
     * @return array
     */
    public function get_client_delivery_info($client_id)
    {
        $contact = $this->get_client_primary_contact($client_id);
        $group_name = $this->get_client_group_name($client_id);
        
        return [
            'contact' => $contact,
            'contact_id' => $contact ? $contact->id : null,
            'school_name' => $contact ? $contact->title : null,
            'class_name' => $group_name,
            'client_id' => $client_id
        ];
    }

    /**
     * Save student delivery preference
     * AUTO-FETCHES: school_name and class_name
     * @param array $data
     * @return int|bool
     */
    public function save_student_delivery($data)
    {
        $delivery_info = $this->get_client_delivery_info($data['client_id']);
        
        if (!$delivery_info['contact']) {
            return false;
        }
        
        $insert_data = [
            'client_id' => $data['client_id'],
            'contact_id' => $delivery_info['contact_id'],
            'school_name' => $delivery_info['school_name'],
            'class_name' => $delivery_info['class_name'],
            'delivery_method' => $data['delivery_method']
        ];
        
        $this->db->insert(db_prefix() . 'omni_student_delivery', $insert_data);
        
        if ($this->db->affected_rows() > 0) {
            log_activity('Omni: Saved delivery for client ' . $data['client_id']);
            return $this->db->insert_id();
        }
        
        return false;
    }

    /**
     * Get student delivery by client ID
     * @param int $client_id
     * @return object|null
     */
    public function get_student_delivery_by_client($client_id)
    {
        $this->db->where('client_id', $client_id);
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit(1);
        return $this->db->get(db_prefix() . 'omni_student_delivery')->row();
    }

    /**
     * Get all deliveries (admin view)
     * @return array
     */
    public function get_all_student_deliveries()
    {
        $this->db->select('sd.*, c.firstname, c.lastname, c.email, cl.company');
        $this->db->from(db_prefix() . 'omni_student_delivery sd');
        $this->db->join(db_prefix() . 'contacts c', 'c.id = sd.contact_id', 'left');
        $this->db->join(db_prefix() . 'clients cl', 'cl.userid = sd.client_id', 'left');
        $this->db->order_by('sd.created_at', 'DESC');
        return $this->db->get()->result();
    }
}