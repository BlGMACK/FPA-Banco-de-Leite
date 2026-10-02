<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;


class AnaliseQualidade {

    public function criar(int $idFrascoPast, ?float $crematocrito, ?float $dornic): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO ANALISE_QUALIDADE (id_frasco_past, crematocrito, dornic)
                VALUES (:id_frasco_past, :crematocrito, :dornic)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_frasco_past' => $idFrascoPast,
            'crematocrito' => $crematocrito,
            'dornic' => $dornic
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM ANALISE_QUALIDADE WHERE id_analise = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorFrasco(int $idFrascoPast): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM ANALISE_QUALIDADE WHERE id_frasco_past = :id_frasco_past");
        $stmt->execute(['id_frasco_past' => $idFrascoPast]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM ANALISE_QUALIDADE WHERE id_analise = :id");
        return $stmt->execute(['id' => $id]);
    }
}
