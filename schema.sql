-- ============================================================
-- Schema do banco de dados - Sistema Banco de Leite Humano
-- ============================================================
-- Como usar (phpMyAdmin): aba "SQL" de qualquer banco > colar
-- tudo > Executar. As duas linhas abaixo já criam o banco
-- "banco_de_leite_humano" sozinhas — não precisa criar antes.
--
-- Como usar (linha de comando):
--   mysql -u root < schema.sql
--
-- As tabelas são criadas na ordem correta de dependência
-- (cada FOREIGN KEY referencia uma tabela já existente).
--
-- Fluxo completo (admissão -> operação), cada etapa é uma
-- tabela própria, criada só quando a etapa realmente acontece:
--
--   SOLICITACAO -> VISITA -> FICHA_TRIAGEM -> REQUISITOS_SAUDE
--       -> DOADORA (aprovação final) -> RETIRADA
--       -> COLETA_DOMICILIAR -> FRASCO_CRU -> FRASCO_PASTEURIZADO
--       -> ANALISE_QUALIDADE / PRESCRICAO -> RECEPTOR
-- ============================================================

CREATE DATABASE IF NOT EXISTS banco_de_leite_humano;
USE banco_de_leite_humano;

-- ------------------------------------------------------------
-- FASE 1: ADMISSÃO — do primeiro contato até a doadora aprovada
-- ------------------------------------------------------------

CREATE TABLE SOLICITACAO (
    id_solicitacao INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    data_desejada DATE NOT NULL,
    status ENUM('pendente', 'confirmada', 'recusada') DEFAULT 'pendente',
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE VISITA (
    id_visita INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitacao INT NOT NULL,
    data_visita DATE NOT NULL,
    status ENUM('pendente', 'realizada', 'cancelada') DEFAULT 'pendente',
    FOREIGN KEY (id_solicitacao) REFERENCES SOLICITACAO(id_solicitacao)
);

CREATE TABLE FICHA_TRIAGEM (
    id_ficha INT AUTO_INCREMENT PRIMARY KEY,
    id_visita INT NOT NULL UNIQUE,
    condicao_moradia VARCHAR(255),
    renda_familiar VARCHAR(100),
    intercorrencias_gestacao VARCHAR(255),
    dados_bebe VARCHAR(255),
    status_revisao ENUM('pendente', 'aprovada', 'reprovada') DEFAULT 'pendente',
    FOREIGN KEY (id_visita) REFERENCES VISITA(id_visita)
);

CREATE TABLE REQUISITOS_SAUDE (
    id_requisitos INT AUTO_INCREMENT PRIMARY KEY,
    id_ficha INT NOT NULL UNIQUE,
    cartao_sus VARCHAR(20),
    resultado_exames VARCHAR(255),
    resultado_sorologia ENUM('apto', 'nao_apto', 'pendente') DEFAULT 'pendente',
    status ENUM('pendente', 'aprovado', 'reprovado') DEFAULT 'pendente',
    FOREIGN KEY (id_ficha) REFERENCES FICHA_TRIAGEM(id_ficha)
);

CREATE TABLE DOADORA (
    id_doadora INT AUTO_INCREMENT PRIMARY KEY,
    id_requisitos INT UNIQUE NULL, -- liga de volta até a solicitação original (traceback)
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    cartao_sus VARCHAR(20),
    telefone VARCHAR(20),
    endereco VARCHAR(255),
    status_sorologia ENUM('apto', 'nao_apto', 'pendente') DEFAULT 'pendente',
    FOREIGN KEY (id_requisitos) REFERENCES REQUISITOS_SAUDE(id_requisitos)
);

-- ------------------------------------------------------------
-- FASE 2: OPERAÇÃO — da doadora aprovada até a distribuição
-- ------------------------------------------------------------

CREATE TABLE RETIRADA (
    id_retirada INT AUTO_INCREMENT PRIMARY KEY,
    id_doadora INT NOT NULL,
    data_informada DATE NOT NULL,
    quantidade_informada_ml DECIMAL(6,2) NOT NULL,
    status ENUM('aguardando_coleta', 'coletado') DEFAULT 'aguardando_coleta',
    FOREIGN KEY (id_doadora) REFERENCES DOADORA(id_doadora)
);

CREATE TABLE COLETA_DOMICILIAR (
    id_coleta INT AUTO_INCREMENT PRIMARY KEY,
    id_doadora INT NOT NULL,
    id_retirada INT NULL, -- qual aviso de retirada esta coleta está atendendo
    data_visita DATE NOT NULL,
    zona_regiao ENUM('norte', 'sul', 'oeste', 'leste', 'rural') NOT NULL,
    volume_ml DECIMAL(6,2),
    temperatura_caixa DECIMAL(4,1),
    FOREIGN KEY (id_doadora) REFERENCES DOADORA(id_doadora),
    FOREIGN KEY (id_retirada) REFERENCES RETIRADA(id_retirada)
);

CREATE TABLE FRASCO_CRU (
    id_frasco_cru INT AUTO_INCREMENT PRIMARY KEY,
    id_coleta INT NOT NULL,
    classificacao ENUM('colostro', 'transicao', 'maduro') NOT NULL,
    status_conformidade ENUM('pendente', 'conforme', 'nao_conforme') DEFAULT 'pendente',
    motivo_descarte VARCHAR(255),
    FOREIGN KEY (id_coleta) REFERENCES COLETA_DOMICILIAR(id_coleta)
);

CREATE TABLE FRASCO_PASTEURIZADO (
    id_frasco_past INT AUTO_INCREMENT PRIMARY KEY,
    id_frasco_cru INT NOT NULL UNIQUE,
    data_pasteurizacao DATETIME NOT NULL,
    volume_final_ml DECIMAL(6,2),
    FOREIGN KEY (id_frasco_cru) REFERENCES FRASCO_CRU(id_frasco_cru)
);

CREATE TABLE RECEPTOR (
    id_receptor INT AUTO_INCREMENT PRIMARY KEY,
    nome_rn VARCHAR(150) NOT NULL,
    hospital VARCHAR(150) NOT NULL,
    setor_uti VARCHAR(100)
);

CREATE TABLE ANALISE_QUALIDADE (
    id_analise INT AUTO_INCREMENT PRIMARY KEY,
    id_frasco_past INT NOT NULL,
    crematocrito DECIMAL(5,2),
    dornic DECIMAL(5,2),
    FOREIGN KEY (id_frasco_past) REFERENCES FRASCO_PASTEURIZADO(id_frasco_past)
);

CREATE TABLE PRESCRICAO (
    id_prescricao INT AUTO_INCREMENT PRIMARY KEY,
    id_frasco_past INT NOT NULL,
    id_receptor INT NOT NULL,
    volume_prescrito_ml DECIMAL(6,2),
    data_entrega DATETIME,
    FOREIGN KEY (id_frasco_past) REFERENCES FRASCO_PASTEURIZADO(id_frasco_past),
    FOREIGN KEY (id_receptor) REFERENCES RECEPTOR(id_receptor)
);
