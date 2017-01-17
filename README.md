# Some PayPal error conditions are recoverable.
## Enhance your store by letting your customers recover a failed check out.
<img src="https://www.paypalobjects.com/webstatic/en_US/developer/docs/ec/EC_10486redirect2.png" />

This module intercepts several recoverable errors resulting from a failed PayPal Express checkout failure and redirects the user back to PayPal in accordance with the <a href="https://developer.paypal.com/docs/classic/express-checkout/ht_ec_fundingfailure10486/">PayPal Documentation</a>.

- ![#f03c15](http://placehold.it/15/f03c15/000000?text=+) Warning: It is HIGHLY RECOMMENDED to install any new extensions on a staging domain in order to test for conflicts.
- ![#f03c15](http://placehold.it/15/f03c15/000000?text=+) It is also HIGHLY RECOMMENDED to utilize a variety of source control management software (such as git).
- ![#f03c15](http://placehold.it/15/f03c15/000000?text=+) This module overrides `app/code/core/Mage/Paypal/Api/Nvp.php`.
- ![#f03c15](http://placehold.it/15/f03c15/000000?text=+) Never modify these files directly.  If you need to override something, use the local code pool.
