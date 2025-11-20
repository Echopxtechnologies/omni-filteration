<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Table 1: Store Address
if (!$CI->db->table_exists(db_prefix() . 'omni_store_address')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'omni_store_address` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `store_name` VARCHAR(255) NOT NULL,
        `address` TEXT NOT NULL,
        `city` VARCHAR(100) NOT NULL,
        `state` VARCHAR(100) NOT NULL,
        `pincode` VARCHAR(20) NOT NULL,
        `phone` VARCHAR(20) NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// Table 2: Student Delivery Preferences with School Name and Class Name
if (!$CI->db->table_exists(db_prefix() . 'omni_student_delivery')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'omni_student_delivery` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `client_id` INT(11) NOT NULL,
        `contact_id` INT(11) NOT NULL,
        `school_name` VARCHAR(255) NULL COMMENT "Fetched from contacts.title",
        `class_name` VARCHAR(255) NULL COMMENT "Fetched from customer group name",
        `delivery_method` VARCHAR(50) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_client_id` (`client_id`),
        KEY `idx_contact_id` (`contact_id`),
        FOREIGN KEY (`contact_id`) REFERENCES `' . db_prefix() . 'contacts`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
} else {
    // Add columns if they don't exist (for existing installations)
    $columns = $CI->db->field_data(db_prefix() . 'omni_student_delivery');
    $column_names = array_column($columns, 'name');
    
    if (!in_array('school_name', $column_names)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'omni_student_delivery` 
            ADD COLUMN `school_name` VARCHAR(255) NULL COMMENT "Fetched from contacts.title" AFTER `contact_id`');
    }
    
    if (!in_array('class_name', $column_names)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'omni_student_delivery` 
            ADD COLUMN `class_name` VARCHAR(255) NULL COMMENT "Fetched from customer group name" AFTER `school_name`');
    }
}

// Insert dummy store address if not exists
$exists = $CI->db->get(db_prefix() . 'omni_store_address')->num_rows();
if ($exists == 0) {
    $CI->db->insert(db_prefix() . 'omni_store_address', [
        'store_name' => 'Main Store',
        'address' => '#123, MG Road, 2nd Floor',
        'city' => 'Bangalore',
        'state' => 'Karnataka',
        'pincode' => '560001',
        'phone' => '+91-9876543210'
    ]);
}