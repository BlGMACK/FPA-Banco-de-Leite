<?php

namespace App\Models;

// Esta classe não representa uma tabela própria — ela percorre a corrente
// de IDs para TRÁS, juntando o que está espalhado em várias tabelas.
// Em produção, só deveria ser chamada por um Controller que confirma que
// quem está pedindo tem permissão de auditoria (ex.: um admin/PO), nunca
// por telas de operação do dia a dia — é exatamente essa restrição de
// acesso que mantém "oculto, mas rastreável" ao mesmo tempo.
class Rastreio {

    // Recebe o ID de uma PRESCRICAO (a ponta final: o leite que chegou a
    // um hospital) e devolve, em ordem, cada etapa até a solicitação
    // original que deu origem a tudo.
    public function rastrearAtéOrigem(int $idPrescricao): array {

        $trilha = [];

        $prescricao = (new Prescricao())->buscarPorId($idPrescricao);
        $trilha['prescricao'] = $prescricao;
        if (!$prescricao) return $trilha; // nada encontrado, encerra aqui

        $trilha['receptor'] = (new Receptor())->buscarPorId($prescricao['id_receptor']);

        $frascoPast = (new FrascoPasteurizado())->buscarPorId($prescricao['id_frasco_past']);
        $trilha['frasco_pasteurizado'] = $frascoPast;

        $trilha['analise_qualidade'] = (new AnaliseQualidade())->buscarPorFrasco($prescricao['id_frasco_past']);

        $frascoCru = (new FrascoCru())->buscarPorId($frascoPast['id_frasco_cru']);
        $trilha['frasco_cru'] = $frascoCru;

        $coleta = (new ColetaDomiciliar())->buscarPorId($frascoCru['id_coleta']);
        $trilha['coleta_domiciliar'] = $coleta;

        if ($coleta['id_retirada'] !== null) {
            $trilha['retirada'] = (new Retirada())->buscarPorId($coleta['id_retirada']);
        }

        $doadora = (new Doadora())->buscarPorId($coleta['id_doadora']);
        $trilha['doadora'] = $doadora;

        // a corrente de admissão só existe se essa doadora tiver nascido
        // de uma aprovação formal (id_requisitos preenchido) — doadoras
        // cadastradas manualmente, fora do fluxo completo, param aqui
        if ($doadora['id_requisitos'] !== null) {
            $requisitos = (new RequisitosSaude())->buscarPorId($doadora['id_requisitos']);
            $trilha['requisitos_saude'] = $requisitos;

            $ficha = (new FichaTriagem())->buscarPorId($requisitos['id_ficha']);
            $trilha['ficha_triagem'] = $ficha;

            $visita = (new Visita())->buscarPorId($ficha['id_visita']);
            $trilha['visita'] = $visita;

            $trilha['solicitacao'] = (new Solicitacao())->buscarPorId($visita['id_solicitacao']);
        }

        return $trilha;
    }
}
