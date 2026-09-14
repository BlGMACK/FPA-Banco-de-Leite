<?php

namespace App\Config; // reflete a pasta config/, conforme o autoload do composer.json

use PDO;           // classe nativa do PHP para conectar a bancos de dados
use PDOException;  // exceção nativa, lançada quando algo dá errado na conexão/consulta

class Conexao {

    // "static" = pertence à CLASSE, não a um objeto — existe uma única cópia, compartilhada
    // "?PDO" = pode ser um PDO ou null; começa como null porque ainda não conectamos
    private static ?PDO $conexao = null;

    // método "static" também: chamado direto como Conexao::conectar(), sem precisar de "new"
    public static function conectar(): PDO {

        // "self::" acessa algo static da própria classe (equivalente ao $this->, mas para static)
        // se a conexão ainda não existe, cria agora; se já existe, pula e reaproveita
        if (self::$conexao === null) {

            $host = "localhost";               // XAMPP local, mesma máquina
            $banco = "banco_de_leite_humano";  // nome exato criado no phpMyAdmin
            $usuario = "root";                 // usuário padrão do MySQL no XAMPP
            $senha = "";                       // XAMPP local não define senha por padrão

            // try/catch: tentar conectar pode falhar (banco fora do ar, senha errada, etc.)
            try {
                self::$conexao = new PDO(
                    // "DSN": string que diz o tipo do banco, host, nome do banco e charset
                    "mysql:host={$host};dbname={$banco};charset=utf8mb4",
                    $usuario,
                    $senha
                );

                // faz o PDO lançar exceções automaticamente quando uma query falhar,
                // em vez de falhar silenciosamente
                self::$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            } catch (PDOException $e) {
                // relança o erro com uma mensagem mais clara, caso a conexão falhe
                throw new PDOException("Erro ao conectar ao banco: " . $e->getMessage());
            }
        }

        // devolve a conexão (recém-criada, ou a que já existia)
        return self::$conexao;
    }
}