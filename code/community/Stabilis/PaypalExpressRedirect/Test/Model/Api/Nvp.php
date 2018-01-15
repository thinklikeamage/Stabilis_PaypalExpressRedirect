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
		$coreSessionMock = $this
          ->getMockBuilder('Mage_Core_Model_Session')
          ->setMethods(array('start'))
          ->getMock();
		$this->replaceByMock('singleton', 'core/session', $coreSessionMock);
	}
	
	/**
	 * Tests the model default constructor.
	 */
	public function testConstructor() {
		
		$version = str_replace('magento-mirror-', '', getenv('MAGENTO_VERSION'));
		
		$class = new ReflectionClass(get_class($this->_model));
		$property = $class->getProperty('_requiredResponseParams');
		$property->setAccessible(true);
		$requiredResponseParams = $property->getValue($this->_model);
		
		$doExpressCheckoutPaymentString = $class->getConstant('DO_EXPRESS_CHECKOUT_PAYMENT');

        if (version_compare($version, '1.9', '>=')) {
			
		    $this->assertArrayHasKey($doExpressCheckoutPaymentString, $requiredResponseParams);
		
			$this->assertEquals(array('ACK', 'CORRELATIONID'), $requiredResponseParams[$doExpressCheckoutPaymentString]);
		} else {
			$this->assertArrayNotHasKey($doExpressCheckoutPaymentString, $requiredResponseParams);
		}
	}
	
	protected function _getHandleCallErrorsMethod() {
		$class = new ReflectionClass(get_class($this->_model));
		$method = $class->getMethod('_handleCallErrors');
		$method->setAccessible(true);
		return $method;
	}
	
	protected function _getFailureResponse($code) {
		return array(
			'ACK' => 'Failure', 
			'L_ERRORCODE0' => $code, 
			'L_SHORTMESSAGE0' => 'Short Message', 
			'L_LONGMESSAGE0' => 'Long Message', 
			'L_SEVERITYCODE0' => ''
		);
	}
	
	public function testUnsupportedResponse() {
		$expected = 'PayPal gateway has rejected request. Long Message (#12345: Short Message).';
		$method = $this->_getHandleCallErrorsMethod();
		try {
			$method->invoke($this->_model, $this->_getFailureResponse(12345));
			$this->assertTrue(false);
		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}
	}
	
/*	public function test10486Response() {
		$method = $this->_getHandleCallErrorsMethod();
	}
	*/
	public function test10417Response() {
		$expected = sprintf(
		    'PayPal could not process your payment at this time.  Please <a href="%s">click here</a> to select a different payment method from within your PayPal account and try again.', 
		    Mage::getUrl('paypal/express/edit')
		);
		$method = $this->_getHandleCallErrorsMethod();
		try {
			$method->invoke($this->_model, $this->_getFailureResponse(10417));
			$this->assertTrue(false);
		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}
	}
	
	public function test10422Response() {
		$expected = sprintf(
		    'PayPal could not process your payment at this time.  Please <a href="%s">click here</a> to select a different payment method from within your PayPal account and try again.', 
		    Mage::getUrl('paypal/express/edit')
		);
		$method = $this->_getHandleCallErrorsMethod();
		try {
			$method->invoke($this->_model, $this->_getFailureResponse(10422));
			$this->assertTrue(false);
		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}
	}
	
	public function test10736Response() {
		$expected = 'PayPal has determined that the specified shipping address does not exist.  Please double-check your shipping address and try again.';
		$method = $this->_getHandleCallErrorsMethod();
		try {
			$method->invoke($this->_model, $this->_getFailureResponse(10736));
			$this->assertTrue(false);
		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}
	}
}
