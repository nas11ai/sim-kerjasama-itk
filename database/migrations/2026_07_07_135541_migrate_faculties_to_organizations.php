<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function(){
            $rootId = DB::table('organizations')->where('type', 'institution')->value('id');
            $faculties = DB::table('faculties')->get();

            foreach($faculties as $faculty){
                DB::table('organizations')->insert([
                    'name' => $faculty->name,
                    'type' => 'faculty',
                    'parent_id' => $rootId,
                    'is_active' => true,
                    'metadata' => json_encode([
                        'legacy' => [
                            'faculty_id' => $faculty->id,
                        ],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('organizations')
                ->where('type', 'faculty')
                ->delete();
        });
    }
};
