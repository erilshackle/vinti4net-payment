<?php

//* Edite com as suas credenciais abaixo e siga as instrucoes para teste:       <- Critical
# no terminal: php -S localhost:8000 -t tests/web                               <- !important
# no navegador: http://localhost:8000/                                          <- Ctrl + click
# depois verifique o response-vinti4.php                                        <- just read

# esta pagina deveria ser chamada pelo responsta da SISP ao responseUrl
# No entando isso pode não funcionar pois é requerido HTTPS para receber a resposta. oq fazer para testes?
//? Para testes, use um tunel como ngrok por exemplo;

//! É extrememente cirucrgico seguinte os passos assim como dito para comprovar os testes
// todo: De forma análoga prepare seu proprio ambiente de teste e execute os passos.



//! por segurança use variaveis no .env

define('VINTI4_POS_ID', 'meu-posid');   
define('VINTI4_POS_AUTCODE', 'meu-pos-autcode');
define('VINTI4_ENDPOINT', 'http://localhost:8000/action.html');
define('VINTI4_RESPONSE_URL', 'http://localhost:8000/response-vinti4.php');

