<?php

namespace App\Domains\Documents;

class DocumentRequest {

    protected $formname;
    protected $uploadname;
    protected $tmpname;
    protected $mimetype;

    public function __construct(array $file)
    {
        $this->uploadname = $file['name'];
        $this->formname   = $file['fieldname'];
        $this->mimetype   = $file['type'];
        $this->tmpname    = $file['tmp_name'];
    }

    public function getHtmlPayload(): string
    {
        return @file_get_contents($this->tmpname);
    }

    /**
     * Return specified paper size stuffed into 'name' or null
     * @return null|string
     */
    public function getPaperSize(): ?string
    {
        $extra = $this->parseExtraParams($this->formname);
        return key_exists('paper', $extra) ? $extra['paper'] : null;
    }

    /**
     * Return specified paper size stuffed into 'name' or null
     * @return null|string
     */
    public function getPaperOrientation(): ?string
    {
        $extra = $this->parseExtraParams($this->formname);
        return key_exists('orientation', $extra) ? $extra['orientation'] : null;
    }


    /**
     * parse key=val,key=val
     *
     * @param mixed $string
     * @return void
     */
    protected function parseExtraParams($string): array
    {
        $kvs = explode(',', $string);

        $keyvalues = array_map(
            function($value) {
                return explode('=', $value) ? explode('=', $value) : [null, null];
            },
            $kvs
        );

        return array_reduce(
            $keyvalues,
            function($carry, $item) {
                $carry[ $item[0] ] = $item[1];
                return $carry;
            }
        );
    }
}
