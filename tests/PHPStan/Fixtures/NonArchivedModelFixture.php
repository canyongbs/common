<?php

declare(strict_types=1);

use Workbench\App\Models\Task;

$query = Task::query();

$query->withoutArchived();
$query->onlyArchived();
$query->withoutArchivedAndUnused();
