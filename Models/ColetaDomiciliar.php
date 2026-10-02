<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

class ColetaDomiciliar {

    public function criar(
        int $idDoadora,
        ?int $idRetirada,
        string $dataVisita,
        string $zonaRegiao,
        ?float $volumeMl,
        ?float $temperaturaCaixa
    ): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO COLETA_DOMICILIAR
                    (id_doadora, id_retirada, data_visita, zona_regiao, volume_ml, temperatura_caixa)
                VALUES
                    (:id_doadora, :id_retirada, :data_visita, :zona_regiao, :volume_ml, :temperatura_caixa)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_doadora' => $idDoadora,
            'id_retirada' => $idRetirada,
            'data_visita' => $dataVisita,
            'zona_regiao' => $zonaRegiao,
            'volume_ml' => $volumeMl,
            'temperatura_caixa' => $temperaturaCaixa
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM COLETA_DOMICILIAR WHERE id_coleta = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM COLETA_DOMICILIAR WHERE id_coleta = :id");
        return $stmt->execute(['id' => $id]);
    }
}
