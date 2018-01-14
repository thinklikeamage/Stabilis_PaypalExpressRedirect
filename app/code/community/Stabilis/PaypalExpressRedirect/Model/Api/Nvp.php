<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Stabilis to newer
 * versions in the future. If you wish to customize Stabilis for your
 * needs please do so within the local code pool.
 *
 * @category    Stabilis
 * @package     Stabilis_PaypalExpressRedirect
 * @copyright  Copyright (c) 2007-2018 Luke A. Leber (https://www.thinklikeamage.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * This override has been put in place in order to ensure that the Magento 
 * instance correctly handles errors that are generated by the Express Checkout 
 * NVP gateway.  Some errors require that the user be redirected back to the 
 * PayPal site with the same token as the request that failed.  This will allow 
 * the customer to recover from the funding failure.
 * 
 * The following error conditions fall into this category:
 * 
 * Error 10486 - The transaction could not be completed (for an unknown reason)
 * 
 * The following error conditions have had their messages made user-friendly:
 * 
 * Error 10736 - PayPal has determined that the shipping address does not exist
 * Error 10417 - Customer must choose another funding source from their wallet
 * Error 10422 - Customer must choose new funding sources
 * 
 * @category   Stabilis
 * @package    Stabilis_PaypalExpressRedirect
 */
class Stabilis_PaypalExpressRedirect_Model_Api_Nvp extends Mage_Paypal_Model_Api_Nvp {

    /** @var int Symbolic constant for HTTP/302 */
    const HTTP_TEMPORARY_REDIRECT = 302;
    
    /** @var int https://developer.paypal.com/docs/classic/express-checkout/ht_ec_fundingfailure10486/ */
    const API_UNABLE_TRANSACTION_COMPLETE       = 10486;

    /** @var int https://www.paypal-knowledge.com/infocenter/index?page=content&id=FAQ1375&actp=LIST */
    const API_UNABLE_PROCESS_PAYMENT_ERROR_CODE = 10417;

    /** @var int https://www.paypal-knowledge.com/infocenter/index?page=content&expand=true&locale=en_US&id=FAQ1850 */
    const API_DO_EXPRESS_CHECKOUT_FAIL          = 10422;

    /** @var int https://www.paypal-knowledge.com/infocenter/index?page=content&id=FAQ2025&actp=LIST */
    const API_BAD_SHIPPING_ADDRESS              = 10736;
    
    /** @var string The identifier of the event that is dispatched when an express redirect is triggered */
    const EVENT_EXPRESS_REDIRECT_TRIGGERED      = 'stabilis_paypalexpressredirect_redirect_triggered';

    /**
     * Internal Constructor
     */
    protected function _construct() {
        parent::_construct();
        
        /// Magento 1.9+ has added the DoExpressCheckoutPayment method to the required response params array.
        /// This array is checked prior to any error checking, therefore an error condition will trigger an 
        /// early exit (even if the error is recoverable).  So we'll remove the 'AMT' field from the required 
        /// params array.
        if (version_compare(Mage::getVersion(), '1.9', '>=')) {
            $this->_requiredResponseParams[static::DO_EXPRESS_CHECKOUT_PAYMENT] = array('ACK', 'CORRELATIONID');
        }
    }

    /**
     * Throws an exception that is dependent upon the version of Magento.
     * 
     * @param Exception $ex the exception to throw in Magento version <= 1.8
     * 
     * @throws Exception
     */
    protected function _rethrow($ex) {
        if (version_compare(Mage::getVersion(), '1.9', '>=')) {

            /// Preserve Magneto 1.9+ Behavior
            Mage::throwException(Mage::helper('paypal')
                    ->__('There was an error processing your order. Please contact us or try again later.'));
        } else {

            /// Preserve Magento <= 1.8 Behavior
            throw $ex;
        }
    }

    /**
     * Dispatches an event that third party code can handle.
     *
     * This event will fire when the user is redirected back to PayPal after payment failure.
     *
     * @param int $error the error condition that triggered the redirect
     *
     * @example
     *   <code>
     *       class Third_Party_Model_Observer {
     *
     *           public function onPayPalExpressRedirect(\Varien_Event_Observer $observer) {
     *
     *               $errorCode = $observer->getEvent()->getErrorCode();
     *
     *               // Custom logging?
     *               // Perhaps send an email or SMS notification.
     *           }
     *       }
     *   </code>
     */
    protected function _dispatchRedirectEvent($error) {

        // Any data sent to the client prior to the redirect header could be detrimental.
        // So start collecting all output into a temporary buffer that will be later destroyed.
        //
        // Never trust third party code.
        ob_start();

        try {

            // Wrapped in a try-catch block, invoke any third party code that is listening
            Mage::dispatchEvent(static::EVENT_EXPRESS_REDIRECT_TRIGGERED, array('error_code' => $error));

        } catch(Exception $thirdPartyException) {

            // Did the third party code throw an exception?  Log it and continue.
            Mage::logException($thirdPartyException);
        }

        // Destroy the temporary output buffer
        ob_end_clean();
    }
    
    /**
     * Extends the functionality of the parent method by setting a redirect to 
     * PayPal in the event of certain error conditions.
     * 
     * @param array $response
     * 
     * @throws Exception if an unrecoverable error exists within the response
     */
    protected function _handleCallErrors($response) {
        try {

            /// Let the default functionality take its course
            parent::_handleCallErrors($response);

        } catch (Exception $ex) {
            
            /// If there's more than one error, then there's no silver bullet.
            if(count($this->_callErrors) > 1) {
                $this->_rethrow($ex);
            }
            
            $error = $this->_callErrors[0];

            $this->_dispatchRedirectEvent($error);

            switch($error) {

                /// Redirect the user back to PayPal
                case self::API_UNABLE_TRANSACTION_COMPLETE:
                    Mage::app()->getFrontController()->getResponse()
                        ->setRedirect(Mage::getUrl('paypal/express/edit'), static::HTTP_TEMPORARY_REDIRECT)
                        ->sendResponse();
                    exit;

                /// Give the user an option to click a link to go back and 
                /// select another funding source
                case self::API_UNABLE_PROCESS_PAYMENT_ERROR_CODE:
                case self::API_DO_EXPRESS_CHECKOUT_FAIL:
                    Mage::throwException(Mage::helper('stabilis_paypalexpressredirect')
                        ->__('PayPal could not process your payment at this time.  Please <a href="%s">click here</a> to select a different payment method from within your PayPal account and try again.', Mage::getUrl('paypal/express/edit')));

                /// The shipping address isn't right.  Fix it on this page.
                case self::API_BAD_SHIPPING_ADDRESS:
                    Mage::throwException(Mage::helper('stabilis_paypalexpressredirect')
                        ->__('PayPal has determined that the specified shipping address does not exist.  Please double-check your shipping address and try again.'));
                    
                /// Other error?  Let the caller handle it.
                default:
                    $this->_rethrow($ex);
            }
        }
    }
}
