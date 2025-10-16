<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeIdsToUuid extends Migration
{
    public function up()
    {
        // Enable UUID support
        DB::statement('ALTER TABLE users MODIFY userID CHAR(36)');
        DB::statement('ALTER TABLE patients MODIFY patientID CHAR(36)');
        DB::statement('ALTER TABLE patients MODIFY userID CHAR(36)');

        // Add UUID extension for databases that support it (like PostgreSQL)
        if (config('database.default') === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        }
    }

    public function down()
    {
        // Note: Proper down migration would require restoring original column types
        // This is a simplified version - adjust according to your original schema
        Schema::table('users', function (Blueprint $table) {
            $table->bigIncrements('userID')->change();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->bigIncrements('patientID')->change();
            $table->unsignedBigInteger('userID')->change();
        });
    }
}