# wavefront-web-analytics

Simple example of pulling Google Analytics data and pushing to wavefront.

## Setup

I took the code for this from:  https://developers.google.com/analytics/devguides/reporting/core/v4/quickstart/service-php

The code is not pretty. You are warned.

To this this up, you'll need the PHP file and:
1. You'll need to get the composer stuff mentioned above: composer require google/apiclient:^2.0
2. You'll need to get the service credential file. See web page above for instructions


## Deployment

Deployment is a little funky. You have to do the following:
1. Make sure you get app the composer files
2. Move the service*.json to the directory for the php file
3. Move the "vendor" directory to the directory where the php file exists


