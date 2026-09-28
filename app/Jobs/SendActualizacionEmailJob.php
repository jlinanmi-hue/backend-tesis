<?php

namespace App\Jobs;

use App\Mail\ActualizacionPersonalMail;
use App\Models\Empleado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendActualizacionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param Empleado $empleado
     * @param array $cambios
     */
    public function __construct(
        public Empleado $empleado,
        public array $cambios
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->empleado->EmpleadoCorreo)
                ->send(new ActualizacionPersonalMail(
                    $this->empleado,
                    $this->cambios
                ));

            Log::info("Correo de notificación de actualización enviado a: {$this->empleado->EmpleadoCorreo}");
        } catch (\Throwable $e) {
            Log::error("Error al enviar correo de actualización a {$this->empleado->EmpleadoCorreo}: " . $e->getMessage());
            throw $e;
        }
    }
}
