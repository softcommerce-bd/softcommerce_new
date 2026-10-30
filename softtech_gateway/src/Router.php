<?php

namespace Gateway;

/** Splits one provider webhook into per-installation payloads. An entry for an unknown account is dropped, never guessed. */
final class Router
{
    public function __construct(private Store $store)
    {
    }

    /** @return array<string, array> installation id => payload holding only that installation's entries */
    public function split(string $platform, array $payload): array
    {
        $entries = []; // owner => list of entries
        foreach ($payload['entry'] ?? [] as $entry) {
            if ($platform === 'whatsapp') {
                $byOwner = [];
                foreach ($entry['changes'] ?? [] as $change) {
                    $owner = $this->store->owner('whatsapp', (string) ($change['value']['metadata']['phone_number_id'] ?? ''));
                    if ($owner) {
                        $byOwner[$owner][] = $change;
                    }
                }
                foreach ($byOwner as $owner => $changes) {
                    $entries[$owner][] = ['id' => $entry['id'] ?? null, 'changes' => $changes];
                }
            } elseif ($owner = $this->store->owner($platform, (string) ($entry['id'] ?? ''))) {
                $entries[$owner][] = $entry;
            }
        }

        return array_map(fn ($list) => ['object' => $payload['object'] ?? null, 'entry' => $list], $entries);
    }
}
