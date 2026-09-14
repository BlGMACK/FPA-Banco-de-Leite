<?php

namespace App\Config; // reflete a pasta Config/, conforme o autoload do composer.json

use PDO;           // classe nativa do PHP para conectar a bancos de dados
use PDOException;  // exceção nativa, lançada quando algo dá errado na conexão/consulta
use RuntimeException; // exceção nativa, lançada aqui quando o config.php não existe

class Conexao {

    // "static" = pertence à CLASSE, não a um objeto — existe uma única cópia, compartilhada
    // "?PDO" = pode ser um PDO ou null; começa como null porque ainda não conectamos
    private static ?PDO $conexao = null;

    // método "static" também: chamado direto como Conexao::conectar(), sem precisar de "new"
    public static function conectar(): PDO {

        // "self::" acessa algo static da própria classe (equivalente ao $this->, mas para static)
        // se a conexão ainda não existe, cria agora; se já existe, pula e reaproveita
        if (self::$conexao === null) {

            // credenciais não ficam mais fixas aqui — vêm do config.php de cada máquina
            // (ver método carregarConfig() logo abaixo)
            $config = self::carregarConfig();

            $host = $config['host'];       // XAMPP local, mesma máquina
            $banco = $config['dbname'];    // nome exato criado no phpMyAdmin
            $usuario = $config['usuario']; // usuário padrão do MySQL no XAMPP
            $senha = $config['senha'];     // XAMPP local não define senha por padrão

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

    // "private" = só a própria classe usa; "static" pelo mesmo motivo do conectar() acima
    // lê Config/config.php (arquivo local, fora do Git) e devolve suas credenciais.
    // Cada pessoa do grupo tem o seu próprio, copiado a partir de config.example.php.
    private static function carregarConfig(): array {

        $caminho = __DIR__ . '/config.php'; // __DIR__ = pasta onde este arquivo está (Config/)

        // se a pessoa esqueceu de copiar o config.example.php, avisa com uma mensagem clara
        // em vez de deixar o erro estourar mais na frente, sem explicação nenhuma
        if (!file_exists($caminho)) {
            throw new RuntimeException(
                "Arquivo Config/config.php não encontrado. " .
                "Copie Config/config.example.php para Config/config.php " .
                "e ajuste as credenciais se necessário."
            );
        }

        // require de um arquivo que só tem um "return [...]" devolve o próprio array
        return require $caminho;
    }
}
