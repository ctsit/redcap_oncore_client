# REDCap OnCore Client
This is a REDCap external module that provides integration with OnCore.

It allows REDCap project builders to associate a REDCap project with an OnCore protocol. It also allows developers to call the OnCore API via SOAP.

This document provides some addtional notes that might be usefiul for software developers who want to extend the functionality of the OnCore client

See the [README](README.md) for information on prereqs, installation, configuration and usage.

## Using the API

Here is an example of an API request to get protocol information (`getProtocol`).

```php
<?php

$module = \ExternalModules\ExternalModules::getModuleInstance('redcap_oncore_client');
$client = $module->getSoapClient();

$result = $client->request('getProtocol', array('protocolNo' => 'OCR20002'));
```

For more complex requests (like `createProtocol` or `registerNewSubjectToProtocol`), check the `requests_examples.txt` file, which contains input examples of valid requests. For additional sample code see the testing code in [PBC's REDCap Module](https://github.com/pbchase/my_redcap_module/tree/redcap_oncore_client_test).

This module does not contain details or definitions about OnCore API services. So for further details you may read the WSDL file or web page that was provided to you - use a Desktop client like [SoapUI](https://www.soapui.org/) for that.
