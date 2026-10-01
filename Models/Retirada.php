<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

// Quando a mãe (já doadora aprovada) extrai leite em casa e avisa o banco
// por telefone, isso vira um aviso de retirada — antes mesmo de alguém ir
// buscar fisicamente. Puramente informativo até a coleta acontecer de fato.
class Retirada {

    public function criar(int $idDoadora, string $dataInformada, float $quantidadeInformadaMl): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO RETIRADA (id_doadora, data_informada, quantidade_informada_ml)
                VALUES (:id_doadora, :data_informada, :quantidade_informada_ml)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_doadora' => $idDoadora,
            'data_informada' => $dataInformada,
            'quantidade_informada_ml' => $quantidadeInformadaMl
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM RETIRADA WHERE id_retirada = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // útil para a equipe de coleta: "quais retiradas ainda precisam ser buscadas?"
    public function listarAguardandoColeta(): array {

        $pdo = Conexao::conectar();

        $stmt = $pdo->query("SELECT * FROM RETIRADA WHERE status = 'aguardando_coleta' ORDER BY data_informada ASC");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // chamado quando a COLETA_DOMICILIAR correspondente é registrada
    public function marcarComoColetada(int $id): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE RETIRADA SET status = 'coletado' WHERE id_retirada = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM RETIRADA WHERE id_retirada = :id");
        return $stmt->execute(['id' => $id]);
    }
}
