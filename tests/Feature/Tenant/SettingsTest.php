<?php

use App\Actions\ProvisionTenant;
use App\Models\Setting;
use App\Settings\BusinessSettings;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** The required non-file business fields, for a valid update payload. */
function businessBase(array $overrides = []): array
{
    return [
        'tax_type' => 'sst',
        'country' => 'MY',
        'default_currency' => 'MYR',
        'financial_year_start_month' => '1',
        'sales_order_prefix' => 'SO',
        'purchase_order_prefix' => 'PO',
        'invoice_prefix' => 'INV',
        'number_reset' => 'yearly',
        ...$overrides,
    ];
}

it('seeds default business settings on tenant provision (form mirrors the DB)', function () {
    $this->tenant->run(function () {
        $values = Setting::valuesFor('business');

        // The config defaults are now actually stored (not phantom form defaults).
        expect($values)->toMatchArray([
            'tax_type' => 'sst',
            'country' => 'MY',
            'default_currency' => 'MYR',
            'financial_year_start_month' => '1',
            'sales_order_prefix' => 'SO',
            'purchase_order_prefix' => 'PO',
            'invoice_prefix' => 'INV',
            'number_reset' => 'yearly',
        ])
            // Nullable identity fields (and the logo) are NOT seeded — they stay blank.
            ->and($values)->not->toHaveKey('legal_name')
            ->and($values)->not->toHaveKey('logo');
    });
});

it('re-seeding is additive — it never overwrites a stored value', function () {
    // A user edits a setting…
    $this->tenant->run(fn () => Setting::putMany('business', ['tax_type' => 'gst']));

    // …re-running the tenant seeder must only backfill missing keys, never clobber it.
    $this->tenant->run(fn () => app(TenantDatabaseSeeder::class)->run());

    $this->tenant->run(function () {
        expect(Setting::valuesFor('business')['tax_type'])->toBe('gst')
            ->and(Setting::where('category', 'business')->where('key', 'tax_type')->count())->toBe(1);
    });
});

it('redirects a guest from a settings page to the tenant login', function () {
    $this->get('/acme/settings/business')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('404s an unknown settings category', function () {
    loginAsAcmeUser();

    $this->get('/acme/settings/nope')->assertNotFound();
});

it('renders the business settings page with its schema and default values', function () {
    loginAsAcmeUser();

    $this->get('/acme/settings/business')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/index')
            ->where('category', 'business')
            ->has('schema')
            ->where('schema.0.key', 'legal_name')
            ->has('schema.0.description')
            ->where('values.tax_type', 'sst')
            ->where('values.country', 'MY')
            ->where('values.default_currency', 'MYR')
        );
});

it('saves business settings as key-value rows and flashes a toast', function () {
    loginAsAcmeUser();

    $this->from('/acme/settings/business')
        ->put('/acme/settings/business', businessBase([
            'legal_name' => 'Acme Manufacturing Sdn Bhd',
            'tax_type' => 'gst',
            'country' => 'SG',
            'default_currency' => 'SGD',
            'financial_year_start_month' => '4',
            'number_reset' => 'never',
        ]))
        ->assertRedirect('/acme/settings/business')
        ->assertToast('Settings saved.');

    $this->tenant->run(function () {
        $values = Setting::valuesFor('business');
        expect($values['legal_name'])->toBe('Acme Manufacturing Sdn Bhd')
            ->and($values['tax_type'])->toBe('gst')
            ->and($values['country'])->toBe('SG')
            ->and($values['default_currency'])->toBe('SGD')
            ->and($values['number_reset'])->toBe('never');
    });
});

it('rejects an invalid tax type and financial-year month from the schema rules', function () {
    loginAsAcmeUser();

    $this->from('/acme/settings/business')
        ->put('/acme/settings/business', businessBase([
            'tax_type' => 'vat',
            'financial_year_start_month' => '13',
        ]))
        ->assertRedirect('/acme/settings/business')
        ->assertSessionHasErrors(['tax_type', 'financial_year_start_month']);
});

it('updates a key in place — no duplicate rows (unique category,key)', function () {
    loginAsAcmeUser();

    $this->put('/acme/settings/business', businessBase(['legal_name' => 'First']));
    $this->put('/acme/settings/business', businessBase(['legal_name' => 'Second']));

    $this->tenant->run(function () {
        expect(Setting::where('category', 'business')->where('key', 'legal_name')->count())->toBe(1)
            ->and(Setting::valuesFor('business')['legal_name'])->toBe('Second');
    });
});

it('exposes saved values to the shared business document header', function () {
    loginAsAcmeUser();

    $this->put('/acme/settings/business', businessBase([
        'legal_name' => 'Acme Sdn Bhd',
        'tax_type' => 'sst',
        'tax_registration_no' => 'W10-1808-32000123',
    ]));

    $this->tenant->run(function () {
        $header = app(BusinessSettings::class)->documentHeader();
        expect($header->legal_name)->toBe('Acme Sdn Bhd')
            ->and($header->tax_registration_no)->toBe('W10-1808-32000123')
            ->and($header->has_logo)->toBeFalse();
    });
});

it('uploads a logo (method-spoofed multipart POST) and then removes it', function () {
    Storage::fake('assets');

    loginAsAcmeUser();

    // Mirror the real browser request: file uploads go out as POST + _method=PUT.
    $this->from('/acme/settings/business')
        ->post('/acme/settings/business', [
            ...businessBase(),
            '_method' => 'PUT',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertRedirect('/acme/settings/business');

    $stored = $this->tenant->run(fn () => Setting::valuesFor('business')['logo'] ?? null);
    expect($stored)->not->toBeNull();
    Storage::disk('assets')->assertExists('acme/'.$stored);

    // The document header now reports a logo.
    $this->tenant->run(function () {
        expect(app(BusinessSettings::class)->documentHeader()->has_logo)->toBeTrue();
    });

    // Removing clears the stored path + deletes the file.
    $this->from('/acme/settings/business')
        ->post('/acme/settings/business', [
            ...businessBase(),
            '_method' => 'PUT',
            'remove_logo' => '1',
        ])
        ->assertRedirect('/acme/settings/business');

    $after = $this->tenant->run(fn () => Setting::valuesFor('business')['logo'] ?? null);
    expect($after)->toBeNull();
    Storage::disk('assets')->assertMissing('acme/'.$stored);
});

it('streams the logo through the auth-gated file route but never a non-file field', function () {
    Storage::fake('assets');

    loginAsAcmeUser();

    $this->post('/acme/settings/business', [
        ...businessBase(['legal_name' => 'Acme Sdn Bhd']),
        '_method' => 'PUT',
        'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
    ]);

    $this->get('/acme/settings/business/file/logo')->assertOk();
    // Unknown key + a real NON-file key (whose value is user text, not a path) both 404.
    $this->get('/acme/settings/business/file/nope')->assertNotFound();
    $this->get('/acme/settings/business/file/legal_name')->assertNotFound();
});
