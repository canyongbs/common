<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor's trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

// TODO: This is a temporary test for our custom ModularLivewirePlugin implementation.
// Remove this file and use `internachi/modular-livewire` once it is published to Packagist.
// See: https://github.com/InterNACHI/modular-livewire

use CanyonGBS\Common\Support\ModularLivewirePlugin;
use Illuminate\Filesystem\Filesystem;
use InterNACHI\Modular\Plugins\Plugin;
use InterNACHI\Modular\Support\Facades\Modules;
use InterNACHI\Modular\Support\FinderFactory;
use InterNACHI\Modular\Support\ModularizedCommandsServiceProvider;
use InterNACHI\Modular\Support\ModularServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\ComponentRegistry;

beforeEach(function () {
    if (! class_exists(Plugin::class)) {
        $this->markTestSkipped('Requires internachi/modular v3');
    }

    $this->app->register(ModularServiceProvider::class);
    $this->app->register(ModularizedCommandsServiceProvider::class);
    $this->app->register(LivewireServiceProvider::class);

    $this->filesystem = new Filesystem();
    $this->modulesPath = $this->app->basePath('app-modules');

    $this->beforeApplicationDestroyed(function () {
        $this->filesystem->deleteDirectory($this->modulesPath);
    });
});

function createTestModule(object $test, string $name, string $namespace): void
{
    $modulePath = $test->modulesPath . '/' . $name;
    $test->filesystem->ensureDirectoryExists($modulePath . '/src');

    $composerJson = json_encode([
        'name' => 'test/' . $name,
        'autoload' => [
            'psr-4' => [
                $namespace . '\\' => 'src/',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $test->filesystem->put($modulePath . '/composer.json', $composerJson);
}

function createLivewireComponent(object $test, string $moduleName, string $relativePath, string $moduleNamespace, string $className): void
{
    $fullPath = $test->modulesPath . '/' . $moduleName . '/src/Livewire/' . $relativePath;
    $test->filesystem->ensureDirectoryExists(dirname($fullPath));

    $relativeDir = dirname($relativePath);
    $componentNamespace = $moduleNamespace . '\\Livewire';

    if ($relativeDir !== '.') {
        $componentNamespace .= '\\' . str_replace('/', '\\', $relativeDir);
    }

    $content = <<<PHP
        <?php

        namespace {$componentNamespace};

        use Livewire\Component;

        class {$className} extends Component
        {
            public function render()
            {
                return '<div></div>';
            }
        }
        PHP;

    $test->filesystem->put($fullPath, $content);
}

it('discovers livewire components from multiple modules', function () {
    createTestModule($this, 'test-module', 'TestModule');
    createLivewireComponent($this, 'test-module', 'TestComponent.php', 'TestModule', 'TestComponent');

    createTestModule($this, 'test-module-two', 'TestModuleTwo');
    createLivewireComponent($this, 'test-module-two', 'AnotherComponent.php', 'TestModuleTwo', 'AnotherComponent');

    Modules::reload();

    $plugin = $this->app->make(ModularLivewirePlugin::class);
    $data = collect($plugin->discover($this->app->make(FinderFactory::class)));

    expect($data)->toHaveCount(2);

    $component1 = $data->firstWhere('module', 'test-module');
    expect($component1)->not->toBeNull()
        ->and($component1['name'])->toBe('test-component')
        ->and($component1['fqcn'])->toBe('TestModule\\Livewire\\TestComponent');

    $component2 = $data->firstWhere('module', 'test-module-two');
    expect($component2)->not->toBeNull()
        ->and($component2['name'])->toBe('another-component')
        ->and($component2['fqcn'])->toBe('TestModuleTwo\\Livewire\\AnotherComponent');
});

it('discovers nested livewire components with dot notation names', function () {
    createTestModule($this, 'test-module', 'TestModule');
    createLivewireComponent($this, 'test-module', 'SubDir/NestedComponent.php', 'TestModule', 'NestedComponent');

    Modules::reload();

    $plugin = $this->app->make(ModularLivewirePlugin::class);
    $data = collect($plugin->discover($this->app->make(FinderFactory::class)));

    expect($data)->toHaveCount(1);

    $component = $data->first();
    expect($component['module'])->toBe('test-module')
        ->and($component['name'])->toBe('sub-dir.nested-component')
        ->and($component['fqcn'])->toBe('TestModule\\Livewire\\SubDir\\NestedComponent');
});

it('registers discovered components with livewire', function () {
    createTestModule($this, 'test-module', 'TestModule');
    createLivewireComponent($this, 'test-module', 'TestComponent.php', 'TestModule', 'TestComponent');

    createTestModule($this, 'test-module-two', 'TestModuleTwo');
    createLivewireComponent($this, 'test-module-two', 'AnotherComponent.php', 'TestModuleTwo', 'AnotherComponent');

    Modules::reload();

    $plugin = $this->app->make(ModularLivewirePlugin::class);
    $data = collect($plugin->discover($this->app->make(FinderFactory::class)));
    $plugin->handle($data);

    $registry = $this->app->make(ComponentRegistry::class);
    $aliases = (new ReflectionProperty($registry, 'aliases'))->getValue($registry);

    expect($aliases['test-module::test-component'])
        ->toBe('TestModule\\Livewire\\TestComponent')
        ->and($aliases['test-module-two::another-component'])
        ->toBe('TestModuleTwo\\Livewire\\AnotherComponent');
});

it('only boots when livewire is installed', function () {
    $called = false;
    $handler = function () use (&$called) {
        $called = true;
    };

    ModularLivewirePlugin::boot($handler, $this->app);

    expect($called)->toBeTrue();
});

it('returns empty results when modules have no livewire components', function () {
    createTestModule($this, 'test-module', 'TestModule');

    Modules::reload();

    $plugin = $this->app->make(ModularLivewirePlugin::class);
    $data = collect($plugin->discover($this->app->make(FinderFactory::class)));

    expect($data)->toBeEmpty();
});
