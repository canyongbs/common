<?php

declare(strict_types=1);

use Workbench\App\Models\Project;

$project = new Project();
$project->tasks()->withoutArchived();
$project->tasks()->onlyArchived();
$project->tasks()->withoutArchivedAndUnused();
