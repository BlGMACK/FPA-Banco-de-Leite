<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

// O recém-nascido (RN) que vai receber o leite, vinculado ao hospital.
// Não tem nenhuma ligação direta com DOADORA — só se conecta a ela
// indiretamente, através da PRESCRICAO.
class Receptor {

    public function criar(string $nomeRn, string $hospital, ?string $setorUti): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO RECEPTOR (nome_rn, hospital, setor_uti)
                VALUES (:nome_rn, :hospital, :setor_uti)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nome_rn' => $nomeRn,
            'hospital' => $hospital,
            'setor_uti' => $setorUti
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM RECEPTOR WHERE id_receptor = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function atualizar(int $id, string $nomeRn, string $hospital, ?string $setorUti): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE RECEPTOR
                SET nome_rn = :nome_rn, hospital = :hospital, setor_uti = :setor_uti
                WHERE id_receptor = :id";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            'nome_rn' => $nomeRn,
            'hospital' => $hospital,
            'setor_uti' => $setorUti,
            'id' => $id
        ]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM RECEPTOR WHERE id_receptor = :id");
        return $stmt->execute(['id' => $id]);
    }
}
