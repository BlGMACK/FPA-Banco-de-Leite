<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banco de Leite Humano</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3">Banco de Leite Humano</h1>
                <p>Quer doar leite humano? Envie seus dados sem criar uma conta. A equipe do Banco de Leite analisará sua solicitação e entrará em contato.</p>
                <p><a class="btn btn-success" href="{{ route('solicitacoes.create') }}">Quero doar leite humano</a></p>

                <p><a href="{{ route('login') }}">Acesso da equipe</a></p>
            </div>
        </div>
    </main>
</body>

</html>
