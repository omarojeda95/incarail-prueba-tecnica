<?php

namespace Tests\Unit\Infraestructura;

use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Infraestructura\FootballData\PresupuestoVentanaDeslizante;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PresupuestoVentanaDeslizanteTest extends TestCase
{
    private PresupuestoVentanaDeslizante $presupuesto;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 12:00:00');
        $this->presupuesto = new PresupuestoVentanaDeslizante(new Repository(new ArrayStore), maximo: 10, ventanaSegundos: 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function si_otro_proceso_tiene_el_contador_se_responde_sin_cupo_y_no_con_un_error(): void
    {
        $cache = new Repository(new ArrayStore);
        $presupuesto = new PresupuestoVentanaDeslizante($cache, maximo: 10, ventanaSegundos: 60, esperaLockSegundos: 0);
        $cache->lock('football-data:presupuesto:lock', 10)->get();

        try {
            $presupuesto->reservar();
            $this->fail('Debía responder sin cupo.');
        } catch (LimiteDePeticionesAlcanzado $e) {
            $this->assertGreaterThan(0, $e->reintentarEn);
        }
    }

    #[Test]
    public function permite_hasta_el_maximo_de_peticiones_por_ventana(): void
    {
        $this->gastar(10);

        $this->assertSame(0, $this->presupuesto->restantes());
    }

    #[Test]
    public function rechaza_la_peticion_que_excede_el_limite_indicando_cuanto_esperar(): void
    {
        $this->gastar(10);
        Carbon::setTestNow(now()->addSeconds(15));

        try {
            $this->presupuesto->reservar();
            $this->fail('La petición 11 de la ventana debió rechazarse.');
        } catch (LimiteDePeticionesAlcanzado $e) {
            $this->assertSame(45, $e->reintentarEn);
        }
    }

    #[Test]
    public function la_ventana_se_desliza_en_vez_de_reiniciarse_de_golpe(): void
    {
        $this->gastar(5);
        Carbon::setTestNow(now()->addSeconds(30));
        $this->gastar(5);

        Carbon::setTestNow(now()->addSeconds(31)); // las cinco primeras ya tienen más de 60 s

        $this->assertSame(5, $this->presupuesto->restantes());
        $this->gastar(5);
        $this->assertSame(0, $this->presupuesto->restantes());
    }

    #[Test]
    public function se_puede_agotar_cuando_el_proveedor_lo_indica(): void
    {
        $this->presupuesto->agotarPor(20);

        $this->assertSame(0, $this->presupuesto->restantes());
        $this->assertSame(20, $this->presupuesto->segundosParaReintentar());

        Carbon::setTestNow(now()->addSeconds(21));
        $this->presupuesto->reservar();
        $this->assertSame(9, $this->presupuesto->restantes());
    }

    #[Test]
    public function la_espera_es_cero_mientras_quede_cupo(): void
    {
        $this->gastar(3);

        $this->assertSame(0, $this->presupuesto->segundosParaReintentar());
        $this->assertSame(7, $this->presupuesto->restantes());
    }

    private function gastar(int $peticiones): void
    {
        for ($i = 0; $i < $peticiones; $i++) {
            $this->presupuesto->reservar();
        }
    }
}
