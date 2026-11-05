<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Banco de Leite Humano</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="blh-interno">
    <a href="#conteudo" class="visually-hidden-focusable position-absolute top-0 start-0 z-3 m-2 p-2 bg-white border rounded">Pular para o conteúdo</a>
    @include('layouts.navigation')
    @isset($header)
        <header class="bg-white border-bottom py-3"><div class="container">{{ $header }}</div></header>
    @endisset
    <main id="conteudo" tabindex="-1">{{ $slot }}</main>
</body>
</html>
