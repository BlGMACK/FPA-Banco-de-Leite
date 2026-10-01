<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;

// Primeira etapa do fluxo: o pedido inicial que o cliente faz no site,
// com dados de contato e a data desejada para a primeira visita.
class Solicitacao {

    public function criar(string $nome, string $telefone, string $endereco, string $dataDesejada): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO SOLICITACAO (nome, telefone, endereco, data_desejada)
                VALUES (:nome, :telefone, :endereco, :data_desejada)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nome' => $nome,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'data_desejada' => $dataDesejada
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM SOLICITACAO WHERE id_solicitacao = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // lista as solicitações que ainda não foram respondidas — útil para
    // a tela de quem analisa disponibilidade e entra em contato via WhatsApp
    public function listarPendentes(): array {

        $pdo = Conexao::conectar();

        $stmt = $pdo->query("SELECT * FROM SOLICITACAO WHERE status = 'pendente' ORDER BY data_criacao ASC");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // depois da conversa (orgânica, fora do sistema) por WhatsApp, o atendente
    // registra o desfecho aqui: confirmada ou recusada/reagendar
    public function atualizarStatus(int $id, string $status): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE SOLICITACAO SET status = :status WHERE id_solicitacao = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM SOLICITACAO WHERE id_solicitacao = :id");
        return $stmt->execute(['id' => $id]);
    }
}
