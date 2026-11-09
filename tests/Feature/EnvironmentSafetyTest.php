<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Les tests ne doivent jamais toucher une vraie base, le vrai agrégateur de paiement ni le vrai serveur d'e-mail. */
class EnvironmentSafetyTest extends TestCase
{
    public function test_tests_run_on_a_local_test_database_with_simulated_payment_and_mail(): void
    {
        $this->assertSame('testing', app()->environment());
        $db = config('database.connections.'.config('database.default'));
        $this->assertContains($db['host'], ['127.0.0.1', 'localhost'], 'les tests ne doivent jamais utiliser une base distante');
        $this->assertStringEndsWith('_test', $db['database'], 'les tests utilisent une base dédiée (…_test), jamais la base de développement ou de production');

        $this->assertSame('mock', config('services.payment.provider'), 'jamais le vrai agrégateur de paiement');
        $this->assertNotSame('live', config('services.payment.env'));
        $this->assertSame('array', config('mail.default'), 'jamais le vrai serveur d\'e-mail');
    }
}
