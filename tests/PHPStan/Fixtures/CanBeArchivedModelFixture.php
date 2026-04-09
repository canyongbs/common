<?php

declare(strict_types=1);

use Workbench\App\Models\Project;

$query = Project::query();

$query->withoutArchived();
$query->onlyArchived();
$query->withoutArchivedAndUnused();
