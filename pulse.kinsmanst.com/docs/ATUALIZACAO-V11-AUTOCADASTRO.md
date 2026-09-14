# Atualização V11 — autocadastro e teste grátis

## Banco de dados

Depois de enviar os arquivos, selecione o banco do Kinsman Pulse no phpMyAdmin e execute:

```sql
SOURCE database/migrations/008_autocadastro_trial.sql;
```

No phpMyAdmin compartilhado, se o comando `SOURCE` não estiver disponível, abra o arquivo
`database/migrations/008_autocadastro_trial.sql`, copie o conteúdo e execute na aba SQL.

## Configuração

Confirme no arquivo `.env`:

```env
PLATFORM_TENANT_ID="1"
```

Use o ID do tenant principal da plataforma. Na instalação padrão ele é `1`.

## Fluxo

- O profissional acessa `index.php?page=register`.
- Escolhe Personal, Nutricionista ou Personal + Nutrição.
- Escolhe limite de 20, 40 ou 100 alunos.
- Recebe sete dias gratuitos sem cartão.
- Após o vencimento, o acesso é direcionado ao Financeiro.
- O checkout da Asaas usa automaticamente o preço da modalidade escolhida.

## Valores mensais

| Limite | Personal ou Nutrição | Completo |
| --- | ---: | ---: |
| 20 alunos | R$ 59,90 | R$ 89,90 |
| 40 alunos | R$ 99,90 | R$ 129,00 |
| 100 alunos | R$ 169,90 | R$ 219,00 |
