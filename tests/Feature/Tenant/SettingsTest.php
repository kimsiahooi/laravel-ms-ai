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
            // Every field is seeded — the nullable identity fields + logo exist as empty
            // (null) rows, so the table fully mirrors the schema.
            ->and($values)->toHaveKeys(['legal_name', 'tin', 'email', 'address', 'logo'])
            ->and($values['legal_name'])->toBeNull()
            ->and($values['tin'])->toBeNull()
            ->and($values['logo'])->toBeNull()
            ->and(count($values))->toBe(count(app(BusinessSettings::class)->fields()));
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

it('rejects empty required fields through the settings form request', function () {
    loginAsAcmeUser();

    // country + tax_type are `required` in the schema; empty values must fail via the
    // SettingsUpdateRequest (not silently save).
    $this->from('/acme/settings/business')
        ->put('/acme/settings/business', businessBase(['country' => '', 'tax_type' => '']))
        ->assertRedirect('/acme/settings/business')
        ->assertSessionHasErrors(['country', 'tax_type']);
});

it('404s a settings update for an unknown category', function () {
    loginAsAcmeUser();

    // The form request resolves the category via SettingsRegistry, which 404s an
    // unknown slug — same as the GET route.
    $this->put('/acme/settings/nope', businessBase())->assertNotFound();
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
            ->and($header->logo_url)->toBeNull();
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

    $path = $this->tenant->run(function () {
        $media = app(BusinessSettings::class)->fileMedia('logo');
        expect($media)->not->toBeNull()
            // Provenance: the morph alias records this came from a setting.
            ->and($media->model_type)->toBe('setting');

        return $media->getPathRelativeToRoot();
    });
    // Files are namespaced by the tenant slug + media id: acme/{id}/{file}.
    expect(str_starts_with($path, 'acme/'))->toBeTrue();
    Storage::disk('assets')->assertExists($path);

    // The document header now exposes the content-addressed logo URL.
    $this->tenant->run(function () {
        expect(app(BusinessSettings::class)->documentHeader()->logo_url)->toContain('/acme/media/');
    });

    // Removing clears the media + deletes the file.
    $this->from('/acme/settings/business')
        ->post('/acme/settings/business', [
            ...businessBase(),
            '_method' => 'PUT',
            'remove_logo' => '1',
        ])
        ->assertRedirect('/acme/settings/business');

    $this->tenant->run(function () {
        expect(app(BusinessSettings::class)->fileMedia('logo'))->toBeNull();
    });
    Storage::disk('assets')->assertMissing($path);
});

it('serves the logo by media id and 404s the old id after a re-upload (never stale)', function () {
    Storage::fake('assets');

    loginAsAcmeUser();

    // Upload the first logo (method-spoofed multipart POST, like the browser).
    $this->from('/acme/settings/business')->post('/acme/settings/business', [
        ...businessBase(),
        '_method' => 'PUT',
        'logo' => UploadedFile::fake()->image('one.png', 120, 120),
    ])->assertRedirect('/acme/settings/business');

    $firstUrl = $this->tenant->run(fn () => app(BusinessSettings::class)->documentHeader()->logo_url);
    expect($firstUrl)->toContain('/acme/media/');

    $response = $this->get($firstUrl);
    $response->assertOk()->assertHeader('etag'); // cache validator (StreamsMedia)
    expect($response->headers->get('cache-control'))->toContain('no-cache');

    // Re-upload -> new media row (singleFile) -> new id -> new URL.
    $this->from('/acme/settings/business')->post('/acme/settings/business', [
        ...businessBase(),
        '_method' => 'PUT',
        'logo' => UploadedFile::fake()->image('two.png', 120, 120),
    ])->assertRedirect('/acme/settings/business');

    $secondUrl = $this->tenant->run(fn () => app(BusinessSettings::class)->documentHeader()->logo_url);

    expect($secondUrl)->toContain('/acme/media/')
        ->and($secondUrl)->not->toBe($firstUrl);

    // The new logo serves; the old media id now shows nothing (404).
    $this->get($secondUrl)->assertOk();
    $this->get($firstUrl)->assertNotFound();
});
