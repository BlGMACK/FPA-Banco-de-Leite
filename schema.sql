-- ============================================================
-- Schema do banco de dados - Sistema Banco de Leite Humano
-- ============================================================
-- Como usar (phpMyAdmin): aba "SQL" de qualquer banco > colar
-- tudo > Executar. As duas linhas abaixo já criam o banco
-- "banco_de_leite_humano" sozinhas — não precisa criar antes.
--
-- Como usar (linha de comando):
--   mysql -u root < schema.sql

-- As tabelas são criadas na ordem correta de dependência
-- (cada FOREIGN KEY referencia uma tabela já existente).
-- ============================================================

CREATE TABLE DOADORA (
    id_doadora INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    cartao_sus VARCHAR(20),
    telefone VARCHAR(20),
    endereco VARCHAR(255),
    status_sorologia ENUM('apto', 'nao_apto', 'pendente') DEFAULT 'pendente'
);

CREATE TABLE COLETA_DOMICILIAR (
    id_coleta INT AUTO_INCREMENT PRIMARY KEY,
    id_doadora INT NOT NULL,
    data_visita DATE NOT NULL,
    zona_regiao ENUM('norte', 'sul', 'oeste', 'leste', 'rural') NOT NULL,
    volume_ml DECIMAL(6,2),
    temperatura_caixa DECIMAL(4,1),
    FOREIGN KEY (id_doadora) REFERENCES DOADORA(id_doadora)
);

CREATE TABLE FRASCO_CRU (
    id_frasco_cru INT AUTO_INCREMENT PRIMARY KEY,
    id_coleta INT NOT NULL,
    classificacao ENUM('colostro', 'transicao', 'maduro') NOT NULL,
    status_conformidade ENUM('conforme', 'nao_conforme') DEFAULT 'pendente',
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
