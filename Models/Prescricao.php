<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

// O elo final da rastreabilidade: liga um frasco pasteurizado específico
// a um receptor específico. Quem distribui vê só a classificação do leite
// e o volume — não precisa (nem deveria) enxergar a doadora de origem.
class Prescricao {

    public function criar(int $idFrascoPast, int $idReceptor, ?float $volumePrescritoMl): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO PRESCRICAO (id_frasco_past, id_receptor, volume_prescrito_ml)
                VALUES (:id_frasco_past, :id_receptor, :volume_prescrito_ml)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_frasco_past' => $idFrascoPast,
            'id_receptor' => $idReceptor,
            'volume_prescrito_ml' => $volumePrescritoMl
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM PRESCRICAO WHERE id_prescricao = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // registrado no momento em que o leite realmente sai para o hospital
    public function registrarEntrega(int $id, string $dataEntrega): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE PRESCRICAO SET data_entrega = :data_entrega WHERE id_prescricao = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['data_entrega' => $dataEntrega, 'id' => $id]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM PRESCRICAO WHERE id_prescricao = :id");
        return $stmt->execute(['id' => $id]);
    }
}
