<?php

use App\Actions\ProvisionTenant;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('keeps the tenant asset folder when the tenant is soft deleted', function () {
    Storage::fake('assets');
    Storage::disk('assets')->put('acme/1/logo.jpg', 'bytes');

    $this->tenant->delete(); // archive — database + files retained for restore

    Storage::disk('assets')->assertExists('acme/1/logo.jpg');
});

it('removes the whole tenant asset folder when the tenant is force deleted', function () {
    Storage::fake('assets');
    Storage::disk('assets')->put('acme/1/logo.jpg', 'bytes');
    Storage::disk('assets')->put('acme/2/photo.jpg', 'bytes');

    // forceDelete fires TenantDeleted -> DeleteDatabase + DeleteTenantAssets (sync).
    $this->tenant->forceDelete();

    Storage::disk('assets')->assertMissing('acme/1/logo.jpg');
    Storage::disk('assets')->assertMissing('acme/2/photo.jpg');
    expect(Storage::disk('assets')->allFiles('acme'))->toBe([]);
});
