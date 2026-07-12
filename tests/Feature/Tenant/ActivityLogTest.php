<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the activity page to the tenant login', function () {
    $this->get('/acme/activity')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('records who created, updated and deleted a record with old → new values', function () {
    loginAsAcmeUser();

    $this->post('/acme/categories', ['name' => 'Metals'])->assertSessionHasNoErrors();
    $categoryId = $this->tenant->run(fn () => Category::first()->id);
    $this->put("/acme/categories/{$categoryId}", ['name' => 'Alloys'])->assertSessionHasNoErrors();
    $this->delete("/acme/categories/{$categoryId}")->assertSessionHasNoErrors();

    $this->tenant->run(function () {
        expect(Activity::query()->orderBy('id')->pluck('event')->all())
            ->toBe(['created', 'updated', 'deleted']);

        $update = Activity::where('event', 'updated')->first();
        expect($update->causer?->name)->toBe('Ada')
            ->and($update->attribute_changes['old']['name'])->toBe('Metals')
            ->and($update->attribute_changes['attributes']['name'])->toBe('Alloys');
    });
});

it('lists activity with the causer, subject and changed fields', function () {
    loginAsAcmeUser();

    $this->post('/acme/categories', ['name' => 'Metals']);
    $categoryId = $this->tenant->run(fn () => Category::first()->id);
    $this->put("/acme/categories/{$categoryId}", ['name' => 'Alloys']);

    $this->get('/acme/activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/activity/index')
            ->where('activities.data.0.event', 'updated')
            ->where('activities.data.0.causer', 'Ada')
            ->where('activities.data.0.subject', 'Alloys')
            ->where('activities.data.0.changes.0.field', 'Name')
            ->where('activities.data.0.changes.0.old', 'Metals')
            ->where('activities.data.0.changes.0.new', 'Alloys')
        );
});
