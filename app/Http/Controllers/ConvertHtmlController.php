<?php

namespace App\Http\Controllers;

use App\Domains\Documents\DocumentRequest;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Ulid\Ulid;
use UnexpectedValueException;

class ConvertHtmlController extends Controller
{
    public function index(HttpRequest $r) {
        $version          = $r->header('DomPdf-Version', '1.2.0');
        $paperSize        = $r->header('DomPdf-PaperSize', 'letter');
        $paperOrientation = $r->header('DomPdf-Orientation', 'portrait');

        $this->registerPackagedAutoloader($version);

        $requestId = Ulid::generate();
        $tempDir = storage_path('dompdf_requests/' . (string)$requestId) . '/';
        File::makeDirectory($tempDir, 0755, true);
        $documentCount = 0;
        foreach ($this->documentRequests() as $docReq) {
            $documentCount++;
            $dompdf = new \Dompdf\Dompdf();

            $options = $dompdf->getOptions();
            $options->set('font_cache', 'storage/fonts/');
            $options->set('isRemoteEnabled', true);
            $options->set('pdfBackend', 'CPDF');
            $options->setChroot([
                'resources/views/',
            ]);

            $paperSize        = $docReq->getPaperSize() ?? $paperSize;
            $paperOrientation = $docReq->getPaperOrientation() ?? $paperOrientation;
            $dompdf->loadHtml(
                $this->base64EncodeSvg(
                    $docReq->getHtmlPayload()
                )
            );
            $dompdf->setPaper($paperSize, $paperOrientation);
            $dompdf->render();
            file_put_contents(
                $tempDir . 'document_' . $documentCount . '.pdf',
                $dompdf->output()
            );
        }
        return response()->stream(function() use ($tempDir) {
            $this->mergeDocuments($tempDir);
        });
    }

    public function mergeDocuments($tempDir, $attachmentName='document.pdf'): void
    {
        $pdfMerger = new \App\Domains\Documents\PdfMerger();
        $pdfMerger->init();

        $splFileInfo = File::allFiles($tempDir);
        foreach ($splFileInfo as $fileInfo) {
            $pdfMerger->addPDF(
                $fileInfo->getPath() . '/' . $fileInfo->getFilename(),
                'all'
            );
        }

        $pdfMerger->merge(); //For a normal merge (No blank page added)
        header('Content-type: application/pdf');
        // "S" is for stream
        $pdfMerger->save($attachmentName, "S");

        // clean up
        foreach ($splFileInfo as $fileInfo) {
            unlink(
                $fileInfo->getPath() . '/' . $fileInfo->getFilename()
            );
        }
        rmdir($tempDir);

    }

    public function documentRequests(): Generator
    {
        foreach ($_FILES as $uploadName => $file) {
            if ($file['type'] == 'text/html') {
                $file['fieldname'] = $uploadName;
                yield new DocumentRequest($file);
            }
        }
        return true;
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

    public function registerPackagedAutoloaderComposer($version='1.2.0')
	{
        $DIR = realpath(base_path('vendor_dompdf/'));
        $DIR .= "/dompdf-$version/";
        if (!@file_exists($DIR)) {
            throw new UnexpectedValueException('unknown version');
        }
		require $DIR . 'autoload.inc.php';
	}

    /**
     * Setup a working clone of the pre-packaged autoloader.inc.php
     * with fixes for Sabberworm namespace
     *
     * This autoloader.inc.php was broken in 1.2.0, might be fixed in 1.3.0
     *
     * @throws \UnexpectedValueException
     * @return void
     */
    public function registerPackagedAutoloader($version='1.2.0')
    {
        // returns 1 if second paramter is lower
        if (version_compare($version, '1.2.0') === 1) {
            $this->registerPackagedAutoloaderComposer($version);
            return;
        }

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
                $file = str_replace('Sabberworm\\CSS\\', '', $class);
                $file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
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
                        ],
                        'name' => 'page=1,orientation=portrait,paper=A4',
                        'filename' => 'my_document.html',  // must include filename for laravel/lumen/symfony/php?
                        'contents' => $payload,
                    ],
                    [
                        'headers' => [
                            'Content-Type' => 'text/html',
                        ],
                        'name' => 'page=2,orientation=landscape,paper=letter',
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
