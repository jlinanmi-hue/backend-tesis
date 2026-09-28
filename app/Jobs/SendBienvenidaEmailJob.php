<?php

namespace App\Jobs;

use App\Mail\BienvenidaPersonalMail;
use App\Models\Empleado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBienvenidaEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Empleado $empleado,
        public string $passwordInicial,
        public string $fechaIngresoFormatted
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->empleado->EmpleadoCorreo)
                ->send(new BienvenidaPersonalMail(
                    $this->empleado,
                    $this->passwordInicial,
                    $this->fechaIngresoFormatted
                ));

            Log::info("Correo de bienvenida enviado exitosamente a: {$this->empleado->EmpleadoCorreo}");
        } catch (\Throwable $e) {
            Log::error("Error al enviar correo de bienvenida a {$this->empleado->EmpleadoCorreo}: " . $e->getMessage());
            throw $e;
        }
    }
}
