ALTER TABLE `customer_entity` ADD `calltab_manager_id` INT UNSIGNED NOT NULL;
ALTER TABLE `customer_entity` ADD INDEX(`calltab_manager_id`);

DROP TABLE customer_callorder_grid;

ALTER TABLE calltab_customer DROP COLUMN internal_sales_rep;

CREATE TABLE `calltab_customer` (
  `customer_id` int(10) unsigned NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `last_call_date` datetime NOT NULL,
  `last_call_outcome` varchar(255) NOT NULL,
  `last_purchase_date` datetime NOT NULL,
  `last_sales_call_date` datetime NOT NULL,
  `days_to_action_count` int(10) unsigned NOT NULL,
  `website_id` int(10) unsigned NOT NULL,
  `manager_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`customer_id`),
  KEY `manager_id` (`manager_id`),
  KEY `last_call_date` (`last_call_date`),
  KEY `last_call_outcome` (`last_call_outcome`),
  KEY `last_purchase_date` (`last_purchase_date`),
  KEY `last_sales_call_date` (`last_sales_call_date`),
  KEY `website_id` (`website_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE `calls`;
CREATE TABLE `calls` (
  `call_id` int(11) NOT NULL AUTO_INCREMENT,
  `uploaded_file` varchar(200) DEFAULT NULL,
  `call_type_id` smallint(6) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `notes` text,
  `call_timestamp` datetime NOT NULL,
  `incoming_call_type_id` smallint(6) DEFAULT NULL,
  `person_spoke_to` varchar(100) DEFAULT NULL,
  `so_number` int(11) DEFAULT NULL,
  `phone_fax_number` varchar(50) DEFAULT NULL,
  `attachment` varchar(200) DEFAULT NULL,
  `cover_page` varchar(1000) DEFAULT NULL,
  `manager_id` int(10) unsigned NOT NULL DEFAULT '0',
  `order_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`call_id`),
  KEY `manager_id` (`manager_id`),
  KEY `customer_id` (`customer_id`),
  KEY `call_type_id` (`call_type_id`),
  KEY `call_timestamp` (`call_timestamp`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1
