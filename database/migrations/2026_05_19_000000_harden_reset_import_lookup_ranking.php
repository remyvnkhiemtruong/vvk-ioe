<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('import_batches', 'report')) {
                $table->json('report')->nullable()->after('errors');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! $this->hasIndex('students', 'students_lookup_identity_index')) {
                $table->index(['normalized_name', 'class_name', 'date_of_birth'], 'students_lookup_identity_index');
            }
            if (! $this->hasIndex('students', 'students_class_birth_index')) {
                $table->index(['class_name', 'date_of_birth'], 'students_class_birth_index');
            }
        });

        $this->removeDuplicateRankings();

        Schema::table('rankings', function (Blueprint $table) {
            if (! $this->hasIndex('rankings', 'rankings_exam_scope_grade_score_unique')) {
                $table->unique(['exam_id', 'scope', 'grade_number', 'student_score_id'], 'rankings_exam_scope_grade_score_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rankings', function (Blueprint $table) {
            if ($this->hasIndex('rankings', 'rankings_exam_scope_grade_score_unique')) {
                $table->dropUnique('rankings_exam_scope_grade_score_unique');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if ($this->hasIndex('students', 'students_lookup_identity_index')) {
                $table->dropIndex('students_lookup_identity_index');
            }
            if ($this->hasIndex('students', 'students_class_birth_index')) {
                $table->dropIndex('students_class_birth_index');
            }
        });

        Schema::table('import_batches', function (Blueprint $table) {
            if (Schema::hasColumn('import_batches', 'report')) {
                $table->dropColumn('report');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $row) => ($row['name'] ?? null) === $index);
        } catch (\Throwable) {
            //
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        if ($driver === 'pgsql') {
            return collect(DB::select(
                'select indexname from pg_indexes where schemaname = current_schema() and tablename = ?',
                [$table]
            ))->contains(fn ($row) => ($row->indexname ?? null) === $index);
        }

        return collect(DB::select('SHOW INDEX FROM '.$table))
            ->contains(fn ($row) => ($row->Key_name ?? null) === $index);
    }

    private function removeDuplicateRankings(): void
    {
        if (! Schema::hasTable('rankings')) {
            return;
        }

        DB::table('rankings')
            ->select('exam_id', 'scope', 'grade_number', 'student_score_id')
            ->selectRaw('MIN(id) as keep_id, COUNT(*) as total')
            ->groupBy('exam_id', 'scope', 'grade_number', 'student_score_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('keep_id')
            ->get()
            ->each(function ($row): void {
                DB::table('rankings')
                    ->where('exam_id', $row->exam_id)
                    ->where('scope', $row->scope)
                    ->where('student_score_id', $row->student_score_id)
                    ->when($row->grade_number === null, fn ($query) => $query->whereNull('grade_number'), fn ($query) => $query->where('grade_number', $row->grade_number))
                    ->where('id', '!=', $row->keep_id)
                    ->delete();
            });
    }
};
