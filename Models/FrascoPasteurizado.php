<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

class FrascoPasteurizado {

    public function criar(int $idFrascoCru, string $dataPasteurizacao, ?float $volumeFinalMl): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO FRASCO_PASTEURIZADO (id_frasco_cru, data_pasteurizacao, volume_final_ml)
                VALUES (:id_frasco_cru, :data_pasteurizacao, :volume_final_ml)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_frasco_cru' => $idFrascoCru,
            'data_pasteurizacao' => $dataPasteurizacao,
            'volume_final_ml' => $volumeFinalMl
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM FRASCO_PASTEURIZADO WHERE id_frasco_past = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM FRASCO_PASTEURIZADO WHERE id_frasco_past = :id");
        return $stmt->execute(['id' => $id]);
    }
}
