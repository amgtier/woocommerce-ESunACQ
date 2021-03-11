ESun ACQ plugin for WooCommerce
===============================

Installation
------------

1. Download https://github.com/amgtier/woocommerce-ESunACQ/archive/master.zip.
2. Unzip as ``` woocommerce-ESunACQ/```.
3. Move ```woocommerce-ESunACQ/``` into ```path_to_wordpress/wp-content/plugins/```.
4. Go to wordpress admin, ```Plugins``` and activate ```WooCommerce - ESun ACQ```.
5. Go to wordpress admin, ```WooCommerce```->```Settings```->```Checkout```, **ESun ACQ** and **UnionPay** are listed on the right of ```Checkout options```.
6. Fill in the ```Store ID``` and ```Mac Key``` provided by ESun bank for production and test mode respectively. ```Store ID``` is the same across the two.
7. With production ```Store ID``` and ```Mac Key``` properly set, check on ```Enable```.
8. Follow steps 5. and 6. for Union Pay.



Notes
-----
1. If ```Test Mode``` is checked, only logged in users with administrator role can see the ESun ACQ checkout option.
2. Both production and testing IP address is provided to ESun bank. At production, only the server IP address is checked; at testing, only IP address on record at ESun bank can go through test credit card authentication flow.
3. UnionPay is not tested due not allowed by ESun bank. We do not have such resource on hand either.

Refund
------
1. Please consult the representative at ESun bank for the time period that refund through ESun ACQ API is allowed. After that, user may access the official site for refund.
2. Refund can only be executed in total due to API spec. 
