<?php

/**
 * @file classes/MetaforaClient.php
 *
 * @class MetaforaClient
 *
 * @brief Thin client for the RCSI "Metafora" API v2
 * (https://metafora.rcsi.science/api/v2/openapi.yaml). Every call returns a
 * MetaforaResponse instead of throwing on HTTP errors, so callers can show
 * Metafora's own error text (XSD errors, missing data…) to the editor.
 */

namespace APP\plugins\generic\jatsExport\classes;

use APP\core\Application;
use GuzzleHttp\Client;
use Throwable;

class MetaforaClient
{
    public const BASE_URL = 'https://metafora.rcsi.science';

    /** XML processing status codes (FileStatusResponse.xml.status.code) */
    public const STATUS_UPLOADED = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_PROCESSED = 3;
    public const STATUS_FAILED = 4;

    protected Client $http;

    public function __construct(protected string $apiKey)
    {
        $this->http = Application::get()->getHttpClient();
    }

    /** POST /api/v2/files/jats/xml_pdf/ (or /jats/xml/ when there is no PDF) */
    public function uploadJats(string $xml, string $xmlName, ?string $pdf, ?string $pdfName): MetaforaResponse
    {
        $multipart = [
            ['name' => 'xml', 'contents' => $xml, 'filename' => $xmlName, 'headers' => ['Content-Type' => 'application/xml']],
            ['name' => 'platform', 'contents' => 'OJS'],
        ];
        if ($pdf !== null) {
            $multipart[] = ['name' => 'pdf', 'contents' => $pdf, 'filename' => $pdfName, 'headers' => ['Content-Type' => 'application/pdf']];
        }
        return $this->request('POST', $pdf !== null ? '/api/v2/files/jats/xml_pdf/' : '/api/v2/files/jats/xml/', ['multipart' => $multipart]);
    }

    /** GET /api/v2/files/status/?file_uid= */
    public function fileStatus(string $fileUid): MetaforaResponse
    {
        return $this->request('GET', '/api/v2/files/status/', ['query' => ['file_uid' => $fileUid]]);
    }

    /** DELETE /api/v2/files/{file_uid} — only for processed files without signed publications */
    public function deleteFile(string $fileUid): MetaforaResponse
    {
        return $this->request('DELETE', '/api/v2/files/' . rawurlencode($fileUid));
    }

    /** GET /api/v2/publications/doi/{doi} — the organisation's own publications only */
    public function findByDoi(string $doi): MetaforaResponse
    {
        // Slashes inside the DOI stay as-is: the API decodes the rest of the path
        return $this->request('GET', '/api/v2/publications/doi/' . str_replace('%2F', '/', rawurlencode($doi)));
    }

    /** POST /api/v2/publications/status/batch (max 100 uids) */
    public function signStatuses(array $articleUids): MetaforaResponse
    {
        return $this->request('POST', '/api/v2/publications/status/batch', ['json' => ['article_uids' => array_values($articleUids)]]);
    }

    public function sign(string $articleUid): MetaforaResponse
    {
        return $this->request('PUT', '/api/v2/publications/' . rawurlencode($articleUid) . '/sign/');
    }

    public function unsign(string $articleUid): MetaforaResponse
    {
        return $this->request('PUT', '/api/v2/publications/' . rawurlencode($articleUid) . '/unsign/');
    }

    /**
     * There is no "ping" endpoint: ask for the status of a file that can't
     * exist. 404 means the key was accepted, 401/403 that it wasn't.
     */
    public function testConnection(): MetaforaResponse
    {
        return $this->fileStatus('00000000-0000-0000-0000-000000000000');
    }

    protected function request(string $method, string $path, array $options = []): MetaforaResponse
    {
        try {
            $response = $this->http->request($method, self::BASE_URL . $path, $options + [
                'headers' => ['Api-Key' => $this->apiKey, 'Accept' => 'application/json'],
                'http_errors' => false,
                'timeout' => 120,
                'connect_timeout' => 15,
            ]);
            $body = (string) $response->getBody();
            return new MetaforaResponse($response->getStatusCode(), json_decode($body, true), $body);
        } catch (Throwable $e) {
            return new MetaforaResponse(0, null, '', 'Нет связи с «Метафорой»: ' . $e->getMessage());
        }
    }
}
