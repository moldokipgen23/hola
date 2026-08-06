<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ImportItem;

/**
 * Duplicate detection + conservative merge for import items.
 *
 * When an import item matches an existing business at approve time, the item is
 * flagged as a duplicate and linked via `duplicate_of`. The admin can then merge
 * the item's data into the canonical business instead of discarding it — only
 * fields the canonical business is missing are copied over.
 */
class ImportMergeService
{
    /**
     * Find the existing business this import item duplicates (if any).
     */
    public function findExistingDuplicate(ImportItem $item): ?Business
    {
        $data = $item->data;

        if (! empty($item->external_id)) {
            $existing = Business::withoutTrashed()->where('external_id', $item->external_id)->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($data['name'])) {
            $name = strtolower(trim($data['name'], " \t\n\r\0\x0B,"));
            $existing = Business::withoutTrashed()
                ->whereRaw('LOWER(name) = ?', [$name])
                ->first();

            if ($existing && ! empty($data['address'])) {
                similar_text(strtolower($existing->address), strtolower($data['address']), $percent);
                if ($percent < 50) {
                    $existing = null;
                }
            }

            if ($existing) {
                return $existing;
            }
        }

        if (! empty($data['phone'])) {
            $normalizedPhone = str_replace([' ', '-', '(', ')', '+'], '', $data['phone']);

            return Business::withoutTrashed()
                ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$normalizedPhone])
                ->first();
        }

        return null;
    }

    /**
     * Flag the item as a duplicate linked to the canonical business.
     */
    public function flagDuplicate(ImportItem $item, Business $existing): void
    {
        $item->update([
            'status' => ImportItem::STATUS_DUPLICATE,
            'duplicate_of' => $existing->id,
            'notes' => "Duplicate of existing business: {$existing->name} (ID: {$existing->id})",
        ]);

        if ($item->batch) {
            $item->batch->increment('rejected');
            $item->batch->decrement('pending');
        }
    }

    /**
     * Merge the item's data into the canonical business (fill gaps only) and
     * record the merge. Returns the number of fields copied over.
     */
    public function merge(ImportItem $item): int
    {
        $existing = $item->duplicateOf;
        if (! $existing) {
            throw new \RuntimeException('This item is not linked to an existing business. Flag it as a duplicate first.');
        }

        $data = $item->data;
        $updates = [];

        foreach ([
            'phone' => 'phone',
            'description' => 'description',
            'website' => 'website',
            'working_hours' => 'working_hours',
        ] as $field => $column) {
            if (blank($existing->getAttribute($column)) && ! empty($data[$field])) {
                $updates[$column] = $data[$field];
            }
        }

        if (blank($existing->latitude) && ! empty($data['latitude'])) {
            $updates['latitude'] = $data['latitude'];
        }
        if (blank($existing->longitude) && ! empty($data['longitude'])) {
            $updates['longitude'] = $data['longitude'];
        }
        if ((float) ($existing->average_rating ?? 0) === 0.0 && ! empty($data['rating'])) {
            $updates['average_rating'] = $data['rating'];
        }
        if ((int) ($existing->review_count ?? 0) === 0 && ! empty($data['total_ratings'])) {
            $updates['review_count'] = $data['total_ratings'];
        }
        if (blank($existing->external_id) && ! empty($item->external_id)) {
            $updates['external_id'] = $item->external_id;
        }

        if (! empty($updates)) {
            $existing->update($updates);
        }

        $item->update([
            'status' => ImportItem::STATUS_MERGED,
            'notes' => "Merged into existing business #{$existing->id} ({$existing->name})",
        ]);

        return count($updates);
    }
}
