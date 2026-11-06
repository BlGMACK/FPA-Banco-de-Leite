<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prazo de retenção
    |--------------------------------------------------------------------------
    |
    | Anos de guarda dos anexos dos exames, contados da decisão de habilitação,
    | e dos valores corrigidos de pedidos, contados da correção. Vazio ou
    | inválido significa prazo não definido: nada fica disponível para descarte.
    | O descarte é sempre disparado por um administrador na tela de retenção.
    |
    */

    'anos' => env('RETENCAO_ANOS'),

];
