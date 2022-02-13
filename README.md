# INSTALLATION

run `composer run new-env` to install a new environment.

run `composer run update-versions` to install pre-packaged dompdf releases into vendor_dompdf.

# Usage
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

# Advanced Usage

You can submit multiple document requests in a single API request, specifying different page orientations
and sizes per document.  The resulting documents will be merged with FPDI/FPDF to form one final document.

This is achieved by specifying key,value pairs in the "name" field of the API multipart request.  The `name` field
corresponds to the name of the form input if this were coming from an HTML document.

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
            'name' => 'paper=A4,orientation=portrait',
            'filename' => 'my_document.html',
            'contents' => $payload,
        ],
        [
            'headers' => [
                'Content-Type' => 'text/html',
            ],
            'name' => 'paper=letter,orientation=landscape',
            'filename' => 'my_document_2.html',
            'contents' => $payload,
        ]
    ]
]);

echo $response->getBody();
```


# TESTING

`sh ./scripts/test-curl.sh`

or visit

[http://localhost:3001/convert/html](http://localhost:3001/convert/html)
