<?php
/**
 * Página de teste de pagamento Vinti4Net
 * --------------------------------------
 * - Permite inserir um valor (em ECV)
 * - Submete o valor para post-vinti4.php
 * - Serve como ponto de entrada simples de testes locais
 *
 * Execução:
 * php -S localhost:8000 -t tests/web
 * Acesse: http://localhost:8000
 */
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Teste de Pagamento Vinti4Net</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: system-ui, Arial, sans-serif;
      background: #f4f6f8;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: #fff;
      padding: 2rem 3rem;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
      width: 350px;
    }
    h1 {
      font-size: 1.4rem;
      margin-bottom: 1.5rem;
    }
    input[type="number"] {
      width: 100%;
      padding: 0.6rem;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      margin-bottom: 1.2rem;
    }
    button {
      background: #0078d4;
      color: #fff;
      border: none;
      padding: 0.7rem 1.5rem;
      border-radius: 4px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    button:hover {
      background: #005ea8;
    }
    .note {
      font-size: 0.9rem;
      color: #666;
      margin-top: 1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <img src="https://comerciante.vinti4.cv/images/logo_vinti4.jpg" alt="" width="32">
    <h1>Teste de Pagamento Vinti4Net</h1>

    <form action="post-vinti4.php" method="POST" target="_blank">
      <label for="price"><strong>Valor (ECV)</strong></label>
      <input type="number" id="price" name="price" value="150" min="0" step="50" required>
      <button type="submit">Enviar Pagamento</button>
    </form>

    <p class="note">O formulário abrirá uma nova aba para iniciar o fluxo de pagamento de teste.</p>
    <smal class="note" style="color: yellowgreen">não esqueça de alterar as credenciais</smal>
  </div>
</body>
</html>
