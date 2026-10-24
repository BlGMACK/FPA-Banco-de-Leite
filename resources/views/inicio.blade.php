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
                <p>Bem-vindo ao sistema.</p>
                <p><a class="btn btn-success" href="{{ route('solicitacoes.create') }}">Quero doar leite humano</a></p>

                <button class="btn btn-primary"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#mensagem"
                    aria-expanded="false"
                    aria-controls="mensagem">
                    Testar Bootstrap
                </button>

                <div class="collapse mt-3" id="mensagem">
                    <div class="alert alert-success mb-0">
                        Bootstrap funcionando!
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
