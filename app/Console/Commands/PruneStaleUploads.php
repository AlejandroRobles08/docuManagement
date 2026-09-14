<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:prune-stale-uploads')]
#[Description('Elimina archivos de documentos subidos que quedaron pendientes de confirmación y nunca se guardaron.')]
class PruneStaleUploads extends Command
{
    private const string DISK = 'local';

    private const string DIRECTORY = 'pending-uploads';

    /**
     * Cuando el staff sube un documento, se guarda temporalmente mientras se
     * revisa el resultado del análisis. Si esa revisión nunca se confirma
     * (se cierra la pestaña, se abandona el formulario, etc.), el archivo
     * queda huérfano en el disco. Este comando lo limpia.
     */
    public function handle(): void
    {
        $cutoff = now()->subHours((int) config('forgery.stale_upload_hours'));
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists(self::DIRECTORY)) {
            $this->info('No hay subidas pendientes que revisar.');

            return;
        }

        $deleted = 0;

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $cutoff->timestamp) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Archivos temporales eliminados: {$deleted}.");
    }
}
