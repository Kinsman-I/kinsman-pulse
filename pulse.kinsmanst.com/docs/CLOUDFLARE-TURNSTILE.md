# Cloudflare Turnstile

O Turnstile protege o login, o cadastro profissional e a recuperação de senha.

## Configuração

1. No painel Cloudflare, mantenha `pulse.kinsmanst.com` entre os hostnames permitidos do widget.
2. No `.env` da hospedagem, informe:

```env
TURNSTILE_SITE_KEY="sua-chave-do-site"
TURNSTILE_SECRET_KEY="sua-chave-secreta"
```

3. Não coloque a chave secreta em HTML, JavaScript, capturas de tela ou arquivos públicos.
4. Envie os arquivos da aplicação e teste login, criação de conta e recuperação de senha em uma janela anônima.

Para a recuperação de senha, mantenha no `.env`:

```env
MAIL_FROM="nao-responda@pulse.kinsmanst.com"
MAIL_FROM_NAME="Kinsman Pulse"
```

Se as duas variáveis estiverem vazias, o sistema continua funcionando sem Turnstile. Se apenas uma delas estiver preenchida, os formulários protegidos serão recusados para evitar uma configuração insegura.
