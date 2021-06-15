<?php

$installer = $this;

$installer->startSetup();

try {
	$installer->run("
		ALTER TABLE {$this->getTable('sales_flat_order_payment')} ADD `cc_cid_enc` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Cc Cid Enc' AFTER `cc_last4`;	
	");
}
catch (Exception $e) {
	// ignore duplicate error
}

$installer->endSetup();
