<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Example adjustments - only apply if needed
            if (!Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }

            if (!Schema::hasColumn('users', 'suffix')) {
                $table->string('suffix')->nullable()->after('last_name');
            }

            if (!Schema::hasColumn('users', 'employeeNumber')) {
                $table->string('employeeNumber')->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'gender')) {
                $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->after('date_of_birth');
            }

            if (!Schema::hasColumn('users', 'specialization')) {
                $table->string('specialization')->nullable()->after('phone_number');
            }

            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('specialization');
            }

            if (!Schema::hasColumn('users', 'EmpAddress')) {
                $table->text('EmpAddress')->nullable()->after('profile_image');
            }

            if (!Schema::hasColumn('users', 'EmpStatus')) {
                $table->string('EmpStatus')->nullable()->after('EmpAddress');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('EmpStatus');
            }

            if (!Schema::hasColumn('users', 'userLevel')) {
                $table->string('userLevel')->default('staff')->after('status');
            }

            if (!Schema::hasColumn('users', 'reset_token')) {
                $table->string('reset_token')->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'reset_expires')) {
                $table->dateTime('reset_expires')->nullable()->after('reset_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name',
                'suffix',
                'employeeNumber',
                'gender',
                'specialization',
                'profile_image',
                'EmpAddress',
                'EmpStatus',
                'status',
                'userLevel',
                'reset_token',
                'reset_expires',
            ]);
        });
    }
};
