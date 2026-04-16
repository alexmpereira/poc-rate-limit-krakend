<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'sucesso',
    'mensagem' => 'Resposta processada pelo backend PHP!',
    'timestamp' => date('Y-m-d H:i:s')
]);