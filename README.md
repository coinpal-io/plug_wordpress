# WooCommerce Coinpal Checkout Installation

## Step 1: Login Coinpal Admin Dashboard get Merchant Key and Secret Key.
1. [Register](https://portal.coinpal.io/#/admin/register)/[login](https://portal.coinpal.io/#/admin/login) and go to Coinpal's Admin Dashboard 

![](./img/register.png)

2. Follow the Dashboard guidelines to fill in the relevant information

3. Click the 'Integration' button in the lower left corner to get the corresponding Merchant Id and Secret Key


## Step 2: Installing the WooCommerce Plugin on your Wordpress Site.
1. Click the  Coinpal plug  Download Coinpal WooCommerce Payment Plug
2. Login to your WordPress Admin Dashboard.
3. Click on the "Plugins" section, and press "Add New".
4. At the Top Left of the Page Click the “Upload Plugin” button.
5. Activate the Coinpal WooCommerce Gateway

Go to the WooCommerce Section, click Settings.

Press the Payments Tab at the top, Enable Coinpal, and press Manage.

Copy and Paste all of the Settings you generated in your Coinpal Dashboard on Step #1.

Click Save Changes.


## Step 3: Testing your Coinpal WooCommerce Integration.

To confirm your Integration is properly working create a test order:

Add Test Item to Shopping Cart and View Cart.

Proceed to Checkout

Select Coinpal as the Payment Method.

Click Place Order

Click the “Pay Now with Coinpal” button.

Verify all of the Wallet Addresses and Order info, and make sure the Validation Tests all have a Green Check Mark.

If you like you can now proceed to making a test payment.



## Step 4: Marking a Payment as Received on WooCommerce.

Login to your Wordpress Admin Dashboard.

Go to the WooCommerce Section and Click Orders.

You will see the Test Orders Marked as “On Hold”

Verify the Coins are in your chosen Coinpal Wallet (The addresses you input in Step #1.)

You may also use a Block Explorer to verify if the transaction was processed.

Go to the order on WooCommerce, and in the Status section, mark it as "Processing", or "Completed" to notify the customer their order is being processed.





