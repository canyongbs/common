<?php

use Illuminate\Database\Schema\Blueprint;

final class MigrationWithBlueprintProperty
{
    public Blueprint $table;

    public function addColumns(): void
    {
        $this->table->after('first_name', function (Blueprint $table) {
            $table->string('middle_name');
        });
    }
}
