<?php

namespace App\Http\Controllers;

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
        if (!@include('../vendor_dompdf/dompdf-' . $version . '/autoload.inc.php')) {
            throw new UnexpectedValueException('unknown version');
        }
    }
}
