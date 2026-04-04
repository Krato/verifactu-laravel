<?php

namespace Krato\Verifactu\Xml;

use Krato\Verifactu\Exceptions\XmlValidationException;

class XsdValidator
{
    /**
     * Validate XML string. Returns true on success, throws on failure.
     *
     * Note: Full XSD validation against AEAT schemas is available when XSD files
     * are provided. Without XSD files, performs basic well-formedness checks.
     */
    public function validate(string $xml): bool
    {
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);

        if (! $dom->loadXML($xml)) {
            $errors = $this->getLibxmlErrors();
            libxml_use_internal_errors($previous);
            throw new XmlValidationException('XML is not well-formed: '.implode('; ', $errors));
        }

        libxml_use_internal_errors($previous);

        return true;
    }

    /**
     * Validate against a specific XSD schema file.
     */
    public function validateAgainstSchema(string $xml, string $xsdPath): bool
    {
        if (! file_exists($xsdPath)) {
            throw new XmlValidationException("XSD schema file not found: {$xsdPath}");
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $dom->loadXML($xml);

        if (! $dom->schemaValidate($xsdPath)) {
            $errors = $this->getLibxmlErrors();
            libxml_use_internal_errors($previous);
            throw new XmlValidationException('XSD validation failed: '.implode('; ', $errors));
        }

        libxml_use_internal_errors($previous);

        return true;
    }

    /**
     * @return string[]
     */
    private function getLibxmlErrors(): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = trim($error->message)." (line {$error->line})";
        }
        libxml_clear_errors();

        return $errors;
    }
}
