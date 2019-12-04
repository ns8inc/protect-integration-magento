#!/bin/bash

# Begin interactive certbot certification
sudo /usr/local/bin/certbot-auto --apache --redirect --debug
# Ensure certification was successful before moving on
RETURN_CODE=$?
if [ $RETURN_CODE != 0 ]
then
  exit $RETURN_CODE
fi

# Configure Magento to use secure Urls
sudo -u apache php /var/www/html/bin/magento setup:store-config:set --use-secure-admin=1
sudo -u apache php /var/www/html/bin/magento setup:store-config:set --use-secure=1

# Clean cache (config cache is dirty after ^^)
sudo -u apache php /var/www/html/bin/magento cache:clean

# Restart apache
sudo service httpd restart