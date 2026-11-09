<?php

namespace Tests\Feature;

use App\Mail\GenericMail;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Shop;
use App\Models\Slot;
use App\Models\User;
use App\Support\Countries;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payment.provider' => 'mock']);
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        Mail::fake();
        Storage::fake('public');
    }

    public function test_welcome_email_is_sent_when_an_account_is_created(): void
    {
        $this->post('/creer-un-compte', [
            'name' => 'Kofi Mensah', 'email' => 'kofi@example.com', 'country' => 'TG', 'phone' => '90123456',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertRedirect(route('reserve'));

        Mail::assertSent(GenericMail::class, 1);
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('kofi@example.com')
            && str_contains($m->mailSubject, 'compte est créé')
            && collect($m->lines)->contains(fn ($l) => str_contains($l, 'Kofi Mensah'))
            && collect($m->lines)->contains(fn ($l) => str_contains($l, 'package'))
            && $m->action['url'] === route('reserve'));

        // Un refus de validation n'envoie aucun e-mail de plus.
        $this->post('/logout');
        $this->post('/creer-un-compte', ['name' => 'X', 'email' => 'kofi@example.com'])->assertSessionHasErrors();
        Mail::assertSent(GenericMail::class, 1);
    }

    private function slot(int $i = 0): Slot
    {
        return Slot::where('status', 'DISPONIBLE')->orderBy('starts_at')->orderBy('position')->skip($i)->first();
    }

    /** Formulaire complet d'un vendeur sans compte. */
    private function form(Slot $slot, array $over = []): array
    {
        return array_merge([
            'package_id' => Package::where('code', 'DECOUVERTE')->value('id'), 'slot_id' => $slot->id, 'mode' => 'SUR_PLACE',
            'full_name' => 'Kofi Mensah', 'phone' => '+22890123456', 'email' => 'kofi@example.com', 'shop_name' => 'Chez Kofi',
            'country' => 'TG', 'city' => 'Kara', 'activity_domain' => 'Artisanat', 'activity_description' => 'Sculptures en bois',
            'tiktok' => '', 'host_notes' => '', 'consent' => '1',
            'products' => [['name' => 'Masque', 'price' => 5000, 'live_price' => 4000, 'quantity' => 2, 'photo' => UploadedFile::fake()->image('m.jpg')]],
        ], $over);
    }

    private function vendor(array $over = []): User
    {
        $u = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'name' => $over['name'] ?? 'Vendeur Test']);
        $u->forceFill(['role' => 'VENDOR', 'is_active' => true, 'country' => $over['country'] ?? 'TG', 'phone_e164' => $over['phone_e164'] ?? '+22890000000'])->save();

        return $u;
    }

    /** Boutique avec un produit (et sa photo) déjà enregistrés dans le catalogue. */
    private function shopWithCatalog(User $u, string $name = 'Aya Shop'): Shop
    {
        $shop = $u->shops()->create(['name' => $name, 'city' => 'Lomé', 'activity_domain' => 'Mode et vêtements', 'activity_description' => 'Robes wax', 'tiktok' => 'aya_shop']);
        Storage::disk('public')->put('products/catalog.webp', 'photo-catalogue');
        $shop->products()->create(['name' => 'Robe wax', 'price' => 15000, 'live_price' => 12000, 'quantity' => 5, 'photo_path' => 'products/catalog.webp', 'position' => 0]);

        return $shop->load('products');
    }

    public function test_registration_creates_a_vendor_and_logs_in(): void
    {
        $this->get('/creer-un-compte')->assertOk()->assertInertia(fn ($p) => $p->component('account/register')->has('countries'));

        $this->post('/creer-un-compte', [
            'name' => 'Aya Koffi', 'email' => 'Aya@Example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
            'country' => 'FR', 'phone' => '+33 6 12 34 56 78',
        ])->assertRedirect('/reserver');

        $u = User::where('email', 'aya@example.com')->first();
        $this->assertSame('VENDOR', $u->role);
        $this->assertSame('+33612345678', $u->phone_e164);
        $this->assertSame('FR', $u->country);
        $this->assertAuthenticatedAs($u);
    }

    public function test_registration_validation_in_french(): void
    {
        $ok = ['name' => 'A B', 'email' => 'a@example.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'country' => 'TG', 'phone' => '90123456'];
        $this->post('/creer-un-compte', [...$ok, 'password' => 'court', 'password_confirmation' => 'court'])->assertSessionHasErrors('password');
        $this->post('/creer-un-compte', [...$ok, 'password_confirmation' => 'autre'])->assertSessionHasErrors(['password' => 'Les deux mots de passe ne correspondent pas.']);
        $this->post('/creer-un-compte', [...$ok, 'country' => 'XX'])->assertSessionHasErrors('country');
        $this->post('/creer-un-compte', [...$ok, 'phone' => '12'])->assertSessionHasErrors(['phone' => 'Numéro WhatsApp invalide.']);
        $this->post('/creer-un-compte', [...$ok, 'name' => ''])->assertSessionHasErrors(['name' => 'Ce champ est obligatoire.']);

        $this->post('/creer-un-compte', $ok)->assertRedirect();
        auth()->logout();
        $this->post('/creer-un-compte', $ok)->assertSessionHasErrors(['email' => 'Un compte existe déjà avec cet e-mail : connectez-vous.']);
    }

    public function test_login_failure_message_is_french_and_vendor_lands_on_account(): void
    {
        $u = $this->vendor();
        $this->post('/login', ['email' => $u->email, 'password' => 'faux'])->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/mon-compte');
        $this->get('/mon-compte')->assertOk()->assertInertia(fn ($p) => $p->component('account/home'));
    }

    public function test_vendor_cannot_reach_staff_areas_and_guests_cannot_reach_account(): void
    {
        $this->get('/mon-compte')->assertRedirect('/login');
        $v = $this->vendor();
        foreach (['/admin', '/admin/reservations', '/equipe'] as $url) {
            $this->actingAs($v)->get($url)->assertForbidden();
        }
        $staff = User::where('role', 'INTERVIEWER')->first();
        $this->actingAs($staff)->get('/mon-compte')->assertForbidden();
    }

    public function test_guest_booking_still_works_without_account(): void
    {
        $this->post('/reserver', $this->form($this->slot()))->assertRedirect();
        $b = Booking::first();
        $this->assertNull($b->user_id);
        $this->assertNull($b->shop_id);
        $this->assertSame(0, User::where('role', 'VENDOR')->count());
        $this->assertSame(0, Shop::count());
        $this->assertGuest();
    }

    public function test_account_created_while_booking_also_creates_the_shop_and_its_catalog(): void
    {
        $this->post('/reserver', $this->form($this->slot(), ['create_account' => '1', 'password' => 'motdepasse1']))->assertRedirect();

        $b = Booking::first();
        $u = User::where('email', 'kofi@example.com')->first();
        $this->assertSame('VENDOR', $u->role);
        $this->assertSame($u->id, $b->user_id);
        $this->assertAuthenticatedAs($u);
        $this->assertSame('TG', $b->country);

        $shop = $u->shops()->first();
        $this->assertSame('Chez Kofi', $shop->name);
        $this->assertSame('Artisanat', $shop->activity_domain);
        $this->assertSame($shop->id, $b->shop_id);
        $this->assertSame(['Masque'], $shop->products->pluck('name')->all(), 'les produits sont mémorisés');
        $this->assertNotSame($b->products()->first()->photo_path, $shop->products->first()->photo_path, 'chacun garde sa propre copie de la photo');
        Storage::disk('public')->assertExists($shop->products->first()->photo_path);
    }

    public function test_account_creation_while_booking_needs_password_and_free_email(): void
    {
        $slot = $this->slot();
        $this->post('/reserver', $this->form($slot, ['create_account' => '1']))->assertSessionHasErrors('password');
        $this->assertSame(0, Booking::count());
        $this->assertSame('DISPONIBLE', $slot->fresh()->status);

        $u = $this->vendor();
        $this->post('/reserver', $this->form($slot, ['email' => $u->email, 'create_account' => '1', 'password' => 'motdepasse1']))
            ->assertSessionHasErrors(['email' => 'Un compte existe déjà avec cet e-mail : connectez-vous.']);
        $this->assertSame(0, Booking::count());
    }

    public function test_returning_vendor_books_with_saved_shop_without_retyping_anything(): void
    {
        $v = $this->vendor(['name' => 'Aya Koffi', 'phone_e164' => '+22890000001']);
        $shop = $this->shopWithCatalog($v);

        $this->actingAs($v)->get('/reserver')->assertOk()->assertInertia(fn ($p) => $p
            ->where('profile.name', 'Aya Koffi')
            ->has('shops', 1)->where('shops.0.name', 'Aya Shop')->has('shops.0.products', 1)->where('shops.0.products.0.name', 'Robe wax'));

        // Rien d'autre que le pack, le créneau, le pays, la boutique et les produits repris du catalogue.
        $this->post('/reserver', [
            'package_id' => Package::where('code', 'DECOUVERTE')->value('id'), 'slot_id' => $this->slot()->id, 'mode' => 'SUR_PLACE', 'country' => 'TG',
            'shop_id' => $shop->id, 'consent' => '1',
            'products' => [['source_id' => $shop->products->first()->id, 'name' => 'Robe wax', 'price' => 15000, 'live_price' => 11000, 'quantity' => 3]],
        ])->assertRedirect();

        $b = Booking::first();
        $this->assertSame($v->id, $b->user_id);
        $this->assertSame($shop->id, $b->shop_id);
        $this->assertSame('Aya Koffi', $b->full_name);
        $this->assertSame('+22890000001', $b->phone);
        $this->assertSame($v->email, $b->email);
        $this->assertSame('Aya Shop', $b->shop_name);
        $this->assertSame('Robes wax', $b->activity_description);
        $this->assertSame('aya_shop', $b->tiktok);

        $p = $b->products()->first();
        $this->assertNotNull($p->photo_path, 'la photo du catalogue est reprise');
        $this->assertNotSame('products/catalog.webp', $p->photo_path);
        Storage::disk('public')->assertExists($p->photo_path);

        // Le catalogue reflète la dernière réservation (prix live et quantité modifiés).
        $this->assertSame(11000, $shop->fresh()->products()->first()->live_price);
        $this->assertSame(1, Shop::count());
    }

    public function test_booking_without_shop_id_creates_a_new_shop_for_the_vendor(): void
    {
        $v = $this->vendor();
        $this->shopWithCatalog($v);

        $this->actingAs($v)->post('/reserver', $this->form($this->slot(), ['shop_name' => 'Deuxième boutique', 'full_name' => 'ignoré', 'email' => 'ignore@example.com']))->assertRedirect();

        $this->assertSame(2, $v->shops()->count());
        $b = Booking::first();
        $this->assertSame('Deuxième boutique', $b->shop->name);
        $this->assertSame($v->email, $b->email, 'les coordonnées viennent du compte, pas du formulaire');
        $this->assertSame('Vendeur Test', $b->full_name);
    }

    public function test_vendor_cannot_use_another_vendors_shop_or_photos(): void
    {
        $owner = $this->vendor();
        $shop = $this->shopWithCatalog($owner);
        $thief = $this->vendor();
        $mine = $thief->shops()->create(['name' => 'Ma boutique', 'city' => 'Kara', 'activity_domain' => 'Services', 'activity_description' => 'Coiffure', 'tiktok' => null]);

        $base = ['package_id' => Package::where('code', 'DECOUVERTE')->value('id'), 'slot_id' => $this->slot()->id, 'mode' => 'A_DISTANCE', 'country' => 'TG', 'consent' => '1'];
        $this->actingAs($thief)->post('/reserver', $base + ['shop_id' => $shop->id, 'products' => [['name' => 'X', 'price' => 1, 'live_price' => 1, 'quantity' => 1]]])->assertNotFound();

        // Avec sa propre boutique, il ne peut pas reprendre la photo d'un produit d'une autre boutique.
        $this->actingAs($thief)->post('/reserver', $base + ['shop_id' => $mine->id, 'products' => [['source_id' => $shop->products->first()->id, 'name' => 'Vol', 'price' => 1, 'live_price' => 1, 'quantity' => 1]]])->assertRedirect();
        $this->assertNull(Booking::first()->products()->first()->photo_path);
    }

    public function test_tracking_edit_updates_the_catalog_too(): void
    {
        $v = $this->vendor();
        $shop = $this->shopWithCatalog($v);
        $this->actingAs($v)->post('/reserver', [
            'package_id' => Package::where('code', 'DECOUVERTE')->value('id'), 'slot_id' => $this->slot()->id, 'mode' => 'SUR_PLACE', 'country' => 'TG',
            'shop_id' => $shop->id, 'consent' => '1', 'products' => [['source_id' => $shop->products->first()->id, 'name' => 'Robe wax', 'price' => 15000, 'live_price' => 12000, 'quantity' => 5]],
        ]);
        $b = Booking::first();
        $b->update(['status' => 'CONFIRMEE']);
        $b->slot->update(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addMinutes(15)]); // loin du délai de 24 h, quelle que soit l'heure du test

        $this->actingAs($v)->post("/suivi/{$b->tracking_token}", ['products' => [
            ['id' => $b->products()->first()->id, 'name' => 'Robe wax XL', 'price' => 16000, 'live_price' => 13000, 'quantity' => 2],
            ['name' => 'Sac', 'price' => 8000, 'live_price' => 6000, 'quantity' => 4],
        ]])->assertSessionHas('success');

        $this->assertSame(['Robe wax XL', 'Sac'], $shop->fresh()->products()->pluck('name')->all());
    }

    public function test_vendor_updates_contact_details(): void
    {
        $v = $this->vendor();
        $this->actingAs($v)->post('/mon-compte', ['name' => 'Nouveau Nom', 'country' => 'BJ', 'phone' => '+229 97 00 00 00'])->assertSessionHas('success');

        $v->refresh();
        $this->assertSame('Nouveau Nom', $v->name);
        $this->assertSame('BJ', $v->country);
        $this->assertSame('+22997000000', $v->phone_e164);
        $this->actingAs($v)->post('/mon-compte', ['name' => '', 'country' => 'XX', 'phone' => 'x'])->assertSessionHasErrors(['name', 'country', 'phone']);
    }

    public function test_vendor_manages_shops_and_cannot_touch_anothers(): void
    {
        $v = $this->vendor();
        $data = ['name' => 'Boutique A', 'city' => 'Lomé', 'activity_domain' => 'Beauté et cosmétiques', 'activity_description' => 'Soins naturels', 'tiktok' => '@boutique_a'];

        $this->actingAs($v)->post('/mon-compte/boutiques', $data)->assertSessionHas('success');
        $shop = $v->shops()->first();
        $this->assertSame('boutique_a', $shop->tiktok);

        $this->actingAs($v)->post("/mon-compte/boutiques/{$shop->id}", [...$data, 'name' => 'Boutique A2'])->assertSessionHas('success');
        $this->assertSame('Boutique A2', $shop->fresh()->name);
        $this->actingAs($v)->post('/mon-compte/boutiques', [...$data, 'activity_domain' => 'Armes'])->assertSessionHasErrors('activity_domain');

        $other = $this->vendor();
        $this->actingAs($other)->post("/mon-compte/boutiques/{$shop->id}", $data)->assertNotFound();
        $this->actingAs($other)->delete("/mon-compte/boutiques/{$shop->id}")->assertNotFound();

        $this->actingAs($v)->get('/mon-compte')->assertInertia(fn ($p) => $p->has('shops', 1)->where('shops.0.name', 'Boutique A2'));
        $this->actingAs($v)->delete("/mon-compte/boutiques/{$shop->id}")->assertSessionHas('success');
        $this->assertSame(0, $v->shops()->count());
    }

    public function test_deleting_a_shop_keeps_past_bookings(): void
    {
        $v = $this->vendor();
        $shop = $this->shopWithCatalog($v);
        $this->actingAs($v)->post('/reserver', [
            'package_id' => Package::where('code', 'DECOUVERTE')->value('id'), 'slot_id' => $this->slot()->id, 'mode' => 'SUR_PLACE', 'country' => 'TG',
            'shop_id' => $shop->id, 'consent' => '1', 'products' => [['source_id' => $shop->products->first()->id, 'name' => 'Robe wax', 'price' => 1, 'live_price' => 1, 'quantity' => 1]],
        ]);
        $this->actingAs($v)->delete("/mon-compte/boutiques/{$shop->id}");

        $b = Booking::first();
        $this->assertNotNull($b);
        $this->assertNull($b->shop_id);
        $this->assertSame('Aya Shop', $b->shop_name, 'la réservation garde les informations de la boutique');
        $this->assertSame(1, $b->products()->count());
    }

    public function test_on_site_passage_is_only_possible_from_togo(): void
    {
        $slot = $this->slot();
        $this->post('/reserver', $this->form($slot, ['country' => 'FR', 'mode' => 'SUR_PLACE']))->assertSessionHasErrors('mode');
        $this->assertSame(0, Booking::count());
        $this->assertSame('DISPONIBLE', $slot->fresh()->status);

        $this->post('/reserver', $this->form($slot, ['country' => 'FR', 'mode' => 'A_DISTANCE', 'phone' => '+33612345678']))->assertRedirect();
        $this->assertSame('FR', Booking::first()->country);

        $this->post('/reserver', $this->form($this->slot(1), ['country' => 'XX']))->assertSessionHasErrors('country');
    }

    public function test_country_list_is_consistent(): void
    {
        $list = collect(Countries::all());
        $this->assertSame('TG', $list->first()['code'], 'le Togo est en tête de liste');
        $this->assertSame('+228', $list->first()['dial']);
        $this->assertSame(count($list), $list->pluck('code')->unique()->count());
        $this->assertTrue($list->every(fn ($c) => preg_match('/^[A-Z]{2}$/', $c['code']) && preg_match('/^\+\d{1,4}$/', $c['dial'])));
    }
}
