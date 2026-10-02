<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

class FichaTriagem {

    public function criar(
        int $idVisita,
        ?string $condicaoMoradia,
        ?string $rendaFamiliar,
        ?string $intercorrenciasGestacao,
        ?string $dadosBebe
    ): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO FICHA_TRIAGEM
                    (id_visita, condicao_moradia, renda_familiar, intercorrencias_gestacao, dados_bebe)
                VALUES
                    (:id_visita, :condicao_moradia, :renda_familiar, :intercorrencias_gestacao, :dados_bebe)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_visita' => $idVisita,
            'condicao_moradia' => $condicaoMoradia,
            'renda_familiar' => $rendaFamiliar,
            'intercorrencias_gestacao' => $intercorrenciasGestacao,
            'dados_bebe' => $dadosBebe
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM FICHA_TRIAGEM WHERE id_ficha = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorVisita(int $idVisita): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM FICHA_TRIAGEM WHERE id_visita = :id_visita");
        $stmt->execute(['id_visita' => $idVisita]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function revisar(int $id, bool $aprovada): bool {

        $pdo = Conexao::conectar();

        $status = $aprovada ? 'aprovada' : 'reprovada';

        $sql = "UPDATE FICHA_TRIAGEM SET status_revisao = :status WHERE id_ficha = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM FICHA_TRIAGEM WHERE id_ficha = :id");
        return $stmt->execute(['id' => $id]);
    }
}
