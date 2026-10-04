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
    @include('layouts.navigation')
    @isset($header)
        <header class="bg-white border-bottom py-3"><div class="container">{{ $header }}</div></header>
    @endisset
    <main>{{ $slot }}</main>
</body>
</html>
