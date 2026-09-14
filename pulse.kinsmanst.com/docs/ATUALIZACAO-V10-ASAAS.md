# Atualização V10 — Asaas em produção

1. Faça backup dos arquivos e do banco.
2. Substitua `app`, `public_html`, `database` e `docs`, preservando o `.env` e `public_html/uploads`.
3. No phpMyAdmin, selecione o banco do Pulse e importe `database/migrations/007_asaas_producao.sql` uma única vez.
4. No `.env` da raiz do projeto, acrescente:

```env
ASAAS_ENV="production"
ASAAS_API_KEY="COLE_A_CHAVE_DIRETAMENTE_NO_CPANEL"
ASAAS_WEBHOOK_TOKEN="CRIE_UM_TOKEN_ALEATORIO_COM_MAIS_DE_32_CARACTERES"
```

5. No painel Asaas, cadastre o webhook `https://pulse.kinsmanst.com/asaas-webhook.php` com o mesmo token do `.env`.
6. Habilite os eventos `CHECKOUT_PAID`, `CHECKOUT_CANCELED`, `CHECKOUT_EXPIRED`, `SUBSCRIPTION_CREATED`, `SUBSCRIPTION_UPDATED`, `SUBSCRIPTION_INACTIVATED`, `SUBSCRIPTION_DELETED`, `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_REFUNDED` e `PAYMENT_CHARGEBACK_REQUESTED`.
7. Use envio sequencial e API v3.
8. Entre como profissional, abra **Planos e financeiro**, escolha o Basic e faça o primeiro pagamento real controlado.
9. Confirme que o status muda para `active`. Depois do teste, mantenha `APP_DEBUG=false`.

Nunca coloque a chave Asaas em `public_html`, em prints ou mensagens.
