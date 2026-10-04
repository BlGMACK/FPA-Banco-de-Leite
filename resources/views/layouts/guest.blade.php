<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Banco de Leite Humano</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="blh-auth mx-auto">
            <a class="d-block text-center text-decoration-none fw-semibold fs-4 mb-4" href="{{ url('/') }}">Banco de Leite Humano</a>
            <div class="card shadow-sm"><div class="card-body p-4">{{ $slot }}</div></div>
        </div>
    </main>
</body>
</html>
