<?php

namespace App\Models;

use App\Config\Conexao;
use PDO;
use Exception;

// Última etapa da admissão: cartão SUS/plano, exames de sangue e sorologia.
// A aprovação aqui é o momento em que uma "candidata" vira oficialmente uma
// DOADORA — o resto do sistema (retiradas, coletas, processamento) passa a
// enxergar só a DOADORA daqui pra frente.
class RequisitosSaude {

    public function criar(int $idFicha, ?string $cartaoSus, ?string $resultadoExames): int {

        $pdo = Conexao::conectar();

        $sql = "INSERT INTO REQUISITOS_SAUDE (id_ficha, cartao_sus, resultado_exames)
                VALUES (:id_ficha, :cartao_sus, :resultado_exames)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_ficha' => $idFicha,
            'cartao_sus' => $cartaoSus,
            'resultado_exames' => $resultadoExames
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("SELECT * FROM REQUISITOS_SAUDE WHERE id_requisitos = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function atualizarSorologia(int $id, string $resultado): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE REQUISITOS_SAUDE SET resultado_sorologia = :resultado WHERE id_requisitos = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['resultado' => $resultado, 'id' => $id]);
    }

    public function reprovar(int $id): bool {

        $pdo = Conexao::conectar();

        $sql = "UPDATE REQUISITOS_SAUDE SET status = 'reprovado' WHERE id_requisitos = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    // O PASSO DECISIVO: aprova os requisitos e, na mesma operação, cria a
    // DOADORA de verdade. "Na mesma operação" é o ponto-chave — por isso
    // usamos uma TRANSAÇÃO (beginTransaction/commit/rollBack).
    //
    // O QUE É UMA TRANSAÇÃO: um conjunto de alterações que precisa funcionar
    // como um "tudo ou nada". Aqui são duas alterações (criar a DOADORA +
    // marcar REQUISITOS_SAUDE como aprovado); se a segunda falhasse depois
    // da primeira já ter acontecido, o banco ficaria num estado incoerente
    // (uma doadora criada, mas sem o requisito correspondente marcado como
    // aprovado). A transação garante que as duas aconteçam juntas, ou nenhuma.
    //
    // Repare também que esse método BUSCA nome/telefone/endereco sozinho,
    // seguindo a corrente de IDs para trás (requisitos -> ficha -> visita
    // -> solicitação) em vez de pedir esses dados de novo por fora — é a
    // mesma corrente de rastreabilidade que desenhamos, só que percorrida
    // para frente, na hora de montar o registro final.
    public function aprovar(int $idRequisitos, string $cpf): int {

        $pdo = Conexao::conectar();

        $requisitos = $this->buscarPorId($idRequisitos);
        if (!$requisitos) {
            throw new Exception("Requisitos de saúde não encontrados (id {$idRequisitos}).");
        }

        $ficha = (new FichaTriagem())->buscarPorId($requisitos['id_ficha']);
        $visita = (new Visita())->buscarPorId($ficha['id_visita']);
        $solicitacao = (new Solicitacao())->buscarPorId($visita['id_solicitacao']);

        $pdo->beginTransaction(); // a partir daqui, nada é gravado de verdade até o commit()

        try {
            $idDoadora = (new Doadora())->criar(
                nome: $solicitacao['nome'],
                cpf: $cpf,
                cartaoSus: $requisitos['cartao_sus'],
                telefone: $solicitacao['telefone'],
                endereco: $solicitacao['endereco'],
                idRequisitos: $idRequisitos
            );

            $stmt = $pdo->prepare("UPDATE REQUISITOS_SAUDE SET status = 'aprovado' WHERE id_requisitos = :id");
            $stmt->execute(['id' => $idRequisitos]);

            $pdo->commit(); // as duas alterações acima são gravadas de vez, juntas

        } catch (Exception $e) {
            $pdo->rollBack(); // desfaz TUDO que foi feito desde o beginTransaction()
            throw $e;
        }

        return $idDoadora;
    }

    public function excluir(int $id): bool {

        $pdo = Conexao::conectar();

        $stmt = $pdo->prepare("DELETE FROM REQUISITOS_SAUDE WHERE id_requisitos = :id");
        return $stmt->execute(['id' => $id]);
    }
}
