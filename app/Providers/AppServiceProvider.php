<?php

namespace App\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\EmpleadoRepositoryInterface::class,
            \App\Repositories\Eloquent\EmpleadoRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\UsuarioRepositoryInterface::class,
            \App\Repositories\Eloquent\UsuarioRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\CargoRepositoryInterface::class,
            \App\Repositories\Eloquent\CargoRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\ProveedorRepositoryInterface::class,
            \App\Repositories\Eloquent\ProveedorRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\CategoriaProductoRepositoryInterface::class,
            \App\Repositories\Eloquent\CategoriaProductoRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\UnidadesMedidaRepositoryInterface::class,
            \App\Repositories\Eloquent\UnidadesMedidaRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\CanalPedidoRepositoryInterface::class,
            \App\Repositories\Eloquent\CanalPedidoRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\ClienteRepositoryInterface::class,
            \App\Repositories\Eloquent\ClienteRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\ProductoUbiRepositoryInterface::class,
            \App\Repositories\Eloquent\ProductoUbiRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\AjusteInventarioRepositoryInterface::class,
            \App\Repositories\Eloquent\AjusteInventarioRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\OrdenCompraRepositoryInterface::class,
            \App\Repositories\Eloquent\OrdenCompraRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\PedidoRepositoryInterface::class,
            \App\Repositories\Eloquent\PedidoRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'sqlsrv') {
                $event->connection->getPdo()->exec('SET DATEFORMAT ymd;');
            }
        });
    }
}
