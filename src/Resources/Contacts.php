<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Contacts extends Resource
{
    /**
     * @param array<string, mixed> $params email, firstName, lastName, status, tags, customFields
     *
     * @return array<mixed> {id}
     */
    public function create(array $params): array
    {
        return $this->requestData('POST', '/v1/contacts', $params);
    }

    /**
     * @param array<string, mixed> $params q (email prefix, case-insensitive; combines with status only),
     *                                     status, audienceId, tag, after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/contacts' . self::query($params)) ?? [];
    }

    /**
     * @param array<string, mixed> $params firstName, lastName, status, tags, customFields
     *
     * @return array<mixed> {id}
     */
    public function update(string $id, array $params): array
    {
        return $this->requestData('PATCH', '/v1/contacts/' . self::id($id), $params);
    }

    /**
     * Starts an asynchronous import. The CSV is sent as it is (text/csv), never JSON-encoded;
     * the API validates it (an empty CSV is answered with a 400).
     *
     * @return array<mixed> {jobId, totalRows}
     */
    public function importCsv(string $csv): array
    {
        return $this->http->request('POST', '/v1/contacts/import', $csv, contentType: 'text/csv', unwrap: true) ?? [];
    }

    /** @return array<mixed> {jobId, status, totalRows, processedRows, failedRows, errors, completedAt} */
    public function getImportJob(string $jobId): array
    {
        return $this->requestData('GET', '/v1/contacts/import/' . self::id($jobId));
    }
}
