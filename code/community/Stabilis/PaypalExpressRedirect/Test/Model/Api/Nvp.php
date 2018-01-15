<?php

/**
 * Test case for the Nvp override.
 * 
 * @covers Stabilis_PaypalExpressRedirect_Model_Api_Nvp
 */
class Stabilis_PaypalExpressRedirect_Test_Model_Api_Nvp extends EcomDev_PHPUnit_Test_Case {

	/**
	 * The model under test.
	 * 
	 * @var Stabilis_PaypalExpressRedirect_Model_Api_Nvp
	 */
	protected $_model;

	/**
	 * The class under test.
	 * 
	 * @var ReflectionClass
	 */
	protected $_modelClass;

	/**
	 * The version string of the current Magento instance.
	 * 
	 * @var string
	 */
	protected $_magentoVersion;

	/**
	 * {@inheritdoc}
	 */
	protected function setUp() {

		parent::setUp();

		// This environment variable is set by the travis-ci build system.
		$this->version = str_replace('magento-mirror-', '', getenv('MAGENTO_VERSION'));

		// Internally the core/session model is used (somewhere).
		// So we'll have to replace it with a mock.
		$this->replaceByMock(
		  'singleton', 
		  'core/session', 
		  $this->getMockBuilder('Mage_Core_Model_Session')
               ->setMethods(array('start'))
               ->getMock()
		);

		// Instantiate the model under test.
		$this->_model = Mage::getModel('stabilis_paypalexpressredirect/api_nvp');
		
		// Some things from the model must be reflected to be accessed.
		$this->_modelClass = new ReflectionClass(get_class($this->_model));
		
		// We'll need to monitor observers.
		Mage::app()->enableEvents();
	}

	/**
	 * Extracts the _requiredResponseParams property from the model under test.
	 * 
	 * @return array
	 */
	protected function getRequiredResponseParams() {
		$property = $this->_modelClass->getProperty('_requiredResponseParams');
		$property->setAccessible(true);
		return $property->getValue($this->_model);
	}

	/**
	 * Extracts the DO_EXPRESS_CHECKOUT_PAYMENT from the model under test.
	 * 
	 * @return string
	 */
	protected function getDoExpressCheckoutPaymentString() {
		return $this->_modelClass->getConstant('DO_EXPRESS_CHECKOUT_PAYMENT');
	}

	/**
	 * Tests the model default constructor.
	 * 
	 * On Magento 1.9+, a required parameter is removed from the _requiredResponseParams property.
	 * 
	 * On versions prior to 1.9, there are no parameter requirements imposed that must be adjusted.
	 */
	public function testConstructor() {
		
		$requiredResponseParams = $this->getRequiredResponseParams();
		
		$doExpressCheckoutPaymentString = $this->getDoExpressCheckoutPaymentString();

        if(version_compare($this->version, '1.9', '>=')) {
			
		    $this->assertArrayHasKey(
				$doExpressCheckoutPaymentString, 
				$requiredResponseParams
			);
		
			$this->assertEquals(
				array('ACK', 'CORRELATIONID'), 
				$requiredResponseParams[$doExpressCheckoutPaymentString]
			);
		} else {
			
			$this->assertArrayNotHasKey(
				$doExpressCheckoutPaymentString, 
				$requiredResponseParams
			);
		}
	}

	/**
	 * Extracts the _handleCallErrors method from the model under test.
	 * 
	 * @return ReflectionMethod
	 */
	protected function _getHandleCallErrorsMethod() {
		$className = get_class($this->_model);
		$class = new ReflectionClass($className);
		$method = $class->getMethod('_handleCallErrors');
		$method->setAccessible(true);
		return $method;
	}

	/**
	 * Retrieves a limited, mock dataset that simulates a PayPal response.
	 * 
	 * @return array
	 */
	protected function _getFailureResponse($code) {
		return array(
			'ACK' => 'Failure', 
			'L_ERRORCODE0' => $code, 
			'L_SHORTMESSAGE0' => 'Short Message', 
			'L_LONGMESSAGE0' => 'Long Message', 
			'L_SEVERITYCODE0' => ''
		);
	}

	/**
	 * Asserts that at least one 'EVENT_EXPRESS_REDIRECT_TRIGGERED' event has been dispatched.
	 */
	protected function assertEventFired() {
		$this->assertNotEquals(
			0,
			Mage::app()->getDispatchedEventCount(
				$this->_modelClass->getConstant(
					'EVENT_EXPRESS_REDIRECT_TRIGGERED'
				)
			)
		);
	}
	
	/**
	 * Asserts that no 'EVENT_EXPRESS_REDIRECT_TRIGGERED' event has been fired.
	 */
	protected function assertEventNotFired() {
		$this->assertEquals(
			0,
			Mage::app()->getDispatchedEventCount(
				$this->_modelClass->getConstant(
					'EVENT_EXPRESS_REDIRECT_TRIGGERED'
				)
			)
		);
	}
	
	/**
	 * Tests a response code that falls outside of the handled cases.
	 * 
	 * The behavior must be the same as the Magento Core in cases such as this.
	 * 
	 * On Magento 1.9+, a generic exception is thrown.
	 * On versions prior to 1.9, the user is presented with the raw PayPal messages.
	 */
	public function testUnsupportedResponse() {

		$expected = version_compare(Mage::getVersion(), '1.9', '>=') ? 
			'There was an error processing your order. Please contact us or try again later.' :
			'PayPal gateway has rejected request. Long Message (#12345: Short Message).';

		try {

			$method = $this->_getHandleCallErrorsMethod();

			$method->invoke(
				$this->_model, 
				$this->_getFailureResponse(12345)
			);

			// If execution gets here, the test failed.
			$this->assertTrue(false);

		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}

		$this->assertEventNotFired();

	}

	/**
	 * Test case for a '10417' response.
	 * 
	 * In this case, the user must be presented with a link to go back to PayPal and try again.
	 */
	public function test10417Response() {

		$expected = sprintf(
		    'PayPal could not process your payment at this time.  Please <a href="%s">click here</a> to select a different payment method from within your PayPal account and try again.', 
		    Mage::getUrl('paypal/express/edit')
		);

		try {

			$method = $this->_getHandleCallErrorsMethod();

			$method->invoke(
				$this->_model, 
				$this->_getFailureResponse(10417)
			);

			// If execution gets here, the test failed.
			$this->assertTrue(false);

		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}

		$this->assertEventFired();

	}

	/**
	 * Test case for a '10422' response.
	 * 
	 * In this case, the user must be presented with a link to go back to PayPal and try again.
	 */
	public function test10422Response() {

		$expected = sprintf(
		    'PayPal could not process your payment at this time.  Please <a href="%s">click here</a> to select a different payment method from within your PayPal account and try again.', 
		    Mage::getUrl('paypal/express/edit')
		);

		try {

			$method = $this->_getHandleCallErrorsMethod();

			$method->invoke(
				$this->_model, 
				$this->_getFailureResponse(10422)
			);

			// If execution gets here, the test failed.
			$this->assertTrue(false);

		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}

		$this->assertEventFired();

	}

	/**
	 * Test case for a '10736' response.
	 * 
	 * In this case, the user is presented with a message explaining how to fix the situation.
	 */
	public function test10736Response() {

		$expected = 'PayPal has determined that the specified shipping address does not exist.  Please double-check your shipping address and try again.';

		try {

			$method = $this->_getHandleCallErrorsMethod();

			$method->invoke(
				$this->_model,
				$this->_getFailureResponse(10736)
			);

			// If execution gets here, the test failed.
			$this->assertTrue(false);

		} catch(Exception $e) {
			$this->assertEquals($expected, $e->getMessage());
		}

		$this->assertEventFired();

	}

	/**
	 * Test case for a '10486' response.
	 * 
	 * I still have to do this one.
	 */
	public function test10486Response() {

        $redirected = false;

		$mock = $this->getMock('Stabilis_PaypalExpressRedirect_Helper_Data');

        $mock->expects($this->once())
             ->method('redirectUser')
             ->will($this->returnCallback(function() use(&$redirected) {
                 $redirected = true;
            }));

		// Ensure that the helper method doesn't actually terminate the process.
        $this->replaceByMock(
            'helper',
            'stabilis/paypalexpressredirect',
            $mock
        );

		$method = $this->_getHandleCallErrorsMethod();

		$method->invoke(
			$this->_model,
			$this->_getFailureResponse(10486)
		);

		$this->assertEventFired();
		$this->assertTrue($redirected);
	}

}
