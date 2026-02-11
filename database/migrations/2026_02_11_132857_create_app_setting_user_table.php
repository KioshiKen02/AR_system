<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Explicitly use the mysql connection as this is for the main database
        Schema::connection('mysql')->create('app_setting_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('app_setting_id')->constrained('app_settings')->onDelete('cascade');
            $table->timestamps();
            
            // Add unique constraint to prevent duplicate user-app pairings
            $table->unique(['user_id', 'app_setting_id']);
        });

        // Migrate existing data from users.app_setting_id to the new pivot table
        $users = DB::connection('mysql')->table('users')->whereNotNull('app_setting_id')->get();
        
        $pivotData = [];
        foreach ($users as $user) {
            $pivotData[] = [
                'user_id' => $user->id,
                'app_setting_id' => $user->app_setting_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($pivotData)) {
            DB::connection('mysql')->table('app_setting_user')->insert($pivotData);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('app_setting_user');
    }
};
