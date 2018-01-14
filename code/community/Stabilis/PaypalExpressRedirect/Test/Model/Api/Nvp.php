<?php

class Stabilis_PaypalExpressRedirect_Test_Model_Api_Nvp extends EcomDev_PHPUnit_Test_Case {
	
	/**
	 * The model under test.
	 * 
	 * @var Stabilis_PaypalExpressRedirect_Model_Api_Nvp
	 */
	protected $_model;
	
	/**
	 * {@inheritdoc}
	 */
	protected function setUp() {
		parent::setUp();
		$this->_model = Mage::getModel('stabilis_paypalexpressredirect/api_nvp');
	}
	
	/**
	 * Tests the model default constructor.
	 */
	public function testConstructor() {
		
		$version = str_replace('magento-mirror-', '', getenv('MAGENTO_VERSION'));
		
		// Need to access a protected member...
		$class = new ReflectionClass(get_class($this->_model));
		$property = $class->getProperty('_requiredResponseParams');
		$property->setAccessible(true);
		$requiredResponseParams = $property->getValue($this->_model);
		
		$doExpressCheckoutPaymentString = $class->getConstant('DO_EXPRESS_CHECKOUT_PAYMENT');
		$this->assertArrayHasKey($doExpressCheckoutPaymentString, $requiredResponseParams);
		
		$requiredDoExpressCheckoutPaymentResponseParams = $requiredResponseParams[$doExpressCheckoutPaymentString];
        if (version_compare($version, '1.9', '>=')) {
			$this->assertEquals(array('ACK', 'CORRELATIONID'), $requiredDoExpressCheckoutPaymentResponseParams);
		} else {
			$this->assertEquals(array('ACK', 'CORRELATIONID', 'AMT'), $requiredDoExpressCheckoutPaymentResponseParams);
		}
	}
}
