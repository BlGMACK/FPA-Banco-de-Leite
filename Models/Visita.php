<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

// Registra a visita assim que o atendente confirma, pelo WhatsApp, que há
// disponibilidade. Fica como "pendente" até o dia em que ela acontecer de fato.
class Visita {

    public function criar(int $idSolicitacao, string $dataVisita): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO VISITA (id_solicitacao, data_visita)
                VALUES (:id_solicitacao, :data_visita)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_solicitacao' => $idSolicitacao,
            'data_visita' => $dataVisita
        ]);

        // nota: marcar a SOLICITACAO original como "confirmada" é tarefa do
        // Controller, que vai chamar Solicitacao::atualizarStatus() em seguida —
        // o Model fica responsável só pela própria tabela, nunca por outras.
        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM VISITA WHERE id_visita = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // chamado pelo responsável que foi até a casa da doadora, assim que a
    // visita realmente acontece (ou precisa ser cancelada)
    public function atualizarStatus(int $id, string $status): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE VISITA SET status = :status WHERE id_visita = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM VISITA WHERE id_visita = :id");
        return $stmt->execute(['id' => $id]);
    }
}
