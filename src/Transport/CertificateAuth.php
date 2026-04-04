<?php

namespace Krato\Verifactu\Transport;

use Krato\Verifactu\DTOs\CertificateCredentials;
use Krato\Verifactu\Exceptions\CertificateException;

class CertificateAuth
{
    /**
     * Extract PEM certificate and private key from a PKCS#12 (.p12/.pfx) file.
     *
     * @return array{cert: string, pkey: string}
     */
    public function extractPem(CertificateCredentials $credentials): array
    {
        $path = $credentials->path;

        if (! file_exists($path)) {
            throw new CertificateException("Certificate file not found: {$path}");
        }

        $pfxContent = file_get_contents($path);
        if ($pfxContent === false) {
            throw new CertificateException("Could not read certificate file: {$path}");
        }

        $certs = [];
        if (! openssl_pkcs12_read($pfxContent, $certs, $credentials->password)) {
            throw new CertificateException('Could not read PKCS#12 certificate. Check password and file format.');
        }

        return [
            'cert' => $certs['cert'],
            'pkey' => $certs['pkey'],
        ];
    }

    /**
     * Write temporary PEM files for SOAP client usage.
     *
     * @return array{cert_file: string, key_file: string}
     */
    public function writeTempPem(CertificateCredentials $credentials): array
    {
        $pem = $this->extractPem($credentials);

        $certFile = tempnam(sys_get_temp_dir(), 'verifactu_cert_');
        $keyFile = tempnam(sys_get_temp_dir(), 'verifactu_key_');

        if ($certFile === false || $keyFile === false) {
            throw new CertificateException('Could not create temporary PEM files');
        }

        file_put_contents($certFile, $pem['cert']);
        file_put_contents($keyFile, $pem['pkey']);

        return [
            'cert_file' => $certFile,
            'key_file' => $keyFile,
        ];
    }
}
