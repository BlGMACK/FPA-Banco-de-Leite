<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste de papel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4">Acesso liberado</h1>
                <p class="mb-1">Esta página é restrita a: <strong>{{ $papel }}</strong></p>
                <p class="text-muted mb-0">
                    Logado como {{ auth()->user()->name }}
                    ({{ auth()->user()->role }})
                </p>
            </div>
        </div>
    </main>
</body>

</html>