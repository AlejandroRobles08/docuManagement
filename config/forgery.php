<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Umbrales del analizador de posible falsificación
    |--------------------------------------------------------------------------
    |
    | ForgeryAnalyzer combina varias heurísticas locales (ver app/Services/Forgery)
    | en una puntuación de 0 a 100. Estos umbrales deciden a qué nivel de riesgo
    | (bajo / medio / alto) corresponde esa puntuación. Un riesgo "alto" impide
    | guardar el documento por completo: debe cancelarse y sustituirse por un
    | archivo distinto (ver App\Enums\RiskLevel::blocksUpload()).
    |
    | IMPORTANTE: estas heurísticas NO son una verificación forense ni legal de
    | autenticidad. Solo señalan indicios (metadatos de edición, archivos
    | repetidos, compresión inconsistente) para que una persona los revise.
    |
    */

    'medium_threshold' => (int) env('FORGERY_MEDIUM_THRESHOLD', 50),

    'high_threshold' => (int) env('FORGERY_HIGH_THRESHOLD', 60),

    /*
    |--------------------------------------------------------------------------
    | Vida útil de los archivos temporales de "revisión"
    |--------------------------------------------------------------------------
    |
    | Cuando el staff sube un documento, se analiza y se guarda temporalmente
    | mientras se revisa el resultado (paso "Analizar" -> "Confirmar y guardar").
    | Si nunca se confirma (el usuario cierra la pestaña, por ejemplo), el
    | comando `app:prune-stale-uploads` (programado a diario) borra los que
    | llevan más de este número de horas sin confirmarse.
    |
    */

    'stale_upload_hours' => (int) env('FORGERY_STALE_UPLOAD_HOURS', 24),

];
