<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Request as HttpRequest;
use UnexpectedValueException;

class ConvertHtmlController extends Controller
{
    public function index(HttpRequest $r) {
        $version          = $r->header('DomPdf-Version', '1.2.0');
        $paperSize        = $r->header('DomPdf-PaperSize', 'letter');
        $paperOrientation = $r->header('DomPdf-Orientation', 'portrait');

        $this->registerPackagedAutoloader($version);
        $dompdf = new \Dompdf\Dompdf();

        $options = $dompdf->getOptions();
        $options->set('font_cache', 'storage/fonts/');
        $options->set('isRemoteEnabled', true);
        $options->set('pdfBackend', 'CPDF');
        $options->setChroot([
            'resources/views/',
        ]);

        $dompdf->loadHtml(
            $this->base64EncodeSvg(
                $this->getPostedHtml()
            )
        );
        $dompdf->setPaper($paperSize, $paperOrientation);
        $dompdf->render();
        header('Content-type: application/pdf');
        echo $dompdf->output();
    }

    public function getPostedHtml(): ?string
    {
        $firstHtmlFile = null;

        foreach ($_FILES as $file) {
            if ($file['type'] == 'text/html') {
                $firstHtmlFile = $file;
                break;
            }
        }
        if ($firstHtmlFile === null) {
            // return sample html for testing
            return file_get_contents('../tests/fixtures/html/svg.html');
        }
        return @file_get_contents($firstHtmlFile['tmp_name']);
    }

    /**
     * base64 encode any nativelly embedded SVG tags
     *
     * @param mixed $content
     * @return null|string
     */
    public function base64EncodeSvg($content): ?string
    {
        return preg_replace_callback("/<svg.*<\/svg>/s", function ($matches) {
            return '<img src="data:imagesvg;base64,' . base64_encode($matches[0]) . '">';
        }, $content);
    }

    /**
     * Setup a working clone of the pre-packaged autoloder.inc.php
     * with fixes for Sabberworm namespace
     * @throws \UnexpectedValueException
     * @return void
     */
    public function registerPackagedAutoloader($version='1.2.0')
    {
        // if (!@include('../vendor_dompdf/dompdf-' . $version . '/autoload.inc.php')) {
        //     throw new UnexpectedValueException('unknown version');
        // }
        $DIR = realpath(base_path('vendor_dompdf/'));
        $DIR .= "/dompdf-$version/";
        if (!@file_exists($DIR)) {
            throw new UnexpectedValueException('unknown version');
        }

        // Sabberworm
        spl_autoload_register(function($class) use ($DIR)
        {
            if (strpos($class, 'Sabberworm') !== false) {
                // dump($class);
                $file = str_replace('\\', DIRECTORY_SEPARATOR, $class);
                $file = str_replace('Sabberworm/CSS/', '', $file);
                // dd($file);
                $file = realpath($DIR . '/lib/php-css-parser/src/' . (empty($file) ? '' : DIRECTORY_SEPARATOR) . $file . '.php');
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
            return false;
        });

        // php-font-lib
        require_once $DIR . '/lib/php-font-lib/src/FontLib/Autoloader.php';

        //php-svg-lib
        require_once $DIR . '/lib/php-svg-lib/src/autoload.php';

        // New PHP 5.3.0 namespaced autoloader
        require_once $DIR . '/src/Autoloader.php';
        \Dompdf\Autoloader::register();
    }

    public function internalTestWithGuzzle() {
        $client = new Client([
        'base_uri' => 'http://host.docker.internal:3001',
        ]);

        // $payload = file_get_contents('/my-report-or-document.html');
        $payload = file_get_contents('../tests/fixtures/html/svg.html');

        try {
            $response = $client->request('POST', '/convert/html', [
                'debug' => FALSE,
                'multipart' => [
                    [
                        'headers' => [
                            'Content-Type' => 'text/html',
                            'DomPdf-Orientation' => 'portrait',
                        ],
                        'name' => 'page=1,orientation=landscape,paper=A4',
                        'filename' => 'my_document.html',  // must include filename for laravel/lumen/symfony/php?
                        'contents' => $payload,
                    ]
                ]
            ]);
        } catch (ServerException $e) {
            $response = $e->getResponse();
        }

        return response(
            $response->getBody(),
            $response->getStatusCode(),
            [
                'Content-type' => $response->getHeader('content-type')
            ]
        );
    }
}
