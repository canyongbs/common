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
      of the licensor in the software. Any use of the licensor’s trademarks is subject
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

use App\Features\AlphaFeature;
use App\Features\BetaFeature;
use App\Features\GammaLandlordFeature;
use CanyonGBS\Common\Support\Tenancy\SpatieTenantRunner;
use CanyonGBS\Common\Tests\Console\Fixtures\FeatureFlags\FakeTenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;

$featureFlagFixturePath = function (string $path = ''): string {
    return __DIR__ . '/Fixtures/FeatureFlags' . ($path === '' ? '' : "/{$path}");
};

$createFeaturesTable = function (string $connection): void {
    Schema::connection($connection)->create('features', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('scope');
        $table->text('value');
        $table->timestamps();
        $table->unique(['name', 'scope']);
    });
};

$storedFeatureValue = function (string $connection, string $flag): ?bool {
    $value = DB::connection($connection)->table('features')->where('name', $flag)->value('value');

    return $value === null ? null : json_decode($value);
};

beforeEach(function () use ($featureFlagFixturePath, $createFeaturesTable): void {
    foreach (glob($featureFlagFixturePath('app/Support/*.php')) as $file) {
        require_once $file;
    }

    foreach (glob($featureFlagFixturePath('app/Features/*.php')) as $file) {
        require_once $file;
    }

    require_once $featureFlagFixturePath('FakeTenant.php');

    $this->app->setBasePath($featureFlagFixturePath());

    foreach (['tenant_one', 'tenant_two'] as $connection) {
        config()->set("database.connections.{$connection}", ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    config()->set('pennant.default', 'database');
    config()->set('pennant.stores.database', ['driver' => 'database', 'connection' => null]);
    config()->set('pennant.stores.landlord', ['driver' => 'database', 'connection' => 'testing']);

    foreach (['testing', 'tenant_one', 'tenant_two'] as $connection) {
        $createFeaturesTable($connection);
    }

    Feature::resolveScopeUsing(fn (): null => null);
    Feature::flushCache();
});

describe('in a single-database application', function () use ($storedFeatureValue): void {
    it('activates a flag given its name without the Feature suffix', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha']])
            ->expectsOutputToContain('Activating AlphaFeature')
            ->assertSuccessful();

        expect($storedFeatureValue('testing', AlphaFeature::class))->toBeTrue();
    });

    it('activates several flags given short or fully-qualified names', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['AlphaFeature', BetaFeature::class]])
            ->assertSuccessful();

        expect($storedFeatureValue('testing', AlphaFeature::class))->toBeTrue()
            ->and($storedFeatureValue('testing', BetaFeature::class))->toBeTrue();
    });

    it('deactivates a flag', function () use ($storedFeatureValue): void {
        AlphaFeature::activate();

        $this->artisan('ff:deactivate', ['flags' => ['Alpha']])
            ->expectsOutputToContain('Deactivating AlphaFeature')
            ->assertSuccessful();

        expect($storedFeatureValue('testing', AlphaFeature::class))->toBeFalse();
    });

    it('activates a landlord flag in the landlord store', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['GammaLandlord']])
            ->expectsOutputToContain('Activating GammaLandlordFeature (landlord)')
            ->assertSuccessful();

        expect($storedFeatureValue('testing', GammaLandlordFeature::class))->toBeTrue();
    });

    it('fails without changing anything when a name is not a feature flag', function (string $name) use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha', $name]])
            ->expectsOutputToContain('is not a feature flag')
            ->assertFailed();

        expect($storedFeatureValue('testing', AlphaFeature::class))->toBeNull();
    })->with([
        'unknown class' => 'Missing',
        'class that is not a feature flag' => 'NotAFeatureFlag',
        'abstract feature flag helper' => 'AbstractHelper',
    ]);

    it('rejects --tenant', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha'], '--tenant' => ['1']])
            ->expectsOutputToContain('only available in multi-tenant applications')
            ->assertFailed();

        expect($storedFeatureValue('testing', AlphaFeature::class))->toBeNull();
    });

    it('rejects --tenant when listing, even with no tenant flags to list', function (): void {
        $emptyApp = sys_get_temp_dir() . '/' . uniqid('common-ff-empty-');
        mkdir($emptyApp . '/app/Features', 0755, true);
        $this->app->setBasePath($emptyApp);

        try {
            $this->artisan('ff:list', ['--tenant' => ['1']])
                ->expectsOutputToContain('only available in multi-tenant applications')
                ->assertFailed();
        } finally {
            File::deleteDirectory($emptyApp);
        }
    });

    describe('in production', function () use ($storedFeatureValue): void {
        beforeEach(function (): void {
            $this->originalEnvironment = $this->app->environment();

            $this->app->detectEnvironment(fn (): string => 'production');
        });

        // Testbench rolls migrations back on teardown, which would itself ask for confirmation in production.
        afterEach(function (): void {
            $this->app->detectEnvironment(fn (): string => $this->originalEnvironment);
        });

        it('asks for confirmation', function () use ($storedFeatureValue): void {
            $this->artisan('ff:activate', ['flags' => ['Alpha']])
                ->expectsConfirmation('Are you sure you want to run this command?', 'no')
                ->assertFailed();

            expect($storedFeatureValue('testing', AlphaFeature::class))->toBeNull();
        });

        it('skips the confirmation with --force', function () use ($storedFeatureValue): void {
            $this->artisan('ff:activate', ['flags' => ['Alpha'], '--force' => true])
                ->assertSuccessful();

            expect($storedFeatureValue('testing', AlphaFeature::class))->toBeTrue();
        });
    });

    it('lists feature flags and whether they are active', function (): void {
        AlphaFeature::activate();

        $this->artisan('ff:list')
            ->expectsTable(['Flag', 'Scope', 'Active'], [
                ['AlphaFeature', 'Global', 'Yes'],
                ['BetaFeature', 'Global', 'No'],
                ['GammaLandlordFeature', 'Landlord', 'No'],
            ])
            ->assertSuccessful();
    });
});

describe('in a multi-tenant application', function () use ($storedFeatureValue): void {
    beforeEach(function (): void {
        Schema::connection('testing')->create('fake_tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('database');
        });

        FakeTenant::query()->create(['id' => 1, 'name' => 'One', 'database' => 'tenant_one']);
        FakeTenant::query()->create(['id' => 2, 'name' => 'Two', 'database' => 'tenant_two']);

        $this->app->bind(SpatieTenantRunner::TENANT_CONTRACT, FakeTenant::class);

        FakeTenant::$executions = 0;
    });

    afterEach(function (): void {
        FakeTenant::forgetCurrent();
    });

    it('switches into each tenant once when changing several flags', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha', 'Beta']])
            ->expectsOutputToContain('Activating AlphaFeature, BetaFeature for tenant One (1)')
            ->expectsOutputToContain('Activating AlphaFeature, BetaFeature for tenant Two (2)')
            ->assertSuccessful();

        expect(FakeTenant::$executions)->toBe(2);

        foreach (['tenant_one', 'tenant_two'] as $connection) {
            expect($storedFeatureValue($connection, AlphaFeature::class))->toBeTrue()
                ->and($storedFeatureValue($connection, BetaFeature::class))->toBeTrue();
        }
    });

    it('switches into each tenant once when listing several flags', function (): void {
        $this->artisan('ff:list')->assertSuccessful();

        expect(FakeTenant::$executions)->toBe(2);
    });

    it('activates a tenant flag for every tenant by default', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha']])
            ->expectsOutputToContain('Activating AlphaFeature for tenant One (1)')
            ->expectsOutputToContain('Activating AlphaFeature for tenant Two (2)')
            ->assertSuccessful();

        expect($storedFeatureValue('tenant_one', AlphaFeature::class))->toBeTrue()
            ->and($storedFeatureValue('tenant_two', AlphaFeature::class))->toBeTrue()
            ->and($storedFeatureValue('testing', AlphaFeature::class))->toBeNull();
    });

    it('only changes the tenants passed with --tenant', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha'], '--tenant' => ['2']])
            ->assertSuccessful();

        expect($storedFeatureValue('tenant_one', AlphaFeature::class))->toBeNull()
            ->and($storedFeatureValue('tenant_two', AlphaFeature::class))->toBeTrue();
    });

    it('matches --tenant against the configured search fields', function () use ($storedFeatureValue): void {
        config()->set('multitenancy.tenant_artisan_search_fields', ['id', 'name']);

        $this->artisan('ff:activate', ['flags' => ['Alpha'], '--tenant' => ['One']])
            ->assertSuccessful();

        expect($storedFeatureValue('tenant_one', AlphaFeature::class))->toBeTrue()
            ->and($storedFeatureValue('tenant_two', AlphaFeature::class))->toBeNull();
    });

    it('fails when no tenant matches', function (): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha'], '--tenant' => ['999']])
            ->expectsOutputToContain('No tenant(s) found.')
            ->assertFailed();
    });

    it('only changes the current tenant when one is already current', function () use ($storedFeatureValue): void {
        FakeTenant::query()->findOrFail(2)->makeCurrent();

        $this->artisan('ff:activate', ['flags' => ['Alpha']])
            ->assertSuccessful();

        expect($storedFeatureValue('tenant_one', AlphaFeature::class))->toBeNull()
            ->and($storedFeatureValue('tenant_two', AlphaFeature::class))->toBeTrue();
    });

    it('rejects --tenant when a tenant is already current', function (): void {
        FakeTenant::query()->findOrFail(2)->makeCurrent();

        $this->artisan('ff:activate', ['flags' => ['Alpha'], '--tenant' => ['1']])
            ->expectsOutputToContain('A tenant is already current')
            ->assertFailed();
    });

    it('activates a landlord flag once, in the landlord store', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['GammaLandlord']])
            ->expectsOutputToContain('Activating GammaLandlordFeature (landlord)')
            ->doesntExpectOutputToContain('for tenant')
            ->assertSuccessful();

        expect($storedFeatureValue('testing', GammaLandlordFeature::class))->toBeTrue()
            ->and($storedFeatureValue('tenant_one', GammaLandlordFeature::class))->toBeNull();
    });

    it('rejects --tenant for landlord flags', function (): void {
        $this->artisan('ff:activate', ['flags' => ['GammaLandlord'], '--tenant' => ['1']])
            ->expectsOutputToContain('cannot be used with landlord feature flags')
            ->assertFailed();
    });

    it('deactivates a tenant flag for every tenant', function () use ($storedFeatureValue): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha']])->assertSuccessful();

        $this->artisan('ff:deactivate', ['flags' => ['Alpha']])
            ->assertSuccessful();

        expect($storedFeatureValue('tenant_one', AlphaFeature::class))->toBeFalse()
            ->and($storedFeatureValue('tenant_two', AlphaFeature::class))->toBeFalse();
    });

    it('reports each tenant separately even though they share a Pennant store name', function (): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha'], '--tenant' => ['1']])->assertSuccessful();
        $this->artisan('ff:activate', ['flags' => ['GammaLandlord']])->assertSuccessful();

        $this->artisan('ff:list')
            ->expectsTable(['Flag', 'Scope', 'Active'], [
                ['AlphaFeature', 'Tenant', 'Partly (1 of 2 tenants; inactive for Two (2))'],
                ['BetaFeature', 'Tenant', 'No (0 of 2 tenants)'],
                ['GammaLandlordFeature', 'Landlord', 'Yes'],
            ])
            ->assertSuccessful();
    });

    it('lists a single tenant when one is passed', function (): void {
        $this->artisan('ff:activate', ['flags' => ['Alpha']])->assertSuccessful();

        $this->artisan('ff:list', ['--tenant' => ['2']])
            ->expectsTable(['Flag', 'Scope', 'Active'], [
                ['AlphaFeature', 'Tenant', 'Yes'],
                ['BetaFeature', 'Tenant', 'No'],
                ['GammaLandlordFeature', 'Landlord', 'No'],
            ])
            ->assertSuccessful();
    });
});
