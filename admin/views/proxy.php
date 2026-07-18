<?php
/**
 * proxy.php
 * Script simples para tentar contornar a restrição de X-Frame-Options carregando o HTML do Gemini no domínio do site.
 */

// Para permitir rodar diretamente como um arquivo PHP avulso, fazemos a requisição HTTP usando curl ou file_get_contents
$url = 'https://gemini.google.com/';

// Configura o curl para simular um navegador comum
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$html = curl_exec($ch);
curl_close($ch);

if ($html === false) {
    echo 'Erro ao carregar o Gemini via Proxy.';
    exit;
}

echo $html;
