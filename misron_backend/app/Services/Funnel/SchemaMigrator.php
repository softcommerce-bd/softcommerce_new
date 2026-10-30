<?php

namespace App\Services\Funnel;

/**
 * Upgrades stored page documents to the current schema version.
 *
 * Each step is a method named upgradeFromN that takes a version N document
 * and returns a version N+1 document. Documents are upgraded when read for
 * editing or validation, so old drafts and snapshots never need a bulk
 * rewrite. Published snapshots stay byte-for-byte as they were saved.
 */
class SchemaMigrator
{
    private $current;

    public function __construct(?int $current = null)
    {
        $this->current = $current ?? (int) config('funnel.schema_version');
    }

    public function currentVersion(): int
    {
        return $this->current;
    }

    /**
     * @throws \InvalidArgumentException for documents newer than this code
     */
    public function migrate(array $document): array
    {
        $version = (int) ($document['schemaVersion'] ?? 0);

        if ($version > $this->current) {
            throw new \InvalidArgumentException("Page schema version {$version} is newer than supported version {$this->current}.");
        }

        while ($version < $this->current) {
            $method = 'upgradeFrom' . $version;
            if (!method_exists($this, $method)) {
                throw new \InvalidArgumentException("No upgrade path from page schema version {$version}.");
            }
            $document = $this->{$method}($document);
            $version++;
            $document['schemaVersion'] = $version;
        }

        return $document;
    }

    public function emptyDocument(): array
    {
        return ['schemaVersion' => $this->current, 'page' => ['sections' => []]];
    }

    /**
     * Version 0 is any document written before versioning: a bare list of
     * sections, or a page object without a schemaVersion.
     */
    protected function upgradeFrom0(array $document): array
    {
        if (PropRules::isList($document)) {
            return ['page' => ['sections' => $document]];
        }

        if (isset($document['sections']) && !isset($document['page'])) {
            return ['page' => ['sections' => $document['sections']]];
        }

        return $document;
    }
}
