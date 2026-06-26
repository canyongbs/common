<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->after('first_name', function (Blueprint $table) {
                $table->string('middle_name');
                $table->string('suffix');
            });
        });
    }
};
