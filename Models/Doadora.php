<?php

namespace App\Models;

use App\Config\Conexao;

class Doadora {

    public function criar(
        string $nome,
        string $cpf,
        ?string $cartaoSus,
        ?string $telefone,
        ?string $endereco,
        ?int $idRequisitos = null
    ): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO DOADORA (nome, cpf, cartao_sus, telefone, endereco, id_requisitos)
                VALUES (:nome, :cpf, :cartao_sus, :telefone, :endereco, :id_requisitos)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'nome' => $nome,
            'cpf' => $cpf,
            'cartao_sus' => $cartaoSus,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'id_requisitos' => $idRequisitos
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $sql = "SELECT * FROM DOADORA WHERE id_doadora = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function atualizar(int $id, string $nome, string $cpf, ?string $cartaoSus, ?string $telefone,
    ?string $endereco): bool {

        $pdo = Conexao::conectar();


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
            'id' => $id
        ]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $sql = "DELETE FROM DOADORA WHERE id_doadora = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }
}
