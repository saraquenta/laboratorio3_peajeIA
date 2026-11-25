<?php

return [
    // Endpoint unificado de inferencia (RunPod o Colab). Se puede sobrescribir desde la pagina Estado.
    'inference' => [
        'url' => env('INFERENCE_URL', ''),
        'token' => env('INFERENCE_TOKEN', ''),
        'timeout' => (int) env('INFERENCE_TIMEOUT', 30),
    ],

    // Un frame solo registra un paso si el OCR supera esta confianza...
    'confianza_minima_ocr' => (float) env('PEAJE_CONFIANZA_MINIMA_OCR', 0.40),
    // ...y si la misma placa no paso por la misma caseta en los ultimos N segundos (evita duplicados a 2-5 fps)
    'ventana_duplicados_s' => (int) env('PEAJE_VENTANA_DUPLICADOS', 60),

    // Lista blanca inicial (ademas del docente que siempre se crea como administrador)
    // ADMIN_EMAIL = tu cuenta de Google; SEED_ADMIN_EMAILS / SEED_OPERADOR_EMAILS aceptan varias separadas por coma
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', env('ADMIN_EMAIL', '').','.env('SEED_ADMIN_EMAILS', ''))))),
    'operador_emails' => array_values(array_filter(array_map('trim', explode(',', env('OPERADOR_EMAIL', '').','.env('SEED_OPERADOR_EMAILS', ''))))),

    // Google OAuth: IDs de cliente aceptados para tokens de la app movil (web client ID y/o Android client ID)
    'google_client_ids' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', env('GOOGLE_CLIENT_ID', ''))))),

    // Inicio de sesion de desarrollo SIN Google: solo funciona con APP_ENV=local y AUTH_DEV_LOGIN=true.
    // En produccion queda deshabilitado (login exclusivamente con Google).
    'dev_login' => env('AUTH_DEV_LOGIN', false) && env('APP_ENV') === 'local',

    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'us2'),
    ],

    'hardware' => [
        'token' => env('HARDWARE_TOKEN', ''),
        'webhook_url' => env('HARDWARE_WEBHOOK_URL', ''),
    ],

    // Agente conversacional: los DATOS siempre salen de la BD; el LLM solo redacta la respuesta.
    // AGENTE_PROVEEDOR: gemini (por defecto, clave gratuita en aistudio.google.com) | anthropic | ninguno
    'agente' => [
        'proveedor' => env('AGENTE_PROVEEDOR', 'gemini'),
        'gemini_api_key' => env('GEMINI_API_KEY', ''),
        'gemini_modelo' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY', ''),
        'modelo' => env('AGENTE_MODELO', 'claude-opus-5-5'),
    ],
];
