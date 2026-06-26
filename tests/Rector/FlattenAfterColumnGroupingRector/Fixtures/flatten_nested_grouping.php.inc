<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->after('first_name', function (Blueprint $table) {
        $table->string('middle_name');
        $table->after('middle_name', function (Blueprint $table) {
            $table->string('suffix');
        });
    });
});

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->string('middle_name');
    $table->string('suffix');
});

?>
