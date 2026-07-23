-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 23, 2026 at 09:20 AM
-- Server version: 10.11.18-MariaDB-cll-lve
-- PHP Version: 8.2.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vantage_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_programs`
--

CREATE TABLE `academic_programs` (
  `id` int(11) NOT NULL,
  `program_type` enum('certificate','diploma') NOT NULL,
  `title` varchar(500) NOT NULL,
  `image_url` text DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `mode` varchar(255) DEFAULT NULL,
  `entry_requirements` text DEFAULT NULL,
  `fee` varchar(255) DEFAULT NULL,
  `certification` text DEFAULT NULL,
  `registration_link` varchar(500) DEFAULT '#',
  `market_problem` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `who_for` text DEFAULT NULL,
  `what_different` text DEFAULT NULL,
  `trainer_profile` text DEFAULT NULL,
  `fees_intakes` text DEFAULT NULL,
  `governing_body_name` varchar(100) DEFAULT NULL,
  `governing_body_full_name` varchar(500) DEFAULT NULL,
  `governing_body_logo_url` text DEFAULT NULL,
  `issuing_institution` text DEFAULT NULL,
  `accreditation_basis` text DEFAULT NULL,
  `professional_alignment` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `id` int(11) NOT NULL,
  `activity_code` varchar(255) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `activity_name` text NOT NULL,
  `cost_center` varchar(255) NOT NULL,
  `activity_type` varchar(255) NOT NULL,
  `current_status` varchar(255) NOT NULL,
  `expected_output` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `risk_analysis` text NOT NULL,
  `output_indicators` text NOT NULL,
  `indicator_type` varchar(255) NOT NULL,
  `target_qualitative` text DEFAULT NULL,
  `target_quantitative` decimal(10,2) DEFAULT NULL,
  `activity_location` varchar(255) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `weight` decimal(10,2) NOT NULL,
  `cost_estimate` decimal(15,2) NOT NULL,
  `cost_class` varchar(255) NOT NULL,
  `departmental_personnel` text NOT NULL,
  `inter_departmental_personnel` text NOT NULL,
  `other_departmental_inputs` text NOT NULL,
  `other_inter_departmental_inputs` text NOT NULL,
  `external_inputs` text NOT NULL,
  `responsible_department` varchar(255) NOT NULL,
  `responsible` text NOT NULL,
  `activity_status` varchar(255) NOT NULL,
  `assumptions` text NOT NULL,
  `comments` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_type`
--

CREATE TABLE `activity_type` (
  `id` int(11) NOT NULL,
  `act_type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `text_description` mediumtext NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `reminder_date` varchar(500) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `email` varchar(500) NOT NULL,
  `link` varchar(500) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `allowance_types`
--

CREATE TABLE `allowance_types` (
  `id` int(11) NOT NULL,
  `allowance_code` varchar(30) NOT NULL,
  `allowance_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_taxable` tinyint(1) DEFAULT 1 COMMENT '1=Taxable, 0=Non-taxable',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application`
--

CREATE TABLE `application` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `cover_letter` text NOT NULL,
  `position_id` int(11) NOT NULL,
  `resume_path` text NOT NULL,
  `process_id` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=for review\r\n',
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `token` varchar(200) DEFAULT NULL,
  `confirmation` varchar(500) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `amount` varchar(50) DEFAULT NULL,
  `resume` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_data_training`
--

CREATE TABLE `application_data_training` (
  `id` int(11) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `organization` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `training_loc` varchar(100) NOT NULL,
  `form_title` varchar(255) NOT NULL,
  `token` varchar(500) DEFAULT NULL,
  `entry_time` datetime NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(32) NOT NULL COMMENT 'Which K40, e.g. K40-GATE',
  `device_user_id` varchar(32) NOT NULL COMMENT 'User ID stored on the device',
  `staff_id` varchar(32) DEFAULT NULL COMMENT 'Mapped VASL-STF-0001 (from device_user_map)',
  `punch_time` datetime NOT NULL COMMENT 'When the punch happened',
  `punch_type` varchar(16) DEFAULT NULL COMMENT 'check-in/out/etc (device-dependent)',
  `status` int(11) DEFAULT NULL COMMENT 'Raw verify/status code from device',
  `source` varchar(16) NOT NULL DEFAULT 'k40',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget`
--

CREATE TABLE `budget` (
  `id` int(11) NOT NULL,
  `record_code` varchar(255) DEFAULT NULL,
  `budget_code` varchar(255) DEFAULT NULL,
  `amount` varchar(255) DEFAULT NULL,
  `staff` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `evidence` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_audit_log`
--

CREATE TABLE `commission_audit_log` (
  `id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'settings_updated, intake_assigned, event_assigned, commission_calculated, approved, rejected, paid',
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'intake, event, commission_record, settings',
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_by_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_records`
--

CREATE TABLE `commission_records` (
  `id` int(11) NOT NULL,
  `commission_type` enum('virtual','international') NOT NULL,
  `source_id` int(11) NOT NULL COMMENT 'intake.id or Event.event_id',
  `source_name` varchar(255) DEFAULT NULL COMMENT 'Intake/Event name for reference',
  `staff_user_id` int(11) NOT NULL COMMENT 'registered_users.id',
  `staff_name` varchar(255) DEFAULT NULL,
  `minimum_clients_required` int(11) DEFAULT 0,
  `fee_collection_threshold` decimal(5,2) DEFAULT 0.00 COMMENT 'Required % (80 or 90)',
  `client_payment_threshold` decimal(5,2) DEFAULT 0.00 COMMENT 'Required client payment % (80 or 100)',
  `total_registered` int(11) DEFAULT 0 COMMENT 'Total clients registered',
  `qualifying_clients` int(11) DEFAULT 0 COMMENT 'Clients who paid required threshold',
  `total_expected_fees` decimal(12,2) DEFAULT 0.00,
  `total_collected_fees` decimal(12,2) DEFAULT 0.00,
  `fee_collection_percentage` decimal(5,2) DEFAULT 0.00,
  `unit_fee` decimal(12,2) DEFAULT 0.00 COMMENT 'Course price_usd or Event early_amount',
  `commission_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Commission % (e.g., 10.00)',
  `commission_per_client` decimal(12,2) DEFAULT 0.00 COMMENT 'unit_fee * commission_rate / 100',
  `commission_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Final calculated commission (0 if not eligible)',
  `commission_currency` varchar(10) DEFAULT 'USD',
  `min_clients_met` tinyint(1) DEFAULT 0,
  `fee_collection_met` tinyint(1) DEFAULT 0,
  `is_eligible` tinyint(1) DEFAULT 0 COMMENT '1 if both conditions met',
  `eligibility_notes` text DEFAULT NULL COMMENT 'Explanation of eligibility',
  `status` enum('draft','pending_approval','approved','rejected','paid') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `payroll_id` int(11) DEFAULT NULL COMMENT 'Link to payroll record when paid',
  `paid_at` datetime DEFAULT NULL,
  `period_start` date DEFAULT NULL COMMENT 'Intake/Event start date',
  `period_end` date DEFAULT NULL COMMENT 'Intake/Event end date',
  `calculated_at` datetime DEFAULT current_timestamp(),
  `calculated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_settings`
--

CREATE TABLE `commission_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CompanyNomination`
--

CREATE TABLE `CompanyNomination` (
  `id` int(10) UNSIGNED NOT NULL,
  `nominator_name` varchar(255) NOT NULL,
  `nominator_email` varchar(255) NOT NULL,
  `nominator_phone` varchar(50) NOT NULL,
  `nominator_organization` varchar(255) DEFAULT NULL,
  `org_name` varchar(255) NOT NULL,
  `org_country` varchar(100) NOT NULL,
  `org_sector` varchar(100) NOT NULL,
  `org_size` varchar(50) DEFAULT NULL,
  `org_contact_name` varchar(255) DEFAULT NULL,
  `org_contact_email` varchar(255) DEFAULT NULL,
  `org_contact_phone` varchar(50) DEFAULT NULL,
  `corporate_program_event_id` int(11) NOT NULL,
  `additional_comments` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CompanyNominationStaff`
--

CREATE TABLE `CompanyNominationStaff` (
  `id` int(10) UNSIGNED NOT NULL,
  `company_nomination_id` int(10) UNSIGNED NOT NULL,
  `staff_name` varchar(255) NOT NULL,
  `staff_email` varchar(255) NOT NULL,
  `staff_phone` varchar(50) DEFAULT NULL,
  `staff_role` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `id` int(11) NOT NULL,
  `course_id` varchar(100) NOT NULL,
  `course_id_new` varchar(20) DEFAULT NULL,
  `module_id` varchar(20) DEFAULT NULL,
  `course` varchar(500) NOT NULL,
  `price_usd` varchar(60) NOT NULL,
  `close_date` varchar(60) DEFAULT NULL,
  `study_type` int(11) NOT NULL DEFAULT 1,
  `status` int(11) NOT NULL DEFAULT 1,
  `resource_id` int(11) DEFAULT NULL,
  `assigned_to` varchar(10000) NOT NULL DEFAULT '1',
  `shortname` varchar(200) NOT NULL DEFAULT 'RM',
  `intro_video` varchar(200) DEFAULT NULL,
  `testmonial_link` varchar(500) DEFAULT NULL,
  `writeup` mediumtext DEFAULT NULL,
  `adm_letter` mediumtext DEFAULT NULL,
  `content_sections` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_config`
--

CREATE TABLE `course_config` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `course_description` varchar(1000) NOT NULL,
  `course_intro` mediumtext DEFAULT NULL,
  `intro_link` varchar(200) DEFAULT NULL,
  `course_pic` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_tm_tasks`
--

CREATE TABLE `crm_tm_tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `status` enum('pending','in_progress','on_hold','pending_approval','completed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `created_by` int(10) UNSIGNED NOT NULL,
  `assigned_to_user_id` int(10) UNSIGNED NOT NULL,
  `requesting_user_id` int(10) UNSIGNED DEFAULT NULL,
  `hod_owner_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `cross_department_flag` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `progress_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `needs_support` tinyint(1) NOT NULL DEFAULT 0,
  `support_summary` text DEFAULT NULL,
  `last_feedback_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_tm_task_requirements`
--

CREATE TABLE `crm_tm_task_requirements` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `requested_by_user_id` int(10) UNSIGNED NOT NULL,
  `requirement_text` text NOT NULL,
  `status` enum('open','provided','declined') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_tm_task_sequence`
--

CREATE TABLE `crm_tm_task_sequence` (
  `year` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_tm_task_updates`
--

CREATE TABLE `crm_tm_task_updates` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `update_type` enum('comment','status_change','support','hod_note') NOT NULL DEFAULT 'comment',
  `progress_pct` tinyint(3) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currency`
--

CREATE TABLE `currency` (
  `id` int(11) NOT NULL,
  `currency` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_income`
--

CREATE TABLE `custom_income` (
  `income_id` int(11) NOT NULL,
  `income_source` varchar(255) NOT NULL COMMENT 'Source of income (e.g., Consulting, Donations, Partnerships)',
  `amount` decimal(10,2) NOT NULL COMMENT 'Income amount in USD',
  `income_date` datetime NOT NULL COMMENT 'Date when income was received',
  `description` text DEFAULT NULL COMMENT 'Description of the income',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'Transaction/Reference number',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'Payment method (e.g., Bank Transfer, Check, PayPal)',
  `received_by` varchar(100) DEFAULT NULL COMMENT 'Staff member who recorded this',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Custom income from sources other than courses and events';

-- --------------------------------------------------------

--
-- Table structure for table `deductions`
--

CREATE TABLE `deductions` (
  `id` int(11) NOT NULL,
  `deduction_code` varchar(20) NOT NULL,
  `deduction_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL COMMENT 'Standard percentage if applicable',
  `is_mandatory` tinyint(1) DEFAULT 0 COMMENT '1=Mandatory, 0=Voluntary',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_id` varchar(20) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_head` int(11) DEFAULT NULL COMMENT 'References registered_users.id',
  `status` tinyint(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `device_user_map`
--

CREATE TABLE `device_user_map` (
  `id` int(10) UNSIGNED NOT NULL,
  `device_id` varchar(32) NOT NULL COMMENT 'e.g. K40-GATE',
  `device_user_id` varchar(32) NOT NULL COMMENT 'ID assigned on THAT device',
  `staff_id` varchar(32) NOT NULL COMMENT 'VASL-STF-xxxx (from staff.staff_id)',
  `staff_table_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'staff.id (numeric PK) for joins',
  `full_name` varchar(128) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dpo_payment`
--

CREATE TABLE `dpo_payment` (
  `special_id` varchar(500) NOT NULL,
  `token` varchar(500) NOT NULL,
  `email` varchar(500) NOT NULL,
  `TransactionAmount` double NOT NULL,
  `purpose` varchar(200) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 4,
  `datee` varchar(200) NOT NULL,
  `app_id` varchar(500) DEFAULT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `discount_type` varchar(20) DEFAULT NULL COMMENT 'percentage or fixed',
  `discount_value` decimal(10,2) DEFAULT NULL COMMENT 'Original discount value entered',
  `currency_original` varchar(10) DEFAULT 'USD',
  `amount_original` decimal(10,2) DEFAULT NULL COMMENT 'Original amount in original currency',
  `package` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emails`
--

CREATE TABLE `emails` (
  `email` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `source_type` enum('register','ticket_congress','enquiry','other') NOT NULL,
  `source_id` varchar(100) NOT NULL COMMENT 'entry_id, ticket_id, or enquiry_ref',
  `record_id` int(11) DEFAULT NULL COMMENT 'Primary key ID from source table',
  `email_type` enum('welcome','admission_letter','invoice','receipt','reminder','followup','custom','moodle_credentials') NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `has_attachments` tinyint(1) DEFAULT 0,
  `attachment_paths` text DEFAULT NULL COMMENT 'JSON array of file paths',
  `status` enum('sent','failed','pending','queued') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_by` int(11) DEFAULT NULL COMMENT 'User ID who triggered the send (NULL for auto)',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_schedules`
--

CREATE TABLE `email_schedules` (
  `id` int(11) NOT NULL,
  `email_type` enum('virtual','international') NOT NULL DEFAULT 'virtual',
  `target_id` int(11) NOT NULL COMMENT 'course_id for virtual, event_id for international',
  `target_name` varchar(255) NOT NULL COMMENT 'Course or Event name for display',
  `email_template_id` int(11) NOT NULL COMMENT 'ID from system_emails1 table',
  `email_number` int(11) NOT NULL COMMENT 'Email number (1-18)',
  `payment_filter` enum('all','paid','unpaid') NOT NULL DEFAULT 'all',
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT '08:00:00',
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `total_recipients` int(11) DEFAULT 0,
  `sent_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_schedule_logs`
--

CREATE TABLE `email_schedule_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `source_table` varchar(50) NOT NULL COMMENT 'register or ticket_congress',
  `source_id` varchar(50) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `engament_comment`
--

CREATE TABLE `engament_comment` (
  `id` int(11) NOT NULL,
  `description` varchar(10000) NOT NULL,
  `staff_name` varchar(500) NOT NULL,
  `date_sent` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL,
  `enquiry_ref` varchar(20) DEFAULT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `interest_type` enum('virtual','international','undecided') DEFAULT 'undecided',
  `program_interest` int(11) DEFAULT NULL COMMENT 'Course ID if virtual',
  `event_interest` int(11) DEFAULT NULL COMMENT 'Event ID if international',
  `priority` enum('high','medium','low') DEFAULT 'medium',
  `status` enum('new','contacted','qualified','proposal_sent','negotiating','converted','lost') DEFAULT 'new',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'FK to registered_users.id',
  `notes` text DEFAULT NULL,
  `converted_to` enum('register','ticket_congress') DEFAULT NULL,
  `converted_id` varchar(50) DEFAULT NULL COMMENT 'entry_id or ticket_id after conversion',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Triggers `enquiries`
--
DELIMITER $$
CREATE TRIGGER `before_enquiry_insert` BEFORE INSERT ON `enquiries` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    DECLARE year_str VARCHAR(4);
    
    SET year_str = YEAR(CURRENT_DATE);
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(enquiry_ref, 10) AS UNSIGNED)), 0) + 1 
    INTO next_num
    FROM enquiries 
    WHERE enquiry_ref LIKE CONCAT('ENQ-', year_str, '-%');
    
    SET NEW.enquiry_ref = CONCAT('ENQ-', year_str, '-', LPAD(next_num, 5, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_flags`
--

CREATE TABLE `enquiry_flags` (
  `id` int(11) NOT NULL,
  `enquiry_type` enum('enquiry','register','ticket_congress') NOT NULL,
  `enquiry_id` varchar(50) NOT NULL,
  `flag_type` enum('high_potential','urgent','vip','needs_attention','cold_lead') NOT NULL,
  `flagged_by` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_followups`
--

CREATE TABLE `enquiry_followups` (
  `id` int(11) NOT NULL,
  `enquiry_type` enum('enquiry','register','ticket_congress') NOT NULL,
  `enquiry_id` varchar(50) NOT NULL COMMENT 'id, entry_id, or ticket_id',
  `staff_id` int(11) DEFAULT NULL,
  `action_taken` text DEFAULT NULL COMMENT 'What was done',
  `client_response` text DEFAULT NULL COMMENT 'What client said',
  `next_step` varchar(255) NOT NULL COMMENT 'Next action required - MANDATORY',
  `reminder_date` date NOT NULL COMMENT 'When to follow up',
  `reminder_time` time DEFAULT '09:00:00',
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_notifications`
--

CREATE TABLE `enquiry_notifications` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL COMMENT 'NULL means all staff',
  `notification_type` enum('followup_due','followup_overdue','new_enquiry','high_potential') NOT NULL,
  `enquiry_type` enum('enquiry','register','ticket_congress') DEFAULT NULL,
  `enquiry_id` varchar(50) DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_sources`
--

CREATE TABLE `enquiry_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'bi-question-circle',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Event`
--

CREATE TABLE `Event` (
  `event_id` int(11) NOT NULL,
  `poster_image` varchar(1000) DEFAULT NULL,
  `event_title` varchar(1000) DEFAULT NULL,
  `event_description` mediumtext DEFAULT NULL,
  `start_on` varchar(100) DEFAULT NULL,
  `end_on` varchar(100) DEFAULT NULL,
  `location` varchar(1000) DEFAULT NULL,
  `host` varchar(100) DEFAULT NULL,
  `early_start_on` varchar(100) DEFAULT NULL,
  `early_end_on` varchar(100) DEFAULT NULL,
  `early_amount` varchar(100) DEFAULT NULL,
  `advance_start_on` varchar(100) DEFAULT NULL,
  `advance_end_on` varchar(100) DEFAULT NULL,
  `advance_amount` varchar(100) DEFAULT NULL,
  `gate_start_on` varchar(100) DEFAULT NULL,
  `gate_end_on` varchar(100) DEFAULT NULL,
  `gate_amount` varchar(100) DEFAULT NULL,
  `currency_code` varchar(100) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `rate` varchar(60) DEFAULT NULL,
  `flag_flier` text DEFAULT NULL,
  `simple_writeup` mediumtext DEFAULT NULL,
  `youtube_link` text DEFAULT NULL,
  `testimonial_video_link` text DEFAULT NULL,
  `training_schedule` text DEFAULT NULL,
  `training_gallery` mediumtext DEFAULT NULL,
  `lead_form_id` int(5) DEFAULT NULL,
  `cohort_data` mediumtext DEFAULT NULL,
  `assigned_to` varchar(200) NOT NULL DEFAULT '6,',
  `minimum_clients` int(11) DEFAULT 0,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `commission_status` enum('active','closed','paid') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_config`
--

CREATE TABLE `event_config` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `lead_form_id` int(11) DEFAULT NULL,
  `coordinator_name` varchar(255) DEFAULT NULL,
  `coordinator_whatsapp` varchar(20) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `training_registration_link` text DEFAULT NULL,
  `program_details_link` text DEFAULT NULL,
  `training_schedule_link` text DEFAULT NULL,
  `testimonial_video_link` text DEFAULT NULL,
  `free_session_link` text DEFAULT NULL,
  `bonus_session_link` text DEFAULT NULL,
  `whatsapp_group_link` text DEFAULT NULL,
  `registration_fee` varchar(100) DEFAULT NULL,
  `training_venue` varchar(255) DEFAULT NULL,
  `start_time` varchar(50) DEFAULT NULL,
  `end_time` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL COMMENT 'Expense category (e.g., Salaries, Rent, Marketing)',
  `expense_name` varchar(255) NOT NULL COMMENT 'Name/title of the expense',
  `amount` decimal(10,2) NOT NULL COMMENT 'Expense amount in USD',
  `expense_date` datetime NOT NULL COMMENT 'Date when expense was incurred',
  `description` text NOT NULL COMMENT 'Description of the expense',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'How the payment was made',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'Invoice/Receipt/Reference number',
  `vendor_supplier` varchar(255) DEFAULT NULL COMMENT 'Vendor or supplier name',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `recorded_by` varchar(100) DEFAULT NULL COMMENT 'Staff member who recorded this',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Business expenses tracking';

-- --------------------------------------------------------

--
-- Table structure for table `file_uploads`
--

CREATE TABLE `file_uploads` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `free_sessions`
--

CREATE TABLE `free_sessions` (
  `id` int(11) NOT NULL,
  `session_type` enum('virtual','international') NOT NULL DEFAULT 'international',
  `title` varchar(500) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `short_description` text DEFAULT NULL,
  `full_description` longtext DEFAULT NULL,
  `poster_image` text DEFAULT NULL,
  `hero_badge` varchar(255) DEFAULT 'Free Session',
  `mode_label` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `timezone_label` varchar(255) DEFAULT NULL,
  `start_on` datetime DEFAULT NULL,
  `end_on` datetime DEFAULT NULL,
  `registration_label` varchar(255) DEFAULT NULL,
  `registration_cta_note` text DEFAULT NULL,
  `virtual_cta_label` varchar(255) DEFAULT NULL,
  `virtual_cta_link` varchar(700) DEFAULT NULL,
  `event_reference_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','draft') NOT NULL DEFAULT 'active',
  `preview_media_type` varchar(10) NOT NULL DEFAULT 'poster',
  `preview_video_link` varchar(500) DEFAULT NULL,
  `testimonial_video_link` varchar(500) DEFAULT NULL,
  `schedule_file` varchar(255) DEFAULT NULL,
  `share_image` varchar(255) DEFAULT NULL,
  `gallery_images` longtext DEFAULT NULL,
  `section_visibility` longtext DEFAULT NULL,
  `trainer_image` varchar(255) DEFAULT NULL,
  `trainer_description` text DEFAULT NULL,
  `zoom_topic` varchar(255) DEFAULT NULL,
  `zoom_date` varchar(120) DEFAULT NULL,
  `zoom_time` varchar(120) DEFAULT NULL,
  `zoom_link` varchar(700) DEFAULT NULL,
  `zoom_meeting_id` varchar(100) DEFAULT NULL,
  `zoom_passcode` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `free_session_highlights`
--

CREATE TABLE `free_session_highlights` (
  `id` int(11) NOT NULL,
  `free_session_id` int(11) NOT NULL,
  `highlight_text` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `free_session_outcomes`
--

CREATE TABLE `free_session_outcomes` (
  `id` int(11) NOT NULL,
  `free_session_id` int(11) NOT NULL,
  `outcome_text` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `free_session_registrations`
--

CREATE TABLE `free_session_registrations` (
  `id` int(11) NOT NULL,
  `free_session_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `country` varchar(150) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `session_time_slot` varchar(100) DEFAULT NULL,
  `status` enum('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `indicators`
--

CREATE TABLE `indicators` (
  `id` int(11) NOT NULL,
  `indicator_code` varchar(50) NOT NULL,
  `record_code` varchar(50) NOT NULL,
  `indicator_name` varchar(255) NOT NULL,
  `operational_definition` text NOT NULL,
  `unit_of_measurement` varchar(50) NOT NULL,
  `data_disaggregation` text NOT NULL,
  `calc_formula` text NOT NULL,
  `mov` text NOT NULL,
  `assumption` text NOT NULL,
  `data_methodology` text NOT NULL,
  `data_collection_type` varchar(100) NOT NULL,
  `data_source` text NOT NULL,
  `staff_responsible` int(11) NOT NULL,
  `data_frequency` varchar(50) NOT NULL,
  `custom_keyword` varchar(255) DEFAULT NULL,
  `custom_entries` int(11) DEFAULT NULL,
  `periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`periods`)),
  `baseline_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`baseline_values`)),
  `target_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`target_values`)),
  `created_by` int(11) NOT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `indicator_results`
--

CREATE TABLE `indicator_results` (
  `id` int(11) NOT NULL,
  `indicator_code` varchar(255) DEFAULT NULL,
  `period` varchar(255) DEFAULT NULL,
  `results_level` text DEFAULT NULL,
  `actual_results` text DEFAULT NULL,
  `program_note` text DEFAULT NULL,
  `program_implication` text DEFAULT NULL,
  `beneficiaries_implication` text DEFAULT NULL,
  `prog_team_recommendation` text DEFAULT NULL,
  `prog_stakeholder_recommendation` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inputs`
--

CREATE TABLE `inputs` (
  `id` int(11) NOT NULL,
  `activity_id` varchar(255) NOT NULL,
  `task_code` varchar(255) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `budget` varchar(255) NOT NULL,
  `weight` varchar(255) NOT NULL,
  `priority` varchar(255) NOT NULL,
  `resp_depart` varchar(255) NOT NULL,
  `resp_user` varchar(255) NOT NULL,
  `location_value` varchar(255) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `results` text DEFAULT NULL,
  `resources` varchar(255) NOT NULL,
  `actors` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL,
  `modified_by` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `institution`
--

CREATE TABLE `institution` (
  `id` int(11) NOT NULL,
  `inst_code` varchar(255) NOT NULL,
  `inst_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `intake`
--

CREATE TABLE `intake` (
  `id` int(11) NOT NULL,
  `description` varchar(500) NOT NULL,
  `course_id` varchar(50) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `assigned_to` varchar(10000) NOT NULL,
  `intake_id` varchar(100) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `start_date` varchar(200) NOT NULL,
  `minimum_clients` int(11) DEFAULT 0,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `commission_status` enum('active','closed','paid') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_forms`
--

CREATE TABLE `lead_forms` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `form_data` text NOT NULL,
  `email_body` longtext DEFAULT NULL,
  `redirect_link` varchar(500) DEFAULT NULL,
  `assigned_to` varchar(500) NOT NULL DEFAULT '6'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_insights`
--

CREATE TABLE `lead_insights` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source` enum('virtual','international') NOT NULL,
  `source_id` varchar(64) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_norm` varchar(255) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `country` varchar(128) DEFAULT NULL,
  `country_norm` varchar(128) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `position_norm` varchar(128) DEFAULT NULL,
  `program_or_term` varchar(255) DEFAULT NULL,
  `lead_segment` varchar(32) DEFAULT NULL,
  `lead_score` smallint(6) NOT NULL DEFAULT 0,
  `lead_status` varchar(64) DEFAULT NULL,
  `assigned_to` varchar(128) DEFAULT NULL,
  `last_contact_date` date DEFAULT NULL,
  `is_converted` tinyint(1) NOT NULL DEFAULT 0,
  `original_date` datetime DEFAULT NULL,
  `refreshed_at` datetime NOT NULL,
  `ai_suggestion` mediumtext DEFAULT NULL,
  `ai_generated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_submissions`
--

CREATE TABLE `lead_submissions` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(8) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `country` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `event_id` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_sync_state`
--

CREATE TABLE `lead_sync_state` (
  `source` varchar(32) NOT NULL,
  `last_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_data_email`
--

CREATE TABLE `marketing_data_email` (
  `id` int(11) NOT NULL,
  `firstname` varchar(200) NOT NULL,
  `lastname` varchar(200) NOT NULL,
  `email` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `comment` varchar(500) NOT NULL,
  `data_id` varchar(20) NOT NULL,
  `date_uploaded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_data_email_one`
--

CREATE TABLE `marketing_data_email_one` (
  `id` int(11) NOT NULL,
  `firstname` varchar(200) NOT NULL,
  `lastname` varchar(200) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(100) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `comment` varchar(500) NOT NULL,
  `data_id` varchar(20) NOT NULL,
  `date_uploaded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_email_messages`
--

CREATE TABLE `marketing_email_messages` (
  `id` int(11) NOT NULL,
  `template` varchar(100) NOT NULL,
  `subject` varchar(1000) NOT NULL,
  `body` longtext NOT NULL,
  `attachment` varchar(100) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_sms_messages`
--

CREATE TABLE `marketing_sms_messages` (
  `id` int(11) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets`
--

CREATE TABLE `mdlvx_adminpresets` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `comments` longtext DEFAULT NULL,
  `site` varchar(255) NOT NULL DEFAULT '',
  `author` varchar(255) DEFAULT NULL,
  `moodleversion` varchar(20) NOT NULL DEFAULT '',
  `moodlerelease` varchar(255) NOT NULL DEFAULT '',
  `iscore` tinyint(1) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timeimported` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_app`
--

CREATE TABLE `mdlvx_adminpresets_app` (
  `id` bigint(20) NOT NULL,
  `adminpresetid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `time` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_app_it`
--

CREATE TABLE `mdlvx_adminpresets_app_it` (
  `id` bigint(20) NOT NULL,
  `adminpresetapplyid` bigint(20) NOT NULL,
  `configlogid` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_app_it_a`
--

CREATE TABLE `mdlvx_adminpresets_app_it_a` (
  `id` bigint(20) NOT NULL,
  `adminpresetapplyid` bigint(20) NOT NULL,
  `configlogid` bigint(20) NOT NULL,
  `itemname` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_app_plug`
--

CREATE TABLE `mdlvx_adminpresets_app_plug` (
  `id` bigint(20) NOT NULL,
  `adminpresetapplyid` bigint(20) NOT NULL,
  `plugin` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` smallint(6) NOT NULL DEFAULT 0,
  `oldvalue` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_it`
--

CREATE TABLE `mdlvx_adminpresets_it` (
  `id` bigint(20) NOT NULL,
  `adminpresetid` bigint(20) NOT NULL,
  `plugin` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_it_a`
--

CREATE TABLE `mdlvx_adminpresets_it_a` (
  `id` bigint(20) NOT NULL,
  `itemid` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_adminpresets_plug`
--

CREATE TABLE `mdlvx_adminpresets_plug` (
  `id` bigint(20) NOT NULL,
  `adminpresetid` bigint(20) NOT NULL,
  `plugin` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `enabled` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_action_explain_text`
--

CREATE TABLE `mdlvx_ai_action_explain_text` (
  `id` bigint(10) NOT NULL,
  `prompt` longtext DEFAULT NULL,
  `responseid` varchar(128) DEFAULT NULL,
  `fingerprint` varchar(128) DEFAULT NULL,
  `generatedcontent` longtext DEFAULT NULL,
  `finishreason` varchar(128) DEFAULT NULL,
  `prompttokens` bigint(10) DEFAULT NULL,
  `completiontoken` bigint(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_action_generate_image`
--

CREATE TABLE `mdlvx_ai_action_generate_image` (
  `id` bigint(10) NOT NULL,
  `prompt` longtext DEFAULT NULL,
  `numberimages` bigint(10) NOT NULL,
  `quality` varchar(21) NOT NULL DEFAULT '',
  `aspectratio` varchar(20) DEFAULT NULL,
  `style` varchar(20) DEFAULT NULL,
  `sourceurl` longtext DEFAULT NULL,
  `revisedprompt` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_action_generate_text`
--

CREATE TABLE `mdlvx_ai_action_generate_text` (
  `id` bigint(10) NOT NULL,
  `prompt` longtext DEFAULT NULL,
  `responseid` varchar(128) DEFAULT NULL,
  `fingerprint` varchar(128) DEFAULT NULL,
  `generatedcontent` longtext DEFAULT NULL,
  `finishreason` varchar(128) DEFAULT NULL,
  `prompttokens` bigint(10) DEFAULT NULL,
  `completiontoken` bigint(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_action_register`
--

CREATE TABLE `mdlvx_ai_action_register` (
  `id` bigint(10) NOT NULL,
  `actionname` varchar(100) NOT NULL DEFAULT '',
  `actionid` bigint(10) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `userid` bigint(10) NOT NULL,
  `contextid` bigint(10) NOT NULL,
  `provider` varchar(100) NOT NULL DEFAULT '',
  `errorcode` smallint(4) DEFAULT NULL,
  `errormessage` longtext DEFAULT NULL,
  `timecreated` bigint(10) NOT NULL,
  `timecompleted` bigint(10) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_action_summarise_text`
--

CREATE TABLE `mdlvx_ai_action_summarise_text` (
  `id` bigint(10) NOT NULL,
  `prompt` longtext DEFAULT NULL,
  `responseid` varchar(128) DEFAULT NULL,
  `fingerprint` varchar(128) DEFAULT NULL,
  `generatedcontent` longtext DEFAULT NULL,
  `finishreason` varchar(128) DEFAULT NULL,
  `prompttokens` bigint(10) DEFAULT NULL,
  `completiontoken` bigint(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_policy_register`
--

CREATE TABLE `mdlvx_ai_policy_register` (
  `id` bigint(10) NOT NULL,
  `userid` bigint(10) NOT NULL,
  `contextid` bigint(10) NOT NULL,
  `timeaccepted` bigint(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_ai_providers`
--

CREATE TABLE `mdlvx_ai_providers` (
  `id` bigint(10) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `provider` varchar(255) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext NOT NULL,
  `actionconfig` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_indicator_calc`
--

CREATE TABLE `mdlvx_analytics_indicator_calc` (
  `id` bigint(20) NOT NULL,
  `starttime` bigint(20) NOT NULL,
  `endtime` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `sampleorigin` varchar(255) NOT NULL DEFAULT '',
  `sampleid` bigint(20) NOT NULL,
  `indicator` varchar(255) NOT NULL DEFAULT '',
  `value` decimal(10,2) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stored indicator calculations' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_models`
--

CREATE TABLE `mdlvx_analytics_models` (
  `id` bigint(20) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `trained` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(1333) DEFAULT NULL,
  `target` varchar(255) NOT NULL DEFAULT '',
  `indicators` longtext NOT NULL,
  `timesplitting` varchar(255) DEFAULT NULL,
  `predictionsprocessor` varchar(255) DEFAULT NULL,
  `version` bigint(20) NOT NULL,
  `contextids` longtext DEFAULT NULL,
  `timecreated` bigint(20) DEFAULT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Analytic models.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_models_log`
--

CREATE TABLE `mdlvx_analytics_models_log` (
  `id` bigint(20) NOT NULL,
  `modelid` bigint(20) NOT NULL,
  `version` bigint(20) NOT NULL,
  `evaluationmode` varchar(50) NOT NULL DEFAULT '',
  `target` varchar(255) NOT NULL DEFAULT '',
  `indicators` longtext NOT NULL,
  `timesplitting` varchar(255) DEFAULT NULL,
  `score` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `info` longtext DEFAULT NULL,
  `dir` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Analytic models changes during evaluation.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_predictions`
--

CREATE TABLE `mdlvx_analytics_predictions` (
  `id` bigint(20) NOT NULL,
  `modelid` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `sampleid` bigint(20) NOT NULL,
  `rangeindex` mediumint(9) NOT NULL,
  `prediction` decimal(10,2) NOT NULL,
  `predictionscore` decimal(10,5) NOT NULL,
  `calculations` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timestart` bigint(20) DEFAULT NULL,
  `timeend` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Predictions' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_prediction_actions`
--

CREATE TABLE `mdlvx_analytics_prediction_actions` (
  `id` bigint(20) NOT NULL,
  `predictionid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `actionname` varchar(255) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Register of user actions over predictions.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_predict_samples`
--

CREATE TABLE `mdlvx_analytics_predict_samples` (
  `id` bigint(20) NOT NULL,
  `modelid` bigint(20) NOT NULL,
  `analysableid` bigint(20) NOT NULL,
  `timesplitting` varchar(255) NOT NULL DEFAULT '',
  `rangeindex` bigint(20) NOT NULL,
  `sampleids` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Samples already used for predictions.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_train_samples`
--

CREATE TABLE `mdlvx_analytics_train_samples` (
  `id` bigint(20) NOT NULL,
  `modelid` bigint(20) NOT NULL,
  `analysableid` bigint(20) NOT NULL,
  `timesplitting` varchar(255) NOT NULL DEFAULT '',
  `sampleids` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Samples used for training' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_used_analysables`
--

CREATE TABLE `mdlvx_analytics_used_analysables` (
  `id` bigint(20) NOT NULL,
  `modelid` bigint(20) NOT NULL,
  `action` varchar(50) NOT NULL DEFAULT '',
  `analysableid` bigint(20) NOT NULL,
  `firstanalysis` bigint(20) NOT NULL,
  `timeanalysed` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='List of analysables used by each model' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_analytics_used_files`
--

CREATE TABLE `mdlvx_analytics_used_files` (
  `id` bigint(20) NOT NULL,
  `modelid` bigint(20) NOT NULL DEFAULT 0,
  `fileid` bigint(20) NOT NULL DEFAULT 0,
  `action` varchar(50) NOT NULL DEFAULT '',
  `time` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Files that have already been used for training and predictio' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign`
--

CREATE TABLE `mdlvx_assign` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `alwaysshowdescription` tinyint(4) NOT NULL DEFAULT 0,
  `activity` longtext DEFAULT NULL,
  `activityformat` smallint(6) NOT NULL DEFAULT 0,
  `submissionattachments` tinyint(4) NOT NULL DEFAULT 0,
  `gradepenalty` tinyint(2) NOT NULL DEFAULT 0,
  `nosubmissions` tinyint(4) NOT NULL DEFAULT 0,
  `submissiondrafts` tinyint(4) NOT NULL DEFAULT 0,
  `sendnotifications` tinyint(4) NOT NULL DEFAULT 0,
  `sendlatenotifications` tinyint(4) NOT NULL DEFAULT 0,
  `duedate` bigint(20) NOT NULL DEFAULT 0,
  `allowsubmissionsfromdate` bigint(20) NOT NULL DEFAULT 0,
  `grade` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `requiresubmissionstatement` tinyint(4) NOT NULL DEFAULT 0,
  `completionsubmit` tinyint(4) NOT NULL DEFAULT 0,
  `cutoffdate` bigint(20) NOT NULL DEFAULT 0,
  `timelimit` bigint(20) NOT NULL DEFAULT 0,
  `gradingduedate` bigint(20) NOT NULL DEFAULT 0,
  `teamsubmission` tinyint(4) NOT NULL DEFAULT 0,
  `requireallteammemberssubmit` tinyint(4) NOT NULL DEFAULT 0,
  `teamsubmissiongroupingid` bigint(20) NOT NULL DEFAULT 0,
  `blindmarking` tinyint(4) NOT NULL DEFAULT 0,
  `hidegrader` tinyint(4) NOT NULL DEFAULT 0,
  `revealidentities` tinyint(4) NOT NULL DEFAULT 0,
  `attemptreopenmethod` varchar(10) NOT NULL DEFAULT 'untilpass',
  `maxattempts` mediumint(6) NOT NULL DEFAULT 1,
  `markingworkflow` tinyint(4) NOT NULL DEFAULT 0,
  `markingallocation` tinyint(4) NOT NULL DEFAULT 0,
  `markinganonymous` tinyint(2) NOT NULL DEFAULT 0,
  `sendstudentnotifications` tinyint(4) NOT NULL DEFAULT 1,
  `preventsubmissionnotingroup` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table saves information about an instance of mod_assign' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignfeedback_comments`
--

CREATE TABLE `mdlvx_assignfeedback_comments` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `grade` bigint(20) NOT NULL DEFAULT 0,
  `commenttext` longtext DEFAULT NULL,
  `commentformat` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Text feedback for submitted assignments' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignfeedback_editpdf_annot`
--

CREATE TABLE `mdlvx_assignfeedback_editpdf_annot` (
  `id` bigint(20) NOT NULL,
  `gradeid` bigint(20) NOT NULL DEFAULT 0,
  `pageno` bigint(20) NOT NULL DEFAULT 0,
  `x` bigint(20) DEFAULT 0,
  `y` bigint(20) DEFAULT 0,
  `endx` bigint(20) DEFAULT 0,
  `endy` bigint(20) DEFAULT 0,
  `path` longtext DEFAULT NULL,
  `type` varchar(10) DEFAULT 'line',
  `colour` varchar(10) DEFAULT 'black',
  `draft` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='stores annotations added to pdfs submitted by students' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignfeedback_editpdf_cmnt`
--

CREATE TABLE `mdlvx_assignfeedback_editpdf_cmnt` (
  `id` bigint(20) NOT NULL,
  `gradeid` bigint(20) NOT NULL DEFAULT 0,
  `x` bigint(20) DEFAULT 0,
  `y` bigint(20) DEFAULT 0,
  `width` bigint(20) DEFAULT 120,
  `rawtext` longtext DEFAULT NULL,
  `pageno` bigint(20) NOT NULL DEFAULT 0,
  `colour` varchar(10) DEFAULT 'black',
  `draft` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores comments added to pdfs' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignfeedback_editpdf_quick`
--

CREATE TABLE `mdlvx_assignfeedback_editpdf_quick` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `rawtext` longtext NOT NULL,
  `width` bigint(20) NOT NULL DEFAULT 120,
  `colour` varchar(10) DEFAULT 'yellow'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores teacher specified quicklist comments' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignfeedback_editpdf_rot`
--

CREATE TABLE `mdlvx_assignfeedback_editpdf_rot` (
  `id` bigint(20) NOT NULL,
  `gradeid` bigint(20) NOT NULL DEFAULT 0,
  `pageno` bigint(20) NOT NULL DEFAULT 0,
  `pathnamehash` longtext NOT NULL,
  `isrotated` tinyint(1) NOT NULL DEFAULT 0,
  `degree` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores rotation information of a page.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignfeedback_file`
--

CREATE TABLE `mdlvx_assignfeedback_file` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `grade` bigint(20) NOT NULL DEFAULT 0,
  `numfiles` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores info about the number of files submitted by a student' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignsubmission_file`
--

CREATE TABLE `mdlvx_assignsubmission_file` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `submission` bigint(20) NOT NULL DEFAULT 0,
  `numfiles` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Info about file submissions for assignments' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assignsubmission_onlinetext`
--

CREATE TABLE `mdlvx_assignsubmission_onlinetext` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `submission` bigint(20) NOT NULL DEFAULT 0,
  `onlinetext` longtext DEFAULT NULL,
  `onlineformat` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Info about onlinetext submission' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign_grades`
--

CREATE TABLE `mdlvx_assign_grades` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `grader` bigint(20) NOT NULL DEFAULT 0,
  `grade` decimal(10,5) DEFAULT 0.00000,
  `penalty` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `attemptnumber` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grading information about a single assignment submission.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign_overrides`
--

CREATE TABLE `mdlvx_assign_overrides` (
  `id` bigint(20) NOT NULL,
  `assignid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) DEFAULT NULL,
  `userid` bigint(20) DEFAULT NULL,
  `sortorder` bigint(20) DEFAULT NULL,
  `allowsubmissionsfromdate` bigint(20) DEFAULT NULL,
  `duedate` bigint(20) DEFAULT NULL,
  `cutoffdate` bigint(20) DEFAULT NULL,
  `timelimit` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The overrides to assign settings.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign_plugin_config`
--

CREATE TABLE `mdlvx_assign_plugin_config` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `plugin` varchar(28) NOT NULL DEFAULT '',
  `subtype` varchar(28) NOT NULL DEFAULT '',
  `name` varchar(28) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Config data for an instance of a plugin in an assignment.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign_submission`
--

CREATE TABLE `mdlvx_assign_submission` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `timestarted` bigint(20) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `groupid` bigint(20) NOT NULL DEFAULT 0,
  `attemptnumber` bigint(20) NOT NULL DEFAULT 0,
  `latest` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table keeps information about student interactions with' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign_user_flags`
--

CREATE TABLE `mdlvx_assign_user_flags` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `locked` bigint(20) NOT NULL DEFAULT 0,
  `mailed` smallint(6) NOT NULL DEFAULT 0,
  `extensionduedate` bigint(20) NOT NULL DEFAULT 0,
  `workflowstate` varchar(20) DEFAULT NULL,
  `allocatedmarker` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='List of flags that can be set for a single user in a single ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_assign_user_mapping`
--

CREATE TABLE `mdlvx_assign_user_mapping` (
  `id` bigint(20) NOT NULL,
  `assignment` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Map an assignment specific id number to a user' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_auth_lti_linked_login`
--

CREATE TABLE `mdlvx_auth_lti_linked_login` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `issuer` longtext NOT NULL,
  `issuer256` varchar(64) NOT NULL DEFAULT '',
  `sub` varchar(255) NOT NULL DEFAULT '',
  `sub256` varchar(64) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_auth_oauth2_linked_login`
--

CREATE TABLE `mdlvx_auth_oauth2_linked_login` (
  `id` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `issuerid` bigint(20) NOT NULL,
  `username` varchar(255) NOT NULL DEFAULT '',
  `email` longtext NOT NULL,
  `confirmtoken` varchar(64) NOT NULL DEFAULT '',
  `confirmtokenexpires` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Accounts linked to a users Moodle account.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_backup_controllers`
--

CREATE TABLE `mdlvx_backup_controllers` (
  `id` bigint(20) NOT NULL,
  `backupid` varchar(32) NOT NULL DEFAULT '',
  `operation` varchar(20) NOT NULL DEFAULT 'backup',
  `type` varchar(10) NOT NULL DEFAULT '',
  `itemid` bigint(20) NOT NULL,
  `format` varchar(20) NOT NULL DEFAULT '',
  `interactive` smallint(6) NOT NULL,
  `purpose` smallint(6) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `status` smallint(6) NOT NULL,
  `execution` smallint(6) NOT NULL,
  `executiontime` bigint(20) NOT NULL,
  `checksum` varchar(32) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `progress` decimal(15,14) NOT NULL DEFAULT 0.00000000000000,
  `controller` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='To store the backup_controllers as they are used' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_backup_courses`
--

CREATE TABLE `mdlvx_backup_courses` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL DEFAULT 0,
  `laststarttime` bigint(20) NOT NULL DEFAULT 0,
  `lastendtime` bigint(20) NOT NULL DEFAULT 0,
  `laststatus` varchar(1) NOT NULL DEFAULT '5',
  `nextstarttime` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='To store every course backup status' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_backup_logs`
--

CREATE TABLE `mdlvx_backup_logs` (
  `id` bigint(20) NOT NULL,
  `backupid` varchar(32) NOT NULL DEFAULT '',
  `loglevel` smallint(6) NOT NULL,
  `message` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='To store all the logs from backup and restore operations (by' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge`
--

CREATE TABLE `mdlvx_badge` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `usercreated` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `issuername` varchar(255) NOT NULL DEFAULT '',
  `issuerurl` varchar(255) NOT NULL DEFAULT '',
  `issuercontact` varchar(255) DEFAULT NULL,
  `expiredate` bigint(20) DEFAULT NULL,
  `expireperiod` bigint(20) DEFAULT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 1,
  `courseid` bigint(20) DEFAULT NULL,
  `message` longtext NOT NULL,
  `messagesubject` longtext NOT NULL,
  `attachment` tinyint(1) NOT NULL DEFAULT 1,
  `notification` tinyint(1) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `nextcron` bigint(20) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `imagecaption` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines badge' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_alignment`
--

CREATE TABLE `mdlvx_badge_alignment` (
  `id` bigint(20) NOT NULL,
  `badgeid` bigint(20) NOT NULL DEFAULT 0,
  `targetname` varchar(255) NOT NULL DEFAULT '',
  `targeturl` varchar(255) NOT NULL DEFAULT '',
  `targetdescription` longtext DEFAULT NULL,
  `targetframework` varchar(255) DEFAULT NULL,
  `targetcode` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines alignment for badges' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_backpack`
--

CREATE TABLE `mdlvx_badge_backpack` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `email` varchar(100) NOT NULL DEFAULT '',
  `backpackuid` bigint(20) NOT NULL,
  `autosync` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(50) DEFAULT NULL,
  `externalbackpackid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines settings for connecting external backpack' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_backpack_oauth2`
--

CREATE TABLE `mdlvx_badge_backpack_oauth2` (
  `id` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL,
  `issuerid` bigint(20) NOT NULL,
  `externalbackpackid` bigint(20) NOT NULL,
  `token` longtext NOT NULL,
  `refreshtoken` longtext NOT NULL,
  `expires` bigint(20) DEFAULT NULL,
  `scope` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Default comment for the table, please edit me' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_criteria`
--

CREATE TABLE `mdlvx_badge_criteria` (
  `id` bigint(20) NOT NULL,
  `badgeid` bigint(20) NOT NULL DEFAULT 0,
  `criteriatype` bigint(20) DEFAULT NULL,
  `method` tinyint(1) NOT NULL DEFAULT 1,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines criteria for issuing badges' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_criteria_met`
--

CREATE TABLE `mdlvx_badge_criteria_met` (
  `id` bigint(20) NOT NULL,
  `issuedid` bigint(20) DEFAULT NULL,
  `critid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `datemet` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines criteria that were met for an issued badge' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_criteria_param`
--

CREATE TABLE `mdlvx_badge_criteria_param` (
  `id` bigint(20) NOT NULL,
  `critid` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines parameters for badges criteria' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_endorsement`
--

CREATE TABLE `mdlvx_badge_endorsement` (
  `id` bigint(20) NOT NULL,
  `badgeid` bigint(20) NOT NULL DEFAULT 0,
  `issuername` varchar(255) NOT NULL DEFAULT '',
  `issuerurl` varchar(255) NOT NULL DEFAULT '',
  `issueremail` varchar(255) NOT NULL DEFAULT '',
  `claimid` varchar(255) DEFAULT NULL,
  `claimcomment` longtext DEFAULT NULL,
  `dateissued` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines endorsement for badge' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_external`
--

CREATE TABLE `mdlvx_badge_external` (
  `id` bigint(20) NOT NULL,
  `backpackid` bigint(20) NOT NULL,
  `collectionid` bigint(20) NOT NULL,
  `entityid` varchar(255) DEFAULT NULL,
  `assertion` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Setting for external badges display' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_external_backpack`
--

CREATE TABLE `mdlvx_badge_external_backpack` (
  `id` bigint(20) NOT NULL,
  `backpackapiurl` varchar(255) NOT NULL DEFAULT '',
  `backpackweburl` varchar(255) NOT NULL DEFAULT '',
  `apiversion` varchar(12) NOT NULL DEFAULT '',
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `oauth2_issuerid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines settings for site level backpacks that a user can co' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_external_identifier`
--

CREATE TABLE `mdlvx_badge_external_identifier` (
  `id` bigint(20) NOT NULL,
  `sitebackpackid` bigint(20) NOT NULL,
  `internalid` varchar(128) NOT NULL DEFAULT '',
  `externalid` varchar(128) NOT NULL DEFAULT '',
  `type` varchar(16) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Setting for external badges mappings' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_issued`
--

CREATE TABLE `mdlvx_badge_issued` (
  `id` bigint(20) NOT NULL,
  `badgeid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `uniquehash` longtext NOT NULL,
  `dateissued` bigint(20) NOT NULL DEFAULT 0,
  `dateexpire` bigint(20) DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 0,
  `issuernotified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines issued badges' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_manual_award`
--

CREATE TABLE `mdlvx_badge_manual_award` (
  `id` bigint(20) NOT NULL,
  `badgeid` bigint(20) NOT NULL,
  `recipientid` bigint(20) NOT NULL,
  `issuerid` bigint(20) NOT NULL,
  `issuerrole` bigint(20) NOT NULL,
  `datemet` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track manual award criteria for badges' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_badge_related`
--

CREATE TABLE `mdlvx_badge_related` (
  `id` bigint(20) NOT NULL,
  `badgeid` bigint(20) NOT NULL DEFAULT 0,
  `relatedbadgeid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines badge related for badges' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_bigbluebuttonbn`
--

CREATE TABLE `mdlvx_bigbluebuttonbn` (
  `id` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT 0,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 1,
  `meetingid` varchar(255) NOT NULL DEFAULT '',
  `moderatorpass` varchar(255) NOT NULL DEFAULT '',
  `viewerpass` varchar(255) NOT NULL DEFAULT '',
  `wait` tinyint(1) NOT NULL DEFAULT 0,
  `record` tinyint(1) NOT NULL DEFAULT 0,
  `recordallfromstart` tinyint(1) NOT NULL DEFAULT 0,
  `recordhidebutton` tinyint(1) NOT NULL DEFAULT 0,
  `welcome` longtext DEFAULT NULL,
  `voicebridge` mediumint(9) NOT NULL DEFAULT 0,
  `openingtime` bigint(20) NOT NULL DEFAULT 0,
  `closingtime` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `presentation` longtext DEFAULT NULL,
  `participants` longtext DEFAULT NULL,
  `userlimit` smallint(6) NOT NULL DEFAULT 0,
  `recordings_html` tinyint(1) NOT NULL DEFAULT 0,
  `recordings_deleted` tinyint(1) NOT NULL DEFAULT 1,
  `recordings_imported` tinyint(1) NOT NULL DEFAULT 0,
  `recordings_preview` tinyint(1) NOT NULL DEFAULT 0,
  `clienttype` tinyint(1) NOT NULL DEFAULT 0,
  `muteonstart` tinyint(1) NOT NULL DEFAULT 0,
  `disablecam` tinyint(1) NOT NULL DEFAULT 0,
  `disablemic` tinyint(1) NOT NULL DEFAULT 0,
  `disableprivatechat` tinyint(1) NOT NULL DEFAULT 0,
  `disablepublicchat` tinyint(1) NOT NULL DEFAULT 0,
  `disablenote` tinyint(1) NOT NULL DEFAULT 0,
  `hideuserlist` tinyint(1) NOT NULL DEFAULT 0,
  `completionattendance` int(11) NOT NULL DEFAULT 0,
  `completionengagementchats` int(11) NOT NULL DEFAULT 0,
  `completionengagementtalks` int(11) NOT NULL DEFAULT 0,
  `completionengagementraisehand` int(11) NOT NULL DEFAULT 0,
  `completionengagementpollvotes` int(11) NOT NULL DEFAULT 0,
  `completionengagementemojis` int(11) NOT NULL DEFAULT 0,
  `guestallowed` tinyint(4) DEFAULT 0,
  `mustapproveuser` tinyint(4) DEFAULT 1,
  `guestlinkuid` varchar(1024) DEFAULT NULL,
  `guestpassword` varchar(255) DEFAULT NULL,
  `showpresentation` tinyint(1) NOT NULL DEFAULT 1,
  `grade` bigint(10) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The bigbluebuttonbn table to store information about a meeti' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_bigbluebuttonbn_logs`
--

CREATE TABLE `mdlvx_bigbluebuttonbn_logs` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `bigbluebuttonbnid` bigint(20) NOT NULL,
  `userid` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `meetingid` varchar(256) NOT NULL DEFAULT '',
  `log` varchar(32) NOT NULL DEFAULT '',
  `meta` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The bigbluebuttonbn table to store meeting activity events' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_bigbluebuttonbn_recordings`
--

CREATE TABLE `mdlvx_bigbluebuttonbn_recordings` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `bigbluebuttonbnid` bigint(20) NOT NULL,
  `groupid` bigint(20) DEFAULT NULL,
  `recordingid` varchar(64) NOT NULL DEFAULT '',
  `headless` tinyint(1) NOT NULL DEFAULT 0,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `importeddata` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `usermodified` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The bigbluebuttonbn table to store references to recordings' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_block`
--

CREATE TABLE `mdlvx_block` (
  `id` bigint(20) NOT NULL,
  `name` varchar(40) NOT NULL DEFAULT '',
  `cron` bigint(20) NOT NULL DEFAULT 0,
  `lastcron` bigint(20) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='contains all installed blocks' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_block_instances`
--

CREATE TABLE `mdlvx_block_instances` (
  `id` bigint(20) NOT NULL,
  `blockname` varchar(40) NOT NULL DEFAULT '',
  `parentcontextid` bigint(20) NOT NULL,
  `showinsubcontexts` smallint(6) NOT NULL,
  `requiredbytheme` smallint(6) NOT NULL DEFAULT 0,
  `pagetypepattern` varchar(64) NOT NULL DEFAULT '',
  `subpagepattern` varchar(16) DEFAULT NULL,
  `defaultregion` varchar(16) NOT NULL DEFAULT '',
  `defaultweight` bigint(20) NOT NULL,
  `configdata` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table stores block instances. The type of block this is' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_block_positions`
--

CREATE TABLE `mdlvx_block_positions` (
  `id` bigint(20) NOT NULL,
  `blockinstanceid` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `pagetype` varchar(64) NOT NULL DEFAULT '',
  `subpage` varchar(16) NOT NULL DEFAULT '',
  `visible` smallint(6) NOT NULL,
  `region` varchar(16) NOT NULL DEFAULT '',
  `weight` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the position of a sticky block_instance on a another ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_block_recentlyaccesseditems`
--

CREATE TABLE `mdlvx_block_recentlyaccesseditems` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `cmid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `timeaccess` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Most recently accessed items accessed by a user' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_block_recent_activity`
--

CREATE TABLE `mdlvx_block_recent_activity` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `cmid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `action` tinyint(1) NOT NULL,
  `modname` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recent activity block' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_block_rss_client`
--

CREATE TABLE `mdlvx_block_rss_client` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `title` longtext NOT NULL,
  `preferredtitle` varchar(64) NOT NULL DEFAULT '',
  `description` longtext NOT NULL,
  `shared` tinyint(4) NOT NULL DEFAULT 0,
  `url` varchar(255) NOT NULL DEFAULT '',
  `skiptime` bigint(20) NOT NULL DEFAULT 0,
  `skipuntil` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Remote news feed information. Contains the news feed id, the' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_blog_association`
--

CREATE TABLE `mdlvx_blog_association` (
  `id` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `blogid` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Associations of blog entries with courses and module instanc' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_blog_external`
--

CREATE TABLE `mdlvx_blog_external` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `url` longtext NOT NULL,
  `filtertags` varchar(255) DEFAULT NULL,
  `failedlastsync` tinyint(1) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) DEFAULT NULL,
  `timefetched` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External blog links used for RSS copying of blog entries to ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_book`
--

CREATE TABLE `mdlvx_book` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `numbering` smallint(6) NOT NULL DEFAULT 0,
  `navstyle` smallint(6) NOT NULL DEFAULT 1,
  `customtitles` tinyint(4) NOT NULL DEFAULT 0,
  `revision` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines book' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_book_chapters`
--

CREATE TABLE `mdlvx_book_chapters` (
  `id` bigint(20) NOT NULL,
  `bookid` bigint(20) NOT NULL DEFAULT 0,
  `pagenum` bigint(20) NOT NULL DEFAULT 0,
  `subchapter` bigint(20) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `contentformat` smallint(6) NOT NULL DEFAULT 0,
  `hidden` tinyint(4) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `importsrc` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines book_chapters' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_cache_filters`
--

CREATE TABLE `mdlvx_cache_filters` (
  `id` bigint(20) NOT NULL,
  `filter` varchar(32) NOT NULL DEFAULT '',
  `version` bigint(20) NOT NULL DEFAULT 0,
  `md5key` varchar(32) NOT NULL DEFAULT '',
  `rawtext` longtext NOT NULL,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='For keeping information about cached data' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_cache_flags`
--

CREATE TABLE `mdlvx_cache_flags` (
  `id` bigint(20) NOT NULL,
  `flagtype` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `value` longtext NOT NULL,
  `expiry` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache of time-sensitive flags' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_capabilities`
--

CREATE TABLE `mdlvx_capabilities` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `captype` varchar(50) NOT NULL DEFAULT '',
  `contextlevel` bigint(20) NOT NULL DEFAULT 0,
  `component` varchar(100) NOT NULL DEFAULT '',
  `riskbitmask` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='this defines all capabilities' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_choice`
--

CREATE TABLE `mdlvx_choice` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `publish` tinyint(4) NOT NULL DEFAULT 0,
  `showresults` tinyint(4) NOT NULL DEFAULT 0,
  `display` smallint(6) NOT NULL DEFAULT 0,
  `allowupdate` tinyint(4) NOT NULL DEFAULT 0,
  `allowmultiple` tinyint(4) NOT NULL DEFAULT 0,
  `showunanswered` tinyint(4) NOT NULL DEFAULT 0,
  `includeinactive` tinyint(4) NOT NULL DEFAULT 1,
  `limitanswers` tinyint(4) NOT NULL DEFAULT 0,
  `timeopen` bigint(20) NOT NULL DEFAULT 0,
  `timeclose` bigint(20) NOT NULL DEFAULT 0,
  `showpreview` tinyint(4) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `completionsubmit` tinyint(1) NOT NULL DEFAULT 0,
  `showavailable` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Available choices are stored here' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_choice_answers`
--

CREATE TABLE `mdlvx_choice_answers` (
  `id` bigint(20) NOT NULL,
  `choiceid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `optionid` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='choices performed by users' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_choice_options`
--

CREATE TABLE `mdlvx_choice_options` (
  `id` bigint(20) NOT NULL,
  `choiceid` bigint(20) NOT NULL DEFAULT 0,
  `text` longtext DEFAULT NULL,
  `maxanswers` bigint(20) DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='available options to choice' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_cohort`
--

CREATE TABLE `mdlvx_cohort` (
  `id` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `name` varchar(254) NOT NULL DEFAULT '',
  `idnumber` varchar(100) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `component` varchar(100) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `theme` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Each record represents one cohort (aka site-wide group).' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_cohort_members`
--

CREATE TABLE `mdlvx_cohort_members` (
  `id` bigint(20) NOT NULL,
  `cohortid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `timeadded` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link a user to a cohort.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_comments`
--

CREATE TABLE `mdlvx_comments` (
  `id` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `component` varchar(255) DEFAULT NULL,
  `commentarea` varchar(255) NOT NULL DEFAULT '',
  `itemid` bigint(20) NOT NULL,
  `content` longtext NOT NULL,
  `format` tinyint(4) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='moodle comments module' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_communication`
--

CREATE TABLE `mdlvx_communication` (
  `id` bigint(10) NOT NULL,
  `contextid` bigint(10) NOT NULL,
  `instanceid` bigint(10) NOT NULL,
  `component` varchar(100) NOT NULL DEFAULT '',
  `instancetype` varchar(100) NOT NULL DEFAULT '',
  `provider` varchar(100) NOT NULL DEFAULT '',
  `roomname` varchar(255) DEFAULT NULL,
  `avatarfilename` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `avatarsynced` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_communication_customlink`
--

CREATE TABLE `mdlvx_communication_customlink` (
  `id` bigint(10) NOT NULL,
  `commid` bigint(10) NOT NULL,
  `url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the link associated with a custom link communication ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_communication_user`
--

CREATE TABLE `mdlvx_communication_user` (
  `id` bigint(10) NOT NULL,
  `commid` bigint(10) NOT NULL,
  `userid` bigint(10) NOT NULL,
  `synced` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency`
--

CREATE TABLE `mdlvx_competency` (
  `id` bigint(20) NOT NULL,
  `shortname` varchar(100) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` smallint(6) NOT NULL DEFAULT 0,
  `idnumber` varchar(100) DEFAULT NULL,
  `competencyframeworkid` bigint(20) NOT NULL,
  `parentid` bigint(20) NOT NULL DEFAULT 0,
  `path` varchar(255) NOT NULL DEFAULT '',
  `sortorder` bigint(20) NOT NULL,
  `ruletype` varchar(100) DEFAULT NULL,
  `ruleoutcome` tinyint(4) NOT NULL DEFAULT 0,
  `ruleconfig` longtext DEFAULT NULL,
  `scaleid` bigint(20) DEFAULT NULL,
  `scaleconfiguration` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table contains the master record of each competency in ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_coursecomp`
--

CREATE TABLE `mdlvx_competency_coursecomp` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `ruleoutcome` tinyint(4) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link a competency to a course.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_coursecompsetting`
--

CREATE TABLE `mdlvx_competency_coursecompsetting` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `pushratingstouserplans` tinyint(4) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table contains the course specific settings for compete' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_evidence`
--

CREATE TABLE `mdlvx_competency_evidence` (
  `id` bigint(20) NOT NULL,
  `usercompetencyid` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `action` tinyint(4) NOT NULL,
  `actionuserid` bigint(20) DEFAULT NULL,
  `descidentifier` varchar(255) NOT NULL DEFAULT '',
  `desccomponent` varchar(255) NOT NULL DEFAULT '',
  `desca` longtext DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `grade` bigint(20) DEFAULT NULL,
  `note` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The evidence linked to a user competency' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_framework`
--

CREATE TABLE `mdlvx_competency_framework` (
  `id` bigint(20) NOT NULL,
  `shortname` varchar(100) DEFAULT NULL,
  `contextid` bigint(20) NOT NULL,
  `idnumber` varchar(100) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` smallint(6) NOT NULL DEFAULT 0,
  `scaleid` bigint(20) DEFAULT NULL,
  `scaleconfiguration` longtext NOT NULL,
  `visible` tinyint(4) NOT NULL DEFAULT 1,
  `taxonomies` varchar(255) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='List of competency frameworks.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_modulecomp`
--

CREATE TABLE `mdlvx_competency_modulecomp` (
  `id` bigint(20) NOT NULL,
  `cmid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `ruleoutcome` tinyint(4) NOT NULL,
  `overridegrade` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link a competency to a module.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_plan`
--

CREATE TABLE `mdlvx_competency_plan` (
  `id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` smallint(6) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL,
  `templateid` bigint(20) DEFAULT NULL,
  `origtemplateid` bigint(20) DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `duedate` bigint(20) DEFAULT 0,
  `reviewerid` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Learning plans' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_plancomp`
--

CREATE TABLE `mdlvx_competency_plancomp` (
  `id` bigint(20) NOT NULL,
  `planid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `sortorder` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plan competencies' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_relatedcomp`
--

CREATE TABLE `mdlvx_competency_relatedcomp` (
  `id` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `relatedcompetencyid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Related competencies' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_template`
--

CREATE TABLE `mdlvx_competency_template` (
  `id` bigint(20) NOT NULL,
  `shortname` varchar(100) DEFAULT NULL,
  `contextid` bigint(20) NOT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` smallint(6) NOT NULL DEFAULT 0,
  `visible` tinyint(4) NOT NULL DEFAULT 1,
  `duedate` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Learning plan templates.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_templatecohort`
--

CREATE TABLE `mdlvx_competency_templatecohort` (
  `id` bigint(20) NOT NULL,
  `templateid` bigint(20) NOT NULL,
  `cohortid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Default comment for the table, please edit me' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_templatecomp`
--

CREATE TABLE `mdlvx_competency_templatecomp` (
  `id` bigint(20) NOT NULL,
  `templateid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `sortorder` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link a competency to a learning plan template.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_usercomp`
--

CREATE TABLE `mdlvx_competency_usercomp` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `reviewerid` bigint(20) DEFAULT NULL,
  `proficiency` tinyint(4) DEFAULT NULL,
  `grade` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User competencies' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_usercompcourse`
--

CREATE TABLE `mdlvx_competency_usercompcourse` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `proficiency` tinyint(4) DEFAULT NULL,
  `grade` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User competencies in a course' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_usercompplan`
--

CREATE TABLE `mdlvx_competency_usercompplan` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `planid` bigint(20) NOT NULL,
  `proficiency` tinyint(4) DEFAULT NULL,
  `grade` bigint(20) DEFAULT NULL,
  `sortorder` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User competencies plans' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_userevidence`
--

CREATE TABLE `mdlvx_competency_userevidence` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` longtext NOT NULL,
  `descriptionformat` tinyint(1) NOT NULL,
  `url` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The evidence of prior learning' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_competency_userevidencecomp`
--

CREATE TABLE `mdlvx_competency_userevidencecomp` (
  `id` bigint(20) NOT NULL,
  `userevidenceid` bigint(20) NOT NULL,
  `competencyid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relationship between user evidence and competencies' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_config`
--

CREATE TABLE `mdlvx_config` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Moodle configuration variables' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_config_log`
--

CREATE TABLE `mdlvx_config_log` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `plugin` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL,
  `oldvalue` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Changes done in server configuration through admin UI' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_config_plugins`
--

CREATE TABLE `mdlvx_config_plugins` (
  `id` bigint(20) NOT NULL,
  `plugin` varchar(100) NOT NULL DEFAULT 'core',
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Moodle modules and plugins configuration variables' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_contentbank_content`
--

CREATE TABLE `mdlvx_contentbank_content` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `contenttype` varchar(100) NOT NULL DEFAULT '',
  `contextid` bigint(20) NOT NULL,
  `visibility` tinyint(1) NOT NULL DEFAULT 1,
  `instanceid` bigint(20) DEFAULT NULL,
  `configdata` longtext DEFAULT NULL,
  `usercreated` bigint(20) NOT NULL,
  `usermodified` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table stores content data in the content bank.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_context`
--

CREATE TABLE `mdlvx_context` (
  `id` bigint(20) NOT NULL,
  `contextlevel` bigint(20) NOT NULL DEFAULT 0,
  `instanceid` bigint(20) NOT NULL DEFAULT 0,
  `path` varchar(255) DEFAULT NULL,
  `depth` tinyint(4) NOT NULL DEFAULT 0,
  `locked` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='one of these must be set' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_context_temp`
--

CREATE TABLE `mdlvx_context_temp` (
  `id` bigint(20) NOT NULL,
  `path` varchar(255) NOT NULL DEFAULT '',
  `depth` tinyint(4) NOT NULL,
  `locked` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Used by build_context_path() in upgrade and cron to keep con' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course`
--

CREATE TABLE `mdlvx_course` (
  `id` bigint(20) NOT NULL,
  `category` bigint(20) NOT NULL DEFAULT 0,
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `fullname` varchar(1333) NOT NULL DEFAULT '',
  `shortname` varchar(255) NOT NULL DEFAULT '',
  `idnumber` varchar(100) NOT NULL DEFAULT '',
  `summary` longtext DEFAULT NULL,
  `summaryformat` tinyint(4) NOT NULL DEFAULT 0,
  `format` varchar(21) NOT NULL DEFAULT 'topics',
  `showgrades` tinyint(4) NOT NULL DEFAULT 1,
  `newsitems` mediumint(9) NOT NULL DEFAULT 1,
  `startdate` bigint(20) NOT NULL DEFAULT 0,
  `enddate` bigint(20) NOT NULL DEFAULT 0,
  `relativedatesmode` tinyint(1) NOT NULL DEFAULT 0,
  `marker` bigint(20) NOT NULL DEFAULT 0,
  `maxbytes` bigint(20) NOT NULL DEFAULT 0,
  `legacyfiles` smallint(6) NOT NULL DEFAULT 0,
  `showreports` smallint(6) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `visibleold` tinyint(1) NOT NULL DEFAULT 1,
  `downloadcontent` tinyint(1) DEFAULT NULL,
  `groupmode` smallint(6) NOT NULL DEFAULT 0,
  `groupmodeforce` smallint(6) NOT NULL DEFAULT 0,
  `defaultgroupingid` bigint(20) NOT NULL DEFAULT 0,
  `lang` varchar(30) NOT NULL DEFAULT '',
  `calendartype` varchar(30) NOT NULL DEFAULT '',
  `theme` varchar(50) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `requested` tinyint(1) NOT NULL DEFAULT 0,
  `enablecompletion` tinyint(1) NOT NULL DEFAULT 0,
  `completionnotify` tinyint(1) NOT NULL DEFAULT 0,
  `cacherev` bigint(20) NOT NULL DEFAULT 0,
  `originalcourseid` bigint(20) DEFAULT NULL,
  `showactivitydates` tinyint(1) NOT NULL DEFAULT 0,
  `showcompletionconditions` tinyint(1) DEFAULT NULL,
  `pdfexportfont` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Central course table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_categories`
--

CREATE TABLE `mdlvx_course_categories` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `idnumber` varchar(100) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL DEFAULT 0,
  `parent` bigint(20) NOT NULL DEFAULT 0,
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `coursecount` bigint(20) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `visibleold` tinyint(1) NOT NULL DEFAULT 1,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `depth` bigint(20) NOT NULL DEFAULT 0,
  `path` varchar(255) NOT NULL DEFAULT '',
  `theme` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course categories' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_completions`
--

CREATE TABLE `mdlvx_course_completions` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `timeenrolled` bigint(20) NOT NULL DEFAULT 0,
  `timestarted` bigint(20) NOT NULL DEFAULT 0,
  `timecompleted` bigint(20) DEFAULT NULL,
  `reaggregate` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course completion records' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_completion_aggr_methd`
--

CREATE TABLE `mdlvx_course_completion_aggr_methd` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `criteriatype` bigint(20) DEFAULT NULL,
  `method` tinyint(1) NOT NULL DEFAULT 0,
  `value` decimal(10,5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course completion aggregation methods for criteria' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_completion_criteria`
--

CREATE TABLE `mdlvx_course_completion_criteria` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `criteriatype` bigint(20) NOT NULL DEFAULT 0,
  `module` varchar(100) DEFAULT NULL,
  `moduleinstance` bigint(20) DEFAULT NULL,
  `courseinstance` bigint(20) DEFAULT NULL,
  `enrolperiod` bigint(20) DEFAULT NULL,
  `timeend` bigint(20) DEFAULT NULL,
  `gradepass` decimal(10,5) DEFAULT NULL,
  `role` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course completion criteria' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_completion_crit_compl`
--

CREATE TABLE `mdlvx_course_completion_crit_compl` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `criteriaid` bigint(20) NOT NULL DEFAULT 0,
  `gradefinal` decimal(10,5) DEFAULT NULL,
  `unenroled` bigint(20) DEFAULT NULL,
  `timecompleted` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course completion user records' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_completion_defaults`
--

CREATE TABLE `mdlvx_course_completion_defaults` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL,
  `module` bigint(20) NOT NULL,
  `completion` tinyint(1) NOT NULL DEFAULT 0,
  `completionview` tinyint(1) NOT NULL DEFAULT 0,
  `completionusegrade` tinyint(1) NOT NULL DEFAULT 0,
  `completionpassgrade` tinyint(1) NOT NULL DEFAULT 0,
  `completionexpected` bigint(20) NOT NULL DEFAULT 0,
  `customrules` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Default settings for activities completion' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_format_options`
--

CREATE TABLE `mdlvx_course_format_options` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `format` varchar(21) NOT NULL DEFAULT '',
  `sectionid` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores format-specific options for the course or course sect' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_modules`
--

CREATE TABLE `mdlvx_course_modules` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `module` bigint(20) NOT NULL DEFAULT 0,
  `instance` bigint(20) NOT NULL DEFAULT 0,
  `section` bigint(20) NOT NULL DEFAULT 0,
  `idnumber` varchar(100) DEFAULT NULL,
  `added` bigint(20) NOT NULL DEFAULT 0,
  `score` smallint(6) NOT NULL DEFAULT 0,
  `indent` mediumint(9) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `visibleoncoursepage` tinyint(1) NOT NULL DEFAULT 1,
  `visibleold` tinyint(1) NOT NULL DEFAULT 1,
  `groupmode` smallint(6) NOT NULL DEFAULT 0,
  `groupingid` bigint(20) NOT NULL DEFAULT 0,
  `completion` tinyint(1) NOT NULL DEFAULT 0,
  `completiongradeitemnumber` bigint(20) DEFAULT NULL,
  `completionview` tinyint(1) NOT NULL DEFAULT 0,
  `completionexpected` bigint(20) NOT NULL DEFAULT 0,
  `completionpassgrade` tinyint(1) NOT NULL DEFAULT 0,
  `showdescription` tinyint(1) NOT NULL DEFAULT 0,
  `availability` longtext DEFAULT NULL,
  `deletioninprogress` tinyint(1) NOT NULL DEFAULT 0,
  `downloadcontent` tinyint(1) DEFAULT 1,
  `lang` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='course_modules table retrofitted from MySQL' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_modules_completion`
--

CREATE TABLE `mdlvx_course_modules_completion` (
  `id` bigint(20) NOT NULL,
  `coursemoduleid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `completionstate` tinyint(1) NOT NULL,
  `overrideby` bigint(20) DEFAULT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the completion state (completed or not completed, etc' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_modules_viewed`
--

CREATE TABLE `mdlvx_course_modules_viewed` (
  `id` bigint(20) NOT NULL,
  `coursemoduleid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_published`
--

CREATE TABLE `mdlvx_course_published` (
  `id` bigint(20) NOT NULL,
  `huburl` varchar(255) DEFAULT NULL,
  `courseid` bigint(20) NOT NULL,
  `timepublished` bigint(20) NOT NULL,
  `enrollable` tinyint(1) NOT NULL DEFAULT 1,
  `hubcourseid` bigint(20) NOT NULL,
  `status` tinyint(1) DEFAULT 0,
  `timechecked` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Information about how and when an local courses were publish' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_request`
--

CREATE TABLE `mdlvx_course_request` (
  `id` bigint(20) NOT NULL,
  `fullname` varchar(1333) NOT NULL DEFAULT '',
  `shortname` varchar(255) NOT NULL DEFAULT '',
  `summary` longtext NOT NULL,
  `summaryformat` tinyint(4) NOT NULL DEFAULT 0,
  `category` bigint(20) NOT NULL DEFAULT 0,
  `reason` longtext NOT NULL,
  `requester` bigint(20) NOT NULL DEFAULT 0,
  `password` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='course requests' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_course_sections`
--

CREATE TABLE `mdlvx_course_sections` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `section` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) DEFAULT NULL,
  `summary` longtext DEFAULT NULL,
  `summaryformat` tinyint(4) NOT NULL DEFAULT 0,
  `sequence` longtext DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `availability` longtext DEFAULT NULL,
  `component` varchar(100) DEFAULT NULL,
  `itemid` bigint(10) DEFAULT NULL,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='to define the sections for each course' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customcert`
--

CREATE TABLE `mdlvx_customcert` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `templateid` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `requiredtime` bigint(20) NOT NULL DEFAULT 0,
  `verifyany` tinyint(1) NOT NULL DEFAULT 0,
  `deliveryoption` varchar(255) DEFAULT NULL,
  `emailstudents` tinyint(1) NOT NULL DEFAULT 0,
  `emailteachers` tinyint(1) NOT NULL DEFAULT 0,
  `emailothers` longtext DEFAULT NULL,
  `protection` varchar(255) NOT NULL DEFAULT '',
  `language` varchar(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines customcerts' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customcert_elements`
--

CREATE TABLE `mdlvx_customcert_elements` (
  `id` bigint(20) NOT NULL,
  `pageid` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `element` varchar(255) NOT NULL DEFAULT '',
  `data` longtext DEFAULT NULL,
  `font` varchar(255) DEFAULT NULL,
  `fontsize` bigint(20) DEFAULT NULL,
  `colour` varchar(50) DEFAULT NULL,
  `posx` bigint(20) DEFAULT NULL,
  `posy` bigint(20) DEFAULT NULL,
  `width` bigint(20) DEFAULT NULL,
  `refpoint` smallint(6) DEFAULT NULL,
  `alignment` varchar(1) NOT NULL DEFAULT 'L',
  `sequence` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the elements for a given page' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customcert_issues`
--

CREATE TABLE `mdlvx_customcert_issues` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `customcertid` bigint(20) NOT NULL DEFAULT 0,
  `code` varchar(40) DEFAULT NULL,
  `emailed` tinyint(1) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores each issue of a customcert' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customcert_pages`
--

CREATE TABLE `mdlvx_customcert_pages` (
  `id` bigint(20) NOT NULL,
  `templateid` bigint(20) NOT NULL DEFAULT 0,
  `width` bigint(20) NOT NULL DEFAULT 0,
  `height` bigint(20) NOT NULL DEFAULT 0,
  `leftmargin` bigint(20) NOT NULL DEFAULT 0,
  `rightmargin` bigint(20) NOT NULL DEFAULT 0,
  `sequence` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores each page of a custom cert' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customcert_templates`
--

CREATE TABLE `mdlvx_customcert_templates` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `contextid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores each customcert template' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customfield_category`
--

CREATE TABLE `mdlvx_customfield_category` (
  `id` bigint(20) NOT NULL,
  `name` varchar(400) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` bigint(20) DEFAULT NULL,
  `sortorder` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `component` varchar(100) NOT NULL DEFAULT '',
  `area` varchar(100) NOT NULL DEFAULT '',
  `itemid` bigint(20) NOT NULL DEFAULT 0,
  `contextid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='core_customfield category table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customfield_data`
--

CREATE TABLE `mdlvx_customfield_data` (
  `id` bigint(20) NOT NULL,
  `fieldid` bigint(20) NOT NULL,
  `instanceid` bigint(20) NOT NULL,
  `intvalue` bigint(20) DEFAULT NULL,
  `decvalue` decimal(10,5) DEFAULT NULL,
  `shortcharvalue` varchar(255) DEFAULT NULL,
  `charvalue` varchar(1333) DEFAULT NULL,
  `value` longtext NOT NULL,
  `valueformat` bigint(20) NOT NULL,
  `valuetrust` tinyint(2) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `contextid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='core_customfield data table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_customfield_field`
--

CREATE TABLE `mdlvx_customfield_field` (
  `id` bigint(20) NOT NULL,
  `shortname` varchar(100) NOT NULL DEFAULT '',
  `name` varchar(400) NOT NULL DEFAULT '',
  `type` varchar(100) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` bigint(20) DEFAULT NULL,
  `sortorder` bigint(20) DEFAULT NULL,
  `categoryid` bigint(20) DEFAULT NULL,
  `configdata` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='core_customfield field table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_data`
--

CREATE TABLE `mdlvx_data` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `comments` smallint(6) NOT NULL DEFAULT 0,
  `timeavailablefrom` bigint(20) NOT NULL DEFAULT 0,
  `timeavailableto` bigint(20) NOT NULL DEFAULT 0,
  `timeviewfrom` bigint(20) NOT NULL DEFAULT 0,
  `timeviewto` bigint(20) NOT NULL DEFAULT 0,
  `requiredentries` int(11) NOT NULL DEFAULT 0,
  `requiredentriestoview` int(11) NOT NULL DEFAULT 0,
  `maxentries` int(11) NOT NULL DEFAULT 0,
  `rssarticles` smallint(6) NOT NULL DEFAULT 0,
  `singletemplate` longtext DEFAULT NULL,
  `listtemplate` longtext DEFAULT NULL,
  `listtemplateheader` longtext DEFAULT NULL,
  `listtemplatefooter` longtext DEFAULT NULL,
  `addtemplate` longtext DEFAULT NULL,
  `rsstemplate` longtext DEFAULT NULL,
  `rsstitletemplate` longtext DEFAULT NULL,
  `csstemplate` longtext DEFAULT NULL,
  `jstemplate` longtext DEFAULT NULL,
  `asearchtemplate` longtext DEFAULT NULL,
  `approval` smallint(6) NOT NULL DEFAULT 0,
  `manageapproved` smallint(6) NOT NULL DEFAULT 1,
  `scale` bigint(20) NOT NULL DEFAULT 0,
  `assessed` bigint(20) NOT NULL DEFAULT 0,
  `assesstimestart` bigint(20) NOT NULL DEFAULT 0,
  `assesstimefinish` bigint(20) NOT NULL DEFAULT 0,
  `defaultsort` bigint(20) NOT NULL DEFAULT 0,
  `defaultsortdir` smallint(6) NOT NULL DEFAULT 0,
  `editany` smallint(6) NOT NULL DEFAULT 0,
  `notification` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `config` longtext DEFAULT NULL,
  `completionentries` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='all database activities' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_data_content`
--

CREATE TABLE `mdlvx_data_content` (
  `id` bigint(20) NOT NULL,
  `fieldid` bigint(20) NOT NULL DEFAULT 0,
  `recordid` bigint(20) NOT NULL DEFAULT 0,
  `content` longtext DEFAULT NULL,
  `content1` longtext DEFAULT NULL,
  `content2` longtext DEFAULT NULL,
  `content3` longtext DEFAULT NULL,
  `content4` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='the content introduced in each record/fields' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_data_fields`
--

CREATE TABLE `mdlvx_data_fields` (
  `id` bigint(20) NOT NULL,
  `dataid` bigint(20) NOT NULL DEFAULT 0,
  `type` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` longtext NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `param1` longtext DEFAULT NULL,
  `param2` longtext DEFAULT NULL,
  `param3` longtext DEFAULT NULL,
  `param4` longtext DEFAULT NULL,
  `param5` longtext DEFAULT NULL,
  `param6` longtext DEFAULT NULL,
  `param7` longtext DEFAULT NULL,
  `param8` longtext DEFAULT NULL,
  `param9` longtext DEFAULT NULL,
  `param10` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='every field available' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_data_records`
--

CREATE TABLE `mdlvx_data_records` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) NOT NULL DEFAULT 0,
  `dataid` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `approved` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='every record introduced' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol`
--

CREATE TABLE `mdlvx_enrol` (
  `id` bigint(20) NOT NULL,
  `enrol` varchar(20) NOT NULL DEFAULT '',
  `status` bigint(20) NOT NULL DEFAULT 0,
  `courseid` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) DEFAULT NULL,
  `enrolperiod` bigint(20) DEFAULT 0,
  `enrolstartdate` bigint(20) DEFAULT 0,
  `enrolenddate` bigint(20) DEFAULT 0,
  `expirynotify` tinyint(1) DEFAULT 0,
  `expirythreshold` bigint(20) DEFAULT 0,
  `notifyall` tinyint(1) DEFAULT 0,
  `password` varchar(50) DEFAULT NULL,
  `cost` varchar(20) DEFAULT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `roleid` bigint(20) DEFAULT 0,
  `customint1` bigint(20) DEFAULT NULL,
  `customint2` bigint(20) DEFAULT NULL,
  `customint3` bigint(20) DEFAULT NULL,
  `customint4` bigint(20) DEFAULT NULL,
  `customint5` bigint(20) DEFAULT NULL,
  `customint6` bigint(20) DEFAULT NULL,
  `customint7` bigint(20) DEFAULT NULL,
  `customint8` bigint(20) DEFAULT NULL,
  `customchar1` varchar(255) DEFAULT NULL,
  `customchar2` varchar(255) DEFAULT NULL,
  `customchar3` varchar(1333) DEFAULT NULL,
  `customdec1` decimal(12,7) DEFAULT NULL,
  `customdec2` decimal(12,7) DEFAULT NULL,
  `customtext1` longtext DEFAULT NULL,
  `customtext2` longtext DEFAULT NULL,
  `customtext3` longtext DEFAULT NULL,
  `customtext4` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Instances of enrolment plugins used in courses, fields marke' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_flatfile`
--

CREATE TABLE `mdlvx_enrol_flatfile` (
  `id` bigint(20) NOT NULL,
  `action` varchar(30) NOT NULL DEFAULT '',
  `roleid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `timestart` bigint(20) NOT NULL DEFAULT 0,
  `timeend` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='enrol_flatfile table retrofitted from MySQL' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_app_registration`
--

CREATE TABLE `mdlvx_enrol_lti_app_registration` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `platformid` longtext DEFAULT NULL,
  `clientid` varchar(1333) DEFAULT NULL,
  `platformclienthash` varchar(64) DEFAULT NULL,
  `authenticationrequesturl` longtext DEFAULT NULL,
  `jwksurl` longtext DEFAULT NULL,
  `accesstokenurl` longtext DEFAULT NULL,
  `uniqueid` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `platformuniqueidhash` varchar(64) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_context`
--

CREATE TABLE `mdlvx_enrol_lti_context` (
  `id` bigint(20) NOT NULL,
  `contextid` varchar(255) NOT NULL DEFAULT '',
  `ltideploymentid` bigint(20) NOT NULL,
  `type` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_deployment`
--

CREATE TABLE `mdlvx_enrol_lti_deployment` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `deploymentid` varchar(255) NOT NULL DEFAULT '',
  `platformid` bigint(20) NOT NULL,
  `legacyconsumerkey` varchar(255) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_consumer`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_consumer` (
  `id` bigint(20) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `consumerkey256` varchar(255) NOT NULL DEFAULT '',
  `consumerkey` longtext DEFAULT NULL,
  `secret` varchar(1024) NOT NULL DEFAULT '',
  `ltiversion` varchar(10) DEFAULT NULL,
  `consumername` varchar(255) DEFAULT NULL,
  `consumerversion` varchar(255) DEFAULT NULL,
  `consumerguid` varchar(1024) DEFAULT NULL,
  `profile` longtext DEFAULT NULL,
  `toolproxy` longtext DEFAULT NULL,
  `settings` longtext DEFAULT NULL,
  `protected` tinyint(1) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `enablefrom` bigint(20) DEFAULT NULL,
  `enableuntil` bigint(20) DEFAULT NULL,
  `lastaccess` bigint(20) DEFAULT NULL,
  `created` bigint(20) NOT NULL,
  `updated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LTI consumers interacting with moodle' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_context`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_context` (
  `id` bigint(20) NOT NULL,
  `consumerid` bigint(20) NOT NULL,
  `lticontextkey` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(100) DEFAULT NULL,
  `settings` longtext DEFAULT NULL,
  `created` bigint(20) NOT NULL,
  `updated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Information about a specific LTI contexts from the consumers' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_nonce`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_nonce` (
  `id` bigint(20) NOT NULL,
  `consumerid` bigint(20) NOT NULL,
  `value` varchar(64) NOT NULL DEFAULT '',
  `expires` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nonce used for authentication between moodle and a consumer' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_resource_link`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_resource_link` (
  `id` bigint(20) NOT NULL,
  `contextid` bigint(20) DEFAULT NULL,
  `consumerid` bigint(20) DEFAULT NULL,
  `ltiresourcelinkkey` varchar(255) NOT NULL DEFAULT '',
  `settings` longtext DEFAULT NULL,
  `primaryresourcelinkid` bigint(20) DEFAULT NULL,
  `shareapproved` tinyint(1) DEFAULT NULL,
  `created` bigint(20) NOT NULL,
  `updated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link from the consumer to the tool' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_share_key`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_share_key` (
  `id` bigint(20) NOT NULL,
  `sharekey` varchar(32) NOT NULL DEFAULT '',
  `resourcelinkid` bigint(20) NOT NULL,
  `autoapprove` tinyint(1) NOT NULL,
  `expires` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resource link share key' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_tool_proxy`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_tool_proxy` (
  `id` bigint(20) NOT NULL,
  `toolproxykey` varchar(32) NOT NULL DEFAULT '',
  `consumerid` bigint(20) NOT NULL,
  `toolproxy` longtext NOT NULL,
  `created` bigint(20) NOT NULL,
  `updated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='A tool proxy between moodle and a consumer' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_lti2_user_result`
--

CREATE TABLE `mdlvx_enrol_lti_lti2_user_result` (
  `id` bigint(20) NOT NULL,
  `resourcelinkid` bigint(20) NOT NULL,
  `ltiuserkey` varchar(255) NOT NULL DEFAULT '',
  `ltiresultsourcedid` varchar(1024) NOT NULL DEFAULT '',
  `created` bigint(20) NOT NULL,
  `updated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Results for each user for each resource link' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_resource_link`
--

CREATE TABLE `mdlvx_enrol_lti_resource_link` (
  `id` bigint(20) NOT NULL,
  `resourcelinkid` varchar(255) NOT NULL DEFAULT '',
  `resourceid` bigint(20) NOT NULL,
  `ltideploymentid` bigint(20) NOT NULL,
  `lticontextid` bigint(20) DEFAULT NULL,
  `lineitemsservice` varchar(1333) DEFAULT NULL,
  `lineitemservice` varchar(1333) DEFAULT NULL,
  `lineitemscope` varchar(255) DEFAULT NULL,
  `resultscope` varchar(255) DEFAULT NULL,
  `scorescope` varchar(255) DEFAULT NULL,
  `contextmembershipsurl` varchar(1333) DEFAULT NULL,
  `nrpsserviceversions` varchar(255) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_tools`
--

CREATE TABLE `mdlvx_enrol_lti_tools` (
  `id` bigint(20) NOT NULL,
  `enrolid` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `ltiversion` varchar(15) NOT NULL DEFAULT 'LTI-1p3',
  `uuid` varchar(36) DEFAULT NULL,
  `provisioningmodelearner` tinyint(4) DEFAULT NULL,
  `provisioningmodeinstructor` tinyint(4) DEFAULT NULL,
  `institution` varchar(40) NOT NULL DEFAULT '',
  `lang` varchar(30) NOT NULL DEFAULT 'en',
  `timezone` varchar(100) NOT NULL DEFAULT '99',
  `maxenrolled` bigint(20) NOT NULL DEFAULT 0,
  `maildisplay` tinyint(4) NOT NULL DEFAULT 2,
  `city` varchar(120) NOT NULL DEFAULT '',
  `country` varchar(2) NOT NULL DEFAULT '',
  `gradesync` tinyint(1) NOT NULL DEFAULT 0,
  `gradesynccompletion` tinyint(1) NOT NULL DEFAULT 0,
  `membersync` tinyint(1) NOT NULL DEFAULT 0,
  `membersyncmode` tinyint(1) NOT NULL DEFAULT 0,
  `roleinstructor` bigint(20) NOT NULL,
  `rolelearner` bigint(20) NOT NULL,
  `secret` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='List of tools provided to the remote system' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_tool_consumer_map`
--

CREATE TABLE `mdlvx_enrol_lti_tool_consumer_map` (
  `id` bigint(20) NOT NULL,
  `toolid` bigint(20) NOT NULL,
  `consumerid` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table that maps the published tool to tool consumers.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_users`
--

CREATE TABLE `mdlvx_enrol_lti_users` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `toolid` bigint(20) NOT NULL,
  `serviceurl` longtext DEFAULT NULL,
  `sourceid` longtext DEFAULT NULL,
  `ltideploymentid` bigint(20) DEFAULT NULL,
  `consumerkey` longtext DEFAULT NULL,
  `consumersecret` longtext DEFAULT NULL,
  `membershipsurl` longtext DEFAULT NULL,
  `membershipsid` longtext DEFAULT NULL,
  `lastgrade` decimal(10,5) DEFAULT NULL,
  `lastaccess` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User access log and gradeback data' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_lti_user_resource_link`
--

CREATE TABLE `mdlvx_enrol_lti_user_resource_link` (
  `id` bigint(20) NOT NULL,
  `ltiuserid` bigint(20) NOT NULL,
  `resourcelinkid` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_enrol_paypal`
--

CREATE TABLE `mdlvx_enrol_paypal` (
  `id` bigint(20) NOT NULL,
  `business` varchar(255) NOT NULL DEFAULT '',
  `receiver_email` varchar(255) NOT NULL DEFAULT '',
  `receiver_id` varchar(255) NOT NULL DEFAULT '',
  `item_name` varchar(255) NOT NULL DEFAULT '',
  `courseid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `instanceid` bigint(20) NOT NULL DEFAULT 0,
  `memo` varchar(255) NOT NULL DEFAULT '',
  `tax` varchar(255) NOT NULL DEFAULT '',
  `option_name1` varchar(255) NOT NULL DEFAULT '',
  `option_selection1_x` varchar(255) NOT NULL DEFAULT '',
  `option_name2` varchar(255) NOT NULL DEFAULT '',
  `option_selection2_x` varchar(255) NOT NULL DEFAULT '',
  `payment_status` varchar(255) NOT NULL DEFAULT '',
  `pending_reason` varchar(255) NOT NULL DEFAULT '',
  `reason_code` varchar(30) NOT NULL DEFAULT '',
  `txn_id` varchar(255) NOT NULL DEFAULT '',
  `parent_txn_id` varchar(255) NOT NULL DEFAULT '',
  `payment_type` varchar(30) NOT NULL DEFAULT '',
  `timeupdated` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Holds all known information about PayPal transactions' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_event`
--

CREATE TABLE `mdlvx_event` (
  `id` bigint(20) NOT NULL,
  `name` longtext NOT NULL,
  `description` longtext NOT NULL,
  `format` smallint(6) NOT NULL DEFAULT 0,
  `categoryid` bigint(20) NOT NULL DEFAULT 0,
  `courseid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `repeatid` bigint(20) NOT NULL DEFAULT 0,
  `component` varchar(100) DEFAULT NULL,
  `modulename` varchar(20) NOT NULL DEFAULT '',
  `instance` bigint(20) NOT NULL DEFAULT 0,
  `type` smallint(6) NOT NULL DEFAULT 0,
  `eventtype` varchar(20) NOT NULL DEFAULT '',
  `timestart` bigint(20) NOT NULL DEFAULT 0,
  `timeduration` bigint(20) NOT NULL DEFAULT 0,
  `timesort` bigint(20) DEFAULT NULL,
  `visible` smallint(6) NOT NULL DEFAULT 1,
  `uuid` varchar(255) NOT NULL DEFAULT '',
  `sequence` bigint(20) NOT NULL DEFAULT 1,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `subscriptionid` bigint(20) DEFAULT NULL,
  `priority` bigint(20) DEFAULT NULL,
  `location` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='For everything with a time associated to it' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_events_handlers`
--

CREATE TABLE `mdlvx_events_handlers` (
  `id` bigint(20) NOT NULL,
  `eventname` varchar(166) NOT NULL DEFAULT '',
  `component` varchar(166) NOT NULL DEFAULT '',
  `handlerfile` varchar(255) NOT NULL DEFAULT '',
  `handlerfunction` longtext DEFAULT NULL,
  `schedule` varchar(255) DEFAULT NULL,
  `status` bigint(20) NOT NULL DEFAULT 0,
  `internal` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table is for storing which components requests what typ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_events_queue`
--

CREATE TABLE `mdlvx_events_queue` (
  `id` bigint(20) NOT NULL,
  `eventdata` longtext NOT NULL,
  `stackdump` longtext DEFAULT NULL,
  `userid` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table is for storing queued events. It stores only one ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_events_queue_handlers`
--

CREATE TABLE `mdlvx_events_queue_handlers` (
  `id` bigint(20) NOT NULL,
  `queuedeventid` bigint(20) NOT NULL,
  `handlerid` bigint(20) NOT NULL,
  `status` bigint(20) DEFAULT NULL,
  `errormessage` longtext DEFAULT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This is the list of queued handlers for processing. The even' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_event_subscriptions`
--

CREATE TABLE `mdlvx_event_subscriptions` (
  `id` bigint(20) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `categoryid` bigint(20) NOT NULL DEFAULT 0,
  `courseid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `eventtype` varchar(20) NOT NULL DEFAULT '',
  `pollinterval` bigint(20) NOT NULL DEFAULT 0,
  `lastupdated` bigint(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks subscriptions to remote calendars.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_external_functions`
--

CREATE TABLE `mdlvx_external_functions` (
  `id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL DEFAULT '',
  `classname` varchar(100) NOT NULL DEFAULT '',
  `methodname` varchar(100) NOT NULL DEFAULT '',
  `classpath` varchar(255) DEFAULT NULL,
  `component` varchar(100) NOT NULL DEFAULT '',
  `capabilities` varchar(255) DEFAULT NULL,
  `services` varchar(1333) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='list of all external functions' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_external_services`
--

CREATE TABLE `mdlvx_external_services` (
  `id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL,
  `requiredcapability` varchar(150) DEFAULT NULL,
  `restrictedusers` tinyint(1) NOT NULL,
  `component` varchar(100) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `shortname` varchar(255) DEFAULT NULL,
  `downloadfiles` tinyint(1) NOT NULL DEFAULT 0,
  `uploadfiles` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='built in and custom external services' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_external_services_functions`
--

CREATE TABLE `mdlvx_external_services_functions` (
  `id` bigint(20) NOT NULL,
  `externalserviceid` bigint(20) NOT NULL,
  `functionname` varchar(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='lists functions available in each service group' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_external_services_users`
--

CREATE TABLE `mdlvx_external_services_users` (
  `id` bigint(20) NOT NULL,
  `externalserviceid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `iprestriction` varchar(255) DEFAULT NULL,
  `validuntil` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='users allowed to use services with restricted users flag' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_external_tokens`
--

CREATE TABLE `mdlvx_external_tokens` (
  `id` bigint(20) NOT NULL,
  `token` varchar(128) NOT NULL DEFAULT '',
  `privatetoken` varchar(64) DEFAULT NULL,
  `tokentype` smallint(6) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `externalserviceid` bigint(20) NOT NULL,
  `sid` varchar(128) DEFAULT NULL,
  `contextid` bigint(20) NOT NULL,
  `creatorid` bigint(20) NOT NULL DEFAULT 1,
  `iprestriction` varchar(255) DEFAULT NULL,
  `validuntil` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `lastaccess` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security tokens for accessing of external services' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_favourite`
--

CREATE TABLE `mdlvx_favourite` (
  `id` bigint(20) NOT NULL,
  `component` varchar(100) NOT NULL DEFAULT '',
  `itemtype` varchar(100) NOT NULL DEFAULT '',
  `itemid` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `ordering` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the relationship between an arbitrary item (itemtype,' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback`
--

CREATE TABLE `mdlvx_feedback` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `anonymous` tinyint(1) NOT NULL DEFAULT 1,
  `email_notification` tinyint(1) NOT NULL DEFAULT 1,
  `multiple_submit` tinyint(1) NOT NULL DEFAULT 1,
  `autonumbering` tinyint(1) NOT NULL DEFAULT 1,
  `site_after_submit` varchar(255) NOT NULL DEFAULT '',
  `page_after_submit` longtext NOT NULL,
  `page_after_submitformat` tinyint(4) NOT NULL DEFAULT 0,
  `publish_stats` tinyint(1) NOT NULL DEFAULT 0,
  `timeopen` bigint(20) NOT NULL DEFAULT 0,
  `timeclose` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `completionsubmit` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='all feedbacks' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_completed`
--

CREATE TABLE `mdlvx_feedback_completed` (
  `id` bigint(20) NOT NULL,
  `feedback` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `random_response` bigint(20) NOT NULL DEFAULT 0,
  `anonymous_response` tinyint(1) NOT NULL DEFAULT 0,
  `courseid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='filled out feedback' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_completedtmp`
--

CREATE TABLE `mdlvx_feedback_completedtmp` (
  `id` bigint(20) NOT NULL,
  `feedback` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `guestid` varchar(255) NOT NULL DEFAULT '',
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `random_response` bigint(20) NOT NULL DEFAULT 0,
  `anonymous_response` tinyint(1) NOT NULL DEFAULT 0,
  `courseid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='filled out feedback' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_item`
--

CREATE TABLE `mdlvx_feedback_item` (
  `id` bigint(20) NOT NULL,
  `feedback` bigint(20) NOT NULL DEFAULT 0,
  `template` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `label` varchar(255) NOT NULL DEFAULT '',
  `presentation` longtext NOT NULL,
  `typ` varchar(255) NOT NULL DEFAULT '',
  `hasvalue` tinyint(1) NOT NULL DEFAULT 0,
  `position` smallint(6) NOT NULL DEFAULT 0,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `dependitem` bigint(20) NOT NULL DEFAULT 0,
  `dependvalue` varchar(255) NOT NULL DEFAULT '',
  `options` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='feedback_items' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_sitecourse_map`
--

CREATE TABLE `mdlvx_feedback_sitecourse_map` (
  `id` bigint(20) NOT NULL,
  `feedbackid` bigint(20) NOT NULL DEFAULT 0,
  `courseid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='feedback sitecourse map' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_template`
--

CREATE TABLE `mdlvx_feedback_template` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `ispublic` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='templates of feedbackstructures' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_value`
--

CREATE TABLE `mdlvx_feedback_value` (
  `id` bigint(20) NOT NULL,
  `course_id` bigint(20) NOT NULL DEFAULT 0,
  `item` bigint(20) NOT NULL DEFAULT 0,
  `completed` bigint(20) NOT NULL DEFAULT 0,
  `tmp_completed` bigint(20) NOT NULL DEFAULT 0,
  `value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='values of the completeds' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_feedback_valuetmp`
--

CREATE TABLE `mdlvx_feedback_valuetmp` (
  `id` bigint(20) NOT NULL,
  `course_id` bigint(20) NOT NULL DEFAULT 0,
  `item` bigint(20) NOT NULL DEFAULT 0,
  `completed` bigint(20) NOT NULL DEFAULT 0,
  `tmp_completed` bigint(20) NOT NULL DEFAULT 0,
  `value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='values of the completedstmp' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_files`
--

CREATE TABLE `mdlvx_files` (
  `id` bigint(20) NOT NULL,
  `contenthash` varchar(40) NOT NULL DEFAULT '',
  `pathnamehash` varchar(40) NOT NULL DEFAULT '',
  `contextid` bigint(20) NOT NULL,
  `component` varchar(100) NOT NULL DEFAULT '',
  `filearea` varchar(50) NOT NULL DEFAULT '',
  `itemid` bigint(20) NOT NULL,
  `filepath` varchar(255) NOT NULL DEFAULT '',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `userid` bigint(20) DEFAULT NULL,
  `filesize` bigint(20) NOT NULL,
  `mimetype` varchar(100) DEFAULT NULL,
  `status` bigint(20) NOT NULL DEFAULT 0,
  `source` longtext DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `license` varchar(255) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `referencefileid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='description of files, content is stored in sha1 file pool' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_files_reference`
--

CREATE TABLE `mdlvx_files_reference` (
  `id` bigint(20) NOT NULL,
  `repositoryid` bigint(20) NOT NULL,
  `lastsync` bigint(20) DEFAULT NULL,
  `reference` longtext DEFAULT NULL,
  `referencehash` varchar(40) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store files references' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_file_conversion`
--

CREATE TABLE `mdlvx_file_conversion` (
  `id` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `sourcefileid` bigint(20) NOT NULL,
  `targetformat` varchar(100) NOT NULL DEFAULT '',
  `status` bigint(20) DEFAULT 0,
  `statusmessage` longtext DEFAULT NULL,
  `converter` varchar(255) DEFAULT NULL,
  `destfileid` bigint(20) DEFAULT NULL,
  `data` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table to track file conversions.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_filter_active`
--

CREATE TABLE `mdlvx_filter_active` (
  `id` bigint(20) NOT NULL,
  `filter` varchar(32) NOT NULL DEFAULT '',
  `contextid` bigint(20) NOT NULL,
  `active` smallint(6) NOT NULL,
  `sortorder` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores information about which filters are active in which c' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_filter_config`
--

CREATE TABLE `mdlvx_filter_config` (
  `id` bigint(20) NOT NULL,
  `filter` varchar(32) NOT NULL DEFAULT '',
  `contextid` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores per-context configuration settings for filters which ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_folder`
--

CREATE TABLE `mdlvx_folder` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `revision` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `display` smallint(6) NOT NULL DEFAULT 0,
  `showexpanded` tinyint(1) NOT NULL DEFAULT 1,
  `showdownloadfolder` tinyint(1) NOT NULL DEFAULT 1,
  `forcedownload` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='each record is one folder resource' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum`
--

CREATE TABLE `mdlvx_forum` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `type` varchar(20) NOT NULL DEFAULT 'general',
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `duedate` bigint(20) NOT NULL DEFAULT 0,
  `cutoffdate` bigint(20) NOT NULL DEFAULT 0,
  `assessed` bigint(20) NOT NULL DEFAULT 0,
  `assesstimestart` bigint(20) NOT NULL DEFAULT 0,
  `assesstimefinish` bigint(20) NOT NULL DEFAULT 0,
  `scale` bigint(20) NOT NULL DEFAULT 0,
  `grade_forum` bigint(20) NOT NULL DEFAULT 0,
  `grade_forum_notify` smallint(6) NOT NULL DEFAULT 0,
  `maxbytes` bigint(20) NOT NULL DEFAULT 0,
  `maxattachments` bigint(20) NOT NULL DEFAULT 1,
  `forcesubscribe` tinyint(1) NOT NULL DEFAULT 0,
  `trackingtype` tinyint(4) NOT NULL DEFAULT 1,
  `rsstype` tinyint(4) NOT NULL DEFAULT 0,
  `rssarticles` tinyint(4) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `warnafter` bigint(20) NOT NULL DEFAULT 0,
  `blockafter` bigint(20) NOT NULL DEFAULT 0,
  `blockperiod` bigint(20) NOT NULL DEFAULT 0,
  `completiondiscussions` int(11) NOT NULL DEFAULT 0,
  `completionreplies` int(11) NOT NULL DEFAULT 0,
  `completionposts` int(11) NOT NULL DEFAULT 0,
  `displaywordcount` tinyint(1) NOT NULL DEFAULT 0,
  `lockdiscussionafter` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Forums contain and structure discussion' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_digests`
--

CREATE TABLE `mdlvx_forum_digests` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `forum` bigint(20) NOT NULL,
  `maildigest` tinyint(1) NOT NULL DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Keeps track of user mail delivery preferences for each forum' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_discussions`
--

CREATE TABLE `mdlvx_forum_discussions` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `forum` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `firstpost` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) NOT NULL DEFAULT -1,
  `assessed` tinyint(1) NOT NULL DEFAULT 1,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `usermodified` bigint(20) NOT NULL DEFAULT 0,
  `timestart` bigint(20) NOT NULL DEFAULT 0,
  `timeend` bigint(20) NOT NULL DEFAULT 0,
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `timelocked` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Forums are composed of discussions' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_discussion_subs`
--

CREATE TABLE `mdlvx_forum_discussion_subs` (
  `id` bigint(20) NOT NULL,
  `forum` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `discussion` bigint(20) NOT NULL,
  `preference` bigint(20) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users may choose to subscribe and unsubscribe from specific ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_grades`
--

CREATE TABLE `mdlvx_forum_grades` (
  `id` bigint(20) NOT NULL,
  `forum` bigint(20) NOT NULL,
  `itemnumber` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `grade` decimal(10,5) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grading data for forum instances' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_posts`
--

CREATE TABLE `mdlvx_forum_posts` (
  `id` bigint(20) NOT NULL,
  `discussion` bigint(20) NOT NULL DEFAULT 0,
  `parent` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `created` bigint(20) NOT NULL DEFAULT 0,
  `modified` bigint(20) NOT NULL DEFAULT 0,
  `mailed` tinyint(4) NOT NULL DEFAULT 0,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `message` longtext NOT NULL,
  `messageformat` tinyint(4) NOT NULL DEFAULT 0,
  `messagetrust` tinyint(4) NOT NULL DEFAULT 0,
  `attachment` varchar(100) NOT NULL DEFAULT '',
  `totalscore` smallint(6) NOT NULL DEFAULT 0,
  `mailnow` bigint(20) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `privatereplyto` bigint(20) NOT NULL DEFAULT 0,
  `wordcount` bigint(20) DEFAULT NULL,
  `charcount` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='All posts are stored in this table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_queue`
--

CREATE TABLE `mdlvx_forum_queue` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `discussionid` bigint(20) NOT NULL DEFAULT 0,
  `postid` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='For keeping track of posts that will be mailed in digest for' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_read`
--

CREATE TABLE `mdlvx_forum_read` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `forumid` bigint(20) NOT NULL DEFAULT 0,
  `discussionid` bigint(20) NOT NULL DEFAULT 0,
  `postid` bigint(20) NOT NULL DEFAULT 0,
  `firstread` bigint(20) NOT NULL DEFAULT 0,
  `lastread` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks each users read posts' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_subscriptions`
--

CREATE TABLE `mdlvx_forum_subscriptions` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `forum` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Keeps track of who is subscribed to what forum' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_forum_track_prefs`
--

CREATE TABLE `mdlvx_forum_track_prefs` (
  `id` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `forumid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks each users untracked forums' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_glossary`
--

CREATE TABLE `mdlvx_glossary` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `allowduplicatedentries` tinyint(4) NOT NULL DEFAULT 0,
  `displayformat` varchar(50) NOT NULL DEFAULT 'dictionary',
  `mainglossary` tinyint(4) NOT NULL DEFAULT 0,
  `showspecial` tinyint(4) NOT NULL DEFAULT 1,
  `showalphabet` tinyint(4) NOT NULL DEFAULT 1,
  `showall` tinyint(4) NOT NULL DEFAULT 1,
  `allowcomments` tinyint(4) NOT NULL DEFAULT 0,
  `allowprintview` tinyint(4) NOT NULL DEFAULT 1,
  `usedynalink` tinyint(4) NOT NULL DEFAULT 1,
  `defaultapproval` tinyint(4) NOT NULL DEFAULT 1,
  `approvaldisplayformat` varchar(50) NOT NULL DEFAULT 'default',
  `globalglossary` tinyint(4) NOT NULL DEFAULT 0,
  `entbypage` smallint(6) NOT NULL DEFAULT 10,
  `editalways` tinyint(4) NOT NULL DEFAULT 0,
  `rsstype` tinyint(4) NOT NULL DEFAULT 0,
  `rssarticles` tinyint(4) NOT NULL DEFAULT 0,
  `assessed` bigint(20) NOT NULL DEFAULT 0,
  `assesstimestart` bigint(20) NOT NULL DEFAULT 0,
  `assesstimefinish` bigint(20) NOT NULL DEFAULT 0,
  `scale` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `completionentries` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='all glossaries' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_glossary_alias`
--

CREATE TABLE `mdlvx_glossary_alias` (
  `id` bigint(20) NOT NULL,
  `entryid` bigint(20) NOT NULL DEFAULT 0,
  `alias` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='entries alias' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_glossary_categories`
--

CREATE TABLE `mdlvx_glossary_categories` (
  `id` bigint(20) NOT NULL,
  `glossaryid` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `usedynalink` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='all categories for glossary entries' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_glossary_entries`
--

CREATE TABLE `mdlvx_glossary_entries` (
  `id` bigint(20) NOT NULL,
  `glossaryid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `concept` varchar(255) NOT NULL DEFAULT '',
  `definition` longtext NOT NULL,
  `definitionformat` tinyint(4) NOT NULL DEFAULT 0,
  `definitiontrust` tinyint(4) NOT NULL DEFAULT 0,
  `attachment` varchar(100) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `teacherentry` tinyint(4) NOT NULL DEFAULT 0,
  `sourceglossaryid` bigint(20) NOT NULL DEFAULT 0,
  `usedynalink` tinyint(4) NOT NULL DEFAULT 1,
  `casesensitive` tinyint(4) NOT NULL DEFAULT 0,
  `fullmatch` tinyint(4) NOT NULL DEFAULT 1,
  `approved` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='all glossary entries' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_glossary_entries_categories`
--

CREATE TABLE `mdlvx_glossary_entries_categories` (
  `id` bigint(20) NOT NULL,
  `categoryid` bigint(20) NOT NULL DEFAULT 0,
  `entryid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='categories of each glossary entry' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_glossary_formats`
--

CREATE TABLE `mdlvx_glossary_formats` (
  `id` bigint(20) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `popupformatname` varchar(50) NOT NULL DEFAULT '',
  `visible` tinyint(4) NOT NULL DEFAULT 1,
  `showgroup` tinyint(4) NOT NULL DEFAULT 1,
  `showtabs` varchar(100) DEFAULT NULL,
  `defaultmode` varchar(50) NOT NULL DEFAULT '',
  `defaulthook` varchar(50) NOT NULL DEFAULT '',
  `sortkey` varchar(50) NOT NULL DEFAULT '',
  `sortorder` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Setting of the display formats' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradepenalty_duedate_rule`
--

CREATE TABLE `mdlvx_gradepenalty_duedate_rule` (
  `id` bigint(10) NOT NULL,
  `contextid` bigint(10) NOT NULL,
  `sortorder` bigint(10) NOT NULL DEFAULT 0,
  `overdueby` bigint(10) NOT NULL,
  `penalty` double(10,0) NOT NULL,
  `usermodified` bigint(10) NOT NULL DEFAULT 0,
  `timecreated` bigint(10) NOT NULL DEFAULT 0,
  `timemodified` bigint(10) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Penalty rules' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_categories`
--

CREATE TABLE `mdlvx_grade_categories` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `parent` bigint(20) DEFAULT NULL,
  `depth` bigint(20) NOT NULL DEFAULT 0,
  `path` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) NOT NULL DEFAULT '',
  `aggregation` bigint(20) NOT NULL DEFAULT 0,
  `keephigh` bigint(20) NOT NULL DEFAULT 0,
  `droplow` bigint(20) NOT NULL DEFAULT 0,
  `aggregateonlygraded` tinyint(1) NOT NULL DEFAULT 0,
  `aggregateoutcomes` tinyint(1) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `hidden` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table keeps information about categories, used for grou' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_categories_history`
--

CREATE TABLE `mdlvx_grade_categories_history` (
  `id` bigint(20) NOT NULL,
  `action` bigint(20) NOT NULL DEFAULT 0,
  `oldid` bigint(20) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `loggeduser` bigint(20) DEFAULT NULL,
  `courseid` bigint(20) NOT NULL,
  `parent` bigint(20) DEFAULT NULL,
  `depth` bigint(20) NOT NULL DEFAULT 0,
  `path` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) NOT NULL DEFAULT '',
  `aggregation` bigint(20) NOT NULL DEFAULT 0,
  `keephigh` bigint(20) NOT NULL DEFAULT 0,
  `droplow` bigint(20) NOT NULL DEFAULT 0,
  `aggregateonlygraded` tinyint(1) NOT NULL DEFAULT 0,
  `aggregateoutcomes` tinyint(1) NOT NULL DEFAULT 0,
  `aggregatesubcats` tinyint(1) NOT NULL DEFAULT 0,
  `hidden` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History of grade_categories' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_grades`
--

CREATE TABLE `mdlvx_grade_grades` (
  `id` bigint(20) NOT NULL,
  `itemid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `rawgrade` decimal(10,5) DEFAULT NULL,
  `rawgrademax` decimal(10,5) NOT NULL DEFAULT 100.00000,
  `rawgrademin` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `rawscaleid` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) DEFAULT NULL,
  `finalgrade` decimal(10,5) DEFAULT NULL,
  `hidden` bigint(20) NOT NULL DEFAULT 0,
  `locked` bigint(20) NOT NULL DEFAULT 0,
  `locktime` bigint(20) NOT NULL DEFAULT 0,
  `exported` bigint(20) NOT NULL DEFAULT 0,
  `overridden` bigint(20) NOT NULL DEFAULT 0,
  `excluded` bigint(20) NOT NULL DEFAULT 0,
  `feedback` longtext DEFAULT NULL,
  `feedbackformat` bigint(20) NOT NULL DEFAULT 0,
  `information` longtext DEFAULT NULL,
  `informationformat` bigint(20) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `aggregationstatus` varchar(10) NOT NULL DEFAULT 'unknown',
  `aggregationweight` decimal(10,5) DEFAULT NULL,
  `deductedmark` decimal(10,5) NOT NULL DEFAULT 0.00000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='grade_grades  This table keeps individual grades for each us' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_grades_history`
--

CREATE TABLE `mdlvx_grade_grades_history` (
  `id` bigint(20) NOT NULL,
  `action` bigint(20) NOT NULL DEFAULT 0,
  `oldid` bigint(20) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `loggeduser` bigint(20) DEFAULT NULL,
  `itemid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `rawgrade` decimal(10,5) DEFAULT NULL,
  `rawgrademax` decimal(10,5) NOT NULL DEFAULT 100.00000,
  `rawgrademin` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `rawscaleid` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) DEFAULT NULL,
  `finalgrade` decimal(10,5) DEFAULT NULL,
  `hidden` bigint(20) NOT NULL DEFAULT 0,
  `locked` bigint(20) NOT NULL DEFAULT 0,
  `locktime` bigint(20) NOT NULL DEFAULT 0,
  `exported` bigint(20) NOT NULL DEFAULT 0,
  `overridden` bigint(20) NOT NULL DEFAULT 0,
  `excluded` bigint(20) NOT NULL DEFAULT 0,
  `feedback` longtext DEFAULT NULL,
  `feedbackformat` bigint(20) NOT NULL DEFAULT 0,
  `information` longtext DEFAULT NULL,
  `informationformat` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_import_newitem`
--

CREATE TABLE `mdlvx_grade_import_newitem` (
  `id` bigint(20) NOT NULL,
  `itemname` varchar(255) NOT NULL DEFAULT '',
  `importcode` bigint(20) NOT NULL,
  `importer` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='temporary table for storing new grade_item names from grade ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_import_values`
--

CREATE TABLE `mdlvx_grade_import_values` (
  `id` bigint(20) NOT NULL,
  `itemid` bigint(20) DEFAULT NULL,
  `newgradeitem` bigint(20) DEFAULT NULL,
  `userid` bigint(20) NOT NULL,
  `finalgrade` decimal(10,5) DEFAULT NULL,
  `feedback` longtext DEFAULT NULL,
  `importcode` bigint(20) NOT NULL,
  `importer` bigint(20) DEFAULT NULL,
  `importonlyfeedback` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporary table for importing grades' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_items`
--

CREATE TABLE `mdlvx_grade_items` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) DEFAULT NULL,
  `categoryid` bigint(20) DEFAULT NULL,
  `itemname` varchar(255) DEFAULT NULL,
  `itemtype` varchar(30) NOT NULL DEFAULT '',
  `itemmodule` varchar(30) DEFAULT NULL,
  `iteminstance` bigint(20) DEFAULT NULL,
  `itemnumber` bigint(20) DEFAULT NULL,
  `iteminfo` longtext DEFAULT NULL,
  `idnumber` varchar(255) DEFAULT NULL,
  `calculation` longtext DEFAULT NULL,
  `gradetype` smallint(6) NOT NULL DEFAULT 1,
  `grademax` decimal(10,5) NOT NULL DEFAULT 100.00000,
  `grademin` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `scaleid` bigint(20) DEFAULT NULL,
  `outcomeid` bigint(20) DEFAULT NULL,
  `gradepass` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `multfactor` decimal(10,5) NOT NULL DEFAULT 1.00000,
  `plusfactor` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `aggregationcoef` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `aggregationcoef2` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `display` bigint(20) NOT NULL DEFAULT 0,
  `decimals` tinyint(1) DEFAULT NULL,
  `hidden` bigint(20) NOT NULL DEFAULT 0,
  `locked` bigint(20) NOT NULL DEFAULT 0,
  `locktime` bigint(20) NOT NULL DEFAULT 0,
  `needsupdate` bigint(20) NOT NULL DEFAULT 0,
  `weightoverride` tinyint(1) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table keeps information about gradeable items (ie colum' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_items_history`
--

CREATE TABLE `mdlvx_grade_items_history` (
  `id` bigint(20) NOT NULL,
  `action` bigint(20) NOT NULL DEFAULT 0,
  `oldid` bigint(20) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `loggeduser` bigint(20) DEFAULT NULL,
  `courseid` bigint(20) DEFAULT NULL,
  `categoryid` bigint(20) DEFAULT NULL,
  `itemname` varchar(255) DEFAULT NULL,
  `itemtype` varchar(30) NOT NULL DEFAULT '',
  `itemmodule` varchar(30) DEFAULT NULL,
  `iteminstance` bigint(20) DEFAULT NULL,
  `itemnumber` bigint(20) DEFAULT NULL,
  `iteminfo` longtext DEFAULT NULL,
  `idnumber` varchar(255) DEFAULT NULL,
  `calculation` longtext DEFAULT NULL,
  `gradetype` smallint(6) NOT NULL DEFAULT 1,
  `grademax` decimal(10,5) NOT NULL DEFAULT 100.00000,
  `grademin` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `scaleid` bigint(20) DEFAULT NULL,
  `outcomeid` bigint(20) DEFAULT NULL,
  `gradepass` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `multfactor` decimal(10,5) NOT NULL DEFAULT 1.00000,
  `plusfactor` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `aggregationcoef` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `aggregationcoef2` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `sortorder` bigint(20) NOT NULL DEFAULT 0,
  `hidden` bigint(20) NOT NULL DEFAULT 0,
  `locked` bigint(20) NOT NULL DEFAULT 0,
  `locktime` bigint(20) NOT NULL DEFAULT 0,
  `needsupdate` bigint(20) NOT NULL DEFAULT 0,
  `display` bigint(20) NOT NULL DEFAULT 0,
  `decimals` tinyint(1) DEFAULT NULL,
  `weightoverride` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History of grade_items' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_letters`
--

CREATE TABLE `mdlvx_grade_letters` (
  `id` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `lowerboundary` decimal(10,5) NOT NULL,
  `letter` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Repository for grade letters, for courses and other moodle e' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_outcomes`
--

CREATE TABLE `mdlvx_grade_outcomes` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) DEFAULT NULL,
  `shortname` varchar(255) NOT NULL DEFAULT '',
  `fullname` longtext NOT NULL,
  `scaleid` bigint(20) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `usermodified` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='This table describes the outcomes used in the system. An out' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_outcomes_courses`
--

CREATE TABLE `mdlvx_grade_outcomes_courses` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `outcomeid` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='stores what outcomes are used in what courses.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_outcomes_history`
--

CREATE TABLE `mdlvx_grade_outcomes_history` (
  `id` bigint(20) NOT NULL,
  `action` bigint(20) NOT NULL DEFAULT 0,
  `oldid` bigint(20) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `timemodified` bigint(20) DEFAULT NULL,
  `loggeduser` bigint(20) DEFAULT NULL,
  `courseid` bigint(20) DEFAULT NULL,
  `shortname` varchar(255) NOT NULL DEFAULT '',
  `fullname` longtext NOT NULL,
  `scaleid` bigint(20) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grade_settings`
--

CREATE TABLE `mdlvx_grade_settings` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='gradebook settings' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradingform_guide_comments`
--

CREATE TABLE `mdlvx_gradingform_guide_comments` (
  `id` bigint(20) NOT NULL,
  `definitionid` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='frequently used comments used in marking guide' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradingform_guide_criteria`
--

CREATE TABLE `mdlvx_gradingform_guide_criteria` (
  `id` bigint(20) NOT NULL,
  `definitionid` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL,
  `shortname` varchar(255) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) DEFAULT NULL,
  `descriptionmarkers` longtext DEFAULT NULL,
  `descriptionmarkersformat` tinyint(4) DEFAULT NULL,
  `maxscore` decimal(10,5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the rows of the criteria grid.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradingform_guide_fillings`
--

CREATE TABLE `mdlvx_gradingform_guide_fillings` (
  `id` bigint(20) NOT NULL,
  `instanceid` bigint(20) NOT NULL,
  `criterionid` bigint(20) NOT NULL,
  `remark` longtext DEFAULT NULL,
  `remarkformat` tinyint(4) DEFAULT NULL,
  `score` decimal(10,5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the data of how the guide is filled by a particular r' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradingform_rubric_criteria`
--

CREATE TABLE `mdlvx_gradingform_rubric_criteria` (
  `id` bigint(20) NOT NULL,
  `definitionid` bigint(20) NOT NULL,
  `sortorder` bigint(20) NOT NULL,
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the rows of the rubric grid.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradingform_rubric_fillings`
--

CREATE TABLE `mdlvx_gradingform_rubric_fillings` (
  `id` bigint(20) NOT NULL,
  `instanceid` bigint(20) NOT NULL,
  `criterionid` bigint(20) NOT NULL,
  `levelid` bigint(20) DEFAULT NULL,
  `remark` longtext DEFAULT NULL,
  `remarkformat` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the data of how the rubric is filled by a particular ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_gradingform_rubric_levels`
--

CREATE TABLE `mdlvx_gradingform_rubric_levels` (
  `id` bigint(20) NOT NULL,
  `criterionid` bigint(20) NOT NULL,
  `score` decimal(10,5) NOT NULL,
  `definition` longtext DEFAULT NULL,
  `definitionformat` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the columns of the rubric grid.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grading_areas`
--

CREATE TABLE `mdlvx_grading_areas` (
  `id` bigint(20) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `component` varchar(100) NOT NULL DEFAULT '',
  `areaname` varchar(100) NOT NULL DEFAULT '',
  `activemethod` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identifies gradable areas where advanced grading can happen.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grading_definitions`
--

CREATE TABLE `mdlvx_grading_definitions` (
  `id` bigint(20) NOT NULL,
  `areaid` bigint(20) NOT NULL,
  `method` varchar(100) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) DEFAULT NULL,
  `status` bigint(20) NOT NULL DEFAULT 0,
  `copiedfromid` bigint(20) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `usercreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `usermodified` bigint(20) NOT NULL,
  `timecopied` bigint(20) DEFAULT 0,
  `options` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contains the basic information about an advanced grading for' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_grading_instances`
--

CREATE TABLE `mdlvx_grading_instances` (
  `id` bigint(20) NOT NULL,
  `definitionid` bigint(20) NOT NULL,
  `raterid` bigint(20) NOT NULL,
  `itemid` bigint(20) DEFAULT NULL,
  `rawgrade` decimal(10,5) DEFAULT NULL,
  `status` bigint(20) NOT NULL DEFAULT 0,
  `feedback` longtext DEFAULT NULL,
  `feedbackformat` tinyint(4) DEFAULT NULL,
  `timemodified` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grading form instance is an assessment record for one gradab' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_groupings`
--

CREATE TABLE `mdlvx_groupings` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `idnumber` varchar(100) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL DEFAULT 0,
  `configdata` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='A grouping is a collection of groups. WAS: groups_groupings' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_groupings_groups`
--

CREATE TABLE `mdlvx_groupings_groups` (
  `id` bigint(20) NOT NULL,
  `groupingid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) NOT NULL DEFAULT 0,
  `timeadded` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link a grouping to a group (note, groups can be in multiple ' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_groups`
--

CREATE TABLE `mdlvx_groups` (
  `id` bigint(20) NOT NULL,
  `courseid` bigint(20) NOT NULL,
  `idnumber` varchar(100) NOT NULL DEFAULT '',
  `name` varchar(254) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `descriptionformat` tinyint(4) NOT NULL DEFAULT 0,
  `enrolmentkey` varchar(50) DEFAULT NULL,
  `picture` bigint(20) NOT NULL DEFAULT 0,
  `visibility` tinyint(1) NOT NULL DEFAULT 0,
  `participation` tinyint(1) NOT NULL DEFAULT 1,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Each record represents a group.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_groups_members`
--

CREATE TABLE `mdlvx_groups_members` (
  `id` bigint(20) NOT NULL,
  `groupid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `timeadded` bigint(20) NOT NULL DEFAULT 0,
  `component` varchar(100) NOT NULL DEFAULT '',
  `itemid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link a user to a group.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5p`
--

CREATE TABLE `mdlvx_h5p` (
  `id` bigint(20) NOT NULL,
  `jsoncontent` longtext NOT NULL,
  `mainlibraryid` bigint(20) NOT NULL,
  `displayoptions` smallint(6) DEFAULT NULL,
  `pathnamehash` varchar(40) NOT NULL DEFAULT '',
  `contenthash` varchar(40) NOT NULL DEFAULT '',
  `filtered` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores H5P content information' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5pactivity`
--

CREATE TABLE `mdlvx_h5pactivity` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `grade` bigint(20) DEFAULT 0,
  `displayoptions` smallint(6) NOT NULL DEFAULT 0,
  `enabletracking` tinyint(1) NOT NULL DEFAULT 1,
  `grademethod` smallint(6) NOT NULL DEFAULT 1,
  `reviewmode` smallint(6) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores the h5pactivity activity module instances.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5pactivity_attempts`
--

CREATE TABLE `mdlvx_h5pactivity_attempts` (
  `id` bigint(20) NOT NULL,
  `h5pactivityid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `timecreated` bigint(20) NOT NULL,
  `timemodified` bigint(20) NOT NULL,
  `attempt` mediumint(9) NOT NULL DEFAULT 1,
  `rawscore` bigint(20) DEFAULT 0,
  `maxscore` bigint(20) DEFAULT 0,
  `scaled` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `duration` bigint(20) DEFAULT 0,
  `completion` tinyint(1) DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users attempts inside H5P activities' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5pactivity_attempts_results`
--

CREATE TABLE `mdlvx_h5pactivity_attempts_results` (
  `id` bigint(20) NOT NULL,
  `attemptid` bigint(20) NOT NULL,
  `subcontent` varchar(128) DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `interactiontype` varchar(128) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `correctpattern` longtext DEFAULT NULL,
  `response` longtext NOT NULL,
  `additionals` longtext DEFAULT NULL,
  `rawscore` bigint(20) NOT NULL DEFAULT 0,
  `maxscore` bigint(20) NOT NULL DEFAULT 0,
  `duration` bigint(20) DEFAULT 0,
  `completion` tinyint(1) DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H5Pactivities_attempts tracking info' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5p_contents_libraries`
--

CREATE TABLE `mdlvx_h5p_contents_libraries` (
  `id` bigint(20) NOT NULL,
  `h5pid` bigint(20) NOT NULL,
  `libraryid` bigint(20) NOT NULL,
  `dependencytype` varchar(10) NOT NULL DEFAULT '',
  `dropcss` tinyint(1) NOT NULL,
  `weight` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store which library is used in which content.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5p_libraries`
--

CREATE TABLE `mdlvx_h5p_libraries` (
  `id` bigint(20) NOT NULL,
  `machinename` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `majorversion` smallint(6) NOT NULL,
  `minorversion` smallint(6) NOT NULL,
  `patchversion` smallint(6) NOT NULL,
  `runnable` tinyint(1) NOT NULL,
  `fullscreen` tinyint(1) NOT NULL DEFAULT 0,
  `embedtypes` varchar(255) NOT NULL DEFAULT '',
  `preloadedjs` longtext DEFAULT NULL,
  `preloadedcss` longtext DEFAULT NULL,
  `droplibrarycss` longtext DEFAULT NULL,
  `semantics` longtext DEFAULT NULL,
  `addto` longtext DEFAULT NULL,
  `coremajor` smallint(6) DEFAULT NULL,
  `coreminor` smallint(6) DEFAULT NULL,
  `metadatasettings` longtext DEFAULT NULL,
  `tutorial` longtext DEFAULT NULL,
  `example` longtext DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores information about libraries used by H5P content.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5p_libraries_cachedassets`
--

CREATE TABLE `mdlvx_h5p_libraries_cachedassets` (
  `id` bigint(20) NOT NULL,
  `libraryid` bigint(20) NOT NULL,
  `hash` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='H5P cached library assets' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_h5p_library_dependencies`
--

CREATE TABLE `mdlvx_h5p_library_dependencies` (
  `id` bigint(20) NOT NULL,
  `libraryid` bigint(20) NOT NULL,
  `requiredlibraryid` bigint(20) NOT NULL,
  `dependencytype` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores H5P library dependencies' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_imscp`
--

CREATE TABLE `mdlvx_imscp` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `revision` bigint(20) NOT NULL DEFAULT 0,
  `keepold` bigint(20) NOT NULL DEFAULT -1,
  `structure` longtext DEFAULT NULL,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='each record is one imscp resource' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_infected_files`
--

CREATE TABLE `mdlvx_infected_files` (
  `id` bigint(20) NOT NULL,
  `filename` longtext NOT NULL,
  `quarantinedfile` longtext DEFAULT NULL,
  `userid` bigint(20) NOT NULL,
  `reason` longtext NOT NULL,
  `timecreated` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_label`
--

CREATE TABLE `mdlvx_label` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext NOT NULL,
  `introformat` smallint(6) DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines labels' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson`
--

CREATE TABLE `mdlvx_lesson` (
  `id` bigint(20) NOT NULL,
  `course` bigint(20) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `intro` longtext DEFAULT NULL,
  `introformat` smallint(6) NOT NULL DEFAULT 0,
  `practice` smallint(6) NOT NULL DEFAULT 0,
  `modattempts` smallint(6) NOT NULL DEFAULT 0,
  `usepassword` smallint(6) NOT NULL DEFAULT 0,
  `password` varchar(32) NOT NULL DEFAULT '',
  `dependency` bigint(20) NOT NULL DEFAULT 0,
  `conditions` longtext NOT NULL,
  `grade` bigint(20) NOT NULL DEFAULT 0,
  `custom` smallint(6) NOT NULL DEFAULT 0,
  `ongoing` smallint(6) NOT NULL DEFAULT 0,
  `usemaxgrade` smallint(6) NOT NULL DEFAULT 0,
  `maxanswers` smallint(6) NOT NULL DEFAULT 4,
  `maxattempts` smallint(6) NOT NULL DEFAULT 5,
  `review` smallint(6) NOT NULL DEFAULT 0,
  `nextpagedefault` smallint(6) NOT NULL DEFAULT 0,
  `feedback` smallint(6) NOT NULL DEFAULT 1,
  `minquestions` smallint(6) NOT NULL DEFAULT 0,
  `maxpages` smallint(6) NOT NULL DEFAULT 0,
  `timelimit` bigint(20) NOT NULL DEFAULT 0,
  `retake` smallint(6) NOT NULL DEFAULT 1,
  `activitylink` bigint(20) NOT NULL DEFAULT 0,
  `mediafile` varchar(255) NOT NULL DEFAULT '',
  `mediaheight` bigint(20) NOT NULL DEFAULT 100,
  `mediawidth` bigint(20) NOT NULL DEFAULT 650,
  `mediaclose` smallint(6) NOT NULL DEFAULT 0,
  `slideshow` smallint(6) NOT NULL DEFAULT 0,
  `width` bigint(20) NOT NULL DEFAULT 640,
  `height` bigint(20) NOT NULL DEFAULT 480,
  `bgcolor` varchar(7) NOT NULL DEFAULT '#FFFFFF',
  `displayleft` smallint(6) NOT NULL DEFAULT 0,
  `displayleftif` smallint(6) NOT NULL DEFAULT 0,
  `progressbar` smallint(6) NOT NULL DEFAULT 0,
  `available` bigint(20) NOT NULL DEFAULT 0,
  `deadline` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `completionendreached` tinyint(1) DEFAULT 0,
  `completiontimespent` bigint(20) DEFAULT 0,
  `allowofflineattempts` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines lesson' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_answers`
--

CREATE TABLE `mdlvx_lesson_answers` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `pageid` bigint(20) NOT NULL DEFAULT 0,
  `jumpto` bigint(20) NOT NULL DEFAULT 0,
  `grade` smallint(6) NOT NULL DEFAULT 0,
  `score` bigint(20) NOT NULL DEFAULT 0,
  `flags` smallint(6) NOT NULL DEFAULT 0,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `answer` longtext DEFAULT NULL,
  `answerformat` tinyint(4) NOT NULL DEFAULT 0,
  `response` longtext DEFAULT NULL,
  `responseformat` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines lesson_answers' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_attempts`
--

CREATE TABLE `mdlvx_lesson_attempts` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `pageid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `answerid` bigint(20) NOT NULL DEFAULT 0,
  `retry` smallint(6) NOT NULL DEFAULT 0,
  `correct` bigint(20) NOT NULL DEFAULT 0,
  `useranswer` longtext DEFAULT NULL,
  `timeseen` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines lesson_attempts' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_branch`
--

CREATE TABLE `mdlvx_lesson_branch` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `pageid` bigint(20) NOT NULL DEFAULT 0,
  `retry` bigint(20) NOT NULL DEFAULT 0,
  `flag` smallint(6) NOT NULL DEFAULT 0,
  `timeseen` bigint(20) NOT NULL DEFAULT 0,
  `nextpageid` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='branches for each lesson/user' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_grades`
--

CREATE TABLE `mdlvx_lesson_grades` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `grade` double NOT NULL DEFAULT 0,
  `late` smallint(6) NOT NULL DEFAULT 0,
  `completed` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines lesson_grades' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_overrides`
--

CREATE TABLE `mdlvx_lesson_overrides` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `groupid` bigint(20) DEFAULT NULL,
  `userid` bigint(20) DEFAULT NULL,
  `available` bigint(20) DEFAULT NULL,
  `deadline` bigint(20) DEFAULT NULL,
  `timelimit` bigint(20) DEFAULT NULL,
  `review` smallint(6) DEFAULT NULL,
  `maxattempts` smallint(6) DEFAULT NULL,
  `retake` smallint(6) DEFAULT NULL,
  `password` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='The overrides to lesson settings.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_pages`
--

CREATE TABLE `mdlvx_lesson_pages` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `prevpageid` bigint(20) NOT NULL DEFAULT 0,
  `nextpageid` bigint(20) NOT NULL DEFAULT 0,
  `qtype` smallint(6) NOT NULL DEFAULT 0,
  `qoption` smallint(6) NOT NULL DEFAULT 0,
  `layout` smallint(6) NOT NULL DEFAULT 1,
  `display` smallint(6) NOT NULL DEFAULT 1,
  `timecreated` bigint(20) NOT NULL DEFAULT 0,
  `timemodified` bigint(20) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `contents` longtext NOT NULL,
  `contentsformat` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines lesson_pages' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lesson_timer`
--

CREATE TABLE `mdlvx_lesson_timer` (
  `id` bigint(20) NOT NULL,
  `lessonid` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `starttime` bigint(20) NOT NULL DEFAULT 0,
  `lessontime` bigint(20) NOT NULL DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0,
  `timemodifiedoffline` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='lesson timer for each lesson' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_license`
--

CREATE TABLE `mdlvx_license` (
  `id` bigint(20) NOT NULL,
  `shortname` varchar(255) DEFAULT NULL,
  `fullname` longtext DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `version` bigint(20) NOT NULL DEFAULT 0,
  `custom` tinyint(1) NOT NULL DEFAULT 0,
  `sortorder` mediumint(9) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='store licenses used by moodle' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_lock_db`
--

CREATE TABLE `mdlvx_lock_db` (
  `id` bigint(20) NOT NULL,
  `resourcekey` varchar(255) NOT NULL DEFAULT '',
  `expires` bigint(20) DEFAULT NULL,
  `owner` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores active and inactive lock types for db locking method.' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_log`
--

CREATE TABLE `mdlvx_log` (
  `id` bigint(20) NOT NULL,
  `time` bigint(20) NOT NULL DEFAULT 0,
  `userid` bigint(20) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `course` bigint(20) NOT NULL DEFAULT 0,
  `module` varchar(20) NOT NULL DEFAULT '',
  `cmid` bigint(20) NOT NULL DEFAULT 0,
  `action` varchar(40) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT '',
  `info` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Every action is logged as far as possible' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `mdlvx_logstore_standard_log`
--

CREATE TABLE `mdlvx_logstore_standard_log` (
  `id` bigint(20) NOT NULL,
  `eventname` varchar(255) NOT NULL DEFAULT '',
  `component` varchar(100) NOT NULL DEFAULT '',
  `action` varchar(100) NOT NULL DEFAULT '',
  `target` varchar(100) NOT NULL DEFAULT '',
  `objecttable` varchar(50) DEFAULT NULL,
  `objectid` bigint(20) DEFAULT NULL,
  `crud` varchar(1) NOT NULL DEFAULT '',
  `edulevel` tinyint(1) NOT NULL,
  `contextid` bigint(20) NOT NULL,
  `contextlevel` bigint(20) NOT NULL,
  `contextinstanceid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `courseid` bigint(20) DEFAULT NULL,
  `relateduserid` bigint(20) DEFAULT NULL,
  `anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `other` longtext DEFAULT NULL,
  `timecreated` bigint(20) NOT NULL,
  `origin` varchar(10) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `realuserid` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Standard log table' ROW_FORMAT=COMPRESSED;

-- --------------------------------------------------------

--
-- Table structure for table `organization_training_form`
--

CREATE TABLE `organization_training_form` (
  `id` int(11) NOT NULL,
  `name_of_org` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_no` varchar(500) NOT NULL,
  `entry_time` datetime NOT NULL,
  `form_name` varchar(255) NOT NULL,
  `upload_document` varchar(255) NOT NULL,
  `training_loc` varchar(500) NOT NULL,
  `number_of_participant` varchar(11) NOT NULL,
  `status` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_program`
--

CREATE TABLE `parent_program` (
  `id` int(11) NOT NULL,
  `pp_code` varchar(255) NOT NULL,
  `pp_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_strategic_objective`
--

CREATE TABLE `parent_strategic_objective` (
  `id` int(11) NOT NULL,
  `pso_code` varchar(255) NOT NULL,
  `pso_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_approval_log`
--

CREATE TABLE `payroll_approval_log` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `action` enum('created','opened','processed','submitted','hr_approved','finance_approved','ceo_approved','rejected','paid','closed') NOT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_expense_records`
--

CREATE TABLE `payroll_expense_records` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL COMMENT 'FK to expenses table',
  `expense_type` enum('net_pay','paye','nssf','shif','housing_levy','helb','sacco','loan','other') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_inputs`
--

CREATE TABLE `payroll_inputs` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `days_worked` decimal(5,2) DEFAULT NULL COMMENT 'Actual days worked',
  `days_absent` decimal(5,2) DEFAULT 0.00,
  `unpaid_leave_days` decimal(5,2) DEFAULT 0.00,
  `overtime_normal` decimal(6,2) DEFAULT 0.00 COMMENT 'Normal overtime hours',
  `overtime_weekend` decimal(6,2) DEFAULT 0.00 COMMENT 'Weekend overtime hours',
  `overtime_holiday` decimal(6,2) DEFAULT 0.00 COMMENT 'Holiday overtime hours',
  `bonus` decimal(12,2) DEFAULT 0.00,
  `commission` decimal(12,2) DEFAULT 0.00,
  `other_earnings` decimal(12,2) DEFAULT 0.00,
  `other_earnings_description` varchar(255) DEFAULT NULL,
  `salary_advance` decimal(12,2) DEFAULT 0.00,
  `loan_deduction` decimal(12,2) DEFAULT 0.00,
  `sacco_deduction` decimal(12,2) DEFAULT 0.00,
  `helb_deduction` decimal(12,2) DEFAULT 0.00,
  `other_deductions` decimal(12,2) DEFAULT 0.00,
  `other_deductions_description` varchar(255) DEFAULT NULL,
  `insurance_premium` decimal(12,2) DEFAULT 0.00 COMMENT 'Monthly insurance premium paid',
  `notes` text DEFAULT NULL,
  `entered_by` int(11) DEFAULT NULL,
  `entered_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_payments`
--

CREATE TABLE `payroll_payments` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `payroll_record_id` int(11) NOT NULL,
  `payment_method` enum('bank','mpesa','cash','cheque') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `mpesa_number` varchar(20) DEFAULT NULL,
  `mpesa_name` varchar(255) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `failure_reason` varchar(255) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int(11) NOT NULL,
  `period_code` varchar(20) NOT NULL COMMENT 'e.g., 2026-01',
  `period_name` varchar(50) NOT NULL COMMENT 'e.g., January 2026',
  `period_month` tinyint(4) NOT NULL,
  `period_year` year(4) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `status` enum('draft','open','processing','pending_approval','approved','paid','closed') DEFAULT 'draft',
  `hr_prepared_by` int(11) DEFAULT NULL,
  `hr_prepared_at` datetime DEFAULT NULL,
  `finance_approved_by` int(11) DEFAULT NULL,
  `finance_approved_at` datetime DEFAULT NULL,
  `ceo_approved_by` int(11) DEFAULT NULL,
  `ceo_approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `total_employees` int(11) DEFAULT 0,
  `total_gross` decimal(15,2) DEFAULT 0.00,
  `total_deductions` decimal(15,2) DEFAULT 0.00,
  `total_net` decimal(15,2) DEFAULT 0.00,
  `total_employer_costs` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expenses_posted` tinyint(1) DEFAULT 0 COMMENT 'Whether payroll has been posted to expenses',
  `expenses_posted_by` int(11) DEFAULT NULL,
  `expenses_posted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_code` varchar(20) NOT NULL,
  `staff_name` varchar(255) NOT NULL,
  `department_name` varchar(100) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `kra_pin` varchar(20) DEFAULT NULL,
  `nssf_number` varchar(20) DEFAULT NULL,
  `nhif_number` varchar(20) DEFAULT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `house_allowance` decimal(12,2) DEFAULT 0.00,
  `transport_allowance` decimal(12,2) DEFAULT 0.00,
  `medical_allowance` decimal(12,2) DEFAULT 0.00,
  `other_allowances` decimal(12,2) DEFAULT 0.00,
  `allowances_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full allowances breakdown' CHECK (json_valid(`allowances_json`)),
  `total_allowances` decimal(12,2) DEFAULT 0.00,
  `overtime_amount` decimal(12,2) DEFAULT 0.00,
  `bonus` decimal(12,2) DEFAULT 0.00,
  `commission` decimal(12,2) DEFAULT 0.00,
  `other_earnings` decimal(12,2) DEFAULT 0.00,
  `gross_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nssf_employee` decimal(12,2) DEFAULT 0.00,
  `nssf_employer` decimal(12,2) DEFAULT 0.00,
  `shif_amount` decimal(12,2) DEFAULT 0.00,
  `housing_levy_employee` decimal(12,2) DEFAULT 0.00,
  `housing_levy_employer` decimal(12,2) DEFAULT 0.00,
  `taxable_income` decimal(12,2) DEFAULT 0.00,
  `tax_before_relief` decimal(12,2) DEFAULT 0.00,
  `personal_relief` decimal(12,2) DEFAULT 0.00,
  `insurance_relief` decimal(12,2) DEFAULT 0.00,
  `paye` decimal(12,2) DEFAULT 0.00,
  `salary_advance` decimal(12,2) DEFAULT 0.00,
  `loan_deduction` decimal(12,2) DEFAULT 0.00,
  `sacco_deduction` decimal(12,2) DEFAULT 0.00,
  `helb_deduction` decimal(12,2) DEFAULT 0.00,
  `other_deductions` decimal(12,2) DEFAULT 0.00,
  `total_statutory_deductions` decimal(12,2) DEFAULT 0.00,
  `total_other_deductions` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `employer_nssf` decimal(12,2) DEFAULT 0.00,
  `employer_housing_levy` decimal(12,2) DEFAULT 0.00,
  `total_employer_cost` decimal(12,2) DEFAULT 0.00,
  `status` enum('calculated','approved','paid','cancelled') DEFAULT 'calculated',
  `calculated_at` datetime DEFAULT current_timestamp(),
  `calculated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_remittances`
--

CREATE TABLE `payroll_remittances` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL COMMENT 'Link to remittance_recipients',
  `remittance_type` varchar(50) NOT NULL COMMENT 'PAYE, NSSF, SHIF, HOUSING_LEVY, SACCO, HELB, LOAN, MORTGAGE, INSURANCE, UNION, OTHER',
  `remittance_name` varchar(255) NOT NULL COMMENT 'Display name: e.g., PAYE (KRA), Stima SACCO',
  `employee_amount` decimal(12,2) DEFAULT 0.00,
  `employer_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `employee_count` int(11) DEFAULT 0 COMMENT 'Number of employees with this deduction',
  `due_date` date NOT NULL,
  `status` enum('pending','processing','paid','overdue','partially_paid') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_amount` decimal(12,2) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `penalty_amount` decimal(12,2) DEFAULT 0.00,
  `interest_amount` decimal(12,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `paid_by` int(11) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_remittance_details`
--

CREATE TABLE `payroll_remittance_details` (
  `id` int(11) NOT NULL,
  `remittance_id` int(11) NOT NULL,
  `payroll_record_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(255) NOT NULL,
  `staff_code` varchar(50) DEFAULT NULL,
  `member_number` varchar(50) DEFAULT NULL COMMENT 'SACCO member no, NSSF no, etc.',
  `amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_remittance_log`
--

CREATE TABLE `payroll_remittance_log` (
  `id` int(11) NOT NULL,
  `remittance_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_settings`
--

CREATE TABLE `payroll_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('rate','amount','json','text') DEFAULT 'amount',
  `description` varchar(255) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `thumbnail` longtext DEFAULT NULL,
  `status` enum('draft','unpublished','published') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `priority_agenda`
--

CREATE TABLE `priority_agenda` (
  `id` int(11) NOT NULL,
  `agenda_code` varchar(255) NOT NULL,
  `agenda_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `program_code` varchar(255) NOT NULL,
  `plan_code` varchar(255) NOT NULL,
  `strategic_objective` varchar(255) NOT NULL,
  `program_name` varchar(255) NOT NULL,
  `parent_program_id` varchar(255) NOT NULL,
  `program_sector_id` varchar(255) NOT NULL,
  `start_date` varchar(255) NOT NULL,
  `end_date` varchar(255) NOT NULL,
  `budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `program_manager_id` varchar(255) NOT NULL,
  `program_status` int(3) NOT NULL DEFAULT 0,
  `percent_complete` text NOT NULL,
  `responsible_agencies_id` text NOT NULL,
  `program_vision` text NOT NULL,
  `program_mission` text NOT NULL,
  `program_outcome` text NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `modified_by` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_curriculum`
--

CREATE TABLE `program_curriculum` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `module_name` varchar(500) NOT NULL,
  `curriculum_tier` enum('foundational','intermediate','advanced') NOT NULL DEFAULT 'foundational',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_lecturers`
--

CREATE TABLE `program_lecturers` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `photo_url` text DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `qualifications` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_outcomes`
--

CREATE TABLE `program_outcomes` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `outcome_text` text NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_sector`
--

CREATE TABLE `program_sector` (
  `id` int(11) NOT NULL,
  `ps_code` varchar(255) NOT NULL,
  `ps_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `outcome_id` varchar(255) DEFAULT NULL,
  `project_id` varchar(255) DEFAULT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `proj_desc` text DEFAULT NULL,
  `start_date` varchar(255) DEFAULT NULL,
  `end_date` varchar(255) DEFAULT NULL,
  `geo_area` varchar(255) DEFAULT NULL,
  `target_population` varchar(255) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `analysis_id` varchar(255) DEFAULT NULL,
  `assumption_id` varchar(255) DEFAULT NULL,
  `risk_id` varchar(255) DEFAULT NULL,
  `proj_goal` text DEFAULT NULL,
  `proj_obj` text DEFAULT NULL,
  `problem_statement` text DEFAULT NULL,
  `root_causes` text DEFAULT NULL,
  `contributing_factors` text DEFAULT NULL,
  `proposed_solutions` text DEFAULT NULL,
  `assumption` text DEFAULT NULL,
  `verification_plan` text DEFAULT NULL,
  `risk_description` text DEFAULT NULL,
  `mitigation_strategy` text DEFAULT NULL,
  `stakeholders` text DEFAULT NULL,
  `proj_status` int(1) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_list`
--

CREATE TABLE `project_list` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` tinyint(4) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `manager_id` int(11) NOT NULL,
  `user_ids` text NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proposal_requests`
--

CREATE TABLE `proposal_requests` (
  `id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_email` varchar(255) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('pending','sent','contacted','closed') DEFAULT 'pending',
  `request_date` timestamp NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recruitment_status`
--

CREATE TABLE `recruitment_status` (
  `id` int(11) NOT NULL,
  `status_label` varchar(200) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `register`
--

CREATE TABLE `register` (
  `id` int(11) NOT NULL,
  `entry_id` varchar(50) NOT NULL,
  `email` varchar(500) NOT NULL,
  `firstname` varchar(500) NOT NULL,
  `lastname` varchar(500) NOT NULL,
  `phone_number` varchar(100) NOT NULL,
  `program` varchar(1000) NOT NULL,
  `organization` varchar(500) DEFAULT NULL,
  `position` varchar(500) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 3,
  `country` varchar(100) NOT NULL,
  `token` varchar(500) DEFAULT NULL,
  `datee` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` int(11) NOT NULL DEFAULT 1,
  `source` varchar(5) NOT NULL DEFAULT '1',
  `intake_id` varchar(200) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `lead_status` enum('new','contacted','qualified','enrolled','completed','dropped') DEFAULT 'new',
  `last_contact_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registered_users`
--

CREATE TABLE `registered_users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(500) DEFAULT NULL,
  `fullname` varchar(500) NOT NULL,
  `phone_no` varchar(20) DEFAULT NULL,
  `role` varchar(500) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 2,
  `token` varchar(500) DEFAULT NULL,
  `transaction_key` varchar(500) NOT NULL DEFAULT 'transaction_key',
  `department_id` int(11) DEFAULT NULL COMMENT 'References departments.id',
  `user_type` enum('admin','manager','staff','viewer') DEFAULT 'staff' COMMENT 'User access level',
  `staff_id` int(11) DEFAULT NULL COMMENT 'Link to staff.id if this is a staff account',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remittance_recipients`
--

CREATE TABLE `remittance_recipients` (
  `id` int(11) NOT NULL,
  `recipient_name` varchar(255) NOT NULL COMMENT 'e.g., Stima SACCO, HELB, Equity Bank',
  `recipient_code` varchar(50) DEFAULT NULL COMMENT 'Unique code for reference',
  `recipient_type` enum('statutory','sacco','bank','insurance','pension','union','other') NOT NULL,
  `deduction_type` varchar(50) NOT NULL COMMENT 'e.g., PAYE, NSSF, SACCO, HELB, LOAN, MORTGAGE',
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `paybill_number` varchar(20) DEFAULT NULL COMMENT 'M-Pesa Paybill',
  `portal_url` varchar(255) DEFAULT NULL COMMENT 'e.g., iTax URL, SACCO portal',
  `portal_username` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_comments`
--

CREATE TABLE `request_comments` (
  `comment_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `commenter_name` varchar(255) NOT NULL,
  `commenter_email` varchar(255) NOT NULL,
  `commenter_type` enum('Staff','Admin','System') NOT NULL,
  `comment_text` text NOT NULL,
  `date_commented` datetime NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_types`
--

CREATE TABLE `request_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `requires_amount` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `resource_code` varchar(255) NOT NULL,
  `resource_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responsible`
--

CREATE TABLE `responsible` (
  `id` int(11) NOT NULL,
  `resp_code` varchar(255) NOT NULL,
  `resp_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responsible_agencies`
--

CREATE TABLE `responsible_agencies` (
  `id` int(11) NOT NULL,
  `res_agencies_code` varchar(255) NOT NULL,
  `res_agencies_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_email`
--

CREATE TABLE `scheduled_email` (
  `id` int(11) NOT NULL,
  `email` varchar(500) NOT NULL,
  `firstname` varchar(500) NOT NULL,
  `bulk_email_id` varchar(100) NOT NULL,
  `schedule_id` varchar(100) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `date_sent` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_mail`
--

CREATE TABLE `schedule_mail` (
  `id` int(11) NOT NULL,
  `schedule_id` varchar(50) NOT NULL,
  `group_id` varchar(50) NOT NULL,
  `bulk_email_id` varchar(100) NOT NULL,
  `start_date` varchar(500) DEFAULT NULL,
  `end_date` varchar(500) DEFAULT NULL,
  `last_email_sent` varchar(500) NOT NULL DEFAULT 'start',
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `send_email`
--

CREATE TABLE `send_email` (
  `id` int(11) NOT NULL,
  `receiver_email` varchar(500) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `body` longtext NOT NULL,
  `datee` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(255) NOT NULL,
  `staff_email` varchar(255) NOT NULL,
  `staff_phone` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `request_type` varchar(100) NOT NULL,
  `request_title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed','Rejected') DEFAULT 'Pending',
  `attachment` varchar(255) DEFAULT NULL,
  `date_submitted` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL COMMENT 'Auto-generated staff ID e.g., VASL-STF-0001',
  `full_name` varchar(255) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `national_id` varchar(50) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `phone_alt` varchar(20) DEFAULT NULL,
  `home_address` text NOT NULL,
  `passport_photo` varchar(255) DEFAULT NULL,
  `kra_pin` varchar(20) NOT NULL,
  `nssf_number` varchar(20) NOT NULL,
  `nhif_number` varchar(20) NOT NULL,
  `id_copy_path` varchar(255) DEFAULT NULL,
  `kra_certificate_path` varchar(255) DEFAULT NULL,
  `nok_name` varchar(255) NOT NULL,
  `nok_relationship` varchar(50) NOT NULL,
  `nok_phone` varchar(20) NOT NULL,
  `nok_phone_alt` varchar(20) DEFAULT NULL,
  `nok_address` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `reporting_to` int(11) DEFAULT NULL COMMENT 'staff_id of supervisor',
  `employment_type` enum('permanent','contract','temporary','internship') DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `probation_end_date` date DEFAULT NULL,
  `working_hours` varchar(100) DEFAULT NULL COMMENT 'e.g., 8:00 AM - 5:00 PM',
  `work_location` enum('office','remote','hybrid') DEFAULT NULL,
  `basic_salary` decimal(12,2) DEFAULT NULL,
  `payment_frequency` enum('monthly','weekly','bi-weekly') DEFAULT 'monthly',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `benefits` text DEFAULT NULL COMMENT 'JSON or comma-separated benefits',
  `allowances` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON: {"HOUSE": 5000, "TRANSPORT": 3000}' CHECK (json_valid(`allowances`)),
  `deductions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON: {"PAYE": true, "NSSF": true, "SHIF": true}' CHECK (json_valid(`deductions`)),
  `onboarding_status` enum('pending','under_review','approved','rejected','active','inactive','terminated') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `contract_signed` tinyint(1) DEFAULT 0,
  `contract_path` varchar(255) DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `system_user_id` int(11) DEFAULT NULL COMMENT 'Link to registered_users.id',
  `corporate_email` varchar(255) DEFAULT NULL COMMENT 'Work email for system access',
  `system_access_granted` tinyint(1) DEFAULT 0 COMMENT '1 if has system login',
  `system_access_granted_at` datetime DEFAULT NULL,
  `system_access_granted_by` int(11) DEFAULT NULL,
  `system_role` enum('staff','hr','finance','manager','admin','ceo') DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_deduction_assignments`
--

CREATE TABLE `staff_deduction_assignments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL COMMENT 'Which SACCO, bank, etc.',
  `deduction_type` varchar(50) NOT NULL,
  `member_number` varchar(50) DEFAULT NULL COMMENT 'SACCO member no, loan account, etc.',
  `account_number` varchar(50) DEFAULT NULL,
  `amount_type` enum('fixed','percentage') DEFAULT 'fixed',
  `amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Fixed amount or percentage',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL COMMENT 'For loans with end date',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_deduction_exemptions`
--

CREATE TABLE `staff_deduction_exemptions` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `exempt_paye` tinyint(1) DEFAULT 0,
  `exempt_nssf` tinyint(1) DEFAULT 0,
  `exempt_shif` tinyint(1) DEFAULT 0,
  `exempt_ahl` tinyint(1) DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `document_type` enum('passport_photo','national_id','kra_certificate','nssf_card','nhif_card','cv','contract','certificate','other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leave_balance`
--

CREATE TABLE `staff_leave_balance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `leave_type` enum('annual','sick','maternity','paternity','compassionate','unpaid') NOT NULL,
  `total_days` decimal(5,2) DEFAULT 0.00,
  `used_days` decimal(5,2) DEFAULT 0.00,
  `year` year(4) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_onboarding_log`
--

CREATE TABLE `staff_onboarding_log` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'e.g., submitted, reviewed, approved, rejected',
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_qualifications`
--

CREATE TABLE `staff_qualifications` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `qualification_type` enum('certificate','diploma','bachelors','masters','doctorate','professional','other') NOT NULL,
  `description` varchar(255) NOT NULL COMMENT 'e.g., Bachelor of Science in Computer Science',
  `institution` varchar(255) NOT NULL,
  `year_completed` year(4) NOT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stakeholder`
--

CREATE TABLE `stakeholder` (
  `id` int(11) NOT NULL,
  `stakeholder_name` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `strategic_pillar`
--

CREATE TABLE `strategic_pillar` (
  `id` int(11) NOT NULL,
  `pillar_code` varchar(255) NOT NULL,
  `pillar_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `strategic_plans`
--

CREATE TABLE `strategic_plans` (
  `id` int(11) NOT NULL,
  `plan_code` varchar(255) NOT NULL,
  `strategic_objective` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `end_date` date NOT NULL,
  `sustainable_goal` varchar(255) NOT NULL,
  `strategic_pillar` varchar(255) NOT NULL,
  `priority_agenda` varchar(255) NOT NULL,
  `budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `responsible` varchar(255) NOT NULL,
  `parent_strategic_objective` varchar(255) NOT NULL,
  `impact` text NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `modified_by` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sustainable_goals`
--

CREATE TABLE `sustainable_goals` (
  `id` int(11) NOT NULL,
  `goal_code` varchar(255) NOT NULL,
  `goal_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_emails`
--

CREATE TABLE `system_emails` (
  `id` int(11) NOT NULL,
  `title` text NOT NULL,
  `subject` text NOT NULL,
  `body` text NOT NULL,
  `updated_by` text NOT NULL,
  `last_updated` text NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_emails1`
--

CREATE TABLE `system_emails1` (
  `id` int(11) NOT NULL,
  `email_type` enum('virtual','international') DEFAULT 'virtual',
  `subject` text NOT NULL,
  `course_opt` text NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `email_opt` text NOT NULL,
  `temp_opt` text NOT NULL,
  `body` text NOT NULL,
  `updated_by` text NOT NULL,
  `last_updated` text DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_emails_config`
--

CREATE TABLE `system_emails_config` (
  `id` int(11) NOT NULL,
  `schedule_no` text NOT NULL,
  `description` text NOT NULL,
  `selected_emails` text NOT NULL,
  `scheduled_date` datetime NOT NULL,
  `create_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `email` varchar(200) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `cover_img` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_list`
--

CREATE TABLE `task_list` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` tinyint(4) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `user_ids` varchar(500) DEFAULT NULL,
  `end_date` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_congress`
--

CREATE TABLE `ticket_congress` (
  `id` int(11) NOT NULL,
  `fullname` varchar(500) NOT NULL,
  `email` varchar(500) NOT NULL,
  `term` varchar(500) NOT NULL,
  `phone_number` varchar(500) NOT NULL,
  `ticket_id` varchar(500) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `amount` varchar(50) NOT NULL,
  `ticket_number` varchar(20) NOT NULL DEFAULT '1',
  `confirmation` varchar(500) DEFAULT NULL,
  `date_sent` datetime NOT NULL DEFAULT current_timestamp(),
  `organization` varchar(500) DEFAULT NULL,
  `position` varchar(500) DEFAULT NULL,
  `event_id` varchar(60) DEFAULT '3',
  `country` varchar(100) NOT NULL DEFAULT '',
  `admission_no` varchar(50) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `lead_status` enum('new','contacted','qualified','registered','attended','no_show') DEFAULT 'new',
  `last_contact_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_import_batches`
--

CREATE TABLE `tm_import_batches` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `total_rows` int(11) DEFAULT 0,
  `imported_rows` int(11) DEFAULT 0,
  `skipped_rows` int(11) DEFAULT 0,
  `error_rows` int(11) DEFAULT 0,
  `errors_log` text DEFAULT NULL COMMENT 'JSON array of row errors',
  `column_mapping` text DEFAULT NULL COMMENT 'JSON of mapped columns',
  `default_settings` text DEFAULT NULL COMMENT 'JSON of bulk defaults applied',
  `status` enum('Pending','Validating','Ready','Importing','Completed','Failed') DEFAULT 'Pending',
  `imported_by` int(11) NOT NULL,
  `imported_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_notifications`
--

CREATE TABLE `tm_notifications` (
  `id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `support_request_id` int(11) DEFAULT NULL,
  `recipient_id` int(11) NOT NULL COMMENT 'registered_users.id',
  `notification_type` enum('reminder','overdue','escalation','support_request','support_decision','status_change','assignment','comment') NOT NULL,
  `subject` varchar(500) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('Low','Normal','High','Critical') DEFAULT 'Normal',
  `is_read` tinyint(1) DEFAULT 0,
  `is_email_sent` tinyint(1) DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_overdue_explanations`
--

CREATE TABLE `tm_overdue_explanations` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `reason_category` enum('Waiting Approval','Budget Delay','Capacity','External Delay','Unclear Scope','Tools/Resources','Staffing','Other') NOT NULL,
  `explanation` text NOT NULL,
  `corrective_action` text NOT NULL,
  `new_eta` date NOT NULL,
  `support_needed` text DEFAULT NULL,
  `submitted_by` int(11) NOT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `review_status` enum('Pending','Acknowledged','Escalated') DEFAULT 'Pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_phases`
--

CREATE TABLE `tm_phases` (
  `id` int(11) NOT NULL,
  `phase_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_pillars`
--

CREATE TABLE `tm_pillars` (
  `id` int(11) NOT NULL,
  `pillar_name` varchar(150) NOT NULL,
  `pillar_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#0d6efd' COMMENT 'Hex color for dashboards',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_settings`
--

CREATE TABLE `tm_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_label` varchar(255) DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_support_attachments`
--

CREATE TABLE `tm_support_attachments` (
  `id` int(11) NOT NULL,
  `support_request_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_support_requests`
--

CREATE TABLE `tm_support_requests` (
  `id` int(11) NOT NULL,
  `request_id` varchar(30) NOT NULL COMMENT 'Auto e.g. SR-2026-000001',
  `task_id` int(11) NOT NULL,
  `request_type` enum('Guidance','Budget','Tools','Extension','Staffing','Remove Blocker') NOT NULL,
  `description` text NOT NULL,
  `justification` text NOT NULL,
  `amount_kes` decimal(14,2) DEFAULT NULL COMMENT 'For budget requests',
  `requested_extension_date` date DEFAULT NULL COMMENT 'For extension requests',
  `requested_by` int(11) NOT NULL,
  `hod_endorsement` enum('Pending','Endorsed','Rejected','N/A') DEFAULT 'N/A',
  `hod_endorsed_by` int(11) DEFAULT NULL,
  `hod_endorsed_at` datetime DEFAULT NULL,
  `hod_notes` text DEFAULT NULL,
  `approver_id` int(11) DEFAULT NULL COMMENT 'CEO/Finance/Procurement user ID',
  `approval_status` enum('Pending','Approved','Rejected','Need Info') DEFAULT 'Pending',
  `approval_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `fulfillment_status` enum('N/A','Pending','In Progress','Fulfilled') DEFAULT 'N/A',
  `fulfillment_notes` text DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_support_sequence`
--

CREATE TABLE `tm_support_sequence` (
  `year` year(4) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_tasks`
--

CREATE TABLE `tm_tasks` (
  `id` int(11) NOT NULL,
  `task_id` varchar(30) NOT NULL COMMENT 'Auto-generated e.g. TSK-2026-000001',
  `strategy_year` year(4) NOT NULL DEFAULT 2026,
  `pillar_id` int(11) DEFAULT NULL,
  `workstream_id` int(11) DEFAULT NULL,
  `phase_id` int(11) DEFAULT NULL,
  `sn` int(11) DEFAULT NULL COMMENT 'Serial number from import sheet',
  `task_title` varchar(500) NOT NULL,
  `task_description` text DEFAULT NULL,
  `deliverable` text NOT NULL COMMENT 'What done looks like',
  `evidence_requirement` text DEFAULT NULL COMMENT 'JSON: types of evidence required',
  `owner_role` varchar(100) DEFAULT NULL COMMENT 'Role label e.g. Sales Lead',
  `owner_id` int(11) NOT NULL COMMENT 'registered_users.id',
  `watchers` text DEFAULT NULL COMMENT 'JSON array of user IDs',
  `priority` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `priority_rank` int(11) DEFAULT 0 COMMENT 'Numeric rank for CEO ordering',
  `start_date` date NOT NULL,
  `due_date` date NOT NULL,
  `cadence` enum('None','Daily','Weekly','Bi-weekly','Monthly','Quarterly','Semi-annual','Annual','Custom') DEFAULT 'None',
  `recurrence_rules` varchar(255) DEFAULT NULL COMMENT 'e.g. every 2 weeks on Mon',
  `recurrence_parent_id` int(11) DEFAULT NULL COMMENT 'Links occurrence to master template',
  `occurrence_number` int(11) DEFAULT NULL COMMENT 'Which occurrence this is (1,2,3...)',
  `dependencies_tasks` text DEFAULT NULL COMMENT 'JSON array of task IDs (predecessors)',
  `dependencies_other` text DEFAULT NULL COMMENT 'Non-task dependencies (text)',
  `budget_kes` decimal(14,2) DEFAULT NULL,
  `kpi_target` text DEFAULT NULL COMMENT 'KPI description or linked KPI',
  `kpi_impact_weight` tinyint(4) DEFAULT NULL COMMENT '1-5 or percentage',
  `status` enum('Draft','Assigned','In Progress','Blocked','On Hold','Submitted for Review','Completed','Verified','Cancelled') NOT NULL DEFAULT 'Assigned',
  `progress_pct` tinyint(3) UNSIGNED DEFAULT 0 COMMENT '0-100',
  `is_overdue` tinyint(1) DEFAULT 0 COMMENT 'System-computed',
  `days_overdue` int(11) DEFAULT 0 COMMENT 'System-computed',
  `overdue_explanation_required` tinyint(1) DEFAULT 0,
  `escalation_level` enum('None','Owner','HOD','CEO') DEFAULT 'None',
  `support_required` varchar(255) DEFAULT NULL COMMENT 'Comma-separated: Guidance,Budget,Extension,Tools,Staffing',
  `notes` text DEFAULT NULL,
  `import_batch_id` int(11) DEFAULT NULL COMMENT 'Links to tm_import_batches',
  `import_row_number` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_task_activity`
--

CREATE TABLE `tm_task_activity` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `activity_type` enum('comment','status_change','priority_change','date_change','owner_change','progress_update','evidence_upload','support_request','escalation','overdue_explanation','budget_change','general','import','recurrence') NOT NULL,
  `description` text NOT NULL COMMENT 'Human-readable description of what changed',
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Required for date/priority/cancellation changes',
  `performed_by` int(11) DEFAULT NULL COMMENT 'NULL = system action',
  `performed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_task_evidence`
--

CREATE TABLE `tm_task_evidence` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `evidence_type` enum('file','link','note','approval') NOT NULL DEFAULT 'file',
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Bytes',
  `mime_type` varchar(100) DEFAULT NULL,
  `link_url` varchar(1000) DEFAULT NULL,
  `note_text` text DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_task_sequence`
--

CREATE TABLE `tm_task_sequence` (
  `year` year(4) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tm_workstreams`
--

CREATE TABLE `tm_workstreams` (
  `id` int(11) NOT NULL,
  `pillar_id` int(11) NOT NULL,
  `workstream_name` varchar(150) NOT NULL,
  `workstream_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `hod_user_id` int(11) DEFAULT NULL COMMENT 'registered_users.id of workstream lead',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainings_details`
--

CREATE TABLE `trainings_details` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `title` varchar(500) NOT NULL,
  `date_training` varchar(200) NOT NULL,
  `place_training` varchar(500) NOT NULL,
  `price` varchar(100) NOT NULL,
  `local_price` varchar(100) NOT NULL,
  `flier` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unpaid_or_failed_payments`
--

CREATE TABLE `unpaid_or_failed_payments` (
  `app_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `surname` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phone_number` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `country` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` int(2) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(200) NOT NULL,
  `lastname` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` text NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 2 COMMENT '1 = admin, 2 = staff',
  `avatar` text NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_lead_forms`
--

CREATE TABLE `user_lead_forms` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(200) NOT NULL,
  `input_data` text NOT NULL,
  `adm_no` varchar(200) DEFAULT NULL,
  `date_applied` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_level_settlement`
--

CREATE TABLE `user_level_settlement` (
  `id` int(11) NOT NULL,
  `key_value` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_productivity`
--

CREATE TABLE `user_productivity` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `subject` varchar(200) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `user_id` int(11) NOT NULL,
  `time_rendered` float NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vacancy`
--

CREATE TABLE `vacancy` (
  `id` int(11) NOT NULL,
  `position` varchar(200) NOT NULL,
  `availability` int(11) NOT NULL,
  `description` text NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `virtual_intake`
--

CREATE TABLE `virtual_intake` (
  `id` int(11) NOT NULL,
  `intake_name` varchar(500) NOT NULL,
  `course_id` varchar(500) NOT NULL,
  `start_on` varchar(500) NOT NULL,
  `end_on` varchar(500) NOT NULL,
  `intake_id` varchar(200) NOT NULL,
  `created_on` varchar(500) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_all_enquiries`
-- (See below for the actual view)
--
CREATE TABLE `v_all_enquiries` (
`source_table` varchar(15)
,`record_id` int(11)
,`reference` varchar(50)
,`fullname` varchar(255)
,`email` varchar(255)
,`phone` varchar(50)
,`country` varchar(100)
,`organization` varchar(255)
,`interest_type` varchar(20)
,`priority` varchar(20)
,`status` varchar(20)
,`assigned_to` int(11)
,`created_at` datetime /* mariadb-5.3 */
,`updated_at` datetime /* mariadb-5.3 */
,`source_name` varchar(100)
,`program_name` varchar(255)
,`event_name` varchar(255)
,`amount_paid` decimal(15,2)
,`is_paid` int(2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_distinct_categories`
-- (See below for the actual view)
--
CREATE TABLE `v_distinct_categories` (
`category` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_enquiries_with_followup`
-- (See below for the actual view)
--
CREATE TABLE `v_enquiries_with_followup` (
`source_table` varchar(15)
,`record_id` int(11)
,`reference` varchar(50)
,`fullname` varchar(255)
,`email` varchar(255)
,`phone` varchar(50)
,`country` varchar(100)
,`organization` varchar(255)
,`interest_type` varchar(20)
,`priority` varchar(20)
,`status` varchar(20)
,`assigned_to` int(11)
,`created_at` datetime /* mariadb-5.3 */
,`updated_at` datetime /* mariadb-5.3 */
,`source_name` varchar(100)
,`program_name` varchar(255)
,`event_name` varchar(255)
,`amount_paid` decimal(15,2)
,`is_paid` int(2)
,`total_followups` bigint(21)
,`next_followup_date` date
,`next_action` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_expense_report`
-- (See below for the actual view)
--
CREATE TABLE `v_expense_report` (
`expense_id` int(11)
,`reference_number` varchar(100)
,`amount` decimal(10,2)
,`expense_date` datetime
,`notes` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_expense_report_new`
-- (See below for the actual view)
--
CREATE TABLE `v_expense_report_new` (
`expense_id` int(11)
,`expense_name` varchar(255)
,`reference_number` varchar(100)
,`amount` decimal(10,2)
,`expense_date` datetime
,`notes` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_followups_overdue`
-- (See below for the actual view)
--
CREATE TABLE `v_followups_overdue` (
`id` int(11)
,`enquiry_type` enum('enquiry','register','ticket_congress')
,`enquiry_id` varchar(50)
,`staff_id` int(11)
,`action_taken` text
,`client_response` text
,`next_step` varchar(255)
,`reminder_date` date
,`reminder_time` time
,`is_completed` tinyint(1)
,`completed_at` datetime
,`created_at` datetime
,`staff_name` varchar(255)
,`days_overdue` int(8)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_followups_today`
-- (See below for the actual view)
--
CREATE TABLE `v_followups_today` (
`id` int(11)
,`enquiry_type` enum('enquiry','register','ticket_congress')
,`enquiry_id` varchar(50)
,`staff_id` int(11)
,`action_taken` text
,`client_response` text
,`next_step` varchar(255)
,`reminder_date` date
,`reminder_time` time
,`is_completed` tinyint(1)
,`completed_at` datetime
,`created_at` datetime
,`staff_name` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_payroll_eligible_staff`
-- (See below for the actual view)
--
CREATE TABLE `v_payroll_eligible_staff` (
`id` int(11)
,`staff_id` varchar(20)
,`full_name` varchar(255)
,`email` varchar(255)
,`job_title` varchar(255)
,`department_id` int(11)
,`department_name` varchar(100)
,`basic_salary` decimal(12,2)
,`allowances` longtext
,`deductions` longtext
,`kra_pin` varchar(20)
,`nssf_number` varchar(20)
,`nhif_number` varchar(20)
,`employment_type` enum('permanent','contract','temporary','internship')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_payroll_period_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_payroll_period_summary` (
`id` int(11)
,`period_code` varchar(20)
,`period_name` varchar(50)
,`period_month` tinyint(4)
,`period_year` year(4)
,`status` enum('draft','open','processing','pending_approval','approved','paid','closed')
,`payment_date` date
,`total_employees` int(11)
,`total_gross` decimal(15,2)
,`total_deductions` decimal(15,2)
,`total_net` decimal(15,2)
,`total_employer_costs` decimal(15,2)
,`hr_prepared_at` datetime
,`finance_approved_at` datetime
,`ceo_approved_at` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pending_onboarding`
-- (See below for the actual view)
--
CREATE TABLE `v_pending_onboarding` (
`id` int(11)
,`staff_id` varchar(20)
,`full_name` varchar(255)
,`email` varchar(255)
,`phone` varchar(20)
,`national_id` varchar(50)
,`onboarding_status` enum('pending','under_review','approved','rejected','active','inactive','terminated')
,`submitted_at` datetime
,`days_pending` int(8)
,`qualifications` bigint(21)
,`documents` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_remittance_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_remittance_summary` (
`id` int(11)
,`period_id` int(11)
,`recipient_id` int(11)
,`remittance_type` varchar(50)
,`remittance_name` varchar(255)
,`employee_amount` decimal(12,2)
,`employer_amount` decimal(12,2)
,`total_amount` decimal(12,2)
,`employee_count` int(11)
,`due_date` date
,`status` enum('pending','processing','paid','overdue','partially_paid')
,`payment_date` date
,`payment_method` varchar(50)
,`payment_reference` varchar(100)
,`payment_amount` decimal(12,2)
,`bank_name` varchar(100)
,`receipt_number` varchar(100)
,`receipt_file` varchar(255)
,`notes` text
,`penalty_amount` decimal(12,2)
,`interest_amount` decimal(12,2)
,`created_by` int(11)
,`created_at` datetime
,`paid_by` int(11)
,`paid_at` datetime
,`updated_at` datetime
,`period_code` varchar(20)
,`period_name` varchar(50)
,`period_month` tinyint(4)
,`period_year` year(4)
,`recipient_type` enum('statutory','sacco','bank','insurance','pension','union','other')
,`recipient_bank` varchar(100)
,`recipient_account` varchar(50)
,`paybill_number` varchar(20)
,`display_status` varchar(9)
,`days_to_due` int(8)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_staff_list`
-- (See below for the actual view)
--
CREATE TABLE `v_staff_list` (
`id` int(11)
,`staff_id` varchar(20)
,`full_name` varchar(255)
,`email` varchar(255)
,`phone` varchar(20)
,`national_id` varchar(50)
,`job_title` varchar(255)
,`department_id` int(11)
,`department_name` varchar(100)
,`department_code` varchar(20)
,`employment_type` enum('permanent','contract','temporary','internship')
,`start_date` date
,`work_location` enum('office','remote','hybrid')
,`onboarding_status` enum('pending','under_review','approved','rejected','active','inactive','terminated')
,`created_at` datetime
,`supervisor_name` varchar(255)
,`qualification_count` bigint(21)
,`document_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_staff_salary_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_staff_salary_summary` (
`id` int(11)
,`staff_id` varchar(20)
,`full_name` varchar(255)
,`job_title` varchar(255)
,`department_name` varchar(100)
,`basic_salary` decimal(12,2)
,`allowances` longtext
,`deductions` longtext
,`employment_type` enum('permanent','contract','temporary','internship')
,`onboarding_status` enum('pending','under_review','approved','rejected','active','inactive','terminated')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_tm_overdue_tasks`
-- (See below for the actual view)
--
CREATE TABLE `v_tm_overdue_tasks` (
`id` int(11)
,`task_id` varchar(30)
,`task_title` varchar(500)
,`priority` enum('Critical','High','Medium','Low')
,`due_date` date
,`status` enum('Draft','Assigned','In Progress','Blocked','On Hold','Submitted for Review','Completed','Verified','Cancelled')
,`owner_id` int(11)
,`escalation_level` enum('None','Owner','HOD','CEO')
,`owner_name` varchar(500)
,`pillar_name` varchar(150)
,`workstream_name` varchar(150)
,`days_overdue` int(8)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_tm_pending_support`
-- (See below for the actual view)
--
CREATE TABLE `v_tm_pending_support` (
`id` int(11)
,`request_id` varchar(30)
,`task_id` int(11)
,`request_type` enum('Guidance','Budget','Tools','Extension','Staffing','Remove Blocker')
,`description` text
,`justification` text
,`amount_kes` decimal(14,2)
,`requested_extension_date` date
,`requested_by` int(11)
,`hod_endorsement` enum('Pending','Endorsed','Rejected','N/A')
,`hod_endorsed_by` int(11)
,`hod_endorsed_at` datetime
,`hod_notes` text
,`approver_id` int(11)
,`approval_status` enum('Pending','Approved','Rejected','Need Info')
,`approval_notes` text
,`approved_by` int(11)
,`approved_at` datetime
,`fulfillment_status` enum('N/A','Pending','In Progress','Fulfilled')
,`fulfillment_notes` text
,`fulfilled_at` datetime
,`created_at` datetime
,`updated_at` datetime
,`task_code` varchar(30)
,`task_title` varchar(500)
,`task_owner_id` int(11)
,`requester_name` varchar(500)
,`approver_name` varchar(500)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_tm_tasks`
-- (See below for the actual view)
--
CREATE TABLE `v_tm_tasks` (
`id` int(11)
,`task_id` varchar(30)
,`strategy_year` year(4)
,`pillar_id` int(11)
,`workstream_id` int(11)
,`phase_id` int(11)
,`sn` int(11)
,`task_title` varchar(500)
,`task_description` text
,`deliverable` text
,`evidence_requirement` text
,`owner_role` varchar(100)
,`owner_id` int(11)
,`watchers` text
,`priority` enum('Critical','High','Medium','Low')
,`priority_rank` int(11)
,`start_date` date
,`due_date` date
,`cadence` enum('None','Daily','Weekly','Bi-weekly','Monthly','Quarterly','Semi-annual','Annual','Custom')
,`recurrence_rules` varchar(255)
,`recurrence_parent_id` int(11)
,`occurrence_number` int(11)
,`dependencies_tasks` text
,`dependencies_other` text
,`budget_kes` decimal(14,2)
,`kpi_target` text
,`kpi_impact_weight` tinyint(4)
,`status` enum('Draft','Assigned','In Progress','Blocked','On Hold','Submitted for Review','Completed','Verified','Cancelled')
,`progress_pct` tinyint(3) unsigned
,`is_overdue` tinyint(1)
,`days_overdue` int(11)
,`overdue_explanation_required` tinyint(1)
,`escalation_level` enum('None','Owner','HOD','CEO')
,`support_required` varchar(255)
,`notes` text
,`import_batch_id` int(11)
,`import_row_number` int(11)
,`created_by` int(11)
,`created_at` datetime
,`updated_by` int(11)
,`updated_at` datetime
,`pillar_name` varchar(150)
,`pillar_code` varchar(20)
,`pillar_color` varchar(7)
,`workstream_name` varchar(150)
,`workstream_code` varchar(20)
,`phase_name` varchar(100)
,`owner_name` varchar(500)
,`owner_email` varchar(255)
,`computed_days_overdue` int(8)
,`computed_is_overdue` int(2)
);

-- --------------------------------------------------------

--
-- Table structure for table `wa_ad_map`
--

CREATE TABLE `wa_ad_map` (
  `id` int(10) UNSIGNED NOT NULL,
  `ad_id` varchar(190) NOT NULL,
  `ref_type` enum('course','event') NOT NULL,
  `ref_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_broadcasts`
--

CREATE TABLE `wa_broadcasts` (
  `id` int(10) UNSIGNED NOT NULL,
  `template` varchar(190) NOT NULL,
  `language` varchar(16) NOT NULL DEFAULT 'en',
  `audience` varchar(32) NOT NULL DEFAULT 'all',
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_contacts`
--

CREATE TABLE `wa_contacts` (
  `id` int(10) UNSIGNED NOT NULL,
  `wa_id` varchar(32) NOT NULL,
  `profile_name` varchar(190) DEFAULT NULL,
  `opted_in` tinyint(1) NOT NULL DEFAULT 0,
  `opted_out` tinyint(1) NOT NULL DEFAULT 0,
  `opted_out_at` datetime DEFAULT NULL,
  `last_inbound_at` datetime DEFAULT NULL,
  `register_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_conversations`
--

CREATE TABLE `wa_conversations` (
  `id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL,
  `ref_type` enum('course','event','unknown') NOT NULL DEFAULT 'unknown',
  `ref_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_user_id` int(10) UNSIGNED DEFAULT NULL,
  `handler` enum('ai','human') NOT NULL DEFAULT 'ai',
  `escalated` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `last_route_reason` varchar(64) DEFAULT NULL,
  `last_route_confidence` decimal(4,3) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_read_at` datetime DEFAULT NULL,
  `last_human_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_course_owner`
--

CREATE TABLE `wa_course_owner` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref_type` enum('course','event') NOT NULL,
  `ref_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_enroll_sessions`
--

CREATE TABLE `wa_enroll_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL,
  `wa_id` varchar(32) NOT NULL,
  `ref_type` enum('course','event') NOT NULL,
  `ref_id` int(10) UNSIGNED NOT NULL,
  `step` int(11) NOT NULL DEFAULT 0,
  `data` text DEFAULT NULL,
  `status` enum('offered','collecting','confirm','done','cancelled') NOT NULL DEFAULT 'collecting',
  `result_ref` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_kb_learnings`
--

CREATE TABLE `wa_kb_learnings` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref_type` enum('course','event') NOT NULL,
  `ref_id` int(10) UNSIGNED NOT NULL,
  `conversation_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `message_id` bigint(20) UNSIGNED DEFAULT NULL,
  `body` mediumtext NOT NULL,
  `status` enum('pending','approved','dismissed') NOT NULL DEFAULT 'pending',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_knowledge`
--

CREATE TABLE `wa_knowledge` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref_type` enum('course','event','program') NOT NULL,
  `ref_id` int(10) UNSIGNED NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `body_ai` mediumtext DEFAULT NULL,
  `ai_updated_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_messages`
--

CREATE TABLE `wa_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wa_message_id` varchar(128) DEFAULT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'text',
  `body` mediumtext DEFAULT NULL,
  `media_id` varchar(190) DEFAULT NULL,
  `media_mime` varchar(120) DEFAULT NULL,
  `referral_ad_id` varchar(190) DEFAULT NULL,
  `broadcast_id` int(10) UNSIGNED DEFAULT NULL,
  `wa_timestamp` datetime DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_programs`
--

CREATE TABLE `wa_programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_quick_replies`
--

CREATE TABLE `wa_quick_replies` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` mediumtext NOT NULL,
  `ref_type` enum('course','event') DEFAULT NULL,
  `ref_id` int(10) UNSIGNED DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_scheduled_broadcasts`
--

CREATE TABLE `wa_scheduled_broadcasts` (
  `id` int(10) UNSIGNED NOT NULL,
  `template` varchar(190) NOT NULL,
  `language` varchar(16) NOT NULL DEFAULT 'en',
  `audience` varchar(32) NOT NULL DEFAULT 'all',
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `vars` text DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` enum('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sent` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `broadcast_id` int(10) UNSIGNED DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `run_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_settings`
--

CREATE TABLE `wa_settings` (
  `key` varchar(64) NOT NULL,
  `value` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wa_templates`
--

CREATE TABLE `wa_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `language` varchar(16) NOT NULL DEFAULT 'en',
  `category` varchar(32) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `status` enum('approved','pending','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_programs`
--
ALTER TABLE `academic_programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `allowance_types`
--
ALTER TABLE `allowance_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`allowance_code`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_punch` (`device_id`,`device_user_id`,`punch_time`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_time` (`punch_time`);

--
-- Indexes for table `commission_audit_log`
--
ALTER TABLE `commission_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `commission_records`
--
ALTER TABLE `commission_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_commission` (`commission_type`,`source_id`),
  ADD KEY `idx_type_source` (`commission_type`,`source_id`),
  ADD KEY `idx_staff` (`staff_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_eligible` (`is_eligible`);

--
-- Indexes for table `commission_settings`
--
ALTER TABLE `commission_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `CompanyNomination`
--
ALTER TABLE `CompanyNomination`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event` (`corporate_program_event_id`),
  ADD KEY `idx_submitted` (`submitted_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `CompanyNominationStaff`
--
ALTER TABLE `CompanyNominationStaff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_nomination_id` (`company_nomination_id`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm_tm_tasks`
--
ALTER TABLE `crm_tm_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_task_code` (`task_code`),
  ADD KEY `idx_assigned_user` (`assigned_to_user_id`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `crm_tm_task_requirements`
--
ALTER TABLE `crm_tm_task_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_id` (`task_id`),
  ADD KEY `idx_requested_by` (`requested_by_user_id`);

--
-- Indexes for table `crm_tm_task_sequence`
--
ALTER TABLE `crm_tm_task_sequence`
  ADD PRIMARY KEY (`year`);

--
-- Indexes for table `crm_tm_task_updates`
--
ALTER TABLE `crm_tm_task_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_id` (`task_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `custom_income`
--
ALTER TABLE `custom_income`
  ADD PRIMARY KEY (`income_id`),
  ADD KEY `income_date` (`income_date`),
  ADD KEY `income_source` (`income_source`);

--
-- Indexes for table `deductions`
--
ALTER TABLE `deductions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`deduction_code`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_id` (`department_id`),
  ADD KEY `department_head` (`department_head`);

--
-- Indexes for table `device_user_map`
--
ALTER TABLE `device_user_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_device_user` (`device_id`,`device_user_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_staff_table` (`staff_table_id`);

--
-- Indexes for table `dpo_payment`
--
ALTER TABLE `dpo_payment`
  ADD KEY `idx_recorded_by` (`recorded_by`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source` (`source_type`,`source_id`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_recipient` (`recipient_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `email_schedules`
--
ALTER TABLE `email_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled_date` (`scheduled_date`),
  ADD KEY `idx_email_type` (`email_type`);

--
-- Indexes for table `email_schedule_logs`
--
ALTER TABLE `email_schedule_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_recipient_email` (`recipient_email`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `enquiry_ref` (`enquiry_ref`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_source_id` (`source_id`),
  ADD KEY `idx_interest_type` (`interest_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `enquiry_flags`
--
ALTER TABLE `enquiry_flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_flag` (`enquiry_type`,`enquiry_id`,`flag_type`),
  ADD KEY `idx_flag_type` (`flag_type`),
  ADD KEY `idx_enquiry_flag` (`enquiry_type`,`enquiry_id`),
  ADD KEY `fk_flag_staff` (`flagged_by`);

--
-- Indexes for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reminder` (`reminder_date`,`is_completed`),
  ADD KEY `idx_enquiry` (`enquiry_type`,`enquiry_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_completed` (`is_completed`);

--
-- Indexes for table `enquiry_notifications`
--
ALTER TABLE `enquiry_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_read` (`staff_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `enquiry_sources`
--
ALTER TABLE `enquiry_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_source_name` (`name`);

--
-- Indexes for table `Event`
--
ALTER TABLE `Event`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `expense_date` (`expense_date`),
  ADD KEY `category` (`category`);

--
-- Indexes for table `free_sessions`
--
ALTER TABLE `free_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_free_sessions_slug` (`slug`),
  ADD KEY `idx_free_sessions_type_status` (`session_type`,`status`),
  ADD KEY `idx_free_sessions_status_dates` (`status`,`start_on`,`end_on`),
  ADD KEY `idx_free_sessions_sort` (`sort_order`,`id`);

--
-- Indexes for table `free_session_highlights`
--
ALTER TABLE `free_session_highlights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_highlights_session_sort` (`free_session_id`,`sort_order`,`id`);

--
-- Indexes for table `free_session_outcomes`
--
ALTER TABLE `free_session_outcomes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_outcomes_session_sort` (`free_session_id`,`sort_order`,`id`);

--
-- Indexes for table `free_session_registrations`
--
ALTER TABLE `free_session_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_free_session_email` (`free_session_id`,`email`),
  ADD KEY `idx_free_session_registrations_created` (`created_at`);

--
-- Indexes for table `intake`
--
ALTER TABLE `intake`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lead_insights`
--
ALTER TABLE `lead_insights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source_row` (`source`,`source_id`),
  ADD KEY `idx_converted` (`is_converted`),
  ADD KEY `idx_country` (`country_norm`),
  ADD KEY `idx_segment` (`lead_segment`),
  ADD KEY `idx_status` (`lead_status`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_email` (`email_norm`);

--
-- Indexes for table `lead_sync_state`
--
ALTER TABLE `lead_sync_state`
  ADD PRIMARY KEY (`source`);

--
-- Indexes for table `marketing_data_email`
--
ALTER TABLE `marketing_data_email`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marketing_data_email_one`
--
ALTER TABLE `marketing_data_email_one`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marketing_email_messages`
--
ALTER TABLE `marketing_email_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organization_training_form`
--
ALTER TABLE `organization_training_form`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parent_program`
--
ALTER TABLE `parent_program`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parent_strategic_objective`
--
ALTER TABLE `parent_strategic_objective`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `payroll_approval_log`
--
ALTER TABLE `payroll_approval_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period` (`period_id`);

--
-- Indexes for table `payroll_expense_records`
--
ALTER TABLE `payroll_expense_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period` (`period_id`),
  ADD KEY `idx_expense` (`expense_id`);

--
-- Indexes for table `payroll_inputs`
--
ALTER TABLE `payroll_inputs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period_staff` (`period_id`,`staff_id`),
  ADD KEY `idx_period` (`period_id`),
  ADD KEY `idx_staff` (`staff_id`);

--
-- Indexes for table `payroll_payments`
--
ALTER TABLE `payroll_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period` (`period_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `payroll_record_id` (`payroll_record_id`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period` (`period_year`,`period_month`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_period_code` (`period_code`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period_staff` (`period_id`,`staff_id`),
  ADD KEY `idx_period` (`period_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payroll_remittances`
--
ALTER TABLE `payroll_remittances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period` (`period_id`),
  ADD KEY `idx_type` (`remittance_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_recipient` (`recipient_id`);

--
-- Indexes for table `payroll_remittance_details`
--
ALTER TABLE `payroll_remittance_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_remittance` (`remittance_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `payroll_record_id` (`payroll_record_id`);

--
-- Indexes for table `payroll_remittance_log`
--
ALTER TABLE `payroll_remittance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_remittance` (`remittance_id`);

--
-- Indexes for table `payroll_settings`
--
ALTER TABLE `payroll_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`setting_key`,`effective_from`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `priority_agenda`
--
ALTER TABLE `priority_agenda`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `program_curriculum`
--
ALTER TABLE `program_curriculum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `program_lecturers`
--
ALTER TABLE `program_lecturers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `program_outcomes`
--
ALTER TABLE `program_outcomes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `program_sector`
--
ALTER TABLE `program_sector`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_list`
--
ALTER TABLE `project_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proposal_requests`
--
ALTER TABLE `proposal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`client_email`),
  ADD KEY `idx_course` (`course_name`),
  ADD KEY `idx_date` (`request_date`);

--
-- Indexes for table `recruitment_status`
--
ALTER TABLE `recruitment_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `register`
--
ALTER TABLE `register`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entry_id` (`entry_id`),
  ADD KEY `idx_register_assigned` (`assigned_to`),
  ADD KEY `idx_register_status` (`lead_status`);

--
-- Indexes for table `registered_users`
--
ALTER TABLE `registered_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_department` (`department_id`),
  ADD KEY `idx_registered_users_staff` (`staff_id`);

--
-- Indexes for table `remittance_recipients`
--
ALTER TABLE `remittance_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`recipient_type`),
  ADD KEY `idx_deduction` (`deduction_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `request_types`
--
ALTER TABLE `request_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `responsible`
--
ALTER TABLE `responsible`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `responsible_agencies`
--
ALTER TABLE `responsible_agencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scheduled_email`
--
ALTER TABLE `scheduled_email`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedule_mail`
--
ALTER TABLE `schedule_mail`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `send_email`
--
ALTER TABLE `send_email`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `status` (`status`),
  ADD KEY `date_submitted` (`date_submitted`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_national_id` (`national_id`),
  ADD UNIQUE KEY `unique_kra_pin` (`kra_pin`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_national_id` (`national_id`),
  ADD KEY `idx_kra_pin` (`kra_pin`),
  ADD KEY `idx_status` (`onboarding_status`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_staff_system_user` (`system_user_id`);

--
-- Indexes for table `staff_deduction_assignments`
--
ALTER TABLE `staff_deduction_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_recipient` (`recipient_id`),
  ADD KEY `idx_type` (`deduction_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `staff_deduction_exemptions`
--
ALTER TABLE `staff_deduction_exemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff` (`staff_id`);

--
-- Indexes for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_type` (`document_type`);

--
-- Indexes for table `staff_leave_balance`
--
ALTER TABLE `staff_leave_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff_leave_year` (`staff_id`,`leave_type`,`year`);

--
-- Indexes for table `staff_onboarding_log`
--
ALTER TABLE `staff_onboarding_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `staff_qualifications`
--
ALTER TABLE `staff_qualifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_type` (`qualification_type`);

--
-- Indexes for table `stakeholder`
--
ALTER TABLE `stakeholder`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `strategic_pillar`
--
ALTER TABLE `strategic_pillar`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `strategic_plans`
--
ALTER TABLE `strategic_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sustainable_goals`
--
ALTER TABLE `sustainable_goals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_emails`
--
ALTER TABLE `system_emails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_emails1`
--
ALTER TABLE `system_emails1`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_type` (`email_type`);

--
-- Indexes for table `system_emails_config`
--
ALTER TABLE `system_emails_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_list`
--
ALTER TABLE `task_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_congress`
--
ALTER TABLE `ticket_congress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `idx_ticket_assigned` (`assigned_to`),
  ADD KEY `idx_ticket_status` (`lead_status`);

--
-- Indexes for table `tm_import_batches`
--
ALTER TABLE `tm_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `imported_by` (`imported_by`);

--
-- Indexes for table `tm_notifications`
--
ALTER TABLE `tm_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_id`,`is_read`),
  ADD KEY `idx_type` (`notification_type`),
  ADD KEY `idx_email` (`is_email_sent`),
  ADD KEY `idx_task` (`task_id`);

--
-- Indexes for table `tm_overdue_explanations`
--
ALTER TABLE `tm_overdue_explanations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task` (`task_id`),
  ADD KEY `idx_status` (`review_status`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `tm_phases`
--
ALTER TABLE `tm_phases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tm_pillars`
--
ALTER TABLE `tm_pillars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pillar_name` (`pillar_name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tm_settings`
--
ALTER TABLE `tm_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setting_key` (`setting_key`);

--
-- Indexes for table `tm_support_attachments`
--
ALTER TABLE `tm_support_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_request_id` (`support_request_id`);

--
-- Indexes for table `tm_support_requests`
--
ALTER TABLE `tm_support_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_request_id` (`request_id`),
  ADD KEY `idx_task` (`task_id`),
  ADD KEY `idx_type` (`request_type`),
  ADD KEY `idx_approval` (`approval_status`),
  ADD KEY `idx_requested_by` (`requested_by`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `tm_support_sequence`
--
ALTER TABLE `tm_support_sequence`
  ADD PRIMARY KEY (`year`);

--
-- Indexes for table `tm_tasks`
--
ALTER TABLE `tm_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_task_id` (`task_id`),
  ADD KEY `idx_strategy_year` (`strategy_year`),
  ADD KEY `idx_pillar` (`pillar_id`),
  ADD KEY `idx_workstream` (`workstream_id`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`,`priority_rank`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_overdue` (`is_overdue`),
  ADD KEY `idx_recurrence_parent` (`recurrence_parent_id`),
  ADD KEY `idx_import_batch` (`import_batch_id`),
  ADD KEY `phase_id` (`phase_id`);

--
-- Indexes for table `tm_task_activity`
--
ALTER TABLE `tm_task_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task` (`task_id`),
  ADD KEY `idx_type` (`activity_type`),
  ADD KEY `idx_performed_at` (`performed_at`);

--
-- Indexes for table `tm_task_evidence`
--
ALTER TABLE `tm_task_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task` (`task_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `tm_task_sequence`
--
ALTER TABLE `tm_task_sequence`
  ADD PRIMARY KEY (`year`);

--
-- Indexes for table `tm_workstreams`
--
ALTER TABLE `tm_workstreams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pillar` (`pillar_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `hod_user_id` (`hod_user_id`);

--
-- Indexes for table `trainings_details`
--
ALTER TABLE `trainings_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `index` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_lead_forms`
--
ALTER TABLE `user_lead_forms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_level_settlement`
--
ALTER TABLE `user_level_settlement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_productivity`
--
ALTER TABLE `user_productivity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vacancy`
--
ALTER TABLE `vacancy`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `virtual_intake`
--
ALTER TABLE `virtual_intake`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wa_ad_map`
--
ALTER TABLE `wa_ad_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_ad_map_ad` (`ad_id`);

--
-- Indexes for table `wa_broadcasts`
--
ALTER TABLE `wa_broadcasts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wa_contacts`
--
ALTER TABLE `wa_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_contacts_wa_id` (`wa_id`);

--
-- Indexes for table `wa_conversations`
--
ALTER TABLE `wa_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_conv_contact` (`contact_id`),
  ADD KEY `idx_wa_conv_assigned` (`assigned_user_id`),
  ADD KEY `idx_wa_conv_ref` (`ref_type`,`ref_id`);

--
-- Indexes for table `wa_course_owner`
--
ALTER TABLE `wa_course_owner`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_course_owner` (`ref_type`,`ref_id`);

--
-- Indexes for table `wa_enroll_sessions`
--
ALTER TABLE `wa_enroll_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_enroll_contact` (`contact_id`),
  ADD KEY `idx_wa_enroll_status` (`status`);

--
-- Indexes for table `wa_kb_learnings`
--
ALTER TABLE `wa_kb_learnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wa_kb_learn_ref` (`ref_type`,`ref_id`,`status`);

--
-- Indexes for table `wa_knowledge`
--
ALTER TABLE `wa_knowledge`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_knowledge` (`ref_type`,`ref_id`);

--
-- Indexes for table `wa_messages`
--
ALTER TABLE `wa_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_messages_wamid` (`wa_message_id`),
  ADD KEY `idx_wa_messages_contact` (`contact_id`),
  ADD KEY `idx_wa_messages_broadcast` (`broadcast_id`);

--
-- Indexes for table `wa_programs`
--
ALTER TABLE `wa_programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_program_name` (`name`);

--
-- Indexes for table `wa_quick_replies`
--
ALTER TABLE `wa_quick_replies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wa_scheduled_broadcasts`
--
ALTER TABLE `wa_scheduled_broadcasts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wa_sched_due` (`status`,`scheduled_at`);

--
-- Indexes for table `wa_settings`
--
ALTER TABLE `wa_settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `wa_templates`
--
ALTER TABLE `wa_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wa_templates` (`name`,`language`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_programs`
--
ALTER TABLE `academic_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `allowance_types`
--
ALTER TABLE `allowance_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_audit_log`
--
ALTER TABLE `commission_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_records`
--
ALTER TABLE `commission_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_settings`
--
ALTER TABLE `commission_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `CompanyNomination`
--
ALTER TABLE `CompanyNomination`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `CompanyNominationStaff`
--
ALTER TABLE `CompanyNominationStaff`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_tm_tasks`
--
ALTER TABLE `crm_tm_tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_tm_task_requirements`
--
ALTER TABLE `crm_tm_task_requirements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_tm_task_updates`
--
ALTER TABLE `crm_tm_task_updates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_income`
--
ALTER TABLE `custom_income`
  MODIFY `income_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deductions`
--
ALTER TABLE `deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `device_user_map`
--
ALTER TABLE `device_user_map`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_schedules`
--
ALTER TABLE `email_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_schedule_logs`
--
ALTER TABLE `email_schedule_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiry_flags`
--
ALTER TABLE `enquiry_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiry_notifications`
--
ALTER TABLE `enquiry_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiry_sources`
--
ALTER TABLE `enquiry_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Event`
--
ALTER TABLE `Event`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `free_sessions`
--
ALTER TABLE `free_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `free_session_highlights`
--
ALTER TABLE `free_session_highlights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `free_session_outcomes`
--
ALTER TABLE `free_session_outcomes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `free_session_registrations`
--
ALTER TABLE `free_session_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `intake`
--
ALTER TABLE `intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_insights`
--
ALTER TABLE `lead_insights`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_data_email_one`
--
ALTER TABLE `marketing_data_email_one`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_email_messages`
--
ALTER TABLE `marketing_email_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organization_training_form`
--
ALTER TABLE `organization_training_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_program`
--
ALTER TABLE `parent_program`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_strategic_objective`
--
ALTER TABLE `parent_strategic_objective`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_approval_log`
--
ALTER TABLE `payroll_approval_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_expense_records`
--
ALTER TABLE `payroll_expense_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_inputs`
--
ALTER TABLE `payroll_inputs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_payments`
--
ALTER TABLE `payroll_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_remittances`
--
ALTER TABLE `payroll_remittances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_remittance_details`
--
ALTER TABLE `payroll_remittance_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_remittance_log`
--
ALTER TABLE `payroll_remittance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_settings`
--
ALTER TABLE `payroll_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `priority_agenda`
--
ALTER TABLE `priority_agenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_curriculum`
--
ALTER TABLE `program_curriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_lecturers`
--
ALTER TABLE `program_lecturers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_outcomes`
--
ALTER TABLE `program_outcomes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_sector`
--
ALTER TABLE `program_sector`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_list`
--
ALTER TABLE `project_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proposal_requests`
--
ALTER TABLE `proposal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recruitment_status`
--
ALTER TABLE `recruitment_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `register`
--
ALTER TABLE `register`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registered_users`
--
ALTER TABLE `registered_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remittance_recipients`
--
ALTER TABLE `remittance_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_comments`
--
ALTER TABLE `request_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_types`
--
ALTER TABLE `request_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `responsible`
--
ALTER TABLE `responsible`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `responsible_agencies`
--
ALTER TABLE `responsible_agencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scheduled_email`
--
ALTER TABLE `scheduled_email`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_mail`
--
ALTER TABLE `schedule_mail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `send_email`
--
ALTER TABLE `send_email`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_deduction_assignments`
--
ALTER TABLE `staff_deduction_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_deduction_exemptions`
--
ALTER TABLE `staff_deduction_exemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_leave_balance`
--
ALTER TABLE `staff_leave_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_onboarding_log`
--
ALTER TABLE `staff_onboarding_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_qualifications`
--
ALTER TABLE `staff_qualifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stakeholder`
--
ALTER TABLE `stakeholder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `strategic_pillar`
--
ALTER TABLE `strategic_pillar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `strategic_plans`
--
ALTER TABLE `strategic_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sustainable_goals`
--
ALTER TABLE `sustainable_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_emails`
--
ALTER TABLE `system_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_emails1`
--
ALTER TABLE `system_emails1`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_emails_config`
--
ALTER TABLE `system_emails_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_list`
--
ALTER TABLE `task_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_congress`
--
ALTER TABLE `ticket_congress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_import_batches`
--
ALTER TABLE `tm_import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_notifications`
--
ALTER TABLE `tm_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_overdue_explanations`
--
ALTER TABLE `tm_overdue_explanations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_phases`
--
ALTER TABLE `tm_phases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_pillars`
--
ALTER TABLE `tm_pillars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_settings`
--
ALTER TABLE `tm_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_support_attachments`
--
ALTER TABLE `tm_support_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_support_requests`
--
ALTER TABLE `tm_support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_tasks`
--
ALTER TABLE `tm_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_task_activity`
--
ALTER TABLE `tm_task_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_task_evidence`
--
ALTER TABLE `tm_task_evidence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tm_workstreams`
--
ALTER TABLE `tm_workstreams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trainings_details`
--
ALTER TABLE `trainings_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_lead_forms`
--
ALTER TABLE `user_lead_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_level_settlement`
--
ALTER TABLE `user_level_settlement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_productivity`
--
ALTER TABLE `user_productivity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vacancy`
--
ALTER TABLE `vacancy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `virtual_intake`
--
ALTER TABLE `virtual_intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_ad_map`
--
ALTER TABLE `wa_ad_map`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_broadcasts`
--
ALTER TABLE `wa_broadcasts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_contacts`
--
ALTER TABLE `wa_contacts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_conversations`
--
ALTER TABLE `wa_conversations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_course_owner`
--
ALTER TABLE `wa_course_owner`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_enroll_sessions`
--
ALTER TABLE `wa_enroll_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_kb_learnings`
--
ALTER TABLE `wa_kb_learnings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_knowledge`
--
ALTER TABLE `wa_knowledge`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_messages`
--
ALTER TABLE `wa_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_programs`
--
ALTER TABLE `wa_programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_quick_replies`
--
ALTER TABLE `wa_quick_replies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_scheduled_broadcasts`
--
ALTER TABLE `wa_scheduled_broadcasts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_templates`
--
ALTER TABLE `wa_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_all_enquiries`
--
DROP TABLE IF EXISTS `v_all_enquiries`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_all_enquiries`  AS SELECT 'enquiry' FROM (`enquiries` `e` left join `enquiry_sources` `es` on(`e`.`source_id` = `es`.`id`)) WHERE `e`.`converted_to` is nullunion allselect 'register' collate utf8mb4_unicode_ci AS `source_table`,`r`.`id` AS `record_id`,cast(`r`.`entry_id` as char(50) charset utf8mb4) collate utf8mb4_unicode_ci AS `reference`,cast(concat(coalesce(`r`.`firstname`,''),' ',coalesce(`r`.`lastname`,'')) as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `fullname`,cast(`r`.`email` as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `email`,cast(`r`.`phone_number` as char(50) charset utf8mb4) collate utf8mb4_unicode_ci AS `phone`,cast(`r`.`country` as char(100) charset utf8mb4) collate utf8mb4_unicode_ci AS `country`,cast(NULL as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `organization`,'virtual' collate utf8mb4_unicode_ci AS `interest_type`,'medium' collate utf8mb4_unicode_ci AS `priority`,cast(coalesce(`r`.`lead_status`,'new') as char(20) charset utf8mb4) collate utf8mb4_unicode_ci AS `status`,`r`.`assigned_to` AS `assigned_to`,`r`.`datee` AS `created_at`,`r`.`datee` AS `updated_at`,cast(case `r`.`source` when 1 then 'Website' when 4 then 'WhatsApp' when 5 then 'Facebook' else 'Other' end as char(100) charset utf8mb4) collate utf8mb4_unicode_ci AS `source_name`,cast(`c`.`course` as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `program_name`,cast(NULL as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `event_name`,cast(coalesce((select sum(`dpo_payment`.`TransactionAmount`) from `dpo_payment` where `dpo_payment`.`email` = `r`.`email` and `dpo_payment`.`app_id` = `r`.`entry_id`),0) as decimal(15,2)) AS `amount_paid`,case when (select sum(`dpo_payment`.`TransactionAmount`) from `dpo_payment` where `dpo_payment`.`email` = `r`.`email` and `dpo_payment`.`app_id` = `r`.`entry_id`) > 0 then 1 else 0 end AS `is_paid` from (`register` `r` left join `course` `c` on(`r`.`program` = `c`.`id` or `r`.`program` = `c`.`course_id`)) union all select 'ticket_congress' collate utf8mb4_unicode_ci AS `source_table`,`t`.`id` AS `record_id`,cast(`t`.`ticket_id` as char(50) charset utf8mb4) collate utf8mb4_unicode_ci AS `reference`,cast(`t`.`fullname` as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `fullname`,cast(`t`.`email` as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `email`,cast(`t`.`phone_number` as char(50) charset utf8mb4) collate utf8mb4_unicode_ci AS `phone`,cast(`t`.`country` as char(100) charset utf8mb4) collate utf8mb4_unicode_ci AS `country`,cast(`t`.`organization` as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `organization`,'international' collate utf8mb4_unicode_ci AS `interest_type`,'medium' collate utf8mb4_unicode_ci AS `priority`,cast(case `t`.`status` when 1 then 'new' when 2 then 'qualified' else 'new' end as char(20) charset utf8mb4) collate utf8mb4_unicode_ci AS `status`,`t`.`assigned_to` AS `assigned_to`,`t`.`date_sent` AS `created_at`,`t`.`date_sent` AS `updated_at`,'Website' collate utf8mb4_unicode_ci AS `source_name`,cast(NULL as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `program_name`,cast(`ev`.`event_title` as char(255) charset utf8mb4) collate utf8mb4_unicode_ci AS `event_name`,cast(coalesce((select sum(`dpo_payment`.`TransactionAmount`) from `dpo_payment` where `dpo_payment`.`email` = `t`.`email` and (`dpo_payment`.`app_id` = `t`.`ticket_id` or `dpo_payment`.`app_id` = `t`.`id`)),0) as decimal(15,2)) AS `amount_paid`,case when `t`.`status` = 2 then 1 else 0 end AS `is_paid` from (`ticket_congress` `t` left join `Event` `ev` on(`t`.`event_id` = `ev`.`event_id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `v_distinct_categories`
--
DROP TABLE IF EXISTS `v_distinct_categories`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_distinct_categories`  AS SELECT DISTINCT `expenses`.`category` AS `category` FROM `expenses` WHERE `expenses`.`category` is not null AND `expenses`.`category` <> '' ;

-- --------------------------------------------------------

--
-- Structure for view `v_enquiries_with_followup`
--
DROP TABLE IF EXISTS `v_enquiries_with_followup`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_enquiries_with_followup`  AS SELECT `e`.`source_table` AS `source_table`, `e`.`record_id` AS `record_id`, `e`.`reference` AS `reference`, `e`.`fullname` AS `fullname`, `e`.`email` AS `email`, `e`.`phone` AS `phone`, `e`.`country` AS `country`, `e`.`organization` AS `organization`, `e`.`interest_type` AS `interest_type`, `e`.`priority` AS `priority`, `e`.`status` AS `status`, `e`.`assigned_to` AS `assigned_to`, `e`.`created_at` AS `created_at`, `e`.`updated_at` AS `updated_at`, `e`.`source_name` AS `source_name`, `e`.`program_name` AS `program_name`, `e`.`event_name` AS `event_name`, `e`.`amount_paid` AS `amount_paid`, `e`.`is_paid` AS `is_paid`, (select count(0) from `enquiry_followups` `f` where `f`.`enquiry_type` = `e`.`source_table` and `f`.`enquiry_id` = `e`.`record_id`) AS `total_followups`, (select max(`f`.`reminder_date`) from `enquiry_followups` `f` where `f`.`enquiry_type` = `e`.`source_table` and `f`.`enquiry_id` = `e`.`record_id` and `f`.`is_completed` = 0) AS `next_followup_date`, (select `f`.`next_step` from `enquiry_followups` `f` where `f`.`enquiry_type` = `e`.`source_table` and `f`.`enquiry_id` = `e`.`record_id` and `f`.`is_completed` = 0 order by `f`.`reminder_date` limit 1) AS `next_action` FROM `v_all_enquiries` AS `e` ;

-- --------------------------------------------------------

--
-- Structure for view `v_expense_report`
--
DROP TABLE IF EXISTS `v_expense_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_expense_report`  AS SELECT `expenses`.`expense_id` AS `expense_id`, `expenses`.`reference_number` AS `reference_number`, `expenses`.`amount` AS `amount`, `expenses`.`expense_date` AS `expense_date`, `expenses`.`notes` AS `notes` FROM `expenses` WHERE `expenses`.`category` = 'Other' ;

-- --------------------------------------------------------

--
-- Structure for view `v_expense_report_new`
--
DROP TABLE IF EXISTS `v_expense_report_new`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_expense_report_new`  AS SELECT `expenses`.`expense_id` AS `expense_id`, `expenses`.`expense_name` AS `expense_name`, `expenses`.`reference_number` AS `reference_number`, `expenses`.`amount` AS `amount`, `expenses`.`expense_date` AS `expense_date`, `expenses`.`notes` AS `notes` FROM `expenses` WHERE `expenses`.`category` = 'Other' ;

-- --------------------------------------------------------

--
-- Structure for view `v_followups_overdue`
--
DROP TABLE IF EXISTS `v_followups_overdue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_followups_overdue`  AS SELECT `f`.`id` AS `id`, `f`.`enquiry_type` AS `enquiry_type`, `f`.`enquiry_id` AS `enquiry_id`, `f`.`staff_id` AS `staff_id`, `f`.`action_taken` AS `action_taken`, `f`.`client_response` AS `client_response`, `f`.`next_step` AS `next_step`, `f`.`reminder_date` AS `reminder_date`, `f`.`reminder_time` AS `reminder_time`, `f`.`is_completed` AS `is_completed`, `f`.`completed_at` AS `completed_at`, `f`.`created_at` AS `created_at`, cast(`ru`.`fullname` as char(255) charset utf8mb4) FROM (`enquiry_followups` `f` left join `registered_users` `ru` on(`f`.`staff_id` = `ru`.`id`)) WHERE `f`.`reminder_date` < curdate() AND `f`.`is_completed` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `v_followups_today`
--
DROP TABLE IF EXISTS `v_followups_today`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_followups_today`  AS SELECT `f`.`id` AS `id`, `f`.`enquiry_type` AS `enquiry_type`, `f`.`enquiry_id` AS `enquiry_id`, `f`.`staff_id` AS `staff_id`, `f`.`action_taken` AS `action_taken`, `f`.`client_response` AS `client_response`, `f`.`next_step` AS `next_step`, `f`.`reminder_date` AS `reminder_date`, `f`.`reminder_time` AS `reminder_time`, `f`.`is_completed` AS `is_completed`, `f`.`completed_at` AS `completed_at`, `f`.`created_at` AS `created_at`, cast(`ru`.`fullname` as char(255) charset utf8mb4) FROM (`enquiry_followups` `f` left join `registered_users` `ru` on(`f`.`staff_id` = `ru`.`id`)) WHERE `f`.`reminder_date` = curdate() AND `f`.`is_completed` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `v_payroll_eligible_staff`
--
DROP TABLE IF EXISTS `v_payroll_eligible_staff`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_payroll_eligible_staff`  AS SELECT `s`.`id` AS `id`, `s`.`staff_id` AS `staff_id`, `s`.`full_name` AS `full_name`, `s`.`email` AS `email`, `s`.`job_title` AS `job_title`, `s`.`department_id` AS `department_id`, `d`.`department_name` AS `department_name`, `s`.`basic_salary` AS `basic_salary`, `s`.`allowances` AS `allowances`, `s`.`deductions` AS `deductions`, `s`.`kra_pin` AS `kra_pin`, `s`.`nssf_number` AS `nssf_number`, `s`.`nhif_number` AS `nhif_number`, `s`.`employment_type` AS `employment_type` FROM (`staff` `s` left join `departments` `d` on(`s`.`department_id` = `d`.`id`)) WHERE `s`.`onboarding_status` = 'active' AND `s`.`basic_salary` > 0 ;

-- --------------------------------------------------------

--
-- Structure for view `v_payroll_period_summary`
--
DROP TABLE IF EXISTS `v_payroll_period_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_payroll_period_summary`  AS SELECT `pp`.`id` AS `id`, `pp`.`period_code` AS `period_code`, `pp`.`period_name` AS `period_name`, `pp`.`period_month` AS `period_month`, `pp`.`period_year` AS `period_year`, `pp`.`status` AS `status`, `pp`.`payment_date` AS `payment_date`, `pp`.`total_employees` AS `total_employees`, `pp`.`total_gross` AS `total_gross`, `pp`.`total_deductions` AS `total_deductions`, `pp`.`total_net` AS `total_net`, `pp`.`total_employer_costs` AS `total_employer_costs`, `pp`.`hr_prepared_at` AS `hr_prepared_at`, `pp`.`finance_approved_at` AS `finance_approved_at`, `pp`.`ceo_approved_at` AS `ceo_approved_at` FROM `payroll_periods` AS `pp` ORDER BY `pp`.`period_year` DESC, `pp`.`period_month` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_pending_onboarding`
--
DROP TABLE IF EXISTS `v_pending_onboarding`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_onboarding`  AS SELECT `s`.`id` AS `id`, `s`.`staff_id` AS `staff_id`, `s`.`full_name` AS `full_name`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`national_id` AS `national_id`, `s`.`onboarding_status` AS `onboarding_status`, `s`.`created_at` AS `submitted_at`, to_days(current_timestamp()) - to_days(`s`.`created_at`) AS `days_pending`, (select count(0) from `staff_qualifications` where `staff_qualifications`.`staff_id` = `s`.`id`) AS `qualifications`, (select count(0) from `staff_documents` where `staff_documents`.`staff_id` = `s`.`id`) AS `documents` FROM `staff` AS `s` WHERE `s`.`onboarding_status` in ('pending','under_review') ORDER BY `s`.`created_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_remittance_summary`
--
DROP TABLE IF EXISTS `v_remittance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_remittance_summary`  AS SELECT `pr`.`id` AS `id`, `pr`.`period_id` AS `period_id`, `pr`.`recipient_id` AS `recipient_id`, `pr`.`remittance_type` AS `remittance_type`, `pr`.`remittance_name` AS `remittance_name`, `pr`.`employee_amount` AS `employee_amount`, `pr`.`employer_amount` AS `employer_amount`, `pr`.`total_amount` AS `total_amount`, `pr`.`employee_count` AS `employee_count`, `pr`.`due_date` AS `due_date`, `pr`.`status` AS `status`, `pr`.`payment_date` AS `payment_date`, `pr`.`payment_method` AS `payment_method`, `pr`.`payment_reference` AS `payment_reference`, `pr`.`payment_amount` AS `payment_amount`, `pr`.`bank_name` AS `bank_name`, `pr`.`receipt_number` AS `receipt_number`, `pr`.`receipt_file` AS `receipt_file`, `pr`.`notes` AS `notes`, `pr`.`penalty_amount` AS `penalty_amount`, `pr`.`interest_amount` AS `interest_amount`, `pr`.`created_by` AS `created_by`, `pr`.`created_at` AS `created_at`, `pr`.`paid_by` AS `paid_by`, `pr`.`paid_at` AS `paid_at`, `pr`.`updated_at` AS `updated_at`, `pp`.`period_code` AS `period_code`, `pp`.`period_name` AS `period_name`, `pp`.`period_month` AS `period_month`, `pp`.`period_year` AS `period_year`, `rr`.`recipient_type` AS `recipient_type`, `rr`.`bank_name` AS `recipient_bank`, `rr`.`account_number` AS `recipient_account`, `rr`.`paybill_number` AS `paybill_number`, CASE WHEN `pr`.`status` = 'paid' THEN 'Paid' WHEN `pr`.`due_date` < curdate() AND `pr`.`status` <> 'paid' THEN 'Overdue' WHEN `pr`.`due_date` = curdate() THEN 'Due Today' ELSE 'Pending' END AS `display_status`, to_days(`pr`.`due_date`) - to_days(curdate()) AS `days_to_due` FROM ((`payroll_remittances` `pr` join `payroll_periods` `pp` on(`pr`.`period_id` = `pp`.`id`)) left join `remittance_recipients` `rr` on(`pr`.`recipient_id` = `rr`.`id`)) ORDER BY `pr`.`due_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_staff_list`
--
DROP TABLE IF EXISTS `v_staff_list`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_staff_list`  AS SELECT `s`.`id` AS `id`, `s`.`staff_id` AS `staff_id`, `s`.`full_name` AS `full_name`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`national_id` AS `national_id`, `s`.`job_title` AS `job_title`, `s`.`department_id` AS `department_id`, `d`.`department_name` AS `department_name`, `d`.`department_id` AS `department_code`, `s`.`employment_type` AS `employment_type`, `s`.`start_date` AS `start_date`, `s`.`work_location` AS `work_location`, `s`.`onboarding_status` AS `onboarding_status`, `s`.`created_at` AS `created_at`, `sup`.`full_name` AS `supervisor_name`, (select count(0) from `staff_qualifications` where `staff_qualifications`.`staff_id` = `s`.`id`) AS `qualification_count`, (select count(0) from `staff_documents` where `staff_documents`.`staff_id` = `s`.`id`) AS `document_count` FROM ((`staff` `s` left join `departments` `d` on(`s`.`department_id` = `d`.`id`)) left join `staff` `sup` on(`s`.`reporting_to` = `sup`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_staff_salary_summary`
--
DROP TABLE IF EXISTS `v_staff_salary_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_staff_salary_summary`  AS SELECT `s`.`id` AS `id`, `s`.`staff_id` AS `staff_id`, `s`.`full_name` AS `full_name`, `s`.`job_title` AS `job_title`, `d`.`department_name` AS `department_name`, `s`.`basic_salary` AS `basic_salary`, `s`.`allowances` AS `allowances`, `s`.`deductions` AS `deductions`, `s`.`employment_type` AS `employment_type`, `s`.`onboarding_status` AS `onboarding_status` FROM (`staff` `s` left join `departments` `d` on(`s`.`department_id` = `d`.`id`)) WHERE `s`.`onboarding_status` in ('approved','active') ;

-- --------------------------------------------------------

--
-- Structure for view `v_tm_overdue_tasks`
--
DROP TABLE IF EXISTS `v_tm_overdue_tasks`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_tm_overdue_tasks`  AS SELECT `t`.`id` AS `id`, `t`.`task_id` AS `task_id`, `t`.`task_title` AS `task_title`, `t`.`priority` AS `priority`, `t`.`due_date` AS `due_date`, `t`.`status` AS `status`, `t`.`owner_id` AS `owner_id`, `t`.`escalation_level` AS `escalation_level`, `u`.`fullname` AS `owner_name`, `p`.`pillar_name` AS `pillar_name`, `w`.`workstream_name` AS `workstream_name`, to_days(curdate()) - to_days(`t`.`due_date`) AS `days_overdue` FROM (((`tm_tasks` `t` left join `registered_users` `u` on(`t`.`owner_id` = `u`.`id`)) left join `tm_pillars` `p` on(`t`.`pillar_id` = `p`.`id`)) left join `tm_workstreams` `w` on(`t`.`workstream_id` = `w`.`id`)) WHERE `t`.`status` not in ('Completed','Verified','Cancelled') AND curdate() > `t`.`due_date` ORDER BY to_days(curdate()) - to_days(`t`.`due_date`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_tm_pending_support`
--
DROP TABLE IF EXISTS `v_tm_pending_support`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_tm_pending_support`  AS SELECT `sr`.`id` AS `id`, `sr`.`request_id` AS `request_id`, `sr`.`task_id` AS `task_id`, `sr`.`request_type` AS `request_type`, `sr`.`description` AS `description`, `sr`.`justification` AS `justification`, `sr`.`amount_kes` AS `amount_kes`, `sr`.`requested_extension_date` AS `requested_extension_date`, `sr`.`requested_by` AS `requested_by`, `sr`.`hod_endorsement` AS `hod_endorsement`, `sr`.`hod_endorsed_by` AS `hod_endorsed_by`, `sr`.`hod_endorsed_at` AS `hod_endorsed_at`, `sr`.`hod_notes` AS `hod_notes`, `sr`.`approver_id` AS `approver_id`, `sr`.`approval_status` AS `approval_status`, `sr`.`approval_notes` AS `approval_notes`, `sr`.`approved_by` AS `approved_by`, `sr`.`approved_at` AS `approved_at`, `sr`.`fulfillment_status` AS `fulfillment_status`, `sr`.`fulfillment_notes` AS `fulfillment_notes`, `sr`.`fulfilled_at` AS `fulfilled_at`, `sr`.`created_at` AS `created_at`, `sr`.`updated_at` AS `updated_at`, `t`.`task_id` AS `task_code`, `t`.`task_title` AS `task_title`, `t`.`owner_id` AS `task_owner_id`, `requester`.`fullname` AS `requester_name`, `approver`.`fullname` AS `approver_name` FROM (((`tm_support_requests` `sr` join `tm_tasks` `t` on(`sr`.`task_id` = `t`.`id`)) left join `registered_users` `requester` on(`sr`.`requested_by` = `requester`.`id`)) left join `registered_users` `approver` on(`sr`.`approver_id` = `approver`.`id`)) WHERE `sr`.`approval_status` = 'Pending' ;

-- --------------------------------------------------------

--
-- Structure for view `v_tm_tasks`
--
DROP TABLE IF EXISTS `v_tm_tasks`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vantage`@`localhost` SQL SECURITY DEFINER VIEW `v_tm_tasks`  AS SELECT `t`.`id` AS `id`, `t`.`task_id` AS `task_id`, `t`.`strategy_year` AS `strategy_year`, `t`.`pillar_id` AS `pillar_id`, `t`.`workstream_id` AS `workstream_id`, `t`.`phase_id` AS `phase_id`, `t`.`sn` AS `sn`, `t`.`task_title` AS `task_title`, `t`.`task_description` AS `task_description`, `t`.`deliverable` AS `deliverable`, `t`.`evidence_requirement` AS `evidence_requirement`, `t`.`owner_role` AS `owner_role`, `t`.`owner_id` AS `owner_id`, `t`.`watchers` AS `watchers`, `t`.`priority` AS `priority`, `t`.`priority_rank` AS `priority_rank`, `t`.`start_date` AS `start_date`, `t`.`due_date` AS `due_date`, `t`.`cadence` AS `cadence`, `t`.`recurrence_rules` AS `recurrence_rules`, `t`.`recurrence_parent_id` AS `recurrence_parent_id`, `t`.`occurrence_number` AS `occurrence_number`, `t`.`dependencies_tasks` AS `dependencies_tasks`, `t`.`dependencies_other` AS `dependencies_other`, `t`.`budget_kes` AS `budget_kes`, `t`.`kpi_target` AS `kpi_target`, `t`.`kpi_impact_weight` AS `kpi_impact_weight`, `t`.`status` AS `status`, `t`.`progress_pct` AS `progress_pct`, `t`.`is_overdue` AS `is_overdue`, `t`.`days_overdue` AS `days_overdue`, `t`.`overdue_explanation_required` AS `overdue_explanation_required`, `t`.`escalation_level` AS `escalation_level`, `t`.`support_required` AS `support_required`, `t`.`notes` AS `notes`, `t`.`import_batch_id` AS `import_batch_id`, `t`.`import_row_number` AS `import_row_number`, `t`.`created_by` AS `created_by`, `t`.`created_at` AS `created_at`, `t`.`updated_by` AS `updated_by`, `t`.`updated_at` AS `updated_at`, `p`.`pillar_name` AS `pillar_name`, `p`.`pillar_code` AS `pillar_code`, `p`.`color` AS `pillar_color`, `w`.`workstream_name` AS `workstream_name`, `w`.`workstream_code` AS `workstream_code`, `ph`.`phase_name` AS `phase_name`, `u`.`fullname` AS `owner_name`, `u`.`email` AS `owner_email`, to_days(curdate()) - to_days(`t`.`due_date`) AS `computed_days_overdue`, CASE WHEN `t`.`status` in ('Completed','Verified','Cancelled') THEN 0 WHEN curdate() > `t`.`due_date` THEN 1 ELSE 0 END AS `computed_is_overdue` FROM ((((`tm_tasks` `t` left join `tm_pillars` `p` on(`t`.`pillar_id` = `p`.`id`)) left join `tm_workstreams` `w` on(`t`.`workstream_id` = `w`.`id`)) left join `tm_phases` `ph` on(`t`.`phase_id` = `ph`.`id`)) left join `registered_users` `u` on(`t`.`owner_id` = `u`.`id`)) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `CompanyNomination`
--
ALTER TABLE `CompanyNomination`
  ADD CONSTRAINT `fk_nomination_event` FOREIGN KEY (`corporate_program_event_id`) REFERENCES `Event` (`event_id`) ON UPDATE CASCADE;

--
-- Constraints for table `CompanyNominationStaff`
--
ALTER TABLE `CompanyNominationStaff`
  ADD CONSTRAINT `fk_staff_nomination` FOREIGN KEY (`company_nomination_id`) REFERENCES `CompanyNomination` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`department_head`) REFERENCES `registered_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_schedule_logs`
--
ALTER TABLE `email_schedule_logs`
  ADD CONSTRAINT `email_schedule_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `email_schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD CONSTRAINT `fk_enquiry_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `registered_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_enquiry_source` FOREIGN KEY (`source_id`) REFERENCES `enquiry_sources` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enquiry_flags`
--
ALTER TABLE `enquiry_flags`
  ADD CONSTRAINT `fk_flag_staff` FOREIGN KEY (`flagged_by`) REFERENCES `registered_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  ADD CONSTRAINT `fk_followup_staff` FOREIGN KEY (`staff_id`) REFERENCES `registered_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enquiry_notifications`
--
ALTER TABLE `enquiry_notifications`
  ADD CONSTRAINT `fk_notification_staff` FOREIGN KEY (`staff_id`) REFERENCES `registered_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `free_session_highlights`
--
ALTER TABLE `free_session_highlights`
  ADD CONSTRAINT `fk_free_session_highlights_session` FOREIGN KEY (`free_session_id`) REFERENCES `free_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `free_session_outcomes`
--
ALTER TABLE `free_session_outcomes`
  ADD CONSTRAINT `fk_free_session_outcomes_session` FOREIGN KEY (`free_session_id`) REFERENCES `free_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `free_session_registrations`
--
ALTER TABLE `free_session_registrations`
  ADD CONSTRAINT `fk_free_session_registrations_session` FOREIGN KEY (`free_session_id`) REFERENCES `free_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_approval_log`
--
ALTER TABLE `payroll_approval_log`
  ADD CONSTRAINT `payroll_approval_log_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_expense_records`
--
ALTER TABLE `payroll_expense_records`
  ADD CONSTRAINT `payroll_expense_records_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_inputs`
--
ALTER TABLE `payroll_inputs`
  ADD CONSTRAINT `payroll_inputs_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_inputs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_payments`
--
ALTER TABLE `payroll_payments`
  ADD CONSTRAINT `payroll_payments_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_payments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_payments_ibfk_3` FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_records_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_remittances`
--
ALTER TABLE `payroll_remittances`
  ADD CONSTRAINT `payroll_remittances_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_remittances_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `remittance_recipients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_remittance_details`
--
ALTER TABLE `payroll_remittance_details`
  ADD CONSTRAINT `payroll_remittance_details_ibfk_1` FOREIGN KEY (`remittance_id`) REFERENCES `payroll_remittances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_remittance_details_ibfk_2` FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_remittance_details_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_remittance_log`
--
ALTER TABLE `payroll_remittance_log`
  ADD CONSTRAINT `payroll_remittance_log_ibfk_1` FOREIGN KEY (`remittance_id`) REFERENCES `payroll_remittances` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `program_curriculum`
--
ALTER TABLE `program_curriculum`
  ADD CONSTRAINT `program_curriculum_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `academic_programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `program_lecturers`
--
ALTER TABLE `program_lecturers`
  ADD CONSTRAINT `program_lecturers_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `academic_programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `program_outcomes`
--
ALTER TABLE `program_outcomes`
  ADD CONSTRAINT `program_outcomes_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `academic_programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registered_users`
--
ALTER TABLE `registered_users`
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD CONSTRAINT `request_comments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_deduction_assignments`
--
ALTER TABLE `staff_deduction_assignments`
  ADD CONSTRAINT `staff_deduction_assignments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_deduction_assignments_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `remittance_recipients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_deduction_exemptions`
--
ALTER TABLE `staff_deduction_exemptions`
  ADD CONSTRAINT `staff_deduction_exemptions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD CONSTRAINT `staff_documents_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_leave_balance`
--
ALTER TABLE `staff_leave_balance`
  ADD CONSTRAINT `staff_leave_balance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_onboarding_log`
--
ALTER TABLE `staff_onboarding_log`
  ADD CONSTRAINT `staff_onboarding_log_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_qualifications`
--
ALTER TABLE `staff_qualifications`
  ADD CONSTRAINT `staff_qualifications_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tm_import_batches`
--
ALTER TABLE `tm_import_batches`
  ADD CONSTRAINT `tm_import_batches_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `registered_users` (`id`);

--
-- Constraints for table `tm_notifications`
--
ALTER TABLE `tm_notifications`
  ADD CONSTRAINT `tm_notifications_ibfk_1` FOREIGN KEY (`recipient_id`) REFERENCES `registered_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tm_notifications_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tm_tasks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tm_overdue_explanations`
--
ALTER TABLE `tm_overdue_explanations`
  ADD CONSTRAINT `tm_overdue_explanations_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tm_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tm_overdue_explanations_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `registered_users` (`id`);

--
-- Constraints for table `tm_support_attachments`
--
ALTER TABLE `tm_support_attachments`
  ADD CONSTRAINT `tm_support_attachments_ibfk_1` FOREIGN KEY (`support_request_id`) REFERENCES `tm_support_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tm_support_requests`
--
ALTER TABLE `tm_support_requests`
  ADD CONSTRAINT `tm_support_requests_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tm_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tm_support_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `registered_users` (`id`),
  ADD CONSTRAINT `tm_support_requests_ibfk_3` FOREIGN KEY (`approver_id`) REFERENCES `registered_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tm_tasks`
--
ALTER TABLE `tm_tasks`
  ADD CONSTRAINT `tm_tasks_ibfk_1` FOREIGN KEY (`pillar_id`) REFERENCES `tm_pillars` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tm_tasks_ibfk_2` FOREIGN KEY (`workstream_id`) REFERENCES `tm_workstreams` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tm_tasks_ibfk_3` FOREIGN KEY (`phase_id`) REFERENCES `tm_phases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tm_tasks_ibfk_4` FOREIGN KEY (`owner_id`) REFERENCES `registered_users` (`id`),
  ADD CONSTRAINT `tm_tasks_ibfk_5` FOREIGN KEY (`recurrence_parent_id`) REFERENCES `tm_tasks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tm_task_activity`
--
ALTER TABLE `tm_task_activity`
  ADD CONSTRAINT `tm_task_activity_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tm_tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tm_task_evidence`
--
ALTER TABLE `tm_task_evidence`
  ADD CONSTRAINT `tm_task_evidence_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tm_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tm_task_evidence_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `registered_users` (`id`);

--
-- Constraints for table `tm_workstreams`
--
ALTER TABLE `tm_workstreams`
  ADD CONSTRAINT `tm_workstreams_ibfk_1` FOREIGN KEY (`pillar_id`) REFERENCES `tm_pillars` (`id`),
  ADD CONSTRAINT `tm_workstreams_ibfk_2` FOREIGN KEY (`hod_user_id`) REFERENCES `registered_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wa_conversations`
--
ALTER TABLE `wa_conversations`
  ADD CONSTRAINT `fk_wa_conv_contact` FOREIGN KEY (`contact_id`) REFERENCES `wa_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wa_messages`
--
ALTER TABLE `wa_messages`
  ADD CONSTRAINT `fk_wa_messages_contact` FOREIGN KEY (`contact_id`) REFERENCES `wa_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
