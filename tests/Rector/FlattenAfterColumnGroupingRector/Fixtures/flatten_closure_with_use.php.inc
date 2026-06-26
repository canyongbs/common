<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$length = 100;

Schema::table('users', function (Blueprint $table) use ($length) {
    $table->after('first_name', function (Blueprint $table) use ($length) {
        $table->string('middle_name', $length);
        $table->string('suffix', $length);
    });
});

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$length = 100;

Schema::table('users', function (Blueprint $table) use ($length) {
    $table->string('middle_name', $length);
    $table->string('suffix', $length);
});

?>
