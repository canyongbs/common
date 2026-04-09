<?php

declare(strict_types=1);

use Workbench\App\Models\Task;

$task = new Task();
$task->project()->withoutArchived();
$task->project()->onlyArchived();
$task->project()->withoutArchivedAndUnused();
