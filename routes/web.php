<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');

Route::view('/admin', 'admin')->name('admin.dashboard');

Route::get('/docs/openapi.yaml', function () {
    abort_if(app()->environment('production'), 404);

    return response()->file(base_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml']);
});

Route::get('/docs/api', function () {
    abort_if(app()->environment('production'), 404);

    return response(<<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AutoMind API</title><link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head><body><div id="swagger-ui"></div><script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script><script>SwaggerUIBundle({url:'/docs/openapi.yaml',dom_id:'#swagger-ui',deepLinking:true,persistAuthorization:true});</script></body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
});
