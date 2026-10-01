<?php

namespace App\Models; // reflete a pasta models/

use App\Config\Conexao; // importa a classe de conexão que já fizemos
use PDO;                 // usada aqui só para a constante PDO::FETCH_ASSOC

class Doadora {

    // "?string" = aceita string OU null, pois esses campos não são NOT NULL no banco
    // ": int" = esse método sempre devolve um número inteiro (o ID gerado)
    // "?int $idRequisitos = null" = parâmetro NOVO, opcional (valor padrão null) —
    // quem já chamava criar() com 5 argumentos continua funcionando sem mudar nada.
    // É usado só quando a doadora nasce de uma aprovação (RequisitosSaude::aprovar),
    // para já registrar o elo de rastreabilidade com a solicitação original.
    public function criar(
        string $nome,
        string $cpf,
        ?string $cartaoSus,
        ?string $telefone,
        ?string $endereco,
        ?int $idRequisitos = null
    ): int {

        $pdo = Conexao::conectar(); // pega a conexão (reaproveitada, graças ao Singleton)

        // SQL com "placeholders" (:nome, :cpf...) em vez de valores colados na string
        $sql = "INSERT INTO DOADORA (nome, cpf, cartao_sus, telefone, endereco, id_requisitos)
                VALUES (:nome, :cpf, :cartao_sus, :telefone, :endereco, :id_requisitos)";

        $stmt = $pdo->prepare($sql); // "prepara" o comando, ainda sem executar

        // executa de fato, ligando cada :placeholder ao valor real correspondente
        $stmt->execute([
            'nome' => $nome,
            'cpf' => $cpf,
            'cartao_sus' => $cartaoSus,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'id_requisitos' => $idRequisitos
        ]);

        // pega o ID que o AUTO_INCREMENT acabou de gerar para esse novo registro
        // (int) converte de string (formato padrão de retorno) para número inteiro
        return (int) $pdo->lastInsertId();
    }

    // "array|false" = pode devolver um array associativo (achou) ou false (não achou)
    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $sql = "SELECT * FROM DOADORA WHERE id_doadora = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]); // só um placeholder aqui, o ID buscado

        // fetch() pega UMA linha do resultado; FETCH_ASSOC devolve como array associativo
        // (ex: ['id_doadora' => 1, 'nome' => 'Maria', ...])
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ": bool" = devolve true (deu certo) ou false (falhou), vindo direto do execute()
    public function atualizar(int $id, string $nome, string $cpf, ?string $cartaoSus, ?string $telefone,
    ?string $endereco): bool {

        $pdo = Conexao::conectar();

        // SET define quais campos mudam; WHERE é OBRIGATÓRIO,
        // senão a atualização valeria para TODAS as linhas da tabela
        $sql = "UPDATE DOADORA
                SET nome = :nome, 
                cpf = :cpf, 
                cartao_sus = :cartao_sus, 
                telefone = :telefone, 
                endereco = :endereco

                WHERE id_doadora = :id";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            'nome' => $nome,
            'cpf' => $cpf,
            'cartao_sus' => $cartaoSus,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'id' => $id // placeholder usado no WHERE, garante que só ESSE registro muda
        ]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        // de novo, o WHERE é essencial — sem ele, apagaria a tabela inteira
        $sql = "DELETE FROM DOADORA WHERE id_doadora = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }
}
