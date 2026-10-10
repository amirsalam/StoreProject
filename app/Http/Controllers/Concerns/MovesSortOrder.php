<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Move up / move down" for admin lists ordered by a sort_order column.
 */
trait MovesSortOrder
{
    protected function moveInOrder(Request $request, Model $item): void
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $ordered = $item->newQuery()->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $ordered->search(fn (Model $m) => $m->is($item));
        $swapWith = $ordered->get($direction === 'up' ? $index - 1 : $index + 1);

        if (! $swapWith) {
            return;
        }

        DB::transaction(function () use ($ordered, $index, $direction) {
            // Normalise to 1..n, then swap the two neighbours.
            $other = $direction === 'up' ? $index - 1 : $index + 1;
            foreach ($ordered as $i => $m) {
                $position = match ($i) {
                    $index => $other + 1,
                    $other => $index + 1,
                    default => $i + 1,
                };
                $m->updateQuietly(['sort_order' => $position]);
            }
        });
    }
}
