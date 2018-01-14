<?php

class Stabilis_PaypalExpressRedirect_Test_Helper_Data extends EcomDev_PHPUnit_Test_Case {
	
	protected $_helper;

    protected function setUp() {
		parent::setUp();
		$this->_helper = Mage::helper('stabilis_paypalexpressredirect');
	}
	
	public function testThing() {
		$this->assertTrue(true);
	}
}
