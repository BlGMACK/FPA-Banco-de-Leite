<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Banco de Leite Humano</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="blh-app">
    <a href="#conteudo" class="visually-hidden-focusable position-absolute top-0 start-0 z-3 m-2 p-2 bg-white border rounded">Pular para o conteúdo</a>
    <header class="blh-topo blh-topo--interno">
        <div class="blh-wrap blh-topo__linha">
            <a class="blh-marca" href="{{ route('dashboard') }}">
                <svg viewBox="0 0 200 250" aria-hidden="true" focusable="false"><path fill="#e8a317" d="M100 6C100 6 22 98 22 158a78 78 0 0 0 156 0C178 98 100 6 100 6Z"/></svg>
                <span>Banco de Leite Humano</span>
            </a>
            <button class="blh-topo__acesso blh-topo__menu" type="button" data-bs-toggle="collapse" data-bs-target="#navegacao" aria-controls="navegacao" aria-expanded="false">Menu</button>
        </div>
    </header>
    <div class="blh-interno">
        @include('layouts.navigation')
        <div class="blh-principal">
            @isset($header)
                <header class="blh-cabecalho"><div class="container">{{ $header }}</div></header>
            @endisset
            <main id="conteudo" tabindex="-1">{{ $slot }}</main>
        </div>
    </div>
</body>
</html>
