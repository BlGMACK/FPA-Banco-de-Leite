<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Doadora;

$doadora = new Doadora();
$id = $doadora->criar("Maria Silva", "123.456.789-00", "123456789012345", "(14) 99999-0000", "Rua das Flores, 123");

echo "Doadora criada com ID: {$id}" . PHP_EOL;

$doadoraEncontrada = $doadora->buscarPorId($id); // usando o $id que você recebeu ao criar

if ($doadoraEncontrada) {
    echo "Nome: {$doadoraEncontrada['nome']}" . PHP_EOL;
    echo "CPF: {$doadoraEncontrada['cpf']}" . PHP_EOL;
} else {
    echo "Doadora não encontrada." . PHP_EOL;
}


// Atualizando a doadora criada
$doadora->atualizar($id, "Maria Silva Santos", "123.456.789-00", "123456789012345", "(14) 98888-1111", "Rua Nova, 456");
$doadoraAtualizada = $doadora->buscarPorId($id);
echo "Telefone atualizado: {$doadoraAtualizada['telefone']}" . PHP_EOL;


// Excluindo uma doadora 
$doadora->excluir(2); // ajusta pro ID que você quer remover nos testes
$doadoraExcluida = $doadora->buscarPorId(2);
var_dump($doadoraExcluida); // deveria mostrar "false", já que não existe mais
?>