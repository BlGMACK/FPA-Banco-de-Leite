<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\Atendimento;
use App\Models\Avaliacao;
use App\Models\ContatoSolicitacao;
use App\Models\EventoSolicitacao;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\ResolucaoSolicitacao;
use App\Models\Solicitacao;
use App\Models\TipoExame;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dados fictícios para demonstração (pitch): pedidos em todas as etapas do primeiro fluxo, do recebimento à doadora habilitada.
 *
 * Somente em ambiente local (e nos testes). Cada etapa passa pelas operações do sistema (as mesmas usadas pelas telas), com o relógio
 * avançado entre elas, para que histórico, eventos e datas fiquem coerentes. Não é idempotente: recusa rodar de novo se já
 * houver dados de demonstração. Para recomeçar, use um banco descartável com migrate:fresh e os seeders de catálogo.
 */
class DemonstracaoSeeder extends Seeder
{
    private const DOMINIO = 'demo.bancodeleite.local';

    private const ZONA = 'America/Sao_Paulo';

    /** Pessoas fictícias: nome, bairro, logradouro e número. */
    private const PESSOAS = [
        ['Fernanda Lima Rocha', 'Jardim Maria Izabel', 'Rua das Azaleias', '120'],
        ['Juliana Prado Martins', 'Palmital', 'Rua Bahia', '845'],
        ['Camila Ferreira Santos', 'Centro', 'Avenida Sampaio Vidal', '1310'],
        ['Patrícia Gomes Alves', 'Jardim Cavallari', 'Rua Aimorés', '77'],
        ['Renata Oliveira Costa', 'Cascata', 'Rua Nove de Julho', '402'],
        ['Beatriz Nunes Carvalho', 'Jardim Acapulco', 'Rua Rio Grande do Sul', '58'],
        ['Larissa Mendes Ribeiro', 'Fragata', 'Rua Paraná', '930'],
        ['Aline Barbosa Teixeira', 'Santa Antonieta', 'Rua Goiás', '215'],
        ['Mariana Castro Duarte', 'Jardim Tangará', 'Rua dos Ipês', '36'],
        ['Tatiane Moreira Lopes', 'Nova Marília', 'Avenida das Esmeraldas', '1504'],
        ['Gabriela Freitas Pinto', 'Jardim Altos do Palmital', 'Rua Espírito Santo', '663'],
        ['Vanessa Araújo Cardoso', 'Bairro Alto Cafezal', 'Rua Pernambuco', '290'],
        ['Débora Rezende Moura', 'Jardim Marajó', 'Rua Amazonas', '18'],
    ];

    private User $admin;

    private User $enfermeira;

    private User $atendente;

    private int $sequencia = 0;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->error('Dados de demonstração só podem ser criados em ambiente local.');

            return;
        }
        if (User::query()->where('email', 'like', '%@'.self::DOMINIO)->exists()) {
            $this->command?->warn('Os dados de demonstração já existem; nada foi alterado. Para recriá-los, use um banco descartável.');

            return;
        }
        $this->call([PermissaoSeeder::class, TipoExameSeeder::class]);

        $agora = CarbonImmutable::now(self::ZONA)->startOfMinute();
        try {
            DB::transaction(function () use ($agora): void {
                $this->criarEquipe($agora->subDays(60));
                $this->popular($agora);
            });
        } finally {
            Carbon::setTestNow();
        }
        $this->command?->info('Dados de demonstração criados: '.count(self::PESSOAS).' pessoas em todas as etapas, de pedido novo a doadora habilitada.');
    }

    private function popular(CarbonImmutable $agora): void
    {
        [$fernanda, $juliana, $camila, $patricia, $renata, $beatriz, $larissa, $aline, $mariana, $tatiane, $gabriela, $vanessa, $debora] = array_keys(self::PESSOAS);

        // Doadoras habilitadas (definitivas e temporária), com o fluxo completo.
        $this->doadora($camila, $agora->subDays(38), 'habilitada', plano: true);
        $this->doadora($patricia, $agora->subDays(30), 'habilitada');
        $this->doadora($renata, $agora->subDays(24), 'habilitada_temporariamente');

        // Exames aceitos, aguardando a decisão de habilitação.
        $this->ateExames($beatriz, $agora->subDays(16), aceitos: true);
        // Exames solicitados, aguardando o envio pela interessada.
        $this->ateExames($larissa, $agora->subDays(9), aceitos: false);

        // Relatório finalizado, aguardando análise da avaliação.
        $this->ateRelatorio($aline, $agora->subDays(7), finalizar: true);
        // Visita realizada com relatório ainda em rascunho.
        $this->ateRelatorio($mariana, $agora->subDays(6), finalizar: false);

        // Visitas agendadas nos próximos dias.
        $this->ateAgenda($tatiane, $agora->subDays(4), $agora->addDays(2)->setTime(9, 0));
        $this->ateAgenda($gabriela, $agora->subDays(3), $agora->addDays(3)->setTime(14, 30));

        // Admitida, ainda sem visita marcada.
        $this->ateAdmissao($vanessa, $agora->subDays(2));

        // Em análise, com uma tentativa de contato sem resposta.
        $pedido = $this->receber($debora, $agora->subDays(2));
        $this->iniciarAnalise($pedido, $agora->subDays(1)->setTime(9, 15));
        $this->contatoAnalise($pedido, $agora->subDays(1)->setTime(9, 20), 'sem_resposta');

        // Pedidos novos; Fernanda enviou o formulário duas vezes (a tela aponta a possível correspondência).
        $this->receber($fernanda, $agora->subDay()->setTime(20, 40));
        $this->receber($fernanda, $agora->subHours(3), ['outros_detalhes' => 'Enviei de novo porque não sei se o primeiro pedido chegou.']);
        $this->receber($juliana, $agora->subHours(1));
    }

    private function criarEquipe(CarbonImmutable $em): void
    {
        $this->relogio($em);
        $this->admin = User::query()->where('role', 'admin')->orderBy('id')->first()
            ?? $this->conta('Coordenação do Banco de Leite', 'coordenacao', 'admin');
        $this->enfermeira = $this->conta('Ana Paula Siqueira', 'ana.siqueira', 'funcionario');
        $this->atendente = $this->conta('Carla Mendonça', 'carla.mendonca', 'funcionario');
        $permissoes = [
            $this->enfermeira->id => [Permissao::ATENDER_SOLICITACOES, Permissao::GERENCIAR_AGENDA, Permissao::CONSULTAR_RELATORIOS_VISITA,
                Permissao::REGISTRAR_RELATORIOS_VISITA, Permissao::CONSULTAR_AVALIACOES, Permissao::AVALIAR_DOADORAS,
                Permissao::CONSULTAR_EXAMES, Permissao::REGISTRAR_EXAMES],
            $this->atendente->id => [Permissao::ATENDER_SOLICITACOES, Permissao::GERENCIAR_AGENDA, Permissao::CONSULTAR_EXAMES,
                Permissao::REGISTRAR_EXAMES],
        ];
        foreach ([$this->enfermeira, $this->atendente] as $funcionaria) {
            foreach ($permissoes[$funcionaria->id] as $codigo) {
                Permissao::query()->where('codigo', $codigo)->firstOrFail()->conceder($funcionaria, $this->admin, 'Conta de demonstração.');
            }
        }
    }

    private function conta(string $nome, string $usuario, string $papel): User
    {
        $senha = Str::password(16, symbols: false);
        $conta = User::factory()->create(['name' => $nome, 'email' => $usuario.'@'.self::DOMINIO, 'role' => $papel, 'password' => Hash::make($senha)]);
        $this->command?->info("Conta {$conta->email} criada com a senha {$senha}. Guarde-a: ela não será exibida de novo.");

        return $conta;
    }

    private function doadora(int $pessoa, CarbonImmutable $inicio, string $resultado, bool $plano = false): void
    {
        $avaliacao = $this->ateExames($pessoa, $inicio, aceitos: true);
        $this->relogio($inicio->addDays(13)->setTime(10, 0));
        $temporaria = $resultado === 'habilitada_temporariamente';
        $avaliacao->habilitar($this->enfermeira, [
            'operacao_id' => (string) Str::uuid(), 'versao' => $avaliacao->fresh()->versao, 'resultado' => $resultado,
            'possui_plano_saude' => $plano ? '1' : '0', 'motivo' => null, 'confirmacao_definitiva' => '1',
            'condicao' => $temporaria ? 'Reavaliar após o retorno ao trabalho, previsto para o próximo trimestre.' : null,
            'valida_ate' => $temporaria ? $inicio->addDays(103)->toDateString() : null,
        ]);
    }

    /** Análise do relatório liberada para exames; exames solicitados e, se pedido, recebidos e aceitos. */
    private function ateExames(int $pessoa, CarbonImmutable $inicio, bool $aceitos): Avaliacao
    {
        $avaliacao = $this->ateRelatorio($pessoa, $inicio, finalizar: true);
        $this->relogio($inicio->addDays(6)->setTime(11, 0));
        $avaliacao->decidir($this->enfermeira, ['operacao_id' => (string) Str::uuid(), 'versao' => $avaliacao->fresh()->versao,
            'resultado' => 'liberada_exames', 'motivo' => null, 'confirmacao_definitiva' => '1']);
        $itens = $avaliacao->itens()->with('tipo')->get();
        foreach ($itens as $item) {
            $this->exame($item, 'solicitacao');
        }
        if ($aceitos) {
            $this->relogio($inicio->addDays(12)->setTime(15, 0));
            foreach ($itens as $item) {
                $cns = $item->tipo->codigo === TipoExame::CNS;
                $this->exame($item, 'recebimento', [
                    'realizado_em' => $inicio->addDays(10)->toDateString(),
                    'numero_documento' => $cns ? $this->cns($pessoa) : null,
                ]);
                $this->exame($item, 'aceite');
            }
        }

        return $avaliacao->fresh();
    }

    private function exame(ItemAvaliacao $item, string $acao, array $extra = []): void
    {
        $item->registrar($this->atendente, ['operacao_id' => (string) Str::uuid(), 'versao' => $item->fresh()->versao, 'acao' => $acao, ...$extra]);
    }

    /** Visita realizada no 5º dia; relatório salvo em rascunho e, se pedido, finalizado (o que abre a avaliação). */
    private function ateRelatorio(int $pessoa, CarbonImmutable $inicio, bool $finalizar): ?Avaliacao
    {
        $visita = $inicio->addDays(5)->setTime(10, 0);
        $agenda = $this->ateAgenda($pessoa, $inicio, $visita);
        $this->relogio($visita->addHours(2));
        $evento = Atendimento::registrar($agenda->id, $this->enfermeira, [
            'operacao_id' => (string) Str::uuid(), 'versao' => $agenda->versao,
            'inicio_efetivo_em' => $visita->format('Y-m-d\TH:i'), 'fim_efetivo_em' => $visita->addMinutes(50)->format('Y-m-d\TH:i'),
            'responsavel_user_id' => $agenda->responsavel_user_id, 'modalidade' => $agenda->modalidade,
            'local_atendimento' => $agenda->local_atendimento, 'realizacao_confirmada' => true,
        ]);
        $relatorio = $evento->relatorio;
        $nome = Str::before(self::PESSOAS[$pessoa][0], ' ');
        $relatorio->salvarRascunho($this->enfermeira, ['operacao_id' => (string) Str::uuid(), 'revisao' => $relatorio->fresh()->revisao,
            'texto' => "Visita domiciliar realizada com {$nome}. Bebê em aleitamento materno exclusivo, com boa pega e ganho de peso adequado. "
                .'Produção de leite acima da demanda do bebê. Orientadas a higiene das mãos e das mamas, a ordenha manual e o armazenamento '
                .'em frasco de vidro esterilizado no congelador. Ambiente domiciliar adequado. Interessada demonstrou compreensão das orientações.']);
        if (! $finalizar) {
            return null;
        }
        $this->relogio($visita->addHours(5));
        $relatorio->finalizar($this->enfermeira, ['operacao_id' => (string) Str::uuid(), 'revisao' => $relatorio->fresh()->revisao]);

        return Avaliacao::daVisita($relatorio->atendimento_id)->firstOrFail();
    }

    /** Admissão e visita domiciliar confirmada para o horário informado. */
    private function ateAgenda(int $pessoa, CarbonImmutable $inicio, CarbonImmutable $visita): Agendamento
    {
        $pedido = $this->ateAdmissao($pessoa, $inicio);
        $combinado = $inicio->addDays(2)->setTime(9, 30);
        $this->relogio($combinado->addMinutes(10));
        $contato = ContatoSolicitacao::registrarAcompanhamento($pedido->id, $this->atendente, [
            'operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao, 'canal' => 'whatsapp', 'resultado' => 'realizado',
            'destinatario' => 'Celular da interessada', 'finalidade' => 'Combinar a visita domiciliar',
            'resumo' => 'Visita combinada; a interessada estará em casa com o bebê.', 'ocorrido_em' => $combinado->format('Y-m-d\TH:i'),
        ]);
        $pessoaDados = self::PESSOAS[$pessoa];

        return Agendamento::confirmar($pedido->id, $this->atendente, [
            'operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao, 'motivo' => 'Horário combinado por WhatsApp.',
            'contato_id' => $contato->id, 'responsavel_user_id' => $this->enfermeira->id, 'modalidade' => 'domicilio',
            'local_atendimento' => "{$pessoaDados[2]}, {$pessoaDados[3]} – {$pessoaDados[1]}, Marília/SP",
            'inicio_previsto_em' => $visita->format('Y-m-d\TH:i'), 'fim_previsto_em' => $visita->addHour()->format('Y-m-d\TH:i'),
            'combinado_confirmado' => true,
        ])->agendamento;
    }

    /** Recebimento, análise e contato com identidade conferida no dia seguinte, e admissão. */
    private function ateAdmissao(int $pessoa, CarbonImmutable $inicio): Solicitacao
    {
        $pedido = $this->receber($pessoa, $inicio->setTime(8, 40));
        $dia = $inicio->addDay();
        $this->iniciarAnalise($pedido, $dia->setTime(9, 0));
        $contato = $this->contatoAnalise($pedido, $dia->setTime(9, 10), 'realizado');
        $this->relogio($dia->setTime(9, 25));
        ResolucaoSolicitacao::executar([
            'operacao_id' => (string) Str::uuid(), 'acao' => 'admitir', 'pedidos' => [['id' => $pedido->id, 'versao' => $pedido->fresh()->versao]],
            'contato_id' => $contato->id, 'motivo' => 'Dados conferidos por telefone; interessada apta a seguir para a visita.',
            'principal_id' => $pedido->id, 'identidade' => 'nova',
        ], $pedido->id, $this->atendente);

        return $pedido->fresh();
    }

    /** Mesmo registro do formulário público (/doar). */
    private function receber(int $pessoa, CarbonImmutable $em, array $extra = []): Solicitacao
    {
        $this->relogio($em);
        [$nome, $bairro, $logradouro, $numero] = self::PESSOAS[$pessoa];
        $dados = [
            'nome_completo' => $nome, 'cpf' => $this->cpf($pessoa), 'telefone_fixo' => null, 'telefone_celular' => $this->telefone($pessoa),
            'email_contato' => Str::slug($nome, '.').'@exemplo.com', 'municipio' => 'Marília', 'bairro' => $bairro, 'uf' => 'SP',
            'cep' => '175'.str_pad((string) (10000 + $pessoa * 731), 5, '0', STR_PAD_LEFT), 'logradouro' => $logradouro,
            'numero' => $numero, 'complemento' => null, 'whatsapp_telefone' => 'celular',
            'outros_detalhes' => $pessoa % 3 === 0 ? 'Bebê com dois meses; posso receber a visita pela manhã.' : null,
            ...$extra,
            'whatsapp_disponivel' => true, 'canal_origem' => 'web', 'registrada_por_user_id' => null,
        ];
        $pedido = new Solicitacao;
        $pedido->forceFill($dados + [
            'operacao_id' => (string) Str::uuid(), 'conteudo_hash' => hash('sha256', json_encode($dados, JSON_THROW_ON_ERROR)),
            'protocolo' => (string) Str::uuid(), 'status' => 'enviada', 'nome_normalizado' => Str::lower(Str::ascii(Str::squish($nome))),
            'dados_originais' => $dados, 'whatsapp_declarado_em' => now(), 'enviada_em' => now(), 'versao' => 1,
        ])->save();
        $evento = new EventoSolicitacao;
        $evento->forceFill([
            'solicitacao_id' => $pedido->id, 'autor_user_id' => null, 'tipo' => 'recebimento', 'estado_novo' => 'enviada',
            'dados_novos' => ['status' => 'enviada', 'canal_origem' => 'web'], 'ocorrido_em' => now(),
        ])->save();

        return $pedido;
    }

    /** Mesmo registro da ação "Iniciar análise" da tela do pedido. */
    private function iniciarAnalise(Solicitacao $pedido, CarbonImmutable $em): void
    {
        $this->relogio($em);
        $pedido->refresh();
        $pedido->status = 'em_analise';
        $pedido->versao++;
        $pedido->save();
        $pedido->registrarEvento('inicio_analise', $this->atendente, 'enviada');
    }

    /** Mesmo registro da ação "Registrar contato" da tela do pedido. */
    private function contatoAnalise(Solicitacao $pedido, CarbonImmutable $em, string $resultado): ContatoSolicitacao
    {
        $this->relogio($em->addMinutes(5));
        $realizado = $resultado === 'realizado';
        $contato = new ContatoSolicitacao;
        $contato->forceFill([
            'canal' => 'telefone', 'resultado' => $resultado, 'destinatario' => 'Celular informado no pedido',
            'finalidade' => 'Conferir os dados do pedido', 'ocorrido_em' => $em->utc(),
            'resumo' => $realizado ? 'Identidade e endereço confirmados; dúvidas sobre a doação esclarecidas.' : 'Ligação não atendida.',
            'proxima_acao' => $realizado ? null : 'Tentar novamente pelo WhatsApp.',
            'registrado_por_user_id' => $this->atendente->id, 'identidade_conferida' => $realizado, 'duvida_esclarecida' => $realizado,
        ])->save();
        $contato->solicitacoes()->attach($pedido->id);
        $pedido->refresh();
        $pedido->versao++;
        $pedido->save();

        return $contato;
    }

    private function relogio(CarbonImmutable $em): void
    {
        // Segundos distintos mantêm a ordem dos eventos registrados no mesmo minuto.
        Carbon::setTestNow($em->utc()->addSeconds(++$this->sequencia % 50));
    }

    private function telefone(int $pessoa): string
    {
        return '1499'.str_pad((string) (1234567 + $pessoa * 98765), 7, '0', STR_PAD_LEFT);
    }

    /** CPF fictício com dígitos verificadores válidos. */
    private function cpf(int $pessoa): string
    {
        $base = str_pad((string) (318204570 + $pessoa * 1009), 9, '0', STR_PAD_LEFT);
        foreach ([10, 11] as $peso) {
            $soma = 0;
            foreach (str_split($base) as $i => $digito) {
                $soma += (int) $digito * ($peso - $i);
            }
            $base .= (string) ((($soma * 10) % 11) % 10);
        }

        return $base;
    }

    /** Número de CNS fictício que passa na validação do sistema. */
    private function cns(int $pessoa): string
    {
        for ($n = 0; ; $n++) {
            $numero = '7'.str_pad((string) (40213580000 + $pessoa * 7919 + $n), 13, '0', STR_PAD_LEFT).'0';
            for ($ultimo = 0; $ultimo <= 9; $ultimo++) {
                $candidato = substr($numero, 0, 14).$ultimo;
                if (ItemAvaliacao::cnsValido($candidato)) {
                    return $candidato;
                }
            }
        }
    }
}
