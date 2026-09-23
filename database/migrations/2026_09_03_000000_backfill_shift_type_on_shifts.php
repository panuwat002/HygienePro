<?php

use App\Models\Shift;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Shifts created through the admin form never carried a shift_type, because the form
     * had no such field. A session started without picking a shift card stores a generic
     * key ('afternoon'), which can only resolve through shift_type — so those shifts
     * matched nothing and the inspector got an empty employee list.
     *
     * Fills the gap from the shift's own name/start_time. Only touches rows that have no
     * value yet, so it is safe to re-run and never overwrites a curated value.
     */
    public function up(): void
    {
        DB::table('shifts')
            // Grouped: eachById() pages with chunkById, which appends "and id > ?" — an
            // ungrouped orWhere would bind as "shift_type is null or (shift_type = '' and id > ?)".
            // each() would page by OFFSET instead, and since each update removes a row from
            // this result set, later pages would skip unprocessed rows.
            ->where(function ($q) {
                $q->whereNull('shift_type')->orWhere('shift_type', '');
            })
            ->orderBy('id')
            ->eachById(function ($shift) {
                DB::table('shifts')->where('id', $shift->id)->update([
                    'shift_type' => Shift::deriveType(
                        $shift->shift_name,
                        $shift->start_time,
                        (bool) ($shift->is_dayoff ?? false)
                    ),
                ]);
            });

        // Written straight through the query builder, so no model event fired
        // to drop Shift::cachedAll(). Anything reading shift types after this
        // in the same process would otherwise still see the un-backfilled rows.
        Shift::forgetCachedAll();
    }

    /**
     * Not reversible: which rows were blank before the backfill isn't recorded anywhere,
     * and clearing every shift_type would reintroduce the empty-employee-list bug.
     */
    public function down(): void
    {
        //
    }
};
