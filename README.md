# INSTALLATION

run `composer run new-env` to install a new environment.

run `composer run update-versions` to install pre-packaged dompdf releases into vendor_dompdf.

# USAGE
POST your HTML to `/convert/html` and get back binary PDF.

```php
<?php
use GuzzleHttp\Client;

$client = new Client([
  'base_uri' => 'http://localhost:3001',
]);

$payload = file_get_contents('/my-report-or-document.html');

$response = $client->request('POST', '/convert/html', [
    'debug' => FALSE,
    'multipart' => [
        [
            'headers' => [
                'Content-Type' => 'text/html',
            ],
            'name' => 'my_document',
            'filename' => 'my_document.html',  // must include filename for laravel/lumen/symfony/php?
            'contents' => $payload,
        ]
    ]
]);

$body = $response->getBody();
echo $body;
```

# TESTING

`sh ./scripts/test-curl.sh`

or visit

[http://localhost:3001/convert/html](http://localhost:3001/convert/html)
