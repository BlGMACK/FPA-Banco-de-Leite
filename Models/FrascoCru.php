<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

class FrascoCru {

    public function criar(int $idColeta, string $classificacao): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO FRASCO_CRU (id_coleta, classificacao) VALUES (:id_coleta, :classificacao)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_coleta' => $idColeta,
            'classificacao' => $classificacao
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM FRASCO_CRU WHERE id_frasco_cru = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function marcarConformidade(int $id, bool $conforme, ?string $motivoDescarte = null): bool {

        $pdo = Conexao::conectar();

        $status = $conforme ? 'conforme' : 'nao_conforme';

        $sql = "UPDATE FRASCO_CRU
                SET status_conformidade = :status, motivo_descarte = :motivo
                WHERE id_frasco_cru = :id";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'motivo' => $conforme ? null : $motivoDescarte,
            'id' => $id
        ]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM FRASCO_CRU WHERE id_frasco_cru = :id");
        return $stmt->execute(['id' => $id]);
    }
}
