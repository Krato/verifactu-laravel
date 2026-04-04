<?php

namespace Krato\Verifactu\Transport;

use Krato\Verifactu\DTOs\CertificateCredentials;
use Krato\Verifactu\Exceptions\SubmissionException;

class SoapClient
{
    private array $tempFiles = [];

    public function __construct(
        private readonly Endpoints $endpoints,
        private readonly CertificateAuth $certificateAuth,
    ) {}

    /**
     * Send a SOAP envelope to AEAT and return the raw XML response.
     */
    public function send(string $soapXml, CertificateCredentials $credentials): string
    {
        $pemFiles = $this->certificateAuth->writeTempPem($credentials);
        $this->tempFiles = array_values($pemFiles);

        try {
            $url = $this->endpoints->submission();

            $ch = curl_init($url);
            if ($ch === false) {
                throw new SubmissionException('Could not initialize cURL');
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $soapXml,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml; charset=UTF-8',
                    'SOAPAction: ""',
                ],
                CURLOPT_SSLCERT => $pemFiles['cert_file'],
                CURLOPT_SSLKEY => $pemFiles['key_file'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new SubmissionException("AEAT request failed: {$error}");
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new SubmissionException("AEAT returned HTTP {$httpCode}: {$response}");
            }

            return (string) $response;
        } finally {
            $this->cleanupTempFiles();
        }
    }

    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function __destruct()
    {
        $this->cleanupTempFiles();
    }
}
