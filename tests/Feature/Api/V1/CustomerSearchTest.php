<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

function searchTestCustomer(string $firstName, string $lastName, string $companyName, string $email, string $phone): Customer
{
    $person = Person::create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
    ]);

    return Customer::create(['person_id' => $person->id, 'company_name' => $companyName]);
}

it('finds a customer by company name', function () {
    searchTestCustomer('Ana', 'Smith', 'Acme Bakery', 'ana@example.test', '555-1000');
    Sanctum::actingAs($this->cashier, ['*']);
    $response = $this->getJson('/api/v1/customers?q=Acme')->assertOk();

    expect(collect($response->json('data'))->pluck('company_name'))->toContain('Acme Bakery');
});

it('finds a customer by person name, email, or phone', function () {
    searchTestCustomer('Priya', 'Nair', 'Nair Consulting', 'priya.nair@example.test', '555-2000');
    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson('/api/v1/customers?q=Priya')->assertOk()
        ->assertJsonFragment(['company_name' => 'Nair Consulting']);
    $this->getJson('/api/v1/customers?q=priya.nair@example.test')->assertOk()
        ->assertJsonFragment(['company_name' => 'Nair Consulting']);
    $this->getJson('/api/v1/customers?q=555-2000')->assertOk()
        ->assertJsonFragment(['company_name' => 'Nair Consulting']);
});

it('clamps per_page=0 instead of dividing by zero', function () {
    searchTestCustomer('Ana', 'Smith', 'Acme Bakery', 'ana@example.test', '555-1000');
    Sanctum::actingAs($this->cashier, ['*']);
    $this->getJson('/api/v1/customers?per_page=0')->assertOk();
});

it('requires customers.view to search', function () {
    $noAbilities = User::factory()->create();
    Sanctum::actingAs($noAbilities, ['*']);
    $this->getJson('/api/v1/customers?q=anything')->assertForbidden();
});
